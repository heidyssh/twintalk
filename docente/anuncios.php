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

// 1) Cargar tipos de anuncio
$sqlTipos = "SELECT id, tipo_anuncio AS nombre_tipo FROM tipos_anuncio ORDER BY tipo_anuncio";
$tipos = $mysqli->query($sqlTipos);

// 2) Cargar horarios del docente (para seleccionar curso)
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

// 3) Manejar acciones POST (crear / eliminar anuncio)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // CREAR ANUNCIO
    if ($accion === 'crear_anuncio') {
        $horario_id      = (int)($_POST['horario_id'] ?? 0);
        $tipo_anuncio_id = (int)($_POST['tipo_anuncio_id'] ?? 0);
        $titulo          = trim($_POST['titulo'] ?? '');
        $contenido       = trim($_POST['contenido'] ?? '');
        $fecha_expiracion = !empty($_POST['fecha_expiracion']) ? $_POST['fecha_expiracion'] : null;
        $importante       = isset($_POST['importante']) ? 1 : 0;

        if ($horario_id <= 0 || $tipo_anuncio_id <= 0 || !$titulo || !$contenido) {
            $error = "Debes completar todos los campos obligatorios.";
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

    // ELIMINAR ANUNCIO
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

// 4) Listar anuncios del docente
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

<div class="container-fluid mt-3">
    <h1 class="h4 fw-bold mb-3">Anuncios de mis cursos</h1>

    <a href="dashboard.php" class="btn btn-sm btn-secondary mb-3">&larr; Volver al dashboard</a>

    <?php if ($mensaje): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Formulario para crear anuncio -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Publicar nuevo anuncio</strong>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="accion" value="crear_anuncio">

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
                        <label class="form-label">Tipo de anuncio</label>
                        <select name="tipo_anuncio_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php while ($t = $tipos->fetch_assoc()): ?>
                                <option value="<?= $t['id'] ?>">
                                    <?= htmlspecialchars($t['nombre_tipo']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Fecha de expiración (opcional)</label>
                        <input type="date" name="fecha_expiracion" class="form-control">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" class="form-control" required>
                </div>

                <div class="mt-3">
                    <label class="form-label">Contenido</label>
                    <textarea name="contenido" rows="3" class="form-control" required></textarea>
                </div>

                <div class="mt-2 form-check">
                    <input class="form-check-input" type="checkbox" name="importante" id="chkImportante">
                    <label class="form-check-label small" for="chkImportante">
                        Marcar como importante
                    </label>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Publicar anuncio</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de anuncios -->
    <div class="card">
        <div class="card-header">
            <strong>Mis anuncios publicados</strong>
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
                            <th>Eliminar</th>
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
                                        <strong><?= htmlspecialchars($a['nombre_curso']) ?></strong><br>
                                        <span class="small text-muted">
                                            <?= htmlspecialchars($a['nombre_dia']) ?> <?= substr($a['hora_inicio'], 0, 5) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($a['nombre_tipo']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($a['titulo']) ?></strong><br>
                                        <span class="small text-muted">
                                            <?= nl2br(htmlspecialchars(substr($a['contenido'], 0, 80))) ?>
                                            <?= (strlen($a['contenido']) > 80 ? '...' : '') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($a['fecha_publicacion']) ?></td>
                                    <td><?= $a['fecha_expiracion'] ? htmlspecialchars($a['fecha_expiracion']) : '-' ?></td>
                                    <td>
                                        <?php if ($a['importante']): ?>
                                            <span class="badge bg-danger-subtle text-danger">Sí</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary">No</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- AQUÍ VA EL BOTÓN DE ELIMINAR -->
                                    <td>
                                        <form method="post" onsubmit="return confirm('¿Eliminar este anuncio?');">
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
