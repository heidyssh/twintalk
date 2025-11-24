<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); // solo estudiantes

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$horario_id = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;

if ($horario_id <= 0) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">Horario no válido.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

// 1) Verificar que el estudiante esté matriculado en ese horario
$check = $mysqli->prepare("
    SELECT m.id AS matricula_id, em.nombre_estado
    FROM matriculas m
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.estudiante_id = ? AND m.horario_id = ?
    LIMIT 1
");
$check->bind_param("ii", $usuario_id, $horario_id);
$check->execute();
$resCheck = $check->get_result();
$matricula = $resCheck->fetch_assoc();
$check->close();

if (!$matricula) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">No estás matriculado en este curso.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

$matricula_id = (int)$matricula['matricula_id'];

$mensaje_tarea = "";
$error_tarea   = "";

// Carpeta para archivos de tareas de estudiantes
$uploadDirTareas = __DIR__ . '/../uploads/tareas/';
if (!is_dir($uploadDirTareas)) {
    mkdir($uploadDirTareas, 0775, true);
}

// Manejar subida de tarea del estudiante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'subir_tarea') {
    $tarea_id = (int)($_POST['tarea_id'] ?? 0);

    if ($tarea_id <= 0 || !isset($_FILES['archivo_tarea'])) {
        $error_tarea = "Datos de tarea inválidos.";
    } else {
        // Validar que la tarea sea de este horario
        $sqlValT = "SELECT id, fecha_entrega, permitir_atraso FROM tareas WHERE id = ? AND horario_id = ? AND activo = 1 LIMIT 1";
        $stmtValT = $mysqli->prepare($sqlValT);
        $stmtValT->bind_param("ii", $tarea_id, $horario_id);
        $stmtValT->execute();
        $resValT = $stmtValT->get_result();
        $stmtValT->close();

        if ($resValT->num_rows === 0) {
            $error_tarea = "La tarea no pertenece a este curso.";
        } else {
            $tareaRow = $resValT->fetch_assoc();

            $hoy = date('Y-m-d');
            if (!empty($tareaRow['fecha_entrega'])
                && $hoy > $tareaRow['fecha_entrega']
                && (int)$tareaRow['permitir_atraso'] === 0) {

                $error_tarea = "⛔ Ya pasó la fecha de entrega y el docente no habilitó entregas tardías.";
            } else {
                $file = $_FILES['archivo_tarea'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error_tarea = "Error al subir el archivo.";
                } else {
                    $nombreOriginal = $file['name'];
                    $tmpName        = $file['tmp_name'];
                    $ext            = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                    $nuevoNombre    = uniqid('entrega_') . ($ext ? '.' . $ext : '');
                    $rutaDestino    = $uploadDirTareas . $nuevoNombre;

                    if (move_uploaded_file($tmpName, $rutaDestino)) {
                        $archivo_url    = '/twintalk/uploads/tareas/' . $nuevoNombre;
                        $tamano_archivo = $file['size'];

                        // Ver si ya existe entrega
                        $sqlCheckEnt = "
                            SELECT id FROM tareas_entregas
                            WHERE tarea_id = ? AND matricula_id = ?
                            LIMIT 1
                        ";
                        $stmtCE = $mysqli->prepare($sqlCheckEnt);
                        $stmtCE->bind_param("ii", $tarea_id, $matricula_id);
                        $stmtCE->execute();
                        $resCE = $stmtCE->get_result();
                        $existente = $resCE->fetch_assoc();
                        $stmtCE->close();

                        if ($existente) {
                            $sqlUpd = "
                                UPDATE tareas_entregas
                                SET archivo_url = ?, tamano_archivo = ?, fecha_entrega = NOW(),
                                    calificacion = NULL, comentarios_docente = NULL, fecha_calificacion = NULL
                                WHERE id = ?
                            ";
                            $stmtUpd = $mysqli->prepare($sqlUpd);
                            $stmtUpd->bind_param("sii", $archivo_url, $tamano_archivo, $existente['id']);
                            $ok = $stmtUpd->execute();
                            $stmtUpd->close();
                        } else {
                            $sqlIns = "
                                INSERT INTO tareas_entregas
                                (tarea_id, matricula_id, archivo_url, tamano_archivo)
                                VALUES (?, ?, ?, ?)
                            ";
                            $stmtIns = $mysqli->prepare($sqlIns);
                            $stmtIns->bind_param("iisi", $tarea_id, $matricula_id, $archivo_url, $tamano_archivo);
                            $ok = $stmtIns->execute();
                            $stmtIns->close();
                        }

                        if (!empty($ok)) {
                            $mensaje_tarea = "Archivo enviado correctamente.";
                        } else {
                            $error_tarea = "No se pudo guardar la entrega.";
                        }
                    } else {
                        $error_tarea = "No se pudo guardar el archivo en el servidor.";
                    }
                }
            }
        }
    }
}

