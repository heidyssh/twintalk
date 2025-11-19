<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); // Docente

$docenteId = $_SESSION['usuario_id'] ?? null;
if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$horario_id_param = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;

$mensaje = "";
$error   = "";

// Carpeta para archivos de tareas
$uploadDir = __DIR__ . '/../uploads/tareas/';
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

// 2) Acciones POST: crear o eliminar tarea
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // CREAR TAREA
    if ($accion === 'crear_tarea') {
        $horario_id    = (int)($_POST['horario_id'] ?? 0);
        $titulo        = trim($_POST['titulo'] ?? '');
        $descripcion   = trim($_POST['descripcion'] ?? '');
        $fecha_entrega = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : null;
        $modalidad     = ($_POST['modalidad'] ?? 'individual') === 'grupo' ? 'grupo' : 'individual';
        $archivo_instrucciones = null;

        if ($horario_id <= 0 || !$titulo) {
            $error = "Debes seleccionar un horario, un título y una modalidad.";
        } else {
            // Validar que el horario pertenezca a este docente
            $checkHor = $mysqli->prepare("SELECT id FROM horarios WHERE id = ? AND docente_id = ? LIMIT 1");
            $checkHor->bind_param("ii", $horario_id, $docenteId);
            $checkHor->execute();
            $resHor = $checkHor->get_result();
            $checkHor->close();

            if ($resHor->num_rows === 0) {
                $error = "El horario seleccionado no pertenece a tu lista de cursos.";
            } else {
                // Archivo de instrucciones (opcional)
                if (isset($_FILES['archivo_instrucciones']) && $_FILES['archivo_instrucciones']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['archivo_instrucciones'];
                    $nombreOriginal = $file['name'];
                    $tmpName        = $file['tmp_name'];
                    $ext            = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                    $nuevoNombre    = uniqid('tarea_') . ($ext ? '.' . $ext : '');
                    $rutaDestino    = $uploadDir . $nuevoNombre;

                    if (move_uploaded_file($tmpName, $rutaDestino)) {
                        $archivo_instrucciones = '/twintalk/uploads/tareas/' . $nuevoNombre;
                    } else {
                        $error = "No se pudo guardar el archivo de instrucciones.";
                    }
                }

                if (!$error) {
                    $sqlIns = "
                        INSERT INTO tareas
                        (docente_id, horario_id, modalidad, titulo, descripcion, fecha_entrega, archivo_instrucciones)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ";
                    $stmtIns = $mysqli->prepare($sqlIns);
                    $stmtIns->bind_param(
                        "iisssss",
                        $docenteId,
                        $horario_id,
                        $modalidad,
                        $titulo,
                        $descripcion,
                        $fecha_entrega,
                        $archivo_instrucciones
                    );
                    if ($stmtIns->execute()) {
                        $mensaje = "Tarea creada correctamente.";
                    } else {
                        $error = "Error al guardar la tarea: " . $stmtIns->error;
                    }
                    $stmtIns->close();
                }
            }
        }

    // ELIMINAR TAREA
    } elseif ($accion === 'eliminar_tarea') {
        $tarea_id = (int)($_POST['tarea_id'] ?? 0);
        if ($tarea_id > 0) {
            // Solo borra tareas del propio docente
            $sqlDel = "DELETE FROM tareas WHERE id = ? AND docente_id = ?";
            $stmtDel = $mysqli->prepare($sqlDel);
            $stmtDel->bind_param("ii", $tarea_id, $docenteId);
            if ($stmtDel->execute()) {
                $mensaje = "Tarea eliminada correctamente.";
            } else {
                $error = "No se pudo eliminar la tarea.";
            }
            $stmtDel->close();
        }
    }
}

