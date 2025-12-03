<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";

require_role([2]); // solo docente

$docenteId = $_SESSION['usuario_id'] ?? 0;
if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

// ==============================
// 1) Parámetros (año / mes / trimestre)
// ==============================
$anio = isset($_GET['anio']) ? (int) $_GET['anio'] : (int) date('Y');
if ($anio < 2000 || $anio > 2100) {
    $anio = (int) date('Y');
}

$mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('m');
if ($mes < 1 || $mes > 12) {
    $mes = (int) date('m');
}

$trimestre = isset($_GET['trimestre']) ? (int) $_GET['trimestre'] : (int) ceil($mes / 3);
if ($trimestre < 1 || $trimestre > 4) {
    $trimestre = 1;
}

// Calcular rango de fechas del trimestre
switch ($trimestre) {
    case 1:
        $mesInicio = 1;
        $mesFin = 3;
        break;
    case 2:
        $mesInicio = 4;
        $mesFin = 6;
        break;
    case 3:
        $mesInicio = 7;
        $mesFin = 9;
        break;
    default:
        $mesInicio = 10;
        $mesFin = 12;
        break;
}

$fechaInicioTrimestre = sprintf("%04d-%02d-01", $anio, $mesInicio);
$fechaFinTrimestre = date('Y-m-t', strtotime(sprintf("%04d-%02d-01", $anio, $mesFin))); // último día del mes fin

// ==============================
// 2) Reporte MENSUAL (por curso del docente)
// ==============================
$sqlMensual = "
SELECT
    h.id AS horario_id,
    c.nombre_curso,
    h.fecha_inicio,
    h.fecha_fin,
    COUNT(DISTINCT m.estudiante_id) AS total_estudiantes,

    COUNT(a.id) AS total_asistencias,
    IFNULL(SUM(a.presente),0) AS presentes,
    CASE 
        WHEN COUNT(a.id) = 0 THEN 0
        ELSE ROUND((SUM(a.presente) / COUNT(a.id)) * 100, 2)
    END AS porcentaje_asistencia,

    (SELECT COUNT(*) 
     FROM tareas t
     WHERE t.horario_id = h.id
       AND MONTH(t.fecha_publicacion) = ?
       AND YEAR(t.fecha_publicacion)  = ?) AS tareas_periodo,

    (SELECT COUNT(*)
     FROM tareas_entregas te
     INNER JOIN tareas tt ON tt.id = te.tarea_id
     WHERE tt.horario_id = h.id
       AND MONTH(te.fecha_entrega) = ?
       AND YEAR(te.fecha_entrega)  = ?) AS entregas_periodo,

    (SELECT ROUND(AVG(te2.calificacion),2)
     FROM tareas_entregas te2
     INNER JOIN tareas tt2 ON tt2.id = te2.tarea_id
     WHERE tt2.horario_id = h.id
       AND te2.calificacion IS NOT NULL
       AND MONTH(te2.fecha_entrega) = ?
       AND YEAR(te2.fecha_entrega)  = ?) AS promedio_periodo

FROM horarios h
INNER JOIN cursos c      ON c.id = h.curso_id
LEFT JOIN matriculas m   ON m.horario_id = h.id
LEFT JOIN asistencia a   ON a.matricula_id = m.id
      AND MONTH(a.fecha_clase) = ?
      AND YEAR(a.fecha_clase)  = ?
WHERE h.docente_id = ?
GROUP BY h.id, c.nombre_curso, h.fecha_inicio, h.fecha_fin
ORDER BY c.nombre_curso
";

$stmtM = $mysqli->prepare($sqlMensual);
$stmtM->bind_param(
    "iiiiiiiii",
    $mes,
    $anio, // tareas
    $mes,
    $anio, // entregas
    $mes,
    $anio, // promedio
    $mes,
    $anio, // asistencia
    $docenteId   // docente
);

$stmtM->execute();
$reportesMensual = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtM->close();

