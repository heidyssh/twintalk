<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";

require_role([1]); // solo admin

// Año que se quiere ver
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
if ($anio < 2000 || $anio > 2100) {
    $anio = (int)date('Y');
}

// Nombres de meses
$mesesNombres = [
    1  => 'Enero',
    2  => 'Febrero',
    3  => 'Marzo',
    4  => 'Abril',
    5  => 'Mayo',
    6  => 'Junio',
    7  => 'Julio',
    8  => 'Agosto',
    9  => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

// Mismo SQL que usas en reportes_mensuales, pero lo ejecutamos 12 veces (uno por mes)
$sqlMes = "
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

// Aquí guardamos los 12 meses
$datosMeses = [];

// Totales anuales (sumamos los meses)
$totales = [
    'nuevos_estudiantes' => 0,
    'matriculas_mes'     => 0,
    'ingresos_mes'       => 0,
    'tareas_asignadas'   => 0,
    'tareas_entregadas'  => 0,
    'mensajes_mes'       => 0,
    'leads_mes'          => 0
];

for ($m = 1; $m <= 12; $m++) {
    $stmt = $mysqli->prepare($sqlMes);
    if (!$stmt) {
        die("Error al preparar la consulta mensual: " . $mysqli->error);
    }

    // mismo patrón que en reportes_mensuales.php
    $stmt->bind_param(
        "iiiiiiiiiiiiiiii",
        $m, $anio,  // nuevos_estudiantes
        $m, $anio,  // matriculas_mes
        $m, $anio,  // ingresos_mes
        $m, $anio,  // asistencia_global
        $m, $anio,  // tareas_asignadas
        $m, $anio,  // tareas_entregadas
        $m, $anio,  // mensajes_mes
        $m, $anio   // leads_mes
    );

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    // Por si algún mes no tiene nada, que no truene
    if (!$row) {
        $row = [
            'nuevos_estudiantes' => 0,
            'matriculas_mes'     => 0,
            'ingresos_mes'       => 0,
            'asistencia_global'  => 0,
            'tareas_asignadas'   => 0,
            'tareas_entregadas'  => 0,
            'mensajes_mes'       => 0,
            'leads_mes'          => 0
        ];
    }

    $row['mes_num']    = $m;
    $row['mes_nombre'] = $mesesNombres[$m];

    // Guardamos para la tabla
    $datosMeses[] = $row;

    // Sumamos totales anuales
    $totales['nuevos_estudiantes'] += (int)$row['nuevos_estudiantes'];
    $totales['matriculas_mes']     += (int)$row['matriculas_mes'];
    $totales['ingresos_mes']       += (float)$row['ingresos_mes'];
    $totales['tareas_asignadas']   += (int)$row['tareas_asignadas'];
    $totales['tareas_entregadas']  += (int)$row['tareas_entregadas'];
    $totales['mensajes_mes']       += (int)$row['mensajes_mes'];
    $totales['leads_mes']          += (int)$row['leads_mes'];
}

include __DIR__ . "/../../includes/header.php";
?>

<div class="container mt-4">

    <!-- HEADER CON ESTÉTICA TWINtalk (similar al mensual) -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"
             style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">

            <div>
                <h2 class="mb-1 d-flex align-items-center" style="color:#b14f72;">
                    <i class="fa-solid fa-calendar-days me-2"></i>
                    Reporte anual general
                </h2>
                <small class="text-muted">
                    Año seleccionado: <strong><?= htmlspecialchars($anio) ?></strong>
                </small>
            </div>

            <div class="d-flex flex-column align-items-md-end gap-2 w-100 w-md-auto">

                <!-- Selector de año -->
                <form method="get"
                      class="d-flex flex-wrap justify-content-end gap-2 align-items-center p-2 rounded-3"
                      style="background-color:#ffffffcc;">
                    <label for="anio" class="form-label mb-0 small">Seleccionar año:</label>
                    <select name="anio" id="anio"
                            class="form-select form-select-sm"
                            style="max-width:120px;"
                            onchange="this.form.submit()">
                        <?php
                        $anioActual = (int)date('Y');
                        for ($a = $anioActual; $a >= $anioActual - 5; $a--):
                        ?>
                            <option value="<?= $a ?>" <?= $a == $anio ? 'selected' : '' ?>>
                                <?= $a ?>
                            </option>
                        <?php endfor; ?>
                    </select>

                    <button type="submit"
                            class="btn btn-sm btn-primary"
                            style="background-color:#b14f72;border-color:#b14f72;">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>
                        Aplicar
                    </button>
                </form>

                <!-- Botones extra -->
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <a href="reportes_mensuales.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left me-1"></i> Volver a mensual
                    </a>
                    <a href="reportes_anuales_pdf.php?anio=<?= $anio ?>"
                       target="_blank"
                       class="btn btn-outline-danger btn-sm">
                        <i class="fa-solid fa-file-pdf me-1"></i> PDF anual
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- TARJETAS RESUMEN ANUAL -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#b14f72;"><?= $totales['nuevos_estudiantes'] ?></h5>
                    <p class="text-muted mb-0">Nuevos estudiantes en el año</p>
                    <small class="text-muted">Año <?= $anio ?></small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#4caf50;"><?= $totales['matriculas_mes'] ?></h5>
                    <p class="text-muted mb-0">Matrículas del año</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#ff9800;">L <?= number_format($totales['ingresos_mes'], 2) ?></h5>
                    <p class="text-muted mb-0">Ingresos del año</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <h5 class="mb-0" style="color:#b14f72;"><?= $totales['tareas_asignadas'] ?></h5>
                    <p class="text-muted mb-0">Tareas asignadas en el año</p>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE LOS 12 MESES -->
    <div class="card card-soft border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0 h6 fw-bold" style="color:#b14f72;">
                Resumen mensual del año <?= htmlspecialchars($anio) ?>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead style="background-color:#fbe9f0;">
                        <tr>
                            <th>Mes</th>
                            <th>Nuevos est.</th>
                            <th>Matrículas</th>
                            <th>Ingresos</th>
                            <th>Asistencia %</th>
                            <th>Tareas asignadas</th>
                            <th>Tareas entregadas</th>
                            <th>Mensajes internos</th>
                            <th>Contactos interesados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datosMeses as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['mes_nombre']) ?></td>
                                <td><?= $m['nuevos_estudiantes'] ?></td>
                                <td><?= $m['matriculas_mes'] ?></td>
                                <td>L <?= number_format($m['ingresos_mes'], 2) ?></td>
                                <td><?= $m['asistencia_global'] ?>%</td>
                                <td><?= $m['tareas_asignadas'] ?></td>
                                <td><?= $m['tareas_entregadas'] ?></td>
                                <td><?= $m['mensajes_mes'] ?></td>
                                <td><?= $m['leads_mes'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background-color:#fafafa;">
                        <tr>
                            <th>Totales</th>
                            <th><?= $totales['nuevos_estudiantes'] ?></th>
                            <th><?= $totales['matriculas_mes'] ?></th>
                            <th>L <?= number_format($totales['ingresos_mes'], 2) ?></th>
                            <th>—</th>
                            <th><?= $totales['tareas_asignadas'] ?></th>
                            <th><?= $totales['tareas_entregadas'] ?></th>
                            <th><?= $totales['mensajes_mes'] ?></th>
                            <th><?= $totales['leads_mes'] ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
include __DIR__ . "/../../includes/footer.php";
