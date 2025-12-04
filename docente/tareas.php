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
$resHor = $stmtHor->get_result();
$horarios = [];
while ($row = $resHor->fetch_assoc()) {
    $horarios[] = $row;
}
$stmtHor->close();

// 2) Acciones POST: crear o eliminar tarea
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // CREAR TAREA
    if ($accion === 'crear_tarea') {
        $horario_id       = (int)($_POST['horario_id'] ?? 0);
        $titulo           = trim($_POST['titulo'] ?? '');
        $descripcion      = trim($_POST['descripcion'] ?? '');
        $fecha_entrega    = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : null;
        $modalidad        = ($_POST['modalidad'] ?? 'individual') === 'grupo' ? 'grupo' : 'individual';
        $permitir_atraso  = isset($_POST['permitir_atraso']) ? 1 : 0;
        $valor_maximo     = isset($_POST['valor_maximo']) ? (int)$_POST['valor_maximo'] : 100;
        $archivo_instrucciones = null;

        if ($valor_maximo <= 0) {
            $valor_maximo = 1;
        }

        if ($horario_id <= 0 || $titulo === '') {
            $error = "Debes seleccionar un horario y escribir un título.";
        } else {
            // Validar que el horario pertenezca a este docente
            $checkHor = $mysqli->prepare("SELECT id FROM horarios WHERE id = ? AND docente_id = ? LIMIT 1");
            $checkHor->bind_param("ii", $horario_id, $docenteId);
            $checkHor->execute();
            $resCheckHor = $checkHor->get_result();
            $checkHor->close();

            if ($resCheckHor->num_rows === 0) {
                $error = "El horario seleccionado no pertenece a tu carga.";
            }
        }

        // Manejo de archivo de instrucciones (opcional)
        if (!$error && !empty($_FILES['archivo_instrucciones']['name'])) {
            $file = $_FILES['archivo_instrucciones'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $nombreOriginal = $file['name'];
                $tmpName        = $file['tmp_name'];
                $ext            = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                $nuevoNombre    = uniqid('tarea_') . ($ext ? '.' . $ext : '');
                $rutaDestino    = $uploadDir . $nuevoNombre;

                if (move_uploaded_file($tmpName, $rutaDestino)) {
                    $archivo_instrucciones = '/twintalk/uploads/tareas/' . $nuevoNombre;
                } else {
                    $error = "No se pudo guardar el archivo de instrucciones en el servidor.";
                }
            } else {
                $error = "Error al subir el archivo de instrucciones.";
            }
        }

        if (!$error) {
            $sqlIns = "
                INSERT INTO tareas
                (docente_id, horario_id, modalidad, titulo, descripcion,
                 fecha_publicacion, fecha_entrega, permitir_atraso,
                 archivo_instrucciones, activo, valor_maximo)
                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, 1, ?)
            ";
            $stmtIns = $mysqli->prepare($sqlIns);
            $stmtIns->bind_param(
                "iissssisi",
                $docenteId,
                $horario_id,
                $modalidad,
                $titulo,
                $descripcion,
                $fecha_entrega,
                $permitir_atraso,
                $archivo_instrucciones,
                $valor_maximo
            );
            if ($stmtIns->execute()) {
                $mensaje = "Tarea creada correctamente.";
            } else {
                $error = "Error al guardar la tarea: " . $stmtIns->error;
            }
            $stmtIns->close();
        }

    // ELIMINAR TAREA
    } elseif ($accion === 'eliminar_tarea') {
        $tarea_id = (int)($_POST['tarea_id'] ?? 0);

        if ($tarea_id > 0) {
            // Validar que la tarea sea de este docente
            $stmtVal = $mysqli->prepare("
                SELECT t.id
                FROM tareas t
                INNER JOIN horarios h ON t.horario_id = h.id
                WHERE t.id = ? AND h.docente_id = ?
                LIMIT 1
            ");
            $stmtVal->bind_param("ii", $tarea_id, $docenteId);
            $stmtVal->execute();
            $resVal = $stmtVal->get_result();
            $stmtVal->close();

            if ($resVal->num_rows === 0) {
                $error = "No puedes eliminar esta tarea.";
            } else {
                // Borrar entregas asociadas
                $stmtDelEnt = $mysqli->prepare("DELETE FROM tareas_entregas WHERE tarea_id = ?");
                $stmtDelEnt->bind_param("i", $tarea_id);
                $stmtDelEnt->execute();
                $stmtDelEnt->close();

                // Marcar tarea como inactiva
                $stmtDelTar = $mysqli->prepare("UPDATE tareas SET activo = 0 WHERE id = ?");
                $stmtDelTar->bind_param("i", $tarea_id);
                $stmtDelTar->execute();
                $stmtDelTar->close();

                $mensaje = "Tarea eliminada correctamente.";
            }
        }
    }
}

