<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); 

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$horario_id = isset($_GET['horario_id']) ? (int) $_GET['horario_id'] : 0;

if ($horario_id <= 0) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">Horario no válido.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}


$check = $mysqli->prepare("
    SELECT 
        m.id AS matricula_id,
        em.nombre_estado
    FROM matriculas m
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.estudiante_id = ? 
      AND m.horario_id = ?
    LIMIT 1
");
$check->bind_param("ii", $usuario_id, $horario_id);
$check->execute();
$resCheck  = $check->get_result();
$matricula = $resCheck->fetch_assoc();
$check->close();

if (!$matricula) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-danger mt-4">No estás matriculado en esta clase.</div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

if ($matricula['nombre_estado'] === 'Cancelada') {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="alert alert-warning mt-4">
            Tu matrícula en esta clase fue cancelada. 
            Ya no puedes acceder al contenido del curso.
          </div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}

$matricula_id = (int)$matricula['matricula_id'];
$matricula_id = (int) $matricula['matricula_id'];
$mensaje_tarea = "";
$error_tarea = "";
$uploadDirTareas = __DIR__ . '/../uploads/tareas/';
if (!is_dir($uploadDirTareas)) {
    mkdir($uploadDirTareas, 0775, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['accion'] ?? '') === 'subir_tarea')) {
    $tarea_id = (int) ($_POST['tarea_id'] ?? 0);

    if ($tarea_id <= 0 || !isset($_FILES['archivo_tarea'])) {
        $error_tarea = "Datos de tarea inválidos.";
    } else {
        
        $sqlValT = "
            SELECT id, fecha_entrega, permitir_atraso, modalidad
            FROM tareas
            WHERE id = ? AND horario_id = ? AND activo = 1
            LIMIT 1
        ";
        $stmtValT = $mysqli->prepare($sqlValT);
        $stmtValT->bind_param("ii", $tarea_id, $horario_id);
        $stmtValT->execute();
        $resValT = $stmtValT->get_result();
        $stmtValT->close();

        if ($resValT->num_rows === 0) {
            $error_tarea = "La tarea no pertenece a este curso.";
        } else {
            $tareaRow = $resValT->fetch_assoc();
            $fecha_limite = $tareaRow['fecha_entrega']; 
            $stmtExt = $mysqli->prepare("
                SELECT MAX(nueva_fecha) AS max_fecha
                FROM tareas_extensiones
                WHERE tarea_id = ?
                  AND (matricula_id = ? OR matricula_id IS NULL)
            ");
            $stmtExt->bind_param("ii", $tarea_id, $matricula_id);
            $stmtExt->execute();
            $resExt = $stmtExt->get_result();
            $rowExt = $resExt->fetch_assoc();
            $stmtExt->close();

            if (!empty($rowExt['max_fecha'])) {
                $fecha_ext = $rowExt['max_fecha']; 
                if (empty($fecha_limite) || $fecha_ext > $fecha_limite) {
                    $fecha_limite = $fecha_ext;
                }
            }

            $hoy = date('Y-m-d');
            if (
                !empty($fecha_limite)
                && $hoy > $fecha_limite
                && (int) $tareaRow['permitir_atraso'] === 0
            ) {
                $error_tarea = "⛔ Ya pasó la fecha de entrega y el docente no habilitó entregas tardías para ti.";
            } else {
                $file = $_FILES['archivo_tarea'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error_tarea = "Error al subir el archivo.";
                } else {
                    $nombreOriginal = $file['name'];
                    $tmpName = $file['tmp_name'];
                    $ext = pathinfo($nombreOriginal, PATHINFO_EXTENSION);

                    $nuevoNombre = uniqid('entrega_') . ($ext ? '.' . $ext : '');
                    $rutaDestino = $uploadDirTareas . $nuevoNombre;

                    if (move_uploaded_file($tmpName, $rutaDestino)) {
                        $archivo_url = '/twintalk/uploads/tareas/' . $nuevoNombre;
                        $tamano_archivo = $file['size'];
                        $ok = false;
                        if (!empty($tareaRow['modalidad']) && $tareaRow['modalidad'] === 'grupo') {
                            $nombreGrupo = null;
                            $stmtGrupo = $mysqli->prepare("
                                SELECT nombre_grupo
                                FROM tareas_destinatarios
                                WHERE tarea_id = ? AND matricula_id = ?
                                LIMIT 1
                            ");
                            $stmtGrupo->bind_param("ii", $tarea_id, $matricula_id);
                            $stmtGrupo->execute();
                            $resGrupo = $stmtGrupo->get_result();
                            if ($rowGrupo = $resGrupo->fetch_assoc()) {
                                $nombreGrupo = trim((string) $rowGrupo['nombre_grupo']);
                            }
                            $stmtGrupo->close();
                            $matriculasGrupo = [];
                            if (!empty($nombreGrupo)) {
                                $stmtMG = $mysqli->prepare("
                                    SELECT matricula_id
                                    FROM tareas_destinatarios
                                    WHERE tarea_id = ? AND nombre_grupo = ?
                                ");
                                $stmtMG->bind_param("is", $tarea_id, $nombreGrupo);
                                $stmtMG->execute();
                                $resMG = $stmtMG->get_result();
                                while ($rowMG = $resMG->fetch_assoc()) {
                                    $matriculasGrupo[] = (int) $rowMG['matricula_id'];
                                }
                                $stmtMG->close();
                            }
                            if (empty($matriculasGrupo)) {
                                $matriculasGrupo[] = $matricula_id;
                            }

                            foreach ($matriculasGrupo as $matIdGrupo) {
                                $sqlCheckEnt = "
                                    SELECT id
                                    FROM tareas_entregas
                                    WHERE tarea_id = ? AND matricula_id = ?
                                    LIMIT 1
                                ";
                                $stmtCE = $mysqli->prepare($sqlCheckEnt);
                                $stmtCE->bind_param("ii", $tarea_id, $matIdGrupo);
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
                                    if ($stmtUpd->execute()) {
                                        $ok = true;
                                    }
                                    $stmtUpd->close();
                                } else {
                                    $sqlIns = "
                                        INSERT INTO tareas_entregas
                                            (tarea_id, matricula_id, archivo_url, tamano_archivo)
                                        VALUES (?, ?, ?, ?)
                                    ";
                                    $stmtIns = $mysqli->prepare($sqlIns);
                                    $stmtIns->bind_param("iisi", $tarea_id, $matIdGrupo, $archivo_url, $tamano_archivo);
                                    if ($stmtIns->execute()) {
                                        $ok = true;
                                    }
                                    $stmtIns->close();
                                }
                            }

                        } else {
                            $sqlCheckEnt = "
                                SELECT id
                                FROM tareas_entregas
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
    include __DIR__ . "/../includes/footer.php>";
    exit;
}

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

$tareasSql = "
    SELECT 
        t.id,
        t.titulo,
        t.descripcion,
        t.fecha_publicacion,
        t.fecha_entrega,
        t.archivo_instrucciones,
        t.valor_maximo,
        t.modalidad,
        td.nombre_grupo AS mi_nombre_grupo,
        
        (SELECT GROUP_CONCAT(CONCAT(u.nombre,' ',u.apellido) SEPARATOR ', ')
         FROM tareas_destinatarios td2
         INNER JOIN matriculas m2 ON m2.id = td2.matricula_id
         INNER JOIN usuarios u ON u.id = m2.estudiante_id
         WHERE td2.tarea_id = t.id
         AND td2.nombre_grupo = td.nombre_grupo
        ) AS companeros_grupo,

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
    LEFT JOIN tareas_destinatarios td
        ON td.tarea_id = t.id
       AND td.matricula_id = ?   -- para saber mi grupo
    WHERE t.horario_id = ? 
      AND t.activo = 1
    ORDER BY t.fecha_publicacion DESC
";

$stmtTar = $mysqli->prepare($tareasSql);
$stmtTar->bind_param(
    "iiiiii",
    $matricula_id, 
    $matricula_id, 
    $matricula_id, 
    $matricula_id, 
    $matricula_id, 
    $horario_id    
);
$stmtTar->execute();
$tareas = $stmtTar->get_result();
$stmtTar->close();
$extensiones_por_tarea = [];

$extSql = "
    SELECT tarea_id, MAX(nueva_fecha) AS max_fecha
    FROM tareas_extensiones
    WHERE matricula_id = ? OR matricula_id IS NULL
    GROUP BY tarea_id
";
if ($stmtExtAll = $mysqli->prepare($extSql)) {
    $stmtExtAll->bind_param("i", $matricula_id);
    $stmtExtAll->execute();
    $resExtAll = $stmtExtAll->get_result();
    while ($rowExt = $resExtAll->fetch_assoc()) {
        $extensiones_por_tarea[(int) $rowExt['tarea_id']] = $rowExt['max_fecha'];
    }
    $stmtExtAll->close();
}
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


$comSql = "
    SELECT u.nombre, u.apellido, u.email
    FROM matriculas m
    INNER JOIN usuarios u ON m.estudiante_id = u.id
    WHERE m.horario_id = ? 
      AND m.estado_id IN (1,2,4)   -- Activa, Pendiente, Finalizada (excluimos Cancelada = 3)
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

<div class="container my-4">
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body" style="background: linear-gradient(90deg, #fbe9f0, #ffffff); border-radius: 0.75rem;">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                        Detalle del curso: <?= htmlspecialchars($curso['nombre_curso']) ?>
                    </h1>
                    <p class="text-muted mb-0 small">
                        Nivel <?= htmlspecialchars($curso['codigo_nivel']) ?> •
                        <?= htmlspecialchars($curso['nombre_nivel']) ?> •
                        <?= htmlspecialchars($curso['nombre_dia']) ?>,
                        <?= substr($curso['hora_inicio'], 0, 5) ?> - <?= substr($curso['hora_fin'], 0, 5) ?> •
                        Aula <?= htmlspecialchars($curso['aula']) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card card-soft mb-3 border-0 shadow-sm">
                <div class="card-body p-3">
                    <h2 class="h6 fw-bold mb-2" style="color:#4b2e83;">Descripción del curso</h2>
                    <p class="mb-0 small">
                        <?= nl2br(htmlspecialchars($curso['descripcion'] ?: 'Sin descripción registrada.')) ?>
                    </p>
                </div>
            </div>

            <div class="card card-soft mb-3 border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 fw-bold mb-0" style="color:#4b2e83;">Anuncios del curso</h2>
                    </div>

                    <?php if ($anuncios->num_rows === 0): ?>
                        <p class="small text-muted mb-0">Aún no hay anuncios para este curso.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php while ($a = $anuncios->fetch_assoc()): ?>
                                <li class="list-group-item border-0 border-bottom small">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <span class="badge bg-light text-muted border small mb-1">
                                                <?= htmlspecialchars($a['tipo']) ?>
                                            </span>

                                            <strong class="d-block mb-1">
                                                <?= htmlspecialchars($a['titulo']) ?>
                                            </strong>

                                            <p class="small mb-0 text-muted">
                                                <?= nl2br(htmlspecialchars($a['contenido'])); ?>
                                            </p>
                                        </div>

                                        <small class="text-muted text-end">
                                            <?= date('d/m/Y H:i', strtotime($a['fecha_publicacion'])) ?>
                                        </small>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="card card-soft mb-3 border-0 shadow-sm">
                <div class="card-body p-3">
                    <h2 class="h6 fw-bold mb-2" style="color:#4b2e83;">Tareas del curso</h2>

                    <?php if ($mensaje_tarea): ?>
                        <div class="alert alert-success py-2 small mb-2"><?= htmlspecialchars($mensaje_tarea) ?></div>
                    <?php endif; ?>

                    <?php if ($error_tarea): ?>
                        <div class="alert alert-danger py-2 small mb-2"><?= htmlspecialchars($error_tarea) ?></div>
                    <?php endif; ?>

                    <?php if ($tareas->num_rows === 0): ?>
                        <p class="small text-muted mb-0">Aún no hay tareas asignadas.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php while ($t = $tareas->fetch_assoc()): ?>
                                <?php
                                $hoy = date('Y-m-d');
                                $fechaPub = $t['fecha_publicacion'] ? date('d/m/Y H:i', strtotime($t['fecha_publicacion'])) : null;

                                
                                $fechaBaseBD = $t['fecha_entrega']; 
                                $fechaLimiteRealBD = $fechaBaseBD;

                                
                                $extFecha = $extensiones_por_tarea[(int) $t['id']] ?? null;

                                if (!empty($extFecha)) {
                                    if (empty($fechaLimiteRealBD) || $extFecha > $fechaLimiteRealBD) {
                                        $fechaLimiteRealBD = $extFecha;
                                    }
                                }

                                $fechaLim = $fechaLimiteRealBD ? date('d/m/Y', strtotime($fechaLimiteRealBD)) : null;

                                $entregada = !empty($t['mi_archivo']);
                                $valorMax = isset($t['valor_maximo']) ? (int) $t['valor_maximo'] : 100;

                                $vencida = (!empty($fechaLimiteRealBD) && $fechaLimiteRealBD < $hoy && !$entregada);
                                $esGrupo = (!empty($t['modalidad']) && $t['modalidad'] === 'grupo');
                                $nombreGrupoLocal = $t['mi_nombre_grupo'] ?? '';
                                $bloquearSubidaGrp = ($esGrupo && $entregada); 
                        
                                $extension_aplicada = (!empty($extFecha) && $fechaLimiteRealBD === $extFecha);
                                ?>
                                <li class="list-group-item border-0 border-bottom py-3">
                                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                                        <div class="flex-grow-1">
                                            
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                <strong class="small"><?= htmlspecialchars($t['titulo']) ?></strong>

                                                <?php if($t['modalidad'] === 'grupo' && !empty($t['mi_nombre_grupo'])): ?>

    <div class="badge bg-primary-subtle text-primary border border-primary-subtle small">
        Grupo: <?= htmlspecialchars($t['mi_nombre_grupo']) ?>
    </div>

    <?php if(!empty($t['companeros_grupo'])): ?>
        <p class="small text-muted mb-1">
            Compañeros: <?= htmlspecialchars($t['companeros_grupo']) ?>
        </p>
    <?php endif; ?>

<?php endif; ?>


                                                <?php if ($fechaPub): ?>
                                                    <span class="badge bg-light text-secondary border small">
                                                        Publicada: <?= $fechaPub ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if ($fechaLim): ?>
                                                    <span
                                                        class="badge small
                                                        <?= $vencida && !$entregada
                                                            ? 'bg-danger-subtle text-danger border border-danger-subtle'
                                                            : 'bg-primary-subtle text-primary border border-primary-subtle' ?>">
                                                        Vence: <?= $fechaLim ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if ($extension_aplicada): ?>
                                                    <span class="small text-info">
                                                        <i class="fa-solid fa-clock-rotate-left me-1"></i>
                                                        Fecha extendida hasta <?= $fechaLim ?>
                                                    </span>
                                                <?php endif; ?>

                                                <span class="badge bg-light text-secondary border small">
                                                    Valor: <?= $valorMax ?> pts
                                                </span>
                                            </div>

                                            
                                            <?php if (!empty($t['descripcion'])): ?>
                                                <p class="small text-muted mb-2">
                                                    <?= nl2br(htmlspecialchars($t['descripcion'])) ?>
                                                </p>
                                            <?php endif; ?>

                                            
                                            <?php if (!empty($t['archivo_instrucciones'])): ?>
                                                <p class="small mb-2">
                                                    <i class="fa-solid fa-paperclip me-1"></i>
                                                    Instrucciones:
                                                    <a href="<?= htmlspecialchars($t['archivo_instrucciones']) ?>" target="_blank"
                                                        style="color:#ff4b7b; font-weight:500; text-decoration:none;"
                                                        onmouseover="this.style.color='#e84372'"
                                                        onmouseout="this.style.color='#ff4b7b'">
                                                        Ver archivo
                                                    </a>
                                                </p>
                                            <?php endif; ?>

                                            
<?php if (!empty($t['mi_archivo'])): ?>
    <div class="border rounded-3 p-2 bg-light mb-2">
        <div class="d-flex justify-content-between gap-2">
            <div>
                <span class="small fw-semibold">
                    <?php if ($esGrupo): ?>
                        Entrega de tu grupo
                        <?php if (!empty($nombreGrupoLocal)): ?>
                            (<?= htmlspecialchars($nombreGrupoLocal) ?>)
                        <?php endif; ?>
                    <?php else: ?>
                        Tu entrega
                    <?php endif; ?>
                </span><br>

                
                <p class="small mb-1">
                    <i class="fa-solid fa-file-arrow-down me-1"></i>
                    <a href="<?= htmlspecialchars($t['mi_archivo']) ?>" target="_blank"
                       style="color:#ff4b7b; font-weight:500; text-decoration:none;"
                       onmouseover="this.style.color='#e84372'"
                       onmouseout="this.style.color='#ff4b7b'">
                        Ver archivo enviado
                    </a>
                </p>

                <div class="small">
                    <?php if (!empty($t['mi_fecha_entrega'])): ?>
                        <div class="text-muted">
                            Enviada el
                            <?= date('d/m/Y H:i', strtotime($t['mi_fecha_entrega'])) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($t['mi_calificacion'] !== null): ?>
                        <div class="mt-1">
                            Nota:
                            <span class="badge bg-success-subtle text-success border">
                                <?= htmlspecialchars($t['mi_calificacion']) ?>
                                / <?= $valorMax ?> pts
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="mt-1">
                            <span class="badge bg-secondary-subtle text-secondary border">
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
    </div>
<?php else: ?>
    <p class="small text-muted mb-2">
        Aún no has enviado tu archivo para esta tarea.
    </p>
<?php endif; ?>


                                            
<div style="min-width: 230px;">
    <?php if ($bloquearSubidaGrp): ?>
        <span class="badge bg-light text-success d-block text-center small mb-2 border">
            Tu grupo ya envió esta tarea
        </span>
    <?php elseif ($vencida): ?>
        <span class="badge bg-danger-subtle text-danger d-block text-center small mb-2 border">
            Entrega vencida
        </span>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="subir_tarea">
        <input type="hidden" name="tarea_id" value="<?= (int)$t['id'] ?>">

        <input
            type="file"
            name="archivo_tarea"
            class="form-control form-control-sm mb-2"
            <?= ($vencida || $bloquearSubidaGrp) ? 'disabled' : '' ?>
            required
        >

        <button
            type="submit"
            class="btn btn-sm w-100"
            style="background-color:#ff4b7b; border:1px solid #ff4b7b; color:white; font-weight:500; border-radius:6px; padding:4px 10px;"
            onmouseover="this.style.backgroundColor='#e84372'"
            onmouseout="this.style.backgroundColor='#ff4b7b'"
            <?= ($vencida || $bloquearSubidaGrp) ? 'disabled' : '' ?>
        >
            <?php if ($esGrupo): ?>
                <?php if ($entregada): ?>
                    Entrega de grupo registrada
                <?php else: ?>
                    Subir archivo de tu grupo
                <?php endif; ?>
            <?php else: ?>
                <?= $entregada ? 'Reemplazar archivo' : 'Subir archivo' ?>
            <?php endif; ?>
        </button>
    </form>
</div>

                                        </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="card card-soft border-0 shadow-sm">
                <div class="card-body p-3">
                    <h2 class="h6 fw-bold mb-2" style="color:#4b2e83;">Materiales del curso</h2>

                    <?php if ($materiales->num_rows === 0): ?>
                        <p class="small text-muted mb-0">Aún no hay materiales subidos.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php while ($m = $materiales->fetch_assoc()): ?>
                                <li class="list-group-item border-0 border-bottom small">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <span class="badge bg-light text-muted border small mb-1">
                                                <?= htmlspecialchars($m['tipo_archivo']) ?>
                                            </span>

                                            <strong class="d-block">
                                                <a href="<?= htmlspecialchars($m['archivo_url']) ?>" target="_blank"
                                                    style="color:#ff4b7b; text-decoration:none;"
                                                    onmouseover="this.style.color='#e84372'"
                                                    onmouseout="this.style.color='#ff4b7b'">
                                                    <?= htmlspecialchars($m['titulo']) ?>
                                                </a>
                                            </strong>

                                            <?php if (!empty($m['descripcion'])): ?>
                                                <p class="small mb-1 text-muted">
                                                    <?= nl2br(htmlspecialchars($m['descripcion'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>

                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($m['fecha_subida'])) ?>
                                        </small>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        
        <div class="col-lg-4">
            
            <div class="card card-soft mb-3 border-0 shadow-sm">
                <div class="card-body p-3">
                    <h2 class="h6 fw-bold mb-2" style="color:#4b2e83;">Docente del curso</h2>

                    <div class="d-flex align-items-center">
                        <?php if (!empty($curso['foto_perfil'])): ?>
                            <img src="<?= htmlspecialchars($curso['foto_perfil']) ?>" class="rounded-circle me-2"
                                style="width:48px;height:48px;object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex justify-content-center align-items-center me-2"
                                style="width:48px;height:48px;">
                                <span class="fw-bold">
                                    <?= strtoupper(substr($curso['docente_nombre'], 0, 1) . substr($curso['docente_apellido'], 0, 1)) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div>
                            <div class="fw-semibold small">
                                <?= htmlspecialchars($curso['docente_nombre'] . " " . $curso['docente_apellido']) ?>
                            </div>
                            <div class="small text-muted"><?= htmlspecialchars($curso['docente_email']) ?></div>
                            <?php if ($curso['pais'] || $curso['ciudad']): ?>
                                <div class="small text-muted">
                                    <?= htmlspecialchars(trim($curso['ciudad'] . ", " . $curso['pais'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="card card-soft border-0 shadow-sm">
                <div class="card-body p-3">
                    <h2 class="h6 fw-bold mb-2" style="color:#4b2e83;">Compañeros de clase</h2>

                    <?php if ($companeros->num_rows === 0): ?>
                        <p class="small text-muted mb-0">Aún no hay otros estudiantes.</p>
                    <?php else: ?>
                        <ul class="list-unstyled small mb-0">
                            <?php while ($c = $companeros->fetch_assoc()): ?>
                                <li class="mb-2">
                                    <strong><?= htmlspecialchars($c['nombre'] . " " . $c['apellido']) ?></strong><br>
                                    <span class="text-muted"><?= htmlspecialchars($c['email']) ?></span>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <a href="dashboard.php" class="btn btn-link px-0" style="color:#ff4b7b; font-weight:500; text-decoration:none;"
        onmouseover="this.style.color='#e84372'" onmouseout="this.style.color='#ff4b7b'">
        ‹ Volver a mis cursos
    </a>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>