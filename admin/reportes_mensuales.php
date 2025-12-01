<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_role([1]); // admin

$mes = date('m');
$anio = date('Y');

// KPIs globales del mes
$sql = "
SELECT
   (SELECT COUNT(*) 
    FROM usuarios 
    WHERE rol_id = 3 
      AND MONTH(fecha_registro) = ? 
      AND YEAR(fecha_registro)  = ?) AS nuevos_estudiantes,

   (SELECT COUNT(*) 
    FROM matriculas 
    WHERE MONTH(fecha_matricula) = ? 
      AND YEAR(fecha_matricula)  = ?) AS matriculas_mes,

   (SELECT IFNULL(SUM(monto_pagado),0)
    FROM matriculas
    WHERE MONTH(fecha_matricula) = ? 
      AND YEAR(fecha_matricula)  = ?) AS ingresos_mes,

   (SELECT CASE 
             WHEN COUNT(*) = 0 THEN 0
             ELSE ROUND((SUM(presente) / COUNT(*)) * 100, 2)
           END
    FROM asistencia 
    WHERE MONTH(fecha_clase) = ? 
      AND YEAR(fecha_clase)  = ?) AS asistencia_global,

   (SELECT COUNT(*) 
    FROM tareas 
    WHERE MONTH(fecha_publicacion) = ? 
      AND YEAR(fecha_publicacion)  = ?) AS tareas_asignadas,

   (SELECT COUNT(*) 
    FROM tareas_entregas 
    WHERE MONTH(fecha_entrega) = ? 
      AND YEAR(fecha_entrega)  = ?) AS tareas_entregadas,

   (SELECT COUNT(*) 
    FROM mensajes 
    WHERE MONTH(fecha_envio) = ? 
      AND YEAR(fecha_envio)  = ?) AS mensajes_mes,

   (SELECT COUNT(*) 
    FROM mensajes_interes 
    WHERE MONTH(fecha_envio) = ? 
      AND YEAR(fecha_envio)  = ?) AS leads_mes
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . $mysqli->error);
}

$stmt->bind_param(
    "iiiiiiiiiiiiiiii",  // 16 i
    $mes,
    $anio,         // 1) nuevos_estudiantes
    $mes,
    $anio,         // 2) matriculas_mes
    $mes,
    $anio,         // 3) ingresos_mes
    $mes,
    $anio,         // 4) asistencia_global
    $mes,
    $anio,         // 5) tareas_asignadas
    $mes,
    $anio,         // 6) tareas_entregadas
    $mes,
    $anio,         // 7) mensajes_mes
    $mes,
    $anio          // 8) leads_mes
);

$stmt->execute();
$kpis = $stmt->get_result()->fetch_assoc();
$stmt->close();

include __DIR__ . "/../includes/header.php";
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            <i class="fa-solid fa-chart-column me-2"></i>
            Reporte Mensual General
        </h2>
        <div class="d-flex gap-2">
            <a href="reportes_cursos.php" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-chart-pie me-1"></i> Ver por curso
            </a>
            <a href="reportes_mensuales_pdf.php" target="_blank" class="btn btn-outline-danger btn-sm">
                <i class="fa-solid fa-file-pdf me-1"></i> Descargar PDF
            </a>
        </div>
    </div>

    <div class="row g-3">

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="text-primary mb-0"><?= $kpis['nuevos_estudiantes'] ?></h5>
                    <p class="text-muted mb-0">Nuevos estudiantes</p>
                    <small class="text-muted"><?= $mes ?>/<?= $anio ?></small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="text-success mb-0"><?= $kpis['matriculas_mes'] ?></h5>
                    <p class="text-muted mb-0">Matrículas del mes</p>
                    <small class="text-muted"><?= $mes ?>/<?= $anio ?></small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="text-warning mb-0">L <?= number_format($kpis['ingresos_mes'], 2) ?></h5>
                    <p class="text-muted mb-0">Ingresos del mes</p>
                    <small class="text-muted">Cursos</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="text-danger mb-0"><?= $kpis['asistencia_global'] ?>%</h5>
                    <p class="text-muted mb-0">Asistencia global</p>
                    <small class="text-muted"><?= $mes ?>/<?= $anio ?></small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 mt-3">
                <div class="card-body text-center">
                    <h5 class="mb-0"><?= $kpis['tareas_asignadas'] ?></h5>
                    <p class="text-muted mb-0">Tareas asignadas</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 mt-3">
                <div class="card-body text-center">
                    <h5 class="mb-0"><?= $kpis['tareas_entregadas'] ?></h5>
                    <p class="text-muted mb-0">Tareas entregadas</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 mt-3">
                <div class="card-body text-center">
                    <h5 class="mb-0"><?= $kpis['mensajes_mes'] ?></h5>
                    <p class="text-muted mb-0">Mensajes internos</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 mt-3">
                <div class="card-body text-center">
                    <h5 class="mb-0"><?= $kpis['leads_mes'] ?></h5>
                    <p class="text-muted mb-0">Contactos interesados</p>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
include __DIR__ . "/../includes/footer.php";