// 3) Listado de tareas del docente (opcionalmente filtradas por horario)
$params = [$docenteId];
$types  = "i";

$sqlTar = "
    SELECT 
        t.id,
        t.titulo,
        t.descripcion,
        t.fecha_publicacion,
        t.fecha_entrega,
        t.modalidad,
        t.permitir_atraso,
        t.archivo_instrucciones,
        t.valor_maximo,
        h.id AS horario_id,
        c.id AS curso_id,
        c.nombre_curso,
        d.nombre_dia,
        h.hora_inicio
    FROM tareas t
    INNER JOIN horarios h ON t.horario_id = h.id
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.docente_id = ?
      AND t.activo = 1
";

if ($horario_id_param > 0) {
    $sqlTar .= " AND t.horario_id = ?";
    $types   .= "i";
    $params[] = $horario_id_param;
}

$sqlTar .= "
    ORDER BY c.nombre_curso, d.numero_dia, h.hora_inicio, t.fecha_publicacion DESC
";

$stmtTar = $mysqli->prepare($sqlTar);
$stmtTar->bind_param($types, ...$params);
$stmtTar->execute();
$tareas = $stmtTar->get_result();
$stmtTar->close();

include __DIR__ . '/../includes/header.php';
?>

<style>
    .tt-tareas-page .tt-header-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #b14f72;
    }
    .tt-tareas-page .tt-header-subtitle {
        font-size: 0.9rem;
        color: #6c757d;
    }
    .tt-tareas-page .card-soft {
        border-radius: 14px;
        border: 1px solid #f1e3ea;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    .tt-tareas-page .btn-tt-primary {
        background-color: #b14f72;
        border-color: #b14f72;
        color: #fff;
        border-radius: 10px;
        transition: all 0.15s ease-in-out;
    }
    .tt-tareas-page .btn-tt-primary:hover {
        background-color: #8f3454;
        border-color: #8f3454;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(177,79,114,0.35);
    }
    .tt-tareas-page .btn-tt-outline {
        border-radius: 999px;
        border: 1px solid #b14f72;
        color: #b14f72;
        background-color: #fff;
        font-size: 0.85rem;
        padding-inline: 0.9rem;
        transition: all 0.15s ease-in-out;
    }
    .tt-tareas-page .btn-tt-outline:hover {
        background-color: #b14f72;
        color: #fff;
        box-shadow: 0 3px 8px rgba(177,79,114,0.35);
    }
    .tt-tareas-page .table thead {
        background-color: #fdf3f7;
        font-size: 0.85rem;
    }
    .tt-tareas-page .table tbody td {
        font-size: 0.85rem;
        vertical-align: middle;
    }
</style>

