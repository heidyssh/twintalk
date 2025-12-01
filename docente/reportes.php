<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_role([2]); // docente

$docenteId = $_SESSION['usuario_id'] ?? 0;
if ($docenteId <= 0) {
    header("Location: /twintalk/login.php");
    exit;
}

// Mes y año actuales (podés luego poner filtros por GET si querés)
$mes  = date('m');
$anio = date('Y');

// =============================
// 1) REPORTE MENSUAL POR CLASE
// =============================

$sqlMensual = "
SELECT 
    h.id AS horario_id,
    c.nombre_curso,
    ds.nombre_dia AS dia_clase,
    h.hora_inicio,
    h.hora_fin,

    COUNT(a.id) AS total_registros_asistencia,
    IFNULL(SUM(a.presente),0) AS total_presentes,
    CASE 
        WHEN COUNT(a.id) = 0 THEN 0
        ELSE ROUND((SUM(a.presente) / COUNT(a.id)) * 100, 2)
    END AS porcentaje_asistencia,

    (SELECT COUNT(*) 
     FROM tareas t 
     WHERE t.horario_id = h.id
       AND MONTH(t.fecha_publicacion) = ? 
       AND YEAR(t.fecha_publicacion) = ?
    ) AS tareas_mes,

    (SELECT COUNT(*) 
     FROM tareas_entregas te 
     INNER JOIN tareas tt ON te.tarea_id = tt.id
     WHERE tt.horario_id = h.id
       AND MONTH(te.fecha_entrega) = ?
       AND YEAR(te.fecha_entrega) = ?
    ) AS entregas_mes,

    (SELECT ROUND(AVG(ca.puntaje), 2)
     FROM calificaciones ca 
     INNER JOIN matriculas mm ON ca.matricula_id = mm.id
     WHERE mm.horario_id = h.id
       AND MONTH(ca.fecha_evaluacion) = ?
       AND YEAR(ca.fecha_evaluacion) = ?
    ) AS promedio_calificaciones

FROM horarios h
INNER JOIN cursos c      ON h.curso_id      = c.id
INNER JOIN dias_semana ds ON h.dia_semana_id = ds.id
LEFT JOIN matriculas m   ON m.horario_id    = h.id
LEFT JOIN asistencia a   ON a.matricula_id  = m.id
       AND MONTH(a.fecha_clase) = ?
       AND YEAR(a.fecha_clase)  = ?

WHERE h.docente_id = ?
GROUP BY h.id, c.nombre_curso, ds.nombre_dia, h.hora_inicio, h.hora_fin
ORDER BY c.nombre_curso;
";

$stmt = $mysqli->prepare($sqlMensual);
$stmt->bind_param(
    "iiiiiiiii",   // 9 i
    $mes, $anio,
    $mes, $anio,
    $mes, $anio,
    $mes, $anio,
    $docenteId
);

$stmt->execute();
$reportesMensuales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ==================================
// 2) REPORTE TRIMESTRAL POR HORARIO
// ==================================
// Tomamos el trimestre desde fecha_inicio hasta fecha_inicio+3 meses

$sqlTrimestre = "
SELECT 
   h.id AS horario_id,
   c.nombre_curso,
   h.fecha_inicio,
   h.fecha_fin,

   COUNT(a.id) AS total_asistencias,
   IFNULL(SUM(a.presente),0) AS presentes,
   CASE 
       WHEN COUNT(a.id) = 0 THEN 0
       ELSE ROUND((SUM(a.presente)/COUNT(a.id)) * 100, 2)
   END AS porcentaje_trimestre,

   (SELECT COUNT(*) 
    FROM tareas t 
    WHERE t.horario_id = h.id
      AND t.fecha_publicacion BETWEEN h.fecha_inicio 
                                  AND h.fecha_fin
   ) AS tareas_trimestre

FROM horarios h
INNER JOIN cursos c ON h.curso_id = c.id
LEFT JOIN matriculas m ON m.horario_id = h.id
LEFT JOIN asistencia a ON a.matricula_id = m.id
     AND a.fecha_clase BETWEEN h.fecha_inicio AND h.fecha_fin

WHERE h.docente_id = ?
GROUP BY h.id, c.nombre_curso, h.fecha_inicio, h.fecha_fin
ORDER BY h.fecha_inicio;
";