// ==============================
// 3) Reporte ANUAL (por curso del docente)
// ==============================
$sqlAnual = "
SELECT
    h.id AS horario_id,
    c.nombre_curso,
    h.fecha_inicio,
    h.fecha_fin,
    COUNT(DISTINCT m.estudiante_id) AS total_estudiantes,

    COUNT(a.id) AS total_asistencias,
    IFNULL(SUM(a.presente),0) AS presentes,
    CASE 
        WHEN COUNT(a.id) = 0 THEN 0
        ELSE ROUND((SUM(a.presente) / COUNT(a.id)) * 100, 2)
    END AS porcentaje_asistencia,

    (SELECT COUNT(*) 
     FROM tareas t
     WHERE t.horario_id = h.id
       AND YEAR(t.fecha_publicacion)  = ?) AS tareas_periodo,

    (SELECT COUNT(*)
     FROM tareas_entregas te
     INNER JOIN tareas tt ON tt.id = te.tarea_id
     WHERE tt.horario_id = h.id
       AND YEAR(te.fecha_entrega)  = ?) AS entregas_periodo,

    (SELECT ROUND(AVG(te2.calificacion),2)
     FROM tareas_entregas te2
     INNER JOIN tareas tt2 ON tt2.id = te2.tarea_id
     WHERE tt2.horario_id = h.id
       AND te2.calificacion IS NOT NULL
       AND YEAR(te2.fecha_entrega)  = ?) AS promedio_periodo

FROM horarios h
INNER JOIN cursos c      ON c.id = h.curso_id
LEFT JOIN matriculas m   ON m.horario_id = h.id
LEFT JOIN asistencia a   ON a.matricula_id = m.id
      AND YEAR(a.fecha_clase) = ?
WHERE h.docente_id = ?
GROUP BY h.id, c.nombre_curso, h.fecha_inicio, h.fecha_fin
ORDER BY c.nombre_curso
";

$stmtA = $mysqli->prepare($sqlAnual);
$stmtA->bind_param(
    "iiiii",
    $anio, // tareas
    $anio, // entregas
    $anio, // promedio
    $anio, // asistencia
    $docenteId
);
$stmtA->execute();
$reportesAnual = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtA->close();

// ==============================
// 4) Reporte TRIMESTRAL (por curso del docente)
// ==============================
$sqlTrimestral = "
SELECT
    h.id AS horario_id,
    c.nombre_curso,
    h.fecha_inicio,
    h.fecha_fin,
    COUNT(DISTINCT m.estudiante_id) AS total_estudiantes,

    COUNT(a.id) AS total_asistencias,
    IFNULL(SUM(a.presente),0) AS presentes,
    CASE 
        WHEN COUNT(a.id) = 0 THEN 0
        ELSE ROUND((SUM(a.presente) / COUNT(a.id)) * 100, 2)
    END AS porcentaje_asistencia,

    (SELECT COUNT(*) 
     FROM tareas t
     WHERE t.horario_id = h.id
       AND t.fecha_publicacion BETWEEN ? AND ?) AS tareas_periodo,

    (SELECT COUNT(*)
     FROM tareas_entregas te
     INNER JOIN tareas tt ON tt.id = te.tarea_id
     WHERE tt.horario_id = h.id
       AND te.fecha_entrega BETWEEN ? AND ?) AS entregas_periodo,

    (SELECT ROUND(AVG(te2.calificacion),2)
     FROM tareas_entregas te2
     INNER JOIN tareas tt2 ON tt2.id = te2.tarea_id
     WHERE tt2.horario_id = h.id
       AND te2.calificacion IS NOT NULL
       AND te2.fecha_entrega BETWEEN ? AND ?) AS promedio_periodo

FROM horarios h
INNER JOIN cursos c      ON c.id = h.curso_id
LEFT JOIN matriculas m   ON m.horario_id = h.id
LEFT JOIN asistencia a   ON a.matricula_id = m.id
      AND a.fecha_clase BETWEEN ? AND ?
WHERE h.docente_id = ?
GROUP BY h.id, c.nombre_curso, h.fecha_inicio, h.fecha_fin
ORDER BY c.nombre_curso
";

$stmtT = $mysqli->prepare($sqlTrimestral);
$stmtT->bind_param(
    "ssssssssi",
    $fechaInicioTrimestre,
    $fechaFinTrimestre, // tareas
    $fechaInicioTrimestre,
    $fechaFinTrimestre, // entregas
    $fechaInicioTrimestre,
    $fechaFinTrimestre, // promedio
    $fechaInicioTrimestre,
    $fechaFinTrimestre, // asistencia
    $docenteId
);
$stmtT->execute();
$reportesTrimestral = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtT->close();