<div class="container mt-4 tt-tareas-page">

    <!-- Encabezado estilo TwinTalk -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
             style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">
            <div>
                <h1 class="tt-header-title mb-1">
                    <i class="fa-solid fa-tasks me-2"></i>
                    Tareas
                </h1>
                <p class="tt-header-subtitle mb-0">
                    Crea, administra y revisa las tareas de tus cursos.
                </p>
            </div>
            <div class="text-md-end">
                <a href="/twintalk/docente/dashboard.php"
                   class="btn btn-sm btn-tt-outline">
                    <i class="fa-solid fa-arrow-left me-1"></i>
                    Volver al dashboard
                </a>
            </div>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success border-0 shadow-sm mb-3">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Card crear nueva tarea -->
    <div class="card card-soft mb-4">
        <div class="card-header bg-white border-0 pb-0">
            <h2 class="h6 fw-bold mb-1" style="color:#b14f72;">
                Crear nueva tarea
            </h2>
            <small class="text-muted">
                Completa la información para publicar una nueva tarea en uno de tus horarios.
            </small>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear_tarea">

                <div class="mb-3">
                    <label class="form-label small">Horario</label>
                    <select name="horario_id" class="form-select form-select-sm" required>
                        <option value="">Seleccione un horario</option>
                        <?php foreach ($horarios as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= ($h['id'] == $horario_id_param ? 'selected' : '') ?>>
                                <?= htmlspecialchars($h['nombre_curso']) ?> -
                                <?= htmlspecialchars($h['nombre_dia']) ?>
                                (<?= substr($h['hora_inicio'], 0, 5) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Título de la tarea</label>
                    <input type="text" name="titulo" class="form-control form-control-sm" required>
                </div>

                <div class="mb-3">
                    <label class="form-label d-block small mb-1">Modalidad</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modalidad" id="mod_individual" value="individual" checked>
                        <label class="form-check-label small" for="mod_individual">Individual</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modalidad" id="mod_grupo" value="grupo">
                        <label class="form-check-label small" for="mod_grupo">Grupo</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Valor máximo de la tarea (puntos)</label>
                    <input type="number" name="valor_maximo" class="form-control form-control-sm" min="1" value="100" required>
                    <div class="form-text">Ejemplo: 10, 20, 25, 100, etc.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Descripción / instrucciones</label>
                    <textarea name="descripcion" rows="3" class="form-control form-control-sm"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Fecha de entrega (opcional)</label>
                    <input type="date" name="fecha_entrega" class="form-control form-control-sm">
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="permitir_atraso" id="permitir_atraso" value="1">
                    <label class="form-check-label small" for="permitir_atraso">
                        Permitir entregas tardías para esta tarea
                    </label>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Archivo de instrucciones (opcional)</label>
                    <input type="file" name="archivo_instrucciones" class="form-control form-control-sm">
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-sm btn-tt-primary">
                        <i class="fa-solid fa-save me-1"></i>
                        Guardar tarea
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de tareas -->
    <div class="card card-soft">
        <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h6 fw-bold mb-1" style="color:#b14f72;">
                    Listado de tareas
                </h2>
                <small class="text-muted">
                    Revisa las tareas creadas y accede a la sección de calificación.
                </small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Curso / Horario</th>
                            <th>Título</th>
                            <th>Valor</th>
                            <th>Modalidad</th>
                            <th>Publicada</th>
                            <th>Vence</th>
                            <th>Entregas</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tareas->num_rows === 0): ?>
                            <tr>
                                <td colspan="8" class="text-muted text-center py-3">
                                    Aún no has creado tareas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($t = $tareas->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="small fw-semibold">
                                            <?= htmlspecialchars($t['nombre_curso']) ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($t['nombre_dia']) ?> - <?= substr($t['hora_inicio'], 0, 5) ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <?= htmlspecialchars($t['titulo']) ?>
                                    </td>
                                    <td class="small">
                                        <?= (int)$t['valor_maximo'] ?> pts
                                    </td>
                                    <td class="small">
                                        <?= $t['modalidad'] === 'grupo' ? 'Grupo' : 'Individual' ?>
                                    </td>
                                    <td class="small">
                                        <?= $t['fecha_publicacion'] ? date('d/m/Y H:i', strtotime($t['fecha_publicacion'])) : '-' ?>
                                    </td>
                                    <td class="small">
                                        <?= $t['fecha_entrega'] ? date('d/m/Y', strtotime($t['fecha_entrega'])) : '-' ?>
                                    </td>
                                    <td class="small">
                                        <a href="/twintalk/docente/calificaciones.php?view=tareas&curso_id=<?= $t['curso_id'] ?>&tarea_id=<?= $t['id'] ?>" 
                                           class="btn btn-sm btn-tt-outline">
                                            Calificar tarea
                                        </a>
                                    </td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('¿Eliminar esta tarea? Se borrarán también las entregas.');">
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
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
