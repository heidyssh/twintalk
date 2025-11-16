<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]);

$docenteId = $_SESSION['usuario_id'] ?? null;

if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$horario_id_param = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;

// Carpeta de subida (ajusta la ruta según tu proyecto)
$uploadDir = __DIR__ . '/../uploads/materiales/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// Horarios del docente
$sqlHor = "
    SELECT h.id, c.nombre_curso, d.nombre_dia, h.hora_inicio
    FROM horarios h
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.docente_id = ?
    ORDER BY d.numero_dia, h.hora_inicio
";
$stmt = $mysqli->prepare($sqlHor);
$stmt->bind_param("i", $docenteId);
$stmt->execute();
$horarios = $stmt->get_result();
$stmt->close();

// Tipos de archivo
$tipos = $mysqli->query("SELECT id, tipo_archivo FROM tipos_archivo ORDER BY tipo_archivo ASC");

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $horario_id     = (int)($_POST['horario_id'] ?? 0);
    $tipo_archivo_id= (int)($_POST['tipo_archivo_id'] ?? 0);
    $titulo         = trim($_POST['titulo'] ?? '');
    $descripcion    = trim($_POST['descripcion'] ?? '');

    if ($horario_id > 0 && $tipo_archivo_id > 0 && $titulo && isset($_FILES['archivo'])) {
        $file = $_FILES['archivo'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $nombreOriginal = $file['name'];
            $tmpName = $file['tmp_name'];

            // Crear nombre único
            $ext = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
            $nuevoNombre = uniqid('mat_') . '.' . $ext;

            $rutaDestino = $uploadDir . $nuevoNombre;
            // URL relativa para guardar en BD, ajústala si usas otra ruta pública
            $archivo_url = '/twintalk/uploads/materiales/' . $nuevoNombre;

            if (move_uploaded_file($tmpName, $rutaDestino)) {
                $tamano_archivo = $file['size'];

                $sqlIns = "
                    INSERT INTO materiales
                    (docente_id, horario_id, tipo_archivo_id, titulo, descripcion, archivo_url, tamano_archivo)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt = $mysqli->prepare($sqlIns);
                $stmt->bind_param(
                    "iiisssi",
                    $docenteId,
                    $horario_id,
                    $tipo_archivo_id,
                    $titulo,
                    $descripcion,
                    $archivo_url,
                    $tamano_archivo
                );

                if ($stmt->execute()) {
                    $mensaje = "Material subido correctamente.";
                } else {
                    $mensaje = "Error al guardar en la base de datos: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $mensaje = "No se pudo mover el archivo al servidor.";
            }
        } else {
            $mensaje = "Error al subir el archivo.";
        }
    } else {
        $mensaje = "Debes seleccionar horario, tipo de archivo, título y archivo.";
    }
}

// Listar materiales del docente (opcionalmente filtrado por horario)
if ($horario_id_param > 0) {
    $sqlMat = "
        SELECT m.*, c.nombre_curso
        FROM materiales m
        INNER JOIN horarios h ON m.horario_id = h.id
        INNER JOIN cursos c ON h.curso_id = c.id
        WHERE m.docente_id = ? AND m.horario_id = ?
        ORDER BY m.fecha_subida DESC
    ";
    $stmt = $mysqli->prepare($sqlMat);
    $stmt->bind_param("ii", $docenteId, $horario_id_param);
} else {
    $sqlMat = "
        SELECT m.*, c.nombre_curso
        FROM materiales m
        INNER JOIN horarios h ON m.horario_id = h.id
        INNER JOIN cursos c ON h.curso_id = c.id
        WHERE m.docente_id = ?
        ORDER BY m.fecha_subida DESC
    ";
    $stmt = $mysqli->prepare($sqlMat);
    $stmt->bind_param("i", $docenteId);
}

$stmt->execute();
$materiales = $stmt->get_result();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 fw-bold mt-3">Materiales del curso</h1>

<a href="cursos.php" class="btn btn-sm btn-secondary mb-3">&larr; Volver a mis cursos</a>

<?php if ($mensaje): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        Subir nuevo material
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Curso / Horario</label>
                    <select name="horario_id" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php
                        // Volvemos a obtener horarios para el select
                        $stmt = $mysqli->prepare($sqlHor);
                        $stmt->bind_param("i", $docenteId);
                        $stmt->execute();
                        $horarios2 = $stmt->get_result();
                        while ($h = $horarios2->fetch_assoc()):
                        ?>
                            <option value="<?php echo (int)$h['id']; ?>" <?php echo $horario_id_param == $h['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($h['nombre_curso'] . " - " . $h['nombre_dia'] . " " . $h['hora_inicio']); ?>
                            </option>
                        <?php endwhile;
                        $stmt->close();
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de archivo</label>
                    <select name="tipo_archivo_id" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php while ($t = $tipos->fetch_assoc()): ?>
                            <option value="<?php echo (int)$t['id']; ?>">
                                <?php echo htmlspecialchars($t['tipo_archivo']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Archivo</label>
                    <input type="file" name="archivo" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descripción (opcional)</label>
                    <input type="text" name="descripcion" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Subir material</button>
        </form>
    </div>
</div>

<h5>Materiales subidos</h5>
<table class="table table-striped table-sm align-middle">
    <thead>
        <tr>
            <th>Curso</th>
            <th>Título</th>
            <th>Archivo</th>
            <th>Tamaño</th>
            <th>Fecha subida</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($m = $materiales->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($m['nombre_curso']); ?></td>
                <td><?php echo htmlspecialchars($m['titulo']); ?></td>
                <td>
                    <a href="<?php echo htmlspecialchars($m['archivo_url']); ?>" target="_blank">
                        Ver / descargar
                    </a>
                </td>
                <td><?php echo number_format(($m['tamano_archivo'] ?? 0) / 1024, 2); ?> KB</td>
                <td><?php echo htmlspecialchars($m['fecha_subida']); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include __DIR__ . "/../includes/footer.php"; ?>
