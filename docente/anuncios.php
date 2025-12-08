<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); 

$docenteId = $_SESSION['usuario_id'] ?? null;
if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error   = "";


$sqlTipos = "SELECT id, tipo_anuncio AS nombre_tipo FROM tipos_anuncio ORDER BY tipo_anuncio";
$tipos = $mysqli->query($sqlTipos);


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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    
    if ($accion === 'crear_anuncio') {
        $horario_id       = (int)($_POST['horario_id'] ?? 0);
        $tipo_anuncio_id  = (int)($_POST['tipo_anuncio_id'] ?? 0);
        $titulo           = trim($_POST['titulo'] ?? '');
        $contenido        = trim($_POST['contenido'] ?? '');
        $fecha_expiracion = !empty($_POST['fecha_expiracion']) ? $_POST['fecha_expiracion'] : null;
        $importante       = isset($_POST['importante']) ? 1 : 0;

        if ($horario_id <= 0 || $tipo_anuncio_id <= 0 || !$titulo || !$contenido) {
            $error = "Debes completar todos los campos obligatorios.";
        } else {
            
            $checkHor = $mysqli->prepare("SELECT id FROM horarios WHERE id = ? AND docente_id = ? LIMIT 1");
            $checkHor->bind_param("ii", $horario_id, $docenteId);
            $checkHor->execute();
            $resHor = $checkHor->get_result();
            $checkHor->close();

            if ($resHor->num_rows === 0) {
                $error = "El horario seleccionado no pertenece a tus cursos.";
            } else {
                $sqlIns = "
                    INSERT INTO anuncios
                    (docente_id, horario_id, tipo_anuncio_id, titulo, contenido, fecha_expiracion, importante)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";
                $stmtIns = $mysqli->prepare($sqlIns);
                $stmtIns->bind_param(
                    "iiisssi",
                    $docenteId,
                    $horario_id,
                    $tipo_anuncio_id,
                    $titulo,
                    $contenido,
                    $fecha_expiracion,
                    $importante
                );
                if ($stmtIns->execute()) {
                    $mensaje = "Anuncio publicado correctamente.";
                } else {
                    $error = "Error al publicar el anuncio: " . $stmtIns->error;
                }
                $stmtIns->close();
            }
        }

    
    } elseif ($accion === 'eliminar_anuncio') {
        $anuncio_id = (int)($_POST['anuncio_id'] ?? 0);
        if ($anuncio_id > 0) {
            $sqlDelAn = "DELETE FROM anuncios WHERE id = ? AND docente_id = ?";
            $stmtDelAn = $mysqli->prepare($sqlDelAn);
            $stmtDelAn->bind_param("ii", $anuncio_id, $docenteId);
            if ($stmtDelAn->execute()) {
                $mensaje = "Anuncio eliminado correctamente.";
            } else {
                $error = "No se pudo eliminar el anuncio.";
            }
            $stmtDelAn->close();
        }
    }
}


$sqlAn = "
    SELECT 
        a.id,
        a.titulo,
        a.contenido,
        a.fecha_publicacion,
        a.fecha_expiracion,
        a.importante,
        ta.tipo_anuncio AS nombre_tipo,
        c.nombre_curso,
        d.nombre_dia,
        h.hora_inicio
    FROM anuncios a
    INNER JOIN tipos_anuncio ta ON a.tipo_anuncio_id = ta.id
    INNER JOIN horarios h ON a.horario_id = h.id
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE a.docente_id = ?
    ORDER BY a.fecha_publicacion DESC
";
$stmtAn = $mysqli->prepare($sqlAn);
$stmtAn->bind_param("i", $docenteId);
$stmtAn->execute();
$anuncios = $stmtAn->get_result();
$stmtAn->close();

include __DIR__ . '/../includes/header.php';
?>

<style>
    .tt-anuncios-page .tt-header-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #b14f72;
    }
    .tt-anuncios-page .tt-header-subtitle {
        font-size: 0.9rem;
        color: #6c757d;
    }
    .tt-anuncios-page .card-soft {
        border-radius: 14px;
        border: 1px solid #f1e3ea;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    .tt-anuncios-page .btn-tt-primary {
        background-color: #b14f72;
        border-color: #b14f72;
        color: #fff;
        border-radius: 10px;
        font-size: 0.9rem;
        padding-inline: 1rem;
        transition: all 0.15s ease-in-out;
    }
    .tt-anuncios-page .btn-tt-primary:hover {
        background-color: #8f3454;
        border-color: #8f3454;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(177,79,114,0.35);
    }
    .tt-anuncios-page .btn-tt-outline {
        border-radius: 999px;
        border: 1px solid #b14f72;
        color: #b14f72;
        background-color: #fff;
        font-size: 0.85rem;
        padding-inline: 0.9rem;
        transition: all 0.15s ease-in-out;
    }
    .tt-anuncios-page .btn-tt-outline:hover {
        background-color: #b14f72;
        color: #fff;
        box-shadow: 0 3px 8px rgba(177,79,114,0.35);
    }
    .tt-anuncios-page .table thead {
        background-color: #fdf3f7;
        font-size: 0.85rem;
    }
    .tt-anuncios-page .table tbody td {
        font-size: 0.85rem;
        vertical-align: middle;
    }
