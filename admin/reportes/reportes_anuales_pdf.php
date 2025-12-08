<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_role([1]); 

require_once __DIR__ . "/../../vendor/autoload.php";
use Dompdf\Dompdf;


$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
if ($anio < 2000 || $anio > 2100) {
    $anio = (int)date('Y');
}


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


$datosMeses = [];


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

    $stmt->bind_param(
        "iiiiiiiiiiiiiiii",
        $m, $anio,  
        $m, $anio,  
        $m, $anio,  
        $m, $anio,  
        $m, $anio,  
        $m, $anio,  
        $m, $anio,  
        $m, $anio   
    );

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

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

    $datosMeses[] = $row;

    $totales['nuevos_estudiantes'] += (int)$row['nuevos_estudiantes'];
    $totales['matriculas_mes']     += (int)$row['matriculas_mes'];
    $totales['ingresos_mes']       += (float)$row['ingresos_mes'];
    $totales['tareas_asignadas']   += (int)$row['tareas_asignadas'];
    $totales['tareas_entregadas']  += (int)$row['tareas_entregadas'];
    $totales['mensajes_mes']       += (int)$row['mensajes_mes'];
    $totales['leads_mes']          += (int)$row['leads_mes'];
}


$logoPath = __DIR__ . '/../../assets/img/logo.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoData   = base64_encode(file_get_contents($logoPath));
    $logoBase64 = 'data:image/png;base64,' . $logoData;
}

$fechaGeneracion = date('d/m/Y H:i');

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Anual <?= htmlspecialchars($anio) ?></title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #333333;
            margin: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 6px;
        }
        .header img {
            max-width: 70px;
            margin-bottom: 4px;
        }
        .academy-title {
            font-size: 16px;
            font-weight: bold;
            color: #A0455A;
        }
        .report-title {
            font-size: 10px;
            color: #555555;
            margin-bottom: 2px;
        }
        .divider {
            border-top: 2px solid #A0455A;
            margin: 2px 0 6px 0;
        }
        .small {
            font-size: 8px;
            color: #777777;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #A0455A;
            margin-top: 4px;
            margin-bottom: 2px;
            border-left: 3px solid #A0455A;
            padding-left: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }
        th, td {
            border: 1px solid #dddddd;
            padding: 2px 3px;
            text-align: center;
            page-break-inside: avoid;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 8px;
        }
        td {
            font-size: 8px;
        }
        tfoot th {
            background-color: #eee;
            font-weight: bold;
        }

        .summary-table {
            margin-bottom: 4px;
        }
        .summary-table td,
        .summary-table th {
            text-align: left;
            padding: 2px 4px;
        }

        .summary-label {
            width: 55%;
        }

        .footer {
            margin-top: 4px;
            text-align: right;
            font-size: 8px;
            color: #777777;
        }
    </style>
</head>
<body>

<div class="header">
    <?php if ($logoBase64): ?>
        <img src="<?= $logoBase64 ?>" alt="Logo">
    <?php endif; ?>
    <div class="academy-title">TwinTalk English Academy</div>
    <div class="report-title">Reporte anual general</div>
    <div class="divider"></div>
    <div class="small">Año <?= htmlspecialchars($anio) ?> — Generado el <?= $fechaGeneracion ?></div>
</div>

<!-- RESUMEN DEL AÑO -->
<div class="section-title">Resumen del año</div>
<table class="summary-table">
    <tr>
        <th class="summary-label">Nuevos estudiantes en el año</th>
        <td><?= $totales['nuevos_estudiantes'] ?></td>
    </tr>
    <tr>
        <th class="summary-label">Matrículas del año</th>
        <td><?= $totales['matriculas_mes'] ?></td>
    </tr>
    <tr>
        <th class="summary-label">Ingresos del año</th>
        <td>L <?= number_format($totales['ingresos_mes'], 2) ?></td>
    </tr>
    <tr>
        <th class="summary-label">Tareas asignadas en el año</th>
        <td><?= $totales['tareas_asignadas'] ?></td>
    </tr>
    <tr>
        <th class="summary-label">Tareas entregadas en el año</th>
        <td><?= $totales['tareas_entregadas'] ?></td>
    </tr>
    <tr>
        <th class="summary-label">Mensajes internos en el año</th>
        <td><?= $totales['mensajes_mes'] ?></td>
    </tr>
    <tr>
        <th class="summary-label">Contactos interesados (leads) en el año</th>
        <td><?= $totales['leads_mes'] ?></td>
    </tr>
</table>

<!-- DETALLE MENSUAL -->
<div class="section-title">Detalle mensual del año <?= htmlspecialchars($anio) ?></div>
<table>
    <thead>
        <tr>
            <th>Mes</th>
            <th>Nuevos est.</th>
            <th>Matrículas</th>
            <th>Ingresos</th>
            <th>Asistencia %</th>
            <th>Tareas asignadas</th>
            <th>Tareas entregadas</th>
            <th>Mensajes internos</th>
            <th>Leads</th>
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
    <tfoot>
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

<div class="footer">
    TwinTalk English — Reporte anual
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); 
$dompdf->render();

$dompdf->stream("Reporte_Anual_{$anio}.pdf", ["Attachment" => true]);
exit;