$stmt2 = $mysqli->prepare($sqlTrimestre);
$stmt2->bind_param("i", $docenteId);
$stmt2->execute();
$reportesTrimestre = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

include __DIR__ . "/../includes/header.php";
?>

<div class="container mt-4">
    <h2 class="mb-4 text-center">
        <i class="fa-solid fa-chart-line me-2"></i>
        Reportes de Clase
    </h2>

    <!-- Pestañas -->
    <ul class="nav nav-pills mb-3 justify-content-center" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pills-mensual-tab" data-bs-toggle="pill" data-bs-target="#pills-mensual" type="button" role="tab">
                Mensual (<?= $mes ?>/<?= $anio ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-trimestre-tab" data-bs-toggle="pill" data-bs-target="#pills-trimestre" type="button" role="tab">
                Trimestral
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">

        <!-- TAB MENSUAL -->
        <div class="tab-pane fade show active" id="pills-mensual" role="tabpanel">
            <?php if (empty($reportesMensuales)): ?>
                <div class="alert alert-info">No hay datos para este mes.</div>
            <?php else: ?>
                <?php foreach($reportesMensuales as $r): ?>
                    <div class="card shadow-sm mb-4 border-0 rounded-3">
                        <div class="card-header bg-primary text-white fw-bold">
                            <?= htmlspecialchars($r['nombre_curso']) ?> — 
                            <?= htmlspecialchars($r['dia_clase']) ?> 
                            (<?= substr($r['hora_inicio'],0,5) ?> - <?= substr($r['hora_fin'],0,5) ?>)
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h5 class="text-primary mb-0">
                                        <?= $r['porcentaje_asistencia'] ?>%
                                    </h5>
                                    <small class="text-muted">
                                        Asistencia del mes<br>
                                        (<?= $r['total_presentes'] ?>/<?= $r['total_registros_asistencia'] ?>)
                                    </small>
                                </div>
                                <div class="col-md-3">
                                    <h5 class="text-success mb-0">
                                        <?= $r['tareas_mes'] ?>
                                    </h5>
                                    <small class="text-muted">Tareas asignadas</small>
                                </div>
                                <div class="col-md-3">
                                    <h5 class="text-warning mb-0">
                                        <?= $r['entregas_mes'] ?>
                                    </h5>
                                    <small class="text-muted">Entregas recibidas</small>
                                </div>
                                <div class="col-md-3">
                                    <h5 class="text-danger mb-0">
                                        <?= $r['promedio_calificaciones'] !== null ? $r['promedio_calificaciones'] : '—' ?>
                                    </h5>
                                    <small class="text-muted">Promedio del mes</small>
                                </div>
                                <div class="mt-3 text-end">
                                    <a href="reporte_horario_pdf.php?horario_id=<?= $r['horario_id'] ?>" 
                                    class="btn btn-sm btn-outline-danger" target="_blank">
                                        <i class="fa-solid fa-file-pdf me-1"></i> PDF de esta clase
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- TAB TRIMESTRAL -->
        <div class="tab-pane fade" id="pills-trimestre" role="tabpanel">
            <?php if (empty($reportesTrimestre)): ?>
                <div class="alert alert-info">No hay datos trimestrales aún.</div>
            <?php else: ?>
                <?php foreach($reportesTrimestre as $r): ?>
                    <div class="card shadow-sm mb-4 border-0 rounded-3">
                        <div class="card-header bg-dark text-white fw-bold">
                            📆 <?= htmlspecialchars($r['nombre_curso']) ?>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Inicio del curso:</strong> <?= $r['fecha_inicio'] ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Fin del curso (trimestre):</strong> <?= $r['fecha_fin'] ?>
                                </div>
                            </div>
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <h5 class="text-primary mb-0">
                                        <?= $r['porcentaje_trimestre'] ?>%
                                    </h5>
                                    <small class="text-muted">
                                        Asistencia trimestral<br>
                                        (<?= $r['presentes'] ?>/<?= $r['total_asistencias'] ?>)
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="text-success mb-0">
                                        <?= $r['tareas_trimestre'] ?>
                                    </h5>
                                    <small class="text-muted">Tareas en el trimestre</small>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">
                                        ID Horario: <?= $r['horario_id'] ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
include __DIR__ . "/../includes/footer.php";
