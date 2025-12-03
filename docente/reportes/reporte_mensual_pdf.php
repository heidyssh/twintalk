<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_role([2]); // docente

require_once __DIR__ . "/../../vendor/autoload.php";
use Dompdf\Dompdf;

$docenteId  = $_SESSION['usuario_id'] ?? 0;
$horario_id = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;
$mes        = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio       = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

if ($docenteId <= 0 || $horario_id <= 0) {
    die("Acceso no válido.");
}

// Nombre del mes (en inglés, como el reporte admin)
$nombreMes = date('F', strtotime(sprintf('%04d-%02d-01', $anio, $mes)));

// 1) Datos del horario + validación docente
$stmt = $mysqli->prepare("
    SELECT 
        h.id,
        h.aula,
        h.fecha_inicio,
        h.fecha_fin,
        ds.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        c.nombre_curso,
        c.descripcion
    FROM horarios h
    INNER JOIN dias_semana ds ON ds.id = h.dia_semana_id
    INNER JOIN cursos c       ON c.id = h.curso_id
    WHERE h.id = ? AND h.docente_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $horario_id, $docenteId);
$stmt->execute();
$horario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$horario) {
    die("No se encontró el horario o no pertenece al docente.");
}

// 2) Total de estudiantes del horario
$stmt = $mysqli->prepare("
    SELECT COUNT(DISTINCT m.estudiante_id) AS total_estudiantes
    FROM matriculas m
    WHERE m.horario_id = ?
");
$stmt->bind_param("i", $horario_id);
$stmt->execute();
$infoEst = $stmt->get_result()->fetch_assoc();
$stmt->close();
$totalEstudiantes = (int)($infoEst['total_estudiantes'] ?? 0);

// 3) Asistencia del mes
$stmt = $mysqli->prepare("
    SELECT 
        COUNT(*) AS total_registros,
        IFNULL(SUM(presente),0) AS presentes
    FROM asistencia a
    WHERE MONTH(a.fecha_clase) = ?
      AND YEAR(a.fecha_clase)  = ?
      AND a.matricula_id IN (
          SELECT id FROM matriculas WHERE horario_id = ?
      )
");
$stmt->bind_param("iii", $mes, $anio, $horario_id);
$stmt->execute();
$asist = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalReg = (int)($asist['total_registros'] ?? 0);
$totalPre = (int)($asist['presentes'] ?? 0);
$porcAsis = $totalReg > 0 ? round(($totalPre / $totalReg) * 100, 2) : 0;

// 4) Tareas del mes (publicadas ese mes)
$stmt = $mysqli->prepare("
    SELECT 
        t.id,
        t.titulo,
        t.fecha_publicacion,
        t.fecha_entrega,
        t.valor_maximo,
        (SELECT COUNT(*)
         FROM tareas_entregas te
         WHERE te.tarea_id = t.id
           AND MONTH(te.fecha_entrega) = ?
           AND YEAR(te.fecha_entrega)  = ?) AS total_entregas,
        (SELECT ROUND(AVG(te2.calificacion),2)
         FROM tareas_entregas te2
         WHERE te2.tarea_id = t.id
           AND te2.calificacion IS NOT NULL
           AND MONTH(te2.fecha_entrega) = ?
           AND YEAR(te2.fecha_entrega)  = ?) AS promedio_tarea
    FROM tareas t
    WHERE t.horario_id = ?
      AND MONTH(t.fecha_publicacion) = ?
      AND YEAR(t.fecha_publicacion)  = ?
    ORDER BY t.fecha_publicacion ASC
");
$stmt->bind_param(
    "iiiiiii",
    $mes, $anio,   // entregas
    $mes, $anio,   // promedio
    $horario_id,
    $mes, $anio    // filtro tareas
);
$stmt->execute();
$tareas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 5) Logo
$logoPath = __DIR__ . "/../../assets/img/logo.png";
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
    <title>Reporte mensual de clase</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        .header {
            text-align: center;
            border-bottom: 2px solid #A45A6A;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #A45A6A;
            margin: 0;
        }
        .subtitle {
            font-size: 11px;
            margin: 2px 0 0 0;
            color: #555;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #444;
            margin-top: 10px;
            margin-bottom: 5px;
            border-left: 4px solid #A45A6A;
            padding-left: 5px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 4px 5px; }
        th { background-color: #f5f0f2; font-weight: bold; }
        .footer { margin-top: 10px; font-size: 9px; text-align: right; color: #777; }
    </style>
</head>
<body>

<div class="header">
    <?php if ($logoBase64): ?>
        <img src="<?= $logoBase64 ?>" style="width:100px; margin-bottom:6px;">
    <?php endif; ?>
    <div class="title">TwinTalk English Academy</div>
    <div class="subtitle">
        Reporte mensual de gestión — <?= $nombreMes ?> <?= $anio ?>
    </div>
</div>

<div class="section-title">Información de la clase</div>
<table>
    <tr><th>Curso</th><td><?= htmlspecialchars($horario['nombre_curso']) ?></td></tr>
    <tr><th>Día y hora</th>
        <td><?= htmlspecialchars($horario['nombre_dia']) ?> (<?= substr($horario['hora_inicio'],0,5) ?> - <?= substr($horario['hora_fin'],0,5) ?>)</td>
    </tr>
    <tr><th>Aula</th><td><?= htmlspecialchars($horario['aula']) ?></td></tr>
    <tr><th>Fechas del curso</th><td><?= $horario['fecha_inicio'] ?> → <?= $horario['fecha_fin'] ?></td></tr>
    <tr><th>Total estudiantes</th><td><?= $totalEstudiantes ?></td></tr>
    <?php if (!empty($horario['descripcion'])): ?>
    <tr><th>Descripción del curso</th><td><?= htmlspecialchars($horario['descripcion']) ?></td></tr>
    <?php endif; ?>
</table>

<div class="section-title">Resumen de asistencia del mes</div>
<table>
    <tr>
        <th>Total registros</th>
        <th>Presentes</th>
        <th>Porcentaje</th>
    </tr>
    <tr>
        <td><?= $totalReg ?></td>
        <td><?= $totalPre ?></td>
        <td><?= $porcAsis ?> %</td>
    </tr>
</table>

<div class="section-title">Tareas publicadas este mes</div>
<?php if (empty($tareas)): ?>
    <p>No se publicaron tareas en este mes para esta clase.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Título</th>
            <th>Publicación</th>
            <th>Entrega</th>
            <th>Valor</th>
            <th>Entregas (mes)</th>
            <th>Promedio (mes)</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($tareas as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['titulo']) ?></td>
            <td><?= $t['fecha_publicacion'] ?></td>
            <td><?= $t['fecha_entrega'] ?></td>
            <td><?= (int)$t['valor_maximo'] ?></td>
            <td><?= (int)($t['total_entregas'] ?? 0) ?></td>
            <td><?= $t['promedio_tarea'] !== null ? $t['promedio_tarea'] : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="footer">
    Generado el <?= $fechaGeneracion ?> — TwinTalk English
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nombreArchivo = "Reporte_Mensual_Horario_{$horario_id}_{$mes}_{$anio}.pdf";
$dompdf->stream($nombreArchivo, ["Attachment" => true]);
exit;
