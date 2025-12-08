<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); 

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if (!$usuario_id) {
    header("Location: /twintalk/login.php");
    exit;
}








$stmt = $mysqli->prepare("
    SELECT id 
    FROM informacion_personal 
    WHERE usuario_id = ? 
    LIMIT 1
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$tiene_info = (bool) $stmt->get_result()->fetch_assoc();
$stmt->close();


$stmt = $mysqli->prepare("
    SELECT COUNT(*) AS total
    FROM matriculas
    WHERE estudiante_id = ?
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$rowMat = $stmt->get_result()->fetch_assoc();
$stmt->close();

$tiene_matriculas = $rowMat && $rowMat['total'] > 0;


if (!$tiene_info && !$tiene_matriculas) {
    header("Location: /twintalk/student/perfil.php?completar=1");
    exit;
}

$mis_cursos = $mysqli->prepare("
    SELECT 
        m.id AS matricula_id, 
        h.id AS horario_id,
        c.nombre_curso,
        n.codigo_nivel,
        h.hora_inicio, 
        h.hora_fin,
        h.fecha_inicio,
        h.fecha_fin,
        d.nombre_dia,
        u.nombre AS docente_nombre,
        u.apellido AS docente_apellido
    FROM matriculas m
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    JOIN horarios h ON m.horario_id = h.id
    JOIN cursos c ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    JOIN docentes dc ON h.docente_id = dc.id
    JOIN usuarios u ON dc.id = u.id
    WHERE m.estudiante_id = ?
      AND em.nombre_estado IN ('Activa', 'Pendiente')
    ORDER BY h.fecha_inicio DESC
");

$mis_cursos->bind_param("i", $usuario_id);
$mis_cursos->execute();
$res_mis_cursos = $mis_cursos->get_result();
$disponibles = $mysqli->prepare("
    SELECT 
        h.id AS horario_id, 
        c.nombre_curso, 
        n.codigo_nivel,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        h.cupos_disponibles
    FROM horarios h
    JOIN cursos c ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.activo = 1
      AND h.cupos_disponibles > 0
      AND NOT EXISTS (
        SELECT 1 
        FROM matriculas m
        INNER JOIN estados_matricula em ON m.estado_id = em.id
        WHERE m.horario_id = h.id 
          AND m.estudiante_id = ?
          AND em.nombre_estado IN ('Activa','Pendiente')
      )
    ORDER BY h.fecha_inicio ASC
    LIMIT 5
");
$disponibles->bind_param("i", $usuario_id);
$disponibles->execute();
$res_disponibles = $disponibles->get_result();


$sqlAnuncios = "
    SELECT 
        a.titulo,
        a.fecha_publicacion,
        a.importante,
        c.nombre_curso
    FROM anuncios a
    INNER JOIN tipos_anuncio ta ON a.tipo_anuncio_id = ta.id
    LEFT JOIN horarios h ON a.horario_id = h.id
    LEFT JOIN cursos c ON h.curso_id = c.id
    WHERE
        ( a.horario_id IS NULL
            OR a.horario_id IN (
                SELECT m.horario_id FROM matriculas m
                INNER JOIN estados_matricula em ON m.estado_id = em.id
                WHERE m.estudiante_id = ? AND em.nombre_estado='Activa'
            )
        )
        AND (a.fecha_expiracion IS NULL OR a.fecha_expiracion >= CURDATE())
    ORDER BY a.importante DESC, a.fecha_publicacion DESC
    LIMIT 3
";
$anuncios_stmt = $mysqli->prepare($sqlAnuncios);
$anuncios_stmt->bind_param("i", $usuario_id);
$anuncios_stmt->execute();
$res_anuncios = $anuncios_stmt->get_result();

$sqlUltimasTareas = "
    SELECT 
        te.id,
        te.calificacion,
        te.fecha_calificacion,
        t.titulo AS titulo_tarea,
        c.nombre_curso
    FROM tareas_entregas te
    INNER JOIN matriculas m ON te.matricula_id = m.id
    INNER JOIN tareas t ON te.tarea_id = t.id
    INNER JOIN horarios h ON m.horario_id = h.id
    INNER JOIN cursos c ON h.curso_id = c.id
    WHERE m.estudiante_id = ? AND te.calificacion IS NOT NULL
    ORDER BY te.fecha_calificacion DESC
    LIMIT 5
";
$stmtUlt = $mysqli->prepare($sqlUltimasTareas);
$stmtUlt->bind_param("i", $usuario_id);
$stmtUlt->execute();
$res_ultimas_tareas = $stmtUlt->get_result();
$stmtUlt->close();


$sqlResumenTareas = "
    SELECT 
        COUNT(*) AS total_entregadas,
        SUM(CASE WHEN te.calificacion IS NOT NULL THEN 1 ELSE 0 END) AS total_calificadas,
        AVG(te.calificacion) AS promedio_calificacion
    FROM tareas_entregas te
    INNER JOIN matriculas m ON te.matricula_id = m.id
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.estudiante_id = ?  
        AND em.nombre_estado IN ('Activa','Pendiente')
";
$stmtResumen = $mysqli->prepare($sqlResumenTareas);
$stmtResumen->bind_param("i", $usuario_id);
$stmtResumen->execute();
$resResumen = $stmtResumen->get_result()->fetch_assoc();
$stmtResumen->close();

$total_tareas_entregadas = (int) ($resResumen['total_entregadas'] ?? 0);
$total_tareas_calificadas = (int) ($resResumen['total_calificadas'] ?? 0);
$promedio_tareas = $resResumen['promedio_calificacion'] !== null
    ? (float) $resResumen['promedio_calificacion']
    : null;


$sqlPendientes = "
    SELECT COUNT(*) AS total_pendientes
    FROM tareas t
    INNER JOIN horarios h           ON t.horario_id = h.id
    INNER JOIN matriculas m         ON m.horario_id = h.id
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.estudiante_id = ?
        AND em.nombre_estado IN ('Activa','Pendiente')
      AND t.activo = 1
      AND (t.fecha_entrega IS NULL OR t.fecha_entrega >= CURDATE())
      AND NOT EXISTS (
          SELECT 1 
          FROM tareas_entregas te
          WHERE te.tarea_id = t.id
            AND te.matricula_id = m.id
      )
";
$stmtPend = $mysqli->prepare($sqlPendientes);
$stmtPend->bind_param("i", $usuario_id);
$stmtPend->execute();
$rowPend = $stmtPend->get_result()->fetch_assoc();
$stmtPend->close();

$total_tareas_pendientes = (int) ($rowPend['total_pendientes'] ?? 0);


include __DIR__ . "/../includes/header.php";
?>


<div class="row mt-3">
    <div class="col-12 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h4 fw-bold mb-1">
                Hola, <?= htmlspecialchars($_SESSION['nombre']) ?> 👋
            </h1>
            <p class="text-muted mb-0">Bienvenido a tu dashboard de estudiante.</p>
        </div>

        <a href="calendario.php"
           class="btn btn-outline-primary btn-sm"
           style="border-color:#A45A6A; color:#A45A6A;"
           onmouseover="this.style.backgroundColor='#A45A6A'; this.style.color='white'; this.querySelector('i').style.color='white';"
           onmouseout="this.style.backgroundColor='transparent'; this.style.color='#A45A6A'; this.querySelector('i').style.color='#A45A6A';">

            <i class="fa-solid fa-calendar-days me-1" style="color:#A45A6A;"></i>
            Calendario de asignaciones
        </a>
    </div>
</div>


<div class="row g-3 mb-3">

    <div class="col-md-4">
        <div class="card card-soft p-3 h-100">
            <p class="small text-muted mb-1">Tareas entregadas</p>
            <div class="d-flex justify-content-between align-items-end">
                <span class="display-6 fw-bold"><?= (int) $total_tareas_entregadas ?></span>
                <span class="small text-muted">Calificadas: <?= (int) $total_tareas_calificadas ?></span>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-soft p-3 h-100">
            <p class="small text-muted mb-1">Tareas pendientes</p>
            <div class="d-flex justify-content-between align-items-end">
                <span class="display-6 fw-bold"><?= (int) $total_tareas_pendientes ?></span>
                <span class="small text-muted">En tus cursos activos</span>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-soft p-3 h-100">
            <p class="small text-muted mb-1">Promedio de calificaciones</p>
            <div class="d-flex justify-content-between align-items-end">
                <span class="display-6 fw-bold">
                    <?= $promedio_tareas !== null ? number_format($promedio_tareas, 2) : '--' ?>
                </span>
                <span class="small text-muted">Sobre tareas evaluadas</span>
            </div>
        </div>
    </div>

</div>

<div class="row g-3">

    
    <div class="col-lg-7">

        
        <div class="card card-soft p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0" style="color:#A45A6A;">Mis clases actuales</h2>
                <a href="mis_matriculas.php" class="small fw-semibold" style="color:#A45A6A; text-decoration:none;">
                    Ver historial ›
                </a>
            </div>

            <?php if ($res_mis_cursos->num_rows > 0): ?>
                <div class="table-responsive table-rounded">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Curso</th>
                                <th>Docente</th>
                                <th>Día</th>
                                <th>Hora</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $res_mis_cursos->fetch_assoc()): ?>
                                <tr>
<td>
    <strong><?= htmlspecialchars($row['nombre_curso']) ?></strong><br>
    <span class="badge-level">Nivel <?= htmlspecialchars($row['codigo_nivel']) ?></span><br>
    <small class="text-muted">
        Inicio: <?= date("d/m/Y", strtotime($row['fecha_inicio'])) ?> · 
        Fin: <?= date("d/m/Y", strtotime($row['fecha_fin'])) ?>
    </small>
</td>
                                    <td><?= htmlspecialchars($row['docente_nombre']." ".$row['docente_apellido']) ?></td>
                                    <td><?= htmlspecialchars($row['nombre_dia']) ?></td>
                                    <td><?= substr($row['hora_inicio'],0,5) ?> - <?= substr($row['hora_fin'],0,5) ?></td>
                                    <td>
                                        <a href="curso_detalle.php?horario_id=<?= (int)$row['horario_id'] ?>"
                                           class="btn btn-sm btn-outline-secondary">Ver detalles</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <p class="text-muted mb-0">Aún no estás matriculado en ningún curso.</p>
            <?php endif; ?>
        </div>


        
        <div class="card card-soft p-3 mb-3">
            <h2 class="h6 fw-bold mb-2">Cursos disponibles para matricular</h2>

            <?php if ($res_disponibles->num_rows > 0): ?>
                <?php while ($row = $res_disponibles->fetch_assoc()): ?>
                    <div class="border rounded-3 p-2 mb-2 bg-white">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?= htmlspecialchars($row['nombre_curso']) ?></strong><br>
                                <span class="badge-level">Nivel <?= htmlspecialchars($row['codigo_nivel']) ?></span>
                                <span class="small d-block text-muted">
                                    <?= htmlspecialchars($row['nombre_dia']) ?> ·
                                    <?= substr($row['hora_inicio'],0,5) ?> - <?= substr($row['hora_fin'],0,5) ?>
                                </span>
                            </div>
                            <div class="text-end">
                                <span class="small text-muted d-block mb-1">Cupos: <?= (int)$row['cupos_disponibles'] ?></span>
                                <a href="cursos_disponibles.php?matricular=<?= (int)$row['horario_id'] ?>"
                                   class="btn btn-sm btn-tt-primary">Matricular</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>

            <?php else: ?>
                <p class="text-muted mb-0">No hay cursos disponibles actualmente.</p>
            <?php endif; ?>
        </div>

    </div> 


    
    <div class="col-lg-5">

        
        <div class="card card-soft p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 fw-bold mb-0" style="color:#A45A6A;">Anuncios recientes</h2>
                <a href="anuncios.php" class="small fw-semibold" style="color:#A45A6A;text-decoration:none;">Ver todos ›</a>
            </div>

            <?php if ($res_anuncios->num_rows>0): ?>
                <ul class="list-unstyled mb-0">
                <?php while($a=$res_anuncios->fetch_assoc()): ?>
                    <li class="mb-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <?php if($a['importante']): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1">¡Importante!</span>
                                <?php endif; ?>

                                <strong class="small d-block"><?= htmlspecialchars($a['titulo']) ?></strong>

                                <span class="badge bg-light text-muted border small">
                                    <?= $a['nombre_curso'] ?? "General" ?>
                                </span>
                            </div>
                            <small class="text-muted"><?= date('d/m',strtotime($a['fecha_publicacion'])) ?></small>
                        </div>
                    </li>
                <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="small text-muted mb-0">No hay anuncios por el momento.</p>
            <?php endif; ?>
        </div>


        
        <div class="card card-soft p-3 mb-3">
            <h2 class="h6 fw-bold mb-2">Mis últimas calificaciones</h2>
            <p class="small text-muted mb-2">Notas recientes de tus tareas entregadas.</p>

            <?php if($res_ultimas_tareas->num_rows>0): ?>
                <ul class="list-unstyled mb-0">
                <?php while($t=$res_ultimas_tareas->fetch_assoc()): ?>
                    <li class="mb-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="small fw-semibold"><?= htmlspecialchars($t['titulo_tarea']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($t['nombre_curso']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($t['calificacion']) ?></div>
                                <?php if($t['fecha_calificacion']): ?>
                                    <div class="small text-muted"><?= date('d/m',strtotime($t['fecha_calificacion'])) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endwhile; ?>
                </ul>

            <?php else: ?>
                <p class="small text-muted mb-0">Aún no tienes tareas calificadas.</p>
            <?php endif; ?>
        </div>


        
        <div class="card shadow-sm border-0 p-3" style="border-left:4px solid #A45A6A;">
            <h2 class="h6 fw-bold mb-2" style="color:#A45A6A;">
                <i class="fa-solid fa-gear me-1"></i> Configuración rápida
            </h2>
            <p class="small text-muted mb-3">Gestiona tu información personal y actividades.</p>

            <div class="d-flex flex-column gap-2">

                <a href="perfil.php" class="btn btn-light border rounded-pill text-start"
                   style="color:#A45A6A;border-color:#A45A6A;">
                    <i class="fa-solid fa-user-pen me-2"></i> Ir a mi perfil
                </a>

                <a href="/twintalk/student/calendario.php" class="btn text-white rounded-pill"
                   style="background-color:#A45A6A;">
                    <i class="fa-solid fa-calendar-days me-2"></i> Ver calendario
                </a>

                <a href="/twintalk/student/calificaciones.php" class="btn btn-outline-dark rounded-pill">
                    <i class="fa-solid fa-graduation-cap me-2"></i> Ver mis calificaciones
                </a>

            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
