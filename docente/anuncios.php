<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]);

$docenteId = $_SESSION['usuario_id'] ?? null;

if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

// Horario opcional desde GET para precargar
$horario_id_default = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;

// Horarios del docente para el select
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

// Tipos de anuncio
$tipos = $mysqli->query("SELECT id, tipo_anuncio FROM tipos_anuncio ORDER BY tipo_anuncio ASC");

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo            = trim($_POST['titulo'] ?? '');
    $contenido         = trim($_POST['contenido'] ?? '');
    $tipo_anuncio_id   = (int)($_POST['tipo_anuncio_id'] ?? 0);
    $horario_id        = (int)($_POST['horario_id'] ?? 0);
    $fecha_expiracion  = $_POST['fecha_expiracion'] ?? null;
    $importante        = isset($_POST['importante']) ? 1 : 0;

    if ($titulo && $contenido && $tipo_anuncio_id > 0) {
        $sqlIns = "
            INSERT INTO anuncios
            (docente_id, horario_id, tipo_anuncio_id, titulo, contenido, fecha_expiracion, importante)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";

        // horario_id puede ser NULL
        $horario_id_db = $horario_id > 0 ? $horario_id : null;

        $stmt = $mysqli->prepare($sqlIns);
        $stmt->bind_param(
            "iiisssi",
            $docenteId,
            $horario_id_db,
            $tipo_anuncio_id,
            $titulo,
            $contenido,
            $fecha_expiracion,
            $importante
        );

        if ($stmt->execute()) {
            $mensaje = "Anuncio publicado correctamente.";
        } else {
            $mensaje = "Error al publicar anuncio: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $mensaje = "Debe completar título, contenido y tipo de anuncio.";
    }
}

// Listar anuncios del docente
$sqlAn = "
    SELECT 
        a.*,
        ta.tipo_anuncio,
        c.nombre_curso,
        h.id AS horario_id
    FROM anuncios a
    INNER JOIN tipos_anuncio ta ON a.tipo_anuncio_id = ta.id
    LEFT JOIN horarios h ON a.horario_id = h.id
    LEFT JOIN cursos c ON h.curso_id = c.id
    WHERE a.docente_id = ?
    ORDER BY a.fecha_publicacion DESC
";
$stmt = $mysqli->prepare($sqlAn);
$stmt->bind_param("i", $docenteId);
$stmt->execute();
$anuncios = $stmt->get_result();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 fw-bold mt-3">Anuncios del curso</h1>

<a href="cursos.php" class="btn btn-sm btn-secondary mb-3">&larr; Volver a mis cursos</a>

<?php if ($mensaje): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        Publicar nuevo anuncio
    </div>
    <div class="card-body">
        <form method="post">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo de anuncio</label>
                    <select name="tipo_anuncio_id" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php while ($t = $tipos->fetch_assoc()): ?>
                            <option value="<?php echo (int)$t['id']; ?>">
                                <?php echo htmlspecialchars($t['tipo_anuncio']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Curso / Horario (opcional)</label>
                    <select name="horario_id" class="form-select">
                        <option value="0">Todos / General</option>
                        <?php
                        // Como ya usamos $horarios arriba, volvemos a hacer la consulta rápida:
                        $stmt = $mysqli->prepare($sqlHor);
                        $stmt->bind_param("i", $docenteId);
                        $stmt->execute();
                        $horarios2 = $stmt->get_result();
                        while ($h = $horarios2->fetch_assoc()):
                        ?>
                            <option value="<?php echo (int)$h['id']; ?>" <?php echo $horario_id_default == $h['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($h['nombre_curso'] . " - " . $h['nombre_dia'] . " " . $h['hora_inicio']); ?>
                            </option>
                        <?php endwhile;
                        $stmt->close();
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha de expiración (opcional)</label>
                    <input type="date" name="fecha_expiracion" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Importante</label><br>
                    <input type="checkbox" name="importante" value="1"> Marcar como importante
                </div>
                <div class="col-md-12">
                    <label class="form-label">Contenido</label>
                    <textarea name="contenido" class="form-control" rows="4" required></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Publicar anuncio</button>
        </form>
    </div>
</div>

<h5>Anuncios publicados</h5>
<ul class="list-group">
    <?php while ($a = $anuncios->fetch_assoc()): ?>
        <li class="list-group-item">
            <div class="d-flex justify-content-between">
                <div>
                    <strong><?php echo htmlspecialchars($a['titulo']); ?></strong>
                    <?php if ($a['importante']): ?>
                        <span class="badge bg-danger ms-2">Importante</span>
                    <?php endif; ?>
                    <br>
                    <small class="text-muted">
                        <?php echo htmlspecialchars($a['tipo_anuncio']); ?> |
                        <?php echo htmlspecialchars($a['fecha_publicacion']); ?>
                        <?php if ($a['fecha_expiracion']): ?>
                            | expira: <?php echo htmlspecialchars($a['fecha_expiracion']); ?>
                        <?php endif; ?>
                        <?php if ($a['nombre_curso']): ?>
                            | Curso: <?php echo htmlspecialchars($a['nombre_curso']); ?>
                        <?php else: ?>
                            | General
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <p class="mt-2 mb-0"><?php echo nl2br(htmlspecialchars($a['contenido'])); ?></p>
        </li>
    <?php endwhile; ?>
</ul>

<?php include __DIR__ . "/../includes/footer.php"; ?>
