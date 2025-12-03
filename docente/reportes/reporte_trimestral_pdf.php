<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_role([2]); // docente

require_once __DIR__ . "/../../vendor/autoload.php";
use Dompdf\Dompdf;

$docenteId      = $_SESSION['usuario_id'] ?? 0;
$horario_id     = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;
$anio           = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$trimestre      = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 1;
$periodo_inicio = isset($_GET['periodo_inicio']) ? $_GET['periodo_inicio'] : null;
$periodo_fin    = isset($_GET['periodo_fin'])    ? $_GET['periodo_fin']    : null;

if ($docenteId <= 0 || $horario_id <= 0) {
    die("Acceso no válido.");
}

// Si no vienen fechas exactas, calcular por trimestre
if (!$periodo_inicio || !$periodo_fin) {
    switch ($trimestre) {
        case 1:
            $mesInicio = 1;  $mesFin = 3;
            break;
        case 2:
            $mesInicio = 4;  $mesFin = 6;
            break;
        case 3:
            $mesInicio = 7;  $mesFin = 9;
            break;
        default:
            $mesInicio = 10; $mesFin = 12;
            break;
    }
    $periodo_inicio = sprintf("%04d-%02d-01", $anio, $mesInicio);
    $periodo_fin    = date('Y-m-t', strtotime(sprintf("%04d-%02d-01", $anio, $mesFin)));
}

// 1) Validar horario
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

// 2) Estudiantes
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

// 3) Asistencia del trimestre
$stmt = $mysqli->prepare("
    SELECT 
        COUNT(*) AS total_registros,
        IFNULL(SUM(presente),0) AS presentes
    FROM asistencia a
    WHERE a.fecha_clase BETWEEN ? AND ?
      AND a.matricula_id IN (
          SELECT id FROM matriculas WHERE horario_id = ?
      )
");
$stmt->bind_param("ssi", $periodo_inicio, $periodo_fin, $horario_id);
$stmt->execute();
$asist = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalReg = (int)($asist['total_registros'] ?? 0);
$totalPre = (int)($asist['presentes'] ?? 0);
$porcAsis = $totalReg > 0 ? round(($totalPre / $totalReg) * 100, 2) : 0;

// 4) Tareas del trimestre
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
           AND te.fecha_entrega BETWEEN ? AND ?) AS total_entregas_trimestre,
        (SELECT ROUND(AVG(te2.calificacion),2)
         FROM tareas_entregas te2
         WHERE te2.tarea_id = t.id
           AND te2.calificacion IS NOT NULL
           AND te2.fecha_entrega BETWEEN ? AND ?) AS promedio_tarea_trimestre
    FROM tareas t
    WHERE t.horario_id = ?
      AND t.fecha_publicacion BETWEEN ? AND ?
    ORDER BY t.fecha_publicacion ASC
");
$stmt->bind_param(
    "ssssiss",
    $periodo_inicio, $periodo_fin,   // entregas
    $periodo_inicio, $periodo_fin,   // promedio
    $horario_id,
    $periodo_inicio, $periodo_fin    // filtro tareas
);
$stmt->execute();
$tareas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 5) Logo
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
    <title>Reporte trimestral de clase</title>
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
        Reporte trimestral de gestión — Trimestre <?= $trimestre ?>, <?= $anio ?><br>
        Periodo: <?= date('d/m/Y', strtotime($periodo_inicio)) ?> al <?= date('d/m/Y', strtotime($periodo_fin)) ?>
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

<div class="section-title">Resumen de asistencia del trimestre</div>
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

<div class="section-title">Tareas del trimestre</div>
<?php if (empty($tareas)): ?>
    <p>No hay tareas registradas en este periodo.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Título</th>
            <th>Publicación</th>
            <th>Entrega</th>
            <th>Valor</th>
            <th>Entregas (trimestre)</th>
            <th>Promedio (trimestre)</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($tareas as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['titulo']) ?></td>
            <td><?= $t['fecha_publicacion'] ?></td>
            <td><?= $t['fecha_entrega'] ?></td>
            <td><?= (int)$t['valor_maximo'] ?></td>
            <td><?= (int)($t['total_entregas_trimestre'] ?? 0) ?></td>
            <td><?= $t['promedio_tarea_trimestre'] !== null ? $t['promedio_tarea_trimestre'] : '—' ?></td>
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

$nombreArchivo = "Reporte_Trimestral_Horario_{$horario_id}_T{$trimestre}_{$anio}.pdf";
$dompdf->stream($nombreArchivo, ["Attachment" => true]);
exit;