</style>

<div class="container-fluid mt-3 tt-anuncios-page">

    <!-- Encabezado bonito -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
             style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">
            <div>
                <h1 class="tt-header-title mb-1">
                    <i class="fa-solid fa-bullhorn me-2"></i>
                    Anuncios de mis cursos
                </h1>
                <p class="tt-header-subtitle mb-0">
                    Publica avisos importantes, recordatorios y comunicados para tus grupos.
                </p>
            </div>
            <div class="text-md-end">
                <a href="dashboard.php" class="btn btn-sm btn-tt-outline">
                    <i class="fa-solid fa-arrow-left me-1"></i>
                    Volver al dashboard
                </a>
            </div>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success border-0 shadow-sm py-2 small mb-3">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm py-2 small mb-3">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Formulario para crear anuncio -->
    <div class="card card-soft mb-4">
        <div class="card-header bg-white border-0 pb-0">
            <strong class="d-block" style="color:#b14f72;">Publicar nuevo anuncio</strong>
            <small class="text-muted">
                Selecciona el curso, tipo de anuncio y redacta el mensaje que verán tus estudiantes.
            </small>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="accion" value="crear_anuncio">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Curso / horario</label>
                        <select name="horario_id" class="form-select form-select-sm" required>
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
                        <label class="form-label small">Tipo de anuncio</label>
                        <select name="tipo_anuncio_id" class="form-select form-select-sm" required>
                            <option value="">Seleccione...</option>
                            <?php while ($t = $tipos->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>">
                                    <?= htmlspecialchars($t['nombre_tipo']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small">Fecha de expiración (opcional)</label>
                        <input type="date" name="fecha_expiracion" class="form-control form-control-sm">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label small">Título</label>
                    <input type="text" name="titulo" class="form-control form-control-sm" required>
                </div>

                <div class="mt-3">
                    <label class="form-label small">Contenido</label>
                    <textarea name="contenido" rows="3" class="form-control form-control-sm" required></textarea>
                </div>

                <div class="mt-2 form-check">
                    <input class="form-check-input" type="checkbox" name="importante" id="chkImportante">
                    <label class="form-check-label small" for="chkImportante">
                        Marcar como importante
                    </label>
                </div>

                <div class="mt-3 text-end">
                    <button type="submit" class="btn btn-tt-primary btn-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i>
                        Publicar anuncio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de anuncios -->
    <div class="card card-soft">
        <div class="card-header bg-white border-0 pb-0">
            <strong class="d-block" style="color:#b14f72;">Mis anuncios publicados</strong>
            <small class="text-muted">
                Revisa el historial de anuncios, su estado e importancia por curso.
            </small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Tipo</th>
                            <th>Título</th>
                            <th>Fecha publicación</th>
                            <th>Expira</th>
                            <th>Importante</th>
                            <th class="text-end">Eliminar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($anuncios->num_rows === 0): ?>
                            <tr>
                                <td colspan="7" class="text-muted small text-center py-3">
                                    Aún no has publicado anuncios.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($a = $anuncios->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong class="small"><?= htmlspecialchars($a['nombre_curso']) ?></strong><br>
                                        <span class="small text-muted">
                                            <?= htmlspecialchars($a['nombre_dia']) ?> <?= substr($a['hora_inicio'], 0, 5) ?>
                                        </span>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($a['nombre_tipo']) ?></td>
                                    <td>
                                        <strong class="small"><?= htmlspecialchars($a['titulo']) ?></strong><br>
                                        <span class="small text-muted">
                                            <?= nl2br(htmlspecialchars(substr($a['contenido'], 0, 80))) ?>
                                            <?= (strlen($a['contenido']) > 80 ? '...' : '') ?>
                                        </span>
                                    </td>
                                    <td class="small">
                                        <?= htmlspecialchars($a['fecha_publicacion']) ?>
                                    </td>
                                    <td class="small">
                                        <?= $a['fecha_expiracion'] ? htmlspecialchars($a['fecha_expiracion']) : '-' ?>
                                    </td>
                                    <td class="small">
                                        <?php if ($a['importante']): ?>
                                            <span class="badge bg-danger-subtle text-danger">Sí</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary">No</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Botón de eliminar -->
                                    <td class="text-end">
                                        <form method="post" onsubmit="return confirm('¿Eliminar este anuncio?');" class="d-inline">
                                            <input type="hidden" name="accion" value="eliminar_anuncio">
                                            <input type="hidden" name="anuncio_id" value="<?= $a['id'] ?>">
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
