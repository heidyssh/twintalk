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
        $sqlValT = "SELECT id FROM tareas WHERE id = ? AND horario_id = ? AND activo = 1 LIMIT 1";
        $stmtValT = $mysqli->prepare($sqlValT);
        $stmtValT->bind_param("ii", $tarea_id, $horario_id);
        $stmtValT->execute();
        $resValT = $stmtValT->get_result();
        $stmtValT->close();

        if ($resValT->num_rows === 0) {
            $error_tarea = "La tarea no pertenece a este curso.";
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

                    // Ver si ya existe entrega para esta tarea y esta matrícula
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
                        // Actualizar entrega (reemplazar archivo y resetear nota)
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
                        // Insertar nueva entrega
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
    INNER JOIN docentes dc ON h.docente_id = dc.id
    INNER JOIN usuarios u ON dc.id = u.id
    LEFT JOIN informacion_personal ip ON ip.usuario_id = u.id
    WHERE h.id = ?
    LIMIT 1
";
$stmtInfo = $mysqli->prepare($infoSql);
$stmtInfo->bind_param("i", $horario_id);
$stmtInfo->execute();
$resInfo = $stmtInfo->get_result();
$curso = $resInfo->fetch_assoc();
$stmtInfo->close();

if (!$curso) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">No se encontró la información del curso.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

// 3) Compañeros de clase (matrículas activas)
$companerosSql = "
    SELECT 
        u.nombre,
        u.apellido,
        u.email
    FROM matriculas m
    INNER JOIN estudiantes e ON m.estudiante_id = e.id
    INNER JOIN usuarios u ON e.id = u.id
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.horario_id = ? AND em.nombre_estado = 'Activa'
    ORDER BY u.nombre, u.apellido
";
$stmtComp = $mysqli->prepare($companerosSql);
$stmtComp->bind_param("i", $horario_id);
$stmtComp->execute();
$companeros = $stmtComp->get_result();
$stmtComp->close();

// 4) Materiales del curso
$matSql = "
    SELECT 
        m.titulo,
        m.descripcion,
        m.archivo_url,
        ta.tipo_archivo,
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

// 4.1) Tareas de este curso
$tareasSql = "
    SELECT 
        t.id,
        t.titulo,
        t.descripcion,
        t.fecha_publicacion,
        t.fecha_entrega,
        t.archivo_instrucciones,
        -- Datos de mi entrega (si existe)
        (SELECT te.archivo_url 
         FROM tareas_entregas te 
         WHERE te.tarea_id = t.id AND te.matricula_id = ? 
         LIMIT 1) AS mi_archivo,
        (SELECT te.fecha_entrega 
         FROM tareas_entregas te 
         WHERE te.tarea_id = t.id AND te.matricula_id = ? 
         LIMIT 1) AS mi_fecha_entrega,
        (SELECT te.calificacion 
         FROM tareas_entregas te 
         WHERE te.tarea_id = t.id AND te.matricula_id = ? 
         LIMIT 1) AS mi_calificacion,
        (SELECT te.comentarios_docente 
         FROM tareas_entregas te 
         WHERE te.tarea_id = t.id AND te.matricula_id = ? 
         LIMIT 1) AS mis_comentarios
    FROM tareas t
    WHERE t.horario_id = ? AND t.activo = 1
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

// 5) Anuncios específicos de este curso
$anSql = "
    SELECT 
        a.titulo,
        a.contenido,
        a.fecha_publicacion,
        a.importante,
        ta.tipo_anuncio
    FROM anuncios a
    INNER JOIN tipos_anuncio ta ON a.tipo_anuncio_id = ta.id
    WHERE a.horario_id = ?
      AND (a.fecha_expiracion IS NULL OR a.fecha_expiracion >= CURDATE())
    ORDER BY a.importante DESC, a.fecha_publicacion DESC
";
$stmtAn = $mysqli->prepare($anSql);
$stmtAn->bind_param("i", $horario_id);
$stmtAn->execute();
$anuncios = $stmtAn->get_result();
$stmtAn->close();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">
    Detalle del curso: <?= htmlspecialchars($curso['nombre_curso']) ?>
</h1>
<p class="text-muted mb-3">
    Nivel <?= htmlspecialchars($curso['codigo_nivel']) ?> · 
    <?= htmlspecialchars($curso['nombre_nivel']) ?> ·
    <?= htmlspecialchars($curso['nombre_dia']) ?>,
    <?= substr($curso['hora_inicio'], 0, 5) ?> - <?= substr($curso['hora_fin'], 0, 5) ?> ·
    Aula <?= htmlspecialchars($curso['aula']) ?>
</p>

<div class="row g-3 mb-4">
    <!-- Columna izquierda: info curso, anuncios, materiales -->
    <div class="col-lg-8">
        <div class="card card-soft mb-3 p-3">
            <h2 class="h6 fw-bold mb-2">Descripción del curso</h2>
            <p class="mb-0">
                <?= nl2br(htmlspecialchars($curso['descripcion'] ?: 'Sin descripción registrada.')) ?>
            </p>
        </div>

        <div class="card card-soft mb-3 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Anuncios de este curso</h2>
            </div>

            <?php if ($anuncios->num_rows === 0): ?>
                <p class="small text-muted mb-0">Aún no hay anuncios específicos para este curso.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php while ($a = $anuncios->fetch_assoc()): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <?php if ($a['importante']): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                Importante
                                            </span>
                                        <?php endif; ?>
                                        <span class="badge bg-light text-muted border">
                                            <?= htmlspecialchars($a['tipo_anuncio']) ?>
                                        </span>
                                    </div>
                                    <strong class="d-block small mb-1">
                                        <?= htmlspecialchars($a['titulo']) ?>
                                    </strong>
                                    <p class="mb-0 small">
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

                <!-- Bloque de TAREAS del curso -->
        <div class="card card-soft mb-3 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Tareas del curso</h2>
            </div>

            <?php if ($mensaje_tarea): ?>
                <div class="alert alert-success py-2 small">
                    <?= htmlspecialchars($mensaje_tarea) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_tarea): ?>
                <div class="alert alert-danger py-2 small">
                    <?= htmlspecialchars($error_tarea) ?>
                </div>
            <?php endif; ?>

            <?php if (!isset($tareas) || $tareas->num_rows === 0): ?>
                <p class="small text-muted mb-0">Aún no hay tareas asignadas para este curso.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php while ($t = $tareas->fetch_assoc()): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <strong class="small">
                                            <?= htmlspecialchars($t['titulo']) ?>
                                        </strong>
                                        <?php if ($t['fecha_entrega']): ?>
                                            <?php
                                            $hoy = date('Y-m-d');
                                            $vencida = ($t['fecha_entrega'] < $hoy);
                                            ?>
                                            <span class="badge <?= $vencida ? 'bg-danger' : 'bg-primary-subtle text-primary' ?> small">
                                                Entrega: <?= htmlspecialchars($t['fecha_entrega']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($t['descripcion']): ?>
                                        <p class="mb-1 small">
                                            <?= nl2br(htmlspecialchars($t['descripcion'])) ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($t['archivo_instrucciones']): ?>
                                        <p class="mb-1 small">
                                            Instrucciones:
                                            <a href="<?= htmlspecialchars($t['archivo_instrucciones']) ?>" target="_blank">
                                                Ver archivo
                                            </a>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($t['mi_archivo']): ?>
                                        <p class="mb-1 small">
                                            <strong>Tu entrega:</strong>
                                            <a href="<?= htmlspecialchars($t['mi_archivo']) ?>" target="_blank">
                                                Ver archivo enviado
                                            </a><br>
                                            <?php if ($t['mi_fecha_entrega']): ?>
                                                <span class="text-muted">
                                                    Enviada el <?= date('d/m/Y H:i', strtotime($t['mi_fecha_entrega'])) ?>
                                                </span><br>
                                            <?php endif; ?>
                                            <?php if ($t['mi_calificacion'] !== null): ?>
                                                <span class="badge bg-success-subtle text-success mt-1">
                                                    Nota: <?= htmlspecialchars($t['mi_calificacion']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($t['mis_comentarios']): ?>
                                                <div class="mt-1 small">
                                                    <strong>Comentario del docente:</strong><br>
                                                    <?= nl2br(htmlspecialchars($t['mis_comentarios'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </p>
                                        <p class="mb-1 small text-muted">
                                            Puedes volver a subir el archivo si el docente lo permite; se reemplazará la entrega anterior.
                                        </p>
                                    <?php else: ?>
                                        <p class="mb-1 small text-muted">
                                            Aún no has enviado esta tarea.
                                        </p>
                                    <?php endif; ?>

                                    <!-- Formulario para subir / reemplazar archivo -->
                                    <form method="post" enctype="multipart/form-data" class="mt-2">
                                        <input type="hidden" name="accion" value="subir_tarea">
                                        <input type="hidden" name="tarea_id" value="<?= $t['id'] ?>">

                                        <div class="row g-2 align-items-center">
                                            <div class="col-sm-8">
                                                <input type="file" name="archivo_tarea"
                                                       class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-sm-4 text-end">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <?= $t['mi_archivo'] ? 'Reemplazar archivo' : 'Subir archivo' ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </div>


        <div class="card card-soft p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0">Materiales del curso</h2>
            </div>

            <?php if ($materiales->num_rows === 0): ?>
                <p class="small text-muted mb-0">Aún no hay materiales subidos para este curso.</p>
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

    <!-- Columna derecha: docente y compañeros -->
    <div class="col-lg-4">
        <div class="card card-soft mb-3 p-3">
            <h2 class="h6 fw-bold mb-2">Docente del curso</h2>
            <div class="d-flex align-items-center">
                <?php if (!empty($curso['foto_perfil'])): ?>
                    <img src="<?= htmlspecialchars($curso['foto_perfil']) ?>"
                         alt="Foto docente"
                         class="rounded-circle me-2"
                         style="width:48px;height:48px;object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center me-2"
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
                    <div class="small text-muted">
                        <?= htmlspecialchars($curso['docente_email']) ?>
                    </div>
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
                <p class="small text-muted mb-0">Aún no hay otros estudiantes activos en esta clase.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0 small">
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

<a href="dashboard.php" class="btn btn-link px-0">
    ‹ Volver a mis cursos
</a>

<?php include __DIR__ . "/../includes/footer.php"; ?>