// ==============================
// 5) Vista
// ==============================
$mesesNombre = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

include __DIR__ . "/../../includes/header.php";
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0">
                <i class="fa-solid fa-file-lines me-2"></i>
                Reportes del docente
            </h2>
            <small class="text-muted">
                Seleccione año, mes y trimestre para ver el desglose por curso.
            </small>
        </div>
    </div>

    <!-- Filtros -->
    <form method="get" class="row g-2 mb-4">
        <div class="col-md-3">
            <label class="form-label mb-1">Año</label>
            <select name="anio" class="form-select form-select-sm">
                <?php
                $anioActual = (int) date('Y');
                for ($a = $anioActual; $a >= $anioActual - 5; $a--): ?>
                    <option value="<?= $a ?>" <?= $a == $anio ? 'selected' : '' ?>>
                        <?= $a ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label mb-1">Mes (para reporte mensual)</label>
            <select name="mes" class="form-select form-select-sm">
                <?php foreach ($mesesNombre as $num => $nombre): ?>
                    <option value="<?= $num ?>" <?= $num == $mes ? 'selected' : '' ?>>
                        <?= $nombre ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label mb-1">Trimestre</label>
            <select name="trimestre" class="form-select form-select-sm">
                <option value="1" <?= $trimestre == 1 ? 'selected' : '' ?>>I (Ene - Mar)</option>
                <option value="2" <?= $trimestre == 2 ? 'selected' : '' ?>>II (Abr - Jun)</option>
                <option value="3" <?= $trimestre == 3 ? 'selected' : '' ?>>III (Jul - Sep)</option>
                <option value="4" <?= $trimestre == 4 ? 'selected' : '' ?>>IV (Oct - Dic)</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="fa-solid fa-magnifying-glass me-1"></i> Aplicar filtros
            </button>
        </div>
    </form>

    <!-- PESTAÑAS -->
    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pills-mensual-tab" data-bs-toggle="pill" data-bs-target="#pills-mensual"
                type="button" role="tab">
                Mensual (<?= $mesesNombre[$mes] ?>/<?= $anio ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-anual-tab" data-bs-toggle="pill" data-bs-target="#pills-anual"
                type="button" role="tab">
                Anual (<?= $anio ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-trimestre-tab" data-bs-toggle="pill" data-bs-target="#pills-trimestre"
                type="button" role="tab">
                Trimestral (T<?= $trimestre ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">

        <!-- TAB MENSUAL -->
        <div class="tab-pane fade show active" id="pills-mensual" role="tabpanel">
            <?php if (empty($reportesMensual)): ?>
                <div class="alert alert-info">No hay datos para este mes.</div>
            <?php else: ?>
                <?php foreach ($reportesMensual as $r): ?>
                    <div class="card shadow-sm mb-3 border-0">
                        <div class="card-header bg-dark text-white fw-bold">
                            <?= htmlspecialchars($r['nombre_curso']) ?> — Mensual
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <strong>Estudiantes:</strong> <?= $r['total_estudiantes'] ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Periodo:</strong> <?= $mesesNombre[$mes] ?> / <?= $anio ?>
                                </div>
                            </div>
                            <div class="row text-center mb-2">
                                <div class="col-md-4">
                                    <h5 class="mb-0 text-primary"><?= $r['porcentaje_asistencia'] ?>%</h5>
                                    <small class="text-muted">
                                        Asistencia (<?= $r['presentes'] ?>/<?= $r['total_asistencias'] ?>)
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="mb-0 text-success"><?= $r['tareas_periodo'] ?></h5>
                                    <small class="text-muted">Tareas del mes</small>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="mb-0 text-warning"><?= $r['entregas_periodo'] ?? 0 ?></h5>
                                    <small class="text-muted">Entregas del mes</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Promedio de calificaciones (mes):</strong>
                                    <?= $r['promedio_periodo'] !== null ? $r['promedio_periodo'] : '—' ?>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="reporte_mensual_pdf.php?horario_id=<?= $r['horario_id'] ?>&mes=<?= $mes ?>&anio=<?= $anio ?>"
                                        target="_blank" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-file-pdf me-1"></i> PDF mensual
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- TAB ANUAL -->
        <div class="tab-pane fade" id="pills-anual" role="tabpanel">
            <?php if (empty($reportesAnual)): ?>
                <div class="alert alert-info">No hay datos para este año.</div>
            <?php else: ?>
                <?php foreach ($reportesAnual as $r): ?>
                    <div class="card shadow-sm mb-3 border-0">
                        <div class="card-header bg-dark text-white fw-bold">
                            <?= htmlspecialchars($r['nombre_curso']) ?> — Anual
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <strong>Estudiantes:</strong> <?= $r['total_estudiantes'] ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Año:</strong> <?= $anio ?>
                                </div>
                            </div>
                            <div class="row text-center mb-2">
                                <div class="col-md-4">
                                    <h5 class="mb-0 text-primary"><?= $r['porcentaje_asistencia'] ?>%</h5>
                                    <small class="text-muted">
                                        Asistencia (<?= $r['presentes'] ?>/<?= $r['total_asistencias'] ?>)
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="mb-0 text-success"><?= $r['tareas_periodo'] ?></h5>
                                    <small class="text-muted">Tareas del año</small>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="mb-0 text-warning"><?= $r['entregas_periodo'] ?? 0 ?></h5>
                                    <small class="text-muted">Entregas del año</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Promedio de calificaciones (año):</strong>
                                    <?= $r['promedio_periodo'] !== null ? $r['promedio_periodo'] : '—' ?>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="reporte_anual_pdf.php?horario_id=<?= $r['horario_id'] ?>&anio=<?= $anio ?>"
                                        target="_blank" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-file-pdf me-1"></i> PDF anual
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
            <?php if (empty($reportesTrimestral)): ?>
                <div class="alert alert-info">
                    No hay datos para este trimestre.<br>
                    <small>Periodo: <?= date('d/m/Y', strtotime($fechaInicioTrimestre)) ?> al
                        <?= date('d/m/Y', strtotime($fechaFinTrimestre)) ?></small>
                </div>
            <?php else: ?>
                <?php foreach ($reportesTrimestral as $r): ?>
                    <div class="card shadow-sm mb-3 border-0">
                        <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between">
                            <span><?= htmlspecialchars($r['nombre_curso']) ?> — Trimestral T<?= $trimestre ?></span>
                            <small>Periodo: <?= date('d/m/Y', strtotime($fechaInicioTrimestre)) ?> al
                                <?= date('d/m/Y', strtotime($fechaFinTrimestre)) ?></small>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <strong>Estudiantes:</strong> <?= $r['total_estudiantes'] ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Clases registradas:</strong> <?= $r['total_asistencias'] ?>
                                </div>
                            </div>
                            <div class="row text-center mb-2">
                                <div class="col-md-4">
                                    <h5 class="mb-0 text-primary"><?= $r['porcentaje_asistencia'] ?>%</h5>
                                    <small class="text-muted">
                                        Asistencia (<?= $r['presentes'] ?>/<?= $r['total_asistencias'] ?>)
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="mb-0 text-success"><?= $r['tareas_periodo'] ?></h5>
                                    <small class="text-muted">Tareas del trimestre</small>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="mb-0 text-warning"><?= $r['entregas_periodo'] ?? 0 ?></h5>
                                    <small class="text-muted">Entregas del trimestre</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Promedio de calificaciones (trimestre):</strong>
                                    <?= $r['promedio_periodo'] !== null ? $r['promedio_periodo'] : '—' ?>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="reporte_trimestral_pdf.php
                                        ?horario_id=<?= $r['horario_id'] ?>
                                        &anio=<?= $anio ?>
                                        &trimestre=<?= $trimestre ?>
                                        &periodo_inicio=<?= urlencode($fechaInicioTrimestre) ?>
                                        &periodo_fin=<?= urlencode($fechaFinTrimestre) ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-file-pdf me-1"></i>
                                        PDF trimestral
                                    </a>
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
include __DIR__ . "/../../includes/footer.php";