// 3) Listar tareas del docente
if ($horario_id_param > 0) {
    $sqlTareas = "
        SELECT t.*, c.nombre_curso
        FROM tareas t
        INNER JOIN horarios h ON t.horario_id = h.id
        INNER INNER JOIN cursos c ON h.curso_id = c.id
        WHERE t.docente_id = ? AND t.horario_id = ? AND t.activo = 1
        ORDER BY t.fecha_publicacion DESC
    ";
    $stmtT = $mysqli->prepare($sqlTareas);
    $stmtT->bind_param("ii", $docenteId, $horario_id_param);
} else {
    $sqlTareas = "
        SELECT t.*, c.nombre_curso
        FROM tareas t
        INNER JOIN horarios h ON t.horario_id = h.id
        INNER JOIN cursos c ON h.curso_id = c.id
        WHERE t.docente_id = ? AND t.activo = 1
        ORDER BY t.fecha_publicacion DESC
    ";
    $stmtT = $mysqli->prepare($sqlTareas);
    $stmtT->bind_param("i", $docenteId);
}
$stmtT->execute();
$tareas = $stmtT->get_result();
$stmtT->close();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 fw-bold mt-3">Tareas de mis cursos</h1>

<a href="dashboard.php" class="btn btn-sm btn-secondary mb-3">&larr; Volver al dashboard</a>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <strong>Crear nueva tarea</strong>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="crear_tarea">

            <div class="mb-3">
                <label class="form-label">Curso / horario</label>
                <select name="horario_id" class="form-select" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($horarios as $h): ?>
                        <option value="<?= $h['id'] ?>" <?= ($horario_id_param == $h['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['nombre_curso']) ?> -
                            <?= htmlspecialchars($h['nombre_dia']) ?> <?= substr($h['hora_inicio'], 0, 5) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Modalidad</label>
                <select name="modalidad" class="form-select" required>
                    <option value="individual">Individual</option>
                    <option value="grupo">Grupal</option>
                </select>
                <div class="form-text small">
                    Solo indica si la tarea la trabajarán en grupo o individual.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Título de la tarea</label>
                <input type="text" name="titulo" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Descripción / instrucciones</label>
                <textarea name="descripcion" rows="3" class="form-control"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Fecha de entrega (opcional)</label>
                <input type="date" name="fecha_entrega" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Archivo de instrucciones (opcional)</label>
                <input type="file" name="archivo_instrucciones" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary">Guardar tarea</button>
        </form>
    </div>
</div>

<h5>Listado de tareas</h5>
<table class="table table-striped table-sm align-middle">
    <thead>
        <tr>
            <th>Curso</th>
            <th>Título</th>
            <th>Modalidad</th>
            <th>Fecha publicación</th>
            <th>Fecha entrega</th>
            <th>Archivo</th>
            <th>Entregas</th>
            <th>Eliminar</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($tareas->num_rows === 0): ?>
            <tr><td colspan="8" class="text-muted">Aún no has creado tareas.</td></tr>
        <?php else: ?>
            <?php while ($t = $tareas->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($t['nombre_curso']) ?></td>
                    <td><?= htmlspecialchars($t['titulo']) ?></td>
                    <td>
                        <?php if ($t['modalidad'] === 'grupo'): ?>
                            <span class="badge bg-info-subtle text-info">Grupal</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary">Individual</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($t['fecha_publicacion']) ?></td>
                    <td><?= $t['fecha_entrega'] ? htmlspecialchars($t['fecha_entrega']) : '-' ?></td>
                    <td>
                        <?php if ($t['archivo_instrucciones']): ?>
                            <a href="<?= htmlspecialchars($t['archivo_instrucciones']) ?>" target="_blank">Ver</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/twintalk/docente/tarea_entregas.php?tarea_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary">
                            Ver entregas
                        </a>
                    </td>
                    <td>
                        <form method="post" onsubmit="return confirm('¿Eliminar esta tarea? Se borrarán también las entregas.');">
                            <input type="hidden" name="accion" value="eliminar_tarea">
                            <input type="hidden" name="tarea_id" value="<?= $t['id'] ?>">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
