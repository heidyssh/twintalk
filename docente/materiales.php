<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); // Docente

$docenteId = $_SESSION['usuario_id'] ?? null;
if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error   = "";

// Carpeta de subida
$uploadDir = __DIR__ . '/../uploads/materiales/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// 1) Horarios del docente
$sqlHor = "
    SELECT h.id, c.nombre_curso, d.nombre_dia, h.hora_inicio
    FROM horarios h
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.docente_id = ?
    ORDER BY c.nombre_curso, d.numero_dia, h.hora_inicio
";
$stmtHor = $mysqli->prepare($sqlHor);
$stmtHor->bind_param("i", $docenteId);
$stmtHor->execute();
$horarios = $stmtHor->get_result();
$stmtHor->close();

// 2) Manejar acciones POST (subir / eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // SUBIR MATERIAL
    if ($accion === 'subir_material') {
        $horario_id  = (int)($_POST['horario_id'] ?? 0);
        $titulo      = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if ($horario_id <= 0 || !$titulo) {
            $error = "Debes elegir un curso y escribir un título.";
        } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $error = "Debes seleccionar un archivo válido.";
        } else {
            // Verificar que el horario sea del docente
            $checkHor = $mysqli->prepare("SELECT id FROM horarios WHERE id = ? AND docente_id = ? LIMIT 1");
            $checkHor->bind_param("ii", $horario_id, $docenteId);
            $checkHor->execute();
            $resHor = $checkHor->get_result();
            $checkHor->close();

            if ($resHor->num_rows === 0) {
                $error = "El horario seleccionado no pertenece a tus cursos.";
            } else {
                $file          = $_FILES['archivo'];
                $nombreOriginal = $file['name'];
                $tmpName        = $file['tmp_name'];
                $ext            = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                $nuevoNombre    = uniqid('mat_') . ($ext ? '.' . $ext : '');
                $rutaDestino    = $uploadDir . $nuevoNombre;

                if (!move_uploaded_file($tmpName, $rutaDestino)) {
                    $error = "No se pudo guardar el archivo en el servidor.";
                } else {
                    $archivo_url = '/twintalk/uploads/materiales/' . $nuevoNombre;

                    $sqlIns = "
                        INSERT INTO materiales
                        (docente_id, horario_id, titulo, descripcion, archivo_url, fecha_subida)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ";
                    $stmtIns = $mysqli->prepare($sqlIns);
                    $stmtIns->bind_param(
                        "iisss",
                        $docenteId,
                        $horario_id,
                        $titulo,
                        $descripcion,
                        $archivo_url
                    );
                    if ($stmtIns->execute()) {
                        $mensaje = "Material subido correctamente.";
                    } else {
                        $error = "Error al guardar el material: " . $stmtIns->error;
                    }
                    $stmtIns->close();
                }
            }
        }

    // ELIMINAR MATERIAL
    } elseif ($accion === 'eliminar_material') {
        $material_id = (int)($_POST['material_id'] ?? 0);
        if ($material_id > 0) {
            // Primero obtener la ruta para eliminar archivo físico (opcional)
            $sqlGet = "SELECT archivo_url FROM materiales WHERE id = ? AND docente_id = ? LIMIT 1";
            $stmtGet = $mysqli->prepare($sqlGet);
            $stmtGet->bind_param("ii", $material_id, $docenteId);
            $stmtGet->execute();
            $resGet = $stmtGet->get_result()->fetch_assoc();
            $stmtGet->close();

            if ($resGet) {
                $rutaRel = $resGet['archivo_url']; // ej: /twintalk/uploads/materiales/xxx.pdf
                $rutaAbs = realpath(__DIR__ . '/..' . str_replace('/twintalk', '', $rutaRel));
                // Si quieres eliminar el archivo físico, descomenta esto:
                // if ($rutaAbs && file_exists($rutaAbs)) {
                //     unlink($rutaAbs);
                // }

                $sqlDelMat = "DELETE FROM materiales WHERE id = ? AND docente_id = ?";
                $stmtDelMat = $mysqli->prepare($sqlDelMat);
                $stmtDelMat->bind_param("ii", $material_id, $docenteId);
                if ($stmtDelMat->execute()) {
                    $mensaje = "Material eliminado correctamente.";
                } else {
                    $error = "No se pudo eliminar el material.";
                }
                $stmtDelMat->close();
            }
        }
    }
}

// 3) Listar materiales del docente
$sqlMat = "
    SELECT 
        m.id,
        m.titulo,
        m.descripcion,
        m.archivo_url,
        m.fecha_subida,
        c.nombre_curso,
        d.nombre_dia,
        h.hora_inicio
    FROM materiales m
    INNER JOIN horarios h ON m.horario_id = h.id
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE m.docente_id = ?
    ORDER BY m.fecha_subida DESC
";
$stmtMat = $mysqli->prepare($sqlMat);
$stmtMat->bind_param("i", $docenteId);
$stmtMat->execute();
$materiales = $stmtMat->get_result();
$stmtMat->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-3">
    <h1 class="h4 fw-bold mb-3">Materiales de mis cursos</h1>

    <a href="dashboard.php" class="btn btn-sm btn-secondary mb-3">&larr; Volver al dashboard</a>

    <?php if ($mensaje): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Formulario para subir material -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Subir nuevo material</strong>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="subir_material">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Curso / horario</label>
                        <select name="horario_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($horarios as $h): ?>
                                <option value="<?= $h['id'] ?>">
                                    <?= htmlspecialchars($h['nombre_curso']) ?> -
                                    <?= htmlspecialchars($h['nombre_dia']) ?> <?= substr($h['hora_inicio'], 0, 5) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Título del material</label>
                        <input type="text" name="titulo" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Archivo</label>
                        <input type="file" name="archivo" class="form-control" required>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">Descripción (opcional)</label>
                    <textarea name="descripcion" rows="2" class="form-control"></textarea>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Subir material</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de materiales -->
    <div class="card">
        <div class="card-header">
            <strong>Mis materiales subidos</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Título</th>
                            <th>Archivo</th>
                            <th>Fecha subida</th>
                            <th>Eliminar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($materiales->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" class="text-muted small text-center py-3">
                                    Aún no has subido materiales.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($m = $materiales->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($m['nombre_curso']) ?></strong><br>
                                        <span class="small text-muted">
                                            <?= htmlspecialchars($m['nombre_dia']) ?> <?= substr($m['hora_inicio'], 0, 5) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($m['titulo']) ?></strong><br>
                                        <?php if ($m['descripcion']): ?>
                                            <span class="small text-muted">
                                                <?= nl2br(htmlspecialchars(substr($m['descripcion'], 0, 80))) ?>
                                                <?= (strlen($m['descripcion']) > 80 ? '...' : '') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($m['archivo_url']): ?>
                                            <a href="<?= htmlspecialchars($m['archivo_url']) ?>" target="_blank">
                                                Ver archivo
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($m['fecha_subida']) ?></td>

                                    <!-- AQUÍ VA EL BOTÓN DE ELIMINAR MATERIAL -->
                                    <td>
                                        <form method="post" onsubmit="return confirm('¿Eliminar este material?');">
                                            <input type="hidden" name="accion" value="eliminar_material">
                                            <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