// 2) Datos del curso, horario y docente
$infoSql = "
    SELECT
        h.id AS horario_id,
        c.nombre_curso,
        c.descripcion,
        n.codigo_nivel,
        n.nombre_nivel,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        h.aula,
        u.nombre AS docente_nombre,
        u.apellido AS docente_apellido,
        u.email AS docente_email,
        u.foto_perfil,
        ip.pais,
        ip.ciudad
    FROM horarios h
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN niveles_academicos n ON c.nivel_id = n.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    INNER JOIN docentes doc ON h.docente_id = doc.id
    INNER JOIN usuarios u ON doc.id = u.id
    LEFT JOIN informacion_personal ip ON u.id = ip.usuario_id
    WHERE h.id = ?
    LIMIT 1
";

$stmtInfo = $mysqli->prepare($infoSql);
$stmtInfo->bind_param("i", $horario_id);
$stmtInfo->execute();
$infoRes = $stmtInfo->get_result();
$curso = $infoRes->fetch_assoc();
$stmtInfo->close();

if (!$curso) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">No se encontró la información del curso.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

// 3) Materiales del curso
$matSql = "
    SELECT 
        m.id,
        m.titulo,
        m.descripcion,
        m.tipo_archivo_id,
        ta.tipo_archivo,
        m.archivo_url,
        m.fecha_subida
    FROM materiales m
    INNER JOIN tipos_archivo ta ON m.tipo_archivo_id = ta.id
    WHERE m.horario_id = ? AND m.activo = 1
    ORDER BY m.fecha_subida DESC
";
$stmtMat = $mysqli->prepare($matSql);
$stmtMat->bind_param("i", $horario_id);
$stmtMat->execute();
$materiales = $stmtMat->get_result();
$stmtMat->close();

// 4) Tareas del curso (con valor_maximo y mi entrega)
$tareasSql = "
    SELECT 
        t.id,
        t.titulo,
        t.descripcion,
        t.fecha_publicacion,
        t.fecha_entrega,
        t.archivo_instrucciones,
        t.valor_maximo,

        (SELECT te.archivo_url 
         FROM tareas_entregas te 
         WHERE te.tarea_id = t.id AND te.matricula_id = ? LIMIT 1) AS mi_archivo,

        (SELECT te.fecha_entrega 
         FROM tareas_entregas te 
         WHERE te.tarea_id = t.id AND te.matricula_id = ? LIMIT 1) AS mi_fecha_entrega,

        (SELECT te.calificacion 
         FROM tareas_entregas te 
         WHERE te.tarea_id = t.id AND te.matricula_id = ? LIMIT 1) AS mi_calificacion,

        (SELECT te.comentarios_docente 
         FROM tareas_entregas te 
         WHERE te.tarea_id = t.id AND te.matricula_id = ? LIMIT 1) AS mis_comentarios

    FROM tareas t
    WHERE t.horario_id = ? 
      AND t.activo = 1
    ORDER BY t.fecha_publicacion DESC
