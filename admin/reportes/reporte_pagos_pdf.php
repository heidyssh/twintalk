<?php


require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_role([1]); 


require_once __DIR__ . "/../../vendor/autoload.php";
use Dompdf\Dompdf;

date_default_timezone_set('America/Tegucigalpa');


$fecha_desde = $_GET['desde'] ?? '';
$fecha_hasta = $_GET['hasta'] ?? '';

$where = "1=1";
$params = [];
$types  = "";


if ($fecha_desde !== '') {
    $where .= " AND DATE(m.fecha_matricula) >= ?";
    $params[] = $fecha_desde;
    $types   .= "s";
}
if ($fecha_hasta !== '') {
    $where .= " AND DATE(m.fecha_matricula) <= ?";
    $params[] = $fecha_hasta;
    $types   .= "s";
}


$sql = "
    SELECT
        m.id AS matricula_id,
        m.fecha_matricula,
        m.fecha_vencimiento,
        m.monto_pagado,
        u.nombre,
        u.apellido,
        u.email,
        c.nombre_curso,
        n.codigo_nivel,
        em.nombre_estado AS estado_matricula,
        mp.nombre_metodo AS metodo_pago,
        pc.precio AS precio_curso,
        (pc.precio - IFNULL(m.monto_pagado, 0)) AS saldo_pendiente
    FROM matriculas m
    INNER JOIN usuarios u       ON u.id = m.estudiante_id
    INNER JOIN horarios h       ON h.id = m.horario_id
    INNER JOIN cursos c         ON c.id = h.curso_id
    INNER JOIN niveles_academicos n ON n.id = c.nivel_id
    INNER JOIN estados_matricula em ON em.id = m.estado_id
    LEFT JOIN metodos_pago mp   ON mp.id = m.metodo_pago_id
    LEFT JOIN precios_cursos pc ON pc.curso_id = c.id AND pc.activo = 1
    WHERE $where
    ORDER BY m.fecha_matricula DESC, m.id DESC
";

$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();


$hoy = date('d/m/Y H:i');


$logoPath = __DIR__ . '/../../assets/img/logo.png';
$logoBase64 = '';

if (file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
}


ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Gestión de Pagos</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            margin: 20px;
        }
        h1, h2, h3 {
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header .titulo {
            font-size: 18px;
            font-weight: bold;
            background-color: #A45A6A;
            color: #ffffff;
            padding: 8px;
            border-radius: 4px;
        }
        .subtitulo {
            font-size: 12px;
            margin-top: 4px;
            color: #555;
        }
        .filtros {
            font-size: 11px;
            margin-top: 8px;
            margin-bottom: 10px;
        }
        .filtros span {
            display: inline-block;
            margin-right: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #cccccc;
            padding: 4px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .right {
            text-align: right;
        }
        .center {
            text-align: center;
        }
        .small {
            font-size: 10px;
        }
        .resumen {
            margin-top: 10px;
            font-size: 11px;
        }
    </style>
</head>
<body>


<div class="header">
    <?php if ($logoBase64): ?>
        <img src="<?= $logoBase64 ?>" alt="Logo" style="width:120px; margin-bottom:5px;">
    <?php endif; ?>
    
    <div class="titulo">TwinTalk English Academy</div>
    <div class="subtitulo">Reporte de Gestión de Pagos</div>
    <div class="small">Generado el: <?= htmlspecialchars($hoy) ?></div>
</div>

<div class="filtros">
    <?php if ($fecha_desde !== '' || $fecha_hasta !== ''): ?>
        <span><strong>Periodo:</strong>
            <?php
            $desdeLabel = $fecha_desde !== '' ? date('d/m/Y', strtotime($fecha_desde)) : 'Inicio';
            $hastaLabel = $fecha_hasta !== '' ? date('d/m/Y', strtotime($fecha_hasta)) : 'Hoy';
            echo $desdeLabel . " al " . $hastaLabel;
            ?>
        </span>
    <?php else: ?>
        <span><strong>Periodo:</strong> Todos los registros</span>
    <?php endif; ?>
</div>

<table>
    <thead>
        <tr>
            <th class="center">#Mat</th>
            <th>Estudiante</th>
            <th>Correo</th>
            <th>Curso / Nivel</th>
            <th class="center">Estado</th>
            <th>Método pago</th>
            <th class="right">Precio curso</th>
            <th class="right">Monto pagado</th>
            <th class="right">Saldo</th>
            <th class="center">Fecha matrícula</th>
            <th class="center">Vencimiento</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $total_registros    = 0;
        $total_precio       = 0;
        $total_pagado       = 0;
        $total_saldo        = 0;

        while ($row = $result->fetch_assoc()):
            $total_registros++;
            $precio   = $row['precio_curso'] ?? 0;
            $pagado   = $row['monto_pagado'] ?? 0;
            $saldo    = $row['saldo_pendiente'] ?? 0;

            $total_precio += $precio;
            $total_pagado += $pagado;
            $total_saldo  += $saldo;
        ?>
        <tr>
            <td class="center"><?php echo (int)$row['matricula_id']; ?></td>
            <td><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido']); ?></td>
            <td class="small"><?php echo htmlspecialchars($row['email']); ?></td>
            <td><?php echo htmlspecialchars($row['nombre_curso'] . ' (' . $row['codigo_nivel'] . ')'); ?></td>
            <td class="center"><?php echo htmlspecialchars($row['estado_matricula']); ?></td>
            <td><?php echo htmlspecialchars($row['metodo_pago'] ?? 'N/D'); ?></td>
            <td class="right"><?php echo number_format($precio, 2); ?></td>
            <td class="right"><?php echo number_format($pagado, 2); ?></td>
            <td class="right"><?php echo number_format($saldo, 2); ?></td>
            <td class="center">
                <?php echo $row['fecha_matricula'] ? date('d/m/Y', strtotime($row['fecha_matricula'])) : ''; ?>
            </td>
            <td class="center">
                <?php echo $row['fecha_vencimiento'] ? date('d/m/Y', strtotime($row['fecha_vencimiento'])) : ''; ?>
            </td>
        </tr>
        <?php endwhile; ?>

        <?php if ($total_registros === 0): ?>
        <tr>
            <td colspan="11" class="center">No hay registros de pagos para el filtro seleccionado.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="resumen">
    <strong>Total matrículas:</strong> <?php echo $total_registros; ?><br>
    <strong>Total precio cursos:</strong> L. <?php echo number_format($total_precio, 2); ?><br>
    <strong>Total pagado:</strong> L. <?php echo number_format($total_pagado, 2); ?><br>
    <strong>Total saldo pendiente:</strong> L. <?php echo number_format($total_saldo, 2); ?>
</div>

</body>
</html>
<?php
$html = ob_get_clean();


$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); 
$dompdf->render();

$filename = "reporte_pagos_" . date('Ymd_His') . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit;
