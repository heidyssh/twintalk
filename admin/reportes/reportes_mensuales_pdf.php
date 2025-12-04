<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_role([1]); // admin

// Dompdf
require_once __DIR__ . "/../../vendor/autoload.php";
use Dompdf\Dompdf;
// Mes y año a usar en el PDF (desde el filtro, o actuales por defecto)
$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Validaciones básicas
if ($mes < 1 || $mes > 12) {
    $mes = (int)date('m');
}
if ($anio < 2000 || $anio > 2100) {
    $anio = (int)date('Y');
}

// ========== MISMOS KPIs QUE reportes_mensuales.php ==========
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
$stmt->bind_param(
    "iiiiiiiiiiiiiiii",
    $mes, $anio,
    $mes, $anio,
    $mes, $anio,
    $mes, $anio,
    $mes, $anio,
    $mes, $anio,
    $mes, $anio,
    $mes, $anio
);
$stmt->execute();
$kpis = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ========== HTML DEL PDF (ESTÉTICO) ==========
$mesNombre = date('F', mktime(0,0,0,$mes,1,$anio)); // en inglés, pero sirve
$fechaGeneracion = date('d/m/Y H:i');
// Convertir logo a base64
$logoPath = __DIR__ . '/../../assets/img/logo.png';
$logoData = base64_encode(file_get_contents($logoPath));
$logoBase64 = 'data:image/png;base64,' . $logoData;

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte mensual TwinTalk</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #A45A6A;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #A45A6A;
            margin: 0;
        }
        .subtitle {
            font-size: 12px;
            margin: 3px 0 0 0;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #444;
            margin-top: 20px;
            margin-bottom: 8px;
            border-left: 4px solid #A45A6A;
            padding-left: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
        }
        th {
            background-color: #f5f0f2;
            font-weight: bold;
        }
        .kpi-table td.label {
            font-weight: bold;
            width: 40%;
        }
        .footer {
            margin-top: 20px;
            font-size: 10px;
            text-align: right;
            color: #777;
        }
    </style>
</head>
<body>

<div class="header">
    <img src="'.$logoBase64.'" style="width:120px; margin-bottom:10px;">
    <div class="title">TwinTalk English Academy</div>
    <div class="subtitle">Reporte mensual de gestión — '.$mesNombre.' '.$anio.'</div>
</div>

<div class="section-title">Resumen general</div>
<table class="kpi-table">
    <tr>
        <td class="label">Nuevos estudiantes</td>
        <td>'.(int)$kpis['nuevos_estudiantes'].'</td>
    </tr>
    <tr>
        <td class="label">Matrículas del mes</td>
        <td>'.(int)$kpis['matriculas_mes'].'</td>
    </tr>
    <tr>
        <td class="label">Ingresos del mes</td>
        <td>L '.number_format($kpis['ingresos_mes'], 2).'</td>
    </tr>
    <tr>
        <td class="label">Asistencia global</td>
        <td>'.$kpis['asistencia_global'].' %</td>
    </tr>
</table>

<div class="section-title">Académico</div>
<table>
    <tr>
        <th>Indicador</th>
        <th>Valor</th>
    </tr>
    <tr>
        <td>Tareas asignadas</td>
        <td>'.(int)$kpis['tareas_asignadas'].'</td>
    </tr>
    <tr>
        <td>Tareas entregadas</td>
        <td>'.(int)$kpis['tareas_entregadas'].'</td>
    </tr>
</table>

<div class="section-title">Comunicación</div>
<table>
    <tr>
        <th>Indicador</th>
        <th>Valor</th>
    </tr>
    <tr>
        <td>Mensajes internos</td>
        <td>'.(int)$kpis['mensajes_mes'].'</td>
    </tr>
    <tr>
        <td>Contactos interesados (leads)</td>
        <td>'.(int)$kpis['leads_mes'].'</td>
    </tr>
</table>

<div class="footer">
    Generado automáticamente el '.$fechaGeneracion.'
</div>

</body>
</html>
';

// ========== GENERAR PDF ==========
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nombreArchivo = "Reporte_Mensual_TwinTalk_{$mes}_{$anio}.pdf";
$dompdf->stream($nombreArchivo, ["Attachment" => true]);
exit;