";
$stmtTar = $mysqli->prepare($tareasSql);
$stmtTar->bind_param(
    "iiiii",
    $matricula_id,
    $matricula_id,
    $matricula_id,
    $matricula_id,
    $horario_id
);
$stmtTar->execute();
$tareas = $stmtTar->get_result();
$stmtTar->close();

// 5) Anuncios específicos del curso
$anSql = "
    SELECT 
        a.titulo,
        a.contenido,
        a.fecha_publicacion,
        ta.tipo_anuncio AS tipo
    FROM anuncios a
    INNER JOIN tipos_anuncio ta ON a.tipo_anuncio_id = ta.id
    WHERE a.horario_id = ?
      AND (a.fecha_expiracion IS NULL OR a.fecha_expiracion >= CURDATE())
    ORDER BY a.fecha_publicacion DESC
";
$stmtAn = $mysqli->prepare($anSql);
$stmtAn->bind_param("i", $horario_id);
$stmtAn->execute();
$anuncios = $stmtAn->get_result();
$stmtAn->close();

// 6) Compañeros de clase
$comSql = "
    SELECT u.nombre, u.apellido, u.email
    FROM matriculas m
    INNER JOIN usuarios u ON m.estudiante_id = u.id
    WHERE m.horario_id = ? 
      AND m.estado_id = 1   -- Activos
      AND u.id != ?
    ORDER BY u.nombre ASC
";
$stmtCom = $mysqli->prepare($comSql);
$stmtCom->bind_param("ii", $horario_id, $usuario_id);
$stmtCom->execute();
$companeros = $stmtCom->get_result();
$stmtCom->close();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">
    Detalle del curso: <?= htmlspecialchars($curso['nombre_curso']) ?>
</h1>

<p class="text-muted mb-3">
    Nivel <?= htmlspecialchars($curso['codigo_nivel']) ?> • 
    <?= htmlspecialchars($curso['nombre_nivel']) ?> •
    <?= htmlspecialchars($curso['nombre_dia']) ?>,
    <?= substr($curso['hora_inicio'], 0, 5) ?> - <?= substr($curso['hora_fin'], 0, 5) ?> •
    Aula <?= htmlspecialchars($curso['aula']) ?>
</p>

