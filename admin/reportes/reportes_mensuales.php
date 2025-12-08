<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";

require_role([1]); 




$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');


if ($mes < 1 || $mes > 12) {
    $mes = (int)date('m');
}

if ($anio < 2000 || $anio > 2100) {
    $anio = (int)date('Y');
}


$mesesNombre = [
    1  => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
    4  => 'Abril', 5 => 'Mayo',    6 => 'Junio',
    7  => 'Julio', 8 => 'Agosto',  9 => 'Septiembre',
    10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];




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
    "iiiiiiiiiiiiiiii",  
    $mes,
    $anio,         
    $mes,
    $anio,         
    $mes,
    $anio,         
    $mes,
    $anio,         
    $mes,
    $anio,         
    $mes,
    $anio,         
    $mes,
    $anio,         
    $mes,
    $anio          
);

$stmt->execute();
$kpis = $stmt->get_result()->fetch_assoc();
$stmt->close();

include __DIR__ . "/../../includes/header.php";
?>

<div class="container mt-4">

    <!-- HEADER / FILTRO EN CARD CON ESTÉTICA TWINtalk -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"
             style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">

            <div>
                <h2 class="mb-1 d-flex align-items-center" style="color:#b14f72;">
                    <i class="fa-solid fa-chart-column me-2"></i>
                    Reporte mensual general
                </h2>
                <small class="text-muted">
                    Periodo: <strong><?= $mesesNombre[$mes] ?> <?= $anio ?></strong>
                </small>
            </div>

            <div class="d-flex flex-column align-items-md-end gap-2 w-100 w-md-auto">

                <!-- Filtros de mes / año -->
                <form method="get"
                      class="d-flex flex-wrap justify-content-end gap-2 p-2 rounded-3"
                      style="background-color: #ffffffcc;">

                    <div class="d-flex flex-wrap gap-2">
                        <select name="mes" class="form-select form-select-sm" style="min-width: 130px;">
                            <?php foreach ($mesesNombre as $num => $nombre): ?>
                                <option value="<?= $num ?>" <?= $num == $mes ? 'selected' : '' ?>>
                                    <?= $nombre ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php $anioActual = (int)date('Y'); ?>
                        <select name="anio" class="form-select form-select-sm" style="min-width: 90px;">
                            <?php for ($a = $anioActual; $a >= $anioActual - 5; $a--): ?>
                                <option value="<?= $a ?>" <?= $a == $anio ? 'selected' : '' ?>>
                                    <?= $a ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary"
                            style="background-color:#b14f72;border-color:#b14f72;">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>
                        Aplicar filtro
                    </button>
                </form>

                <!-- Botones extra alineados al lado / abajo -->
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <a href="reportes_anuales.php?anio=<?= $anio ?>" class="btn btn-outline-dark btn-sm">
                        <i class="fa-solid fa-calendar-days me-1"></i>
                        Reporte anual <?= $anio ?>
                    </a>

                    <a href="reportes_cursos.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-chart-pie me-1"></i> Por curso
                    </a>

                    <a href="reportes_mensuales_pdf.php?mes=<?= $mes ?>&anio=<?= $anio ?>"
                       target="_blank"
                       class="btn btn-outline-danger btn-sm">
                        <i class="fa-solid fa-file-pdf me-1"></i> PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- TARJETAS KPI -->
    <div class="row g-3">

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#b14f72;"><?= $kpis['nuevos_estudiantes'] ?></h5>
                    <p class="text-muted mb-0">Nuevos estudiantes</p>
                    <small class="text-muted"><?= $mes ?>/<?= $anio ?></small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#4caf50;"><?= $kpis['matriculas_mes'] ?></h5>
                    <p class="text-muted mb-0">Matrículas del mes</p>
                    <small class="text-muted"><?= $mes ?>/<?= $anio ?></small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#ff9800;">L <?= number_format($kpis['ingresos_mes'], 2) ?></h5>
                    <p class="text-muted mb-0">Ingresos del mes</p>
                    <small class="text-muted">Cursos</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#e53935;"><?= $kpis['asistencia_global'] ?>%</h5>
                    <p class="text-muted mb-0">Asistencia global</p>
                    <small class="text-muted"><?= $mes ?>/<?= $anio ?></small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 mt-3">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#b14f72;"><?= $kpis['tareas_asignadas'] ?></h5>
                    <p class="text-muted mb-0">Tareas asignadas</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 mt-3">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#4caf50;"><?= $kpis['tareas_entregadas'] ?></h5>
                    <p class="text-muted mb-0">Tareas entregadas</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 mt-3">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#b14f72;"><?= $kpis['mensajes_mes'] ?></h5>
                    <p class="text-muted mb-0">Mensajes internos</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100 mt-3">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#b14f72;"><?= $kpis['leads_mes'] ?></h5>
                    <p class="text-muted mb-0">Contactos interesados</p>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
include __DIR__ . "/../../includes/footer.php";