<div class="row g-3 mb-4">
    <div class="col-lg-8">

        <!-- Descripción curso -->
        <div class="card card-soft mb-3 p-3">
            <h2 class="h6 fw-bold mb-2">Descripción del curso</h2>
            <p class="mb-0">
                <?= nl2br(htmlspecialchars($curso['descripcion'] ?: 'Sin descripción registrada.')) ?>
            </p>
        </div>

        <!-- Anuncios -->
        <div class="card card-soft mb-3 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Anuncios del curso</h2>
            </div>

            <?php if ($anuncios->num_rows === 0): ?>
                <p class="small text-muted">Aún no hay anuncios para este curso.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php while ($a = $anuncios->fetch_assoc()): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">

                                <div>
                                    <span class="badge bg-light text-muted border small mb-1">
                                        <?= htmlspecialchars($a['tipo']) ?>
                                    </span>

                                    <strong class="d-block small mb-1">
                                        <?= htmlspecialchars($a['titulo']) ?>
                                    </strong>

                                    <p class="small mb-0">
                                        <?= nl2br(htmlspecialchars($a['contenido'])) ?>
                                    </p>
                                </div>

                                <small class="text-muted ms-3">
                                    <?= date('d/m/Y H:i', strtotime($a['fecha_publicacion'])) ?>
                                </small>

                            </div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- TAREAS -->
        <div class="card card-soft mb-3 p-3">

            <h2 class="h6 fw-bold mb-2">Tareas del curso</h2>

            <?php if ($mensaje_tarea): ?>
                <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje_tarea) ?></div>
            <?php endif; ?>

            <?php if ($error_tarea): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error_tarea) ?></div>
            <?php endif; ?>

            <?php if ($tareas->num_rows === 0): ?>
                <p class="small text-muted">Aún no hay tareas asignadas.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">

                    <?php while ($t = $tareas->fetch_assoc()): ?>
                        <?php
                            $hoy       = date('Y-m-d');
                            $fechaPub  = $t['fecha_publicacion'] ? date('d/m/Y H:i', strtotime($t['fecha_publicacion'])) : null;
                            $fechaLim  = $t['fecha_entrega'] ? date('d/m/Y', strtotime($t['fecha_entrega'])) : null;
                            $vencida   = ($t['fecha_entrega'] && $t['fecha_entrega'] < $hoy);
                            $entregada = !empty($t['mi_archivo']);
                            $valorMax  = isset($t['valor_maximo']) ? (int)$t['valor_maximo'] : 100;
                        ?>

                        <li class="list-group-item py-3">

                            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">

                                <div class="flex-grow-1">

                                    <!-- Título y badges -->
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">

                                        <strong class="small"><?= htmlspecialchars($t['titulo']) ?></strong>

                                        <?php if ($fechaPub): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small">
                                                Publicada: <?= $fechaPub ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($fechaLim): ?>
                                            <span class="badge small 
                                                <?= $vencida && !$entregada 
                                                    ? 'bg-danger-subtle text-danger border border-danger-subtle' 
                                                    : 'bg-primary-subtle text-primary border border-primary-subtle' ?>">
                                                Vence: <?= $fechaLim ?>
                                            </span>
                                        <?php endif; ?>

                                        <span class="badge bg-light text-secondary border border-secondary-subtle small">
                                            Valor: <?= $valorMax ?> pts
                                        </span>
                                    </div>

                                    <!-- Descripción -->
                                    <?php if (!empty($t['descripcion'])): ?>
                                        <p class="small text-muted mb-2">
                                            <?= nl2br(htmlspecialchars($t['descripcion'])) ?>
                                        </p>
                                    <?php endif; ?>

                                    <!-- Archivo de instrucciones -->
                                    <?php if (!empty($t['archivo_instrucciones'])): ?>
                                        <p class="small mb-2">
                                            <i class="fa-solid fa-paperclip me-1"></i>
                                            Instrucciones:
                                            <a href="<?= htmlspecialchars($t['archivo_instrucciones']) ?>" target="_blank">Ver archivo</a>
                                        </p>
                                    <?php endif; ?>

                                    <!-- Mi entrega -->
                                    <?php if (!empty($t['mi_archivo'])): ?>
                                        <div class="border rounded-3 p-2 bg-light mb-2">

                                            <div class="d-flex justify-content-between">

                                                <div>
                                                    <span class="small fw-semibold">Tu entrega</span><br>
                                                    <a href="<?= htmlspecialchars($t['mi_archivo']) ?>" target="_blank" class="small">
                                                        Ver archivo enviado
                                                    </a>
                                                </div>

                                                <div class="small text-end">
                                                    <?php if (!empty($t['mi_fecha_entrega'])): ?>
                                                        <div class="text-muted">
                                                            Enviada el <?= date('d/m/Y H:i', strtotime($t['mi_fecha_entrega'])) ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($t['mi_calificacion'] !== null): ?>
                                                        <div class="mt-1">
                                                            Nota:
                                                            <span class="badge bg-success-subtle text-success">
                                                                <?= htmlspecialchars($t['mi_calificacion']) ?> / <?= $valorMax ?> pts
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="mt-1">
                                                            <span class="badge bg-secondary-subtle text-secondary">
                                                                Valor: <?= $valorMax ?> pts
                                                            </span>
                                                            <div class="text-muted">En revisión</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (!empty($t['mis_comentarios'])): ?>
                                                <div class="small mt-2">
                                                    <strong>Comentario del docente:</strong><br>
                                                    <?= nl2br(htmlspecialchars($t['mis_comentarios'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                    <?php else: ?>
                                        <p class="small text-muted mb-2">Aún no has enviado tu archivo para esta tarea.</p>
                                    <?php endif; ?>

                                </div>

                                <!-- Columna derecha: subida -->
                                <div style="min-width: 230px;">

                                    <?php if ($t['fecha_entrega'] && $t['fecha_entrega'] < $hoy && empty($t['mi_archivo'])): ?>
                                        <span class="badge bg-danger-subtle text-danger d-block text-center small mb-2">
                                            Entrega vencida
                                        </span>
                                    <?php endif; ?>

                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="accion" value="subir_tarea">
                                        <input type="hidden" name="tarea_id" value="<?= $t['id'] ?>">

                                        <input type="file" name="archivo_tarea" class="form-control form-control-sm mb-2" required>

                                        <button type="submit" class="btn btn-sm btn-primary w-100">
                                            <?= $t['mi_archivo'] ? 'Reemplazar archivo' : 'Subir archivo' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </li>
                    <?php endwhile; ?>

                </ul>
            <?php endif; ?>

        </div>

        <!-- Materiales -->
        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-2">Materiales del curso</h2>

            <?php if ($materiales->num_rows === 0): ?>
                <p class="small text-muted">Aún no hay materiales subidos.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php while ($m = $materiales->fetch_assoc()): ?>
                        <li class="list-group-item">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>
                                    <span class="badge bg-light text-muted border small mb-1">
                                        <?= htmlspecialchars($m['tipo_archivo']) ?>
                                    </span>

                                    <strong class="d-block">
                                        <a href="<?= htmlspecialchars($m['archivo_url']) ?>" target="_blank">
                                            <?= htmlspecialchars($m['titulo']) ?>
                                        </a>
                                    </strong>

                                    <?php if (!empty($m['descripcion'])): ?>
                                        <p class="small mb-1">
                                            <?= nl2br(htmlspecialchars($m['descripcion'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <small class="text-muted ms-3">
                                    <?= date('d/m/Y', strtotime($m['fecha_subida'])) ?>
                                </small>

                            </div>

                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>

        </div>

    </div>

    <!-- Columna derecha -->
    <div class="col-lg-4">

        <div class="card card-soft mb-3 p-3">
            <h2 class="h6 fw-bold mb-2">Docente del curso</h2>

            <div class="d-flex align-items-center">

                <?php if (!empty($curso['foto_perfil'])): ?>
                    <img src="<?= htmlspecialchars($curso['foto_perfil']) ?>"
                         class="rounded-circle me-2"
                         style="width:48px;height:48px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-2"
                         style="width:48px;height:48px;">
                        <span class="fw-bold">
                            <?= strtoupper(substr($curso['docente_nombre'],0,1) . substr($curso['docente_apellido'],0,1)) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="fw-semibold">
                        <?= htmlspecialchars($curso['docente_nombre'] . " " . $curso['docente_apellido']) ?>
                    </div>
                    <div class="small text-muted"><?= htmlspecialchars($curso['docente_email']) ?></div>
                    <?php if ($curso['pais'] || $curso['ciudad']): ?>
                        <div class="small text-muted">
                            <?= htmlspecialchars(trim($curso['ciudad'] . ", " . $curso['pais']), ", ") ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-2">Compañeros de clase</h2>

            <?php if ($companeros->num_rows === 0): ?>
                <p class="small text-muted">Aún no hay otros estudiantes.</p>
            <?php else: ?>
                <ul class="list-unstyled small mb-0">

                    <?php while ($c = $companeros->fetch_assoc()): ?>
                        <li class="mb-1">
                            <strong><?= htmlspecialchars($c['nombre'] . " " . $c['apellido']) ?></strong><br>
                            <span class="text-muted"><?= htmlspecialchars($c['email']) ?></span>
                        </li>
                    <?php endwhile; ?>

                </ul>
            <?php endif; ?>
        </div>

    </div>
</div>

<a href="dashboard.php" class="btn btn-link px-0">‹ Volver a mis cursos</a>

<?php include __DIR__ . "/../includes/footer.php"; ?>
