<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([2]); // docente

require_once __DIR__ . "/../vendor/autoload.php";
use Dompdf\Dompdf;

$docenteId  = $_SESSION['usuario_id'] ?? 0;
$horario_id = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;

if ($docenteId <= 0 || $horario_id <= 0) {
    die("Acceso no válido.");
}

// Datos del horario + curso (validando que sea del docente)
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

// Alumnos de ese horario
$stmt = $mysqli->prepare("
    SELECT 
        u.nombre,
        u.apellido,
        u.email,
        em.nombre_estado
    FROM matriculas m
    INNER JOIN estudiantes e ON e.id = m.estudiante_id
    INNER JOIN usuarios u    ON u.id = e.id
    INNER JOIN estados_matricula em ON em.id = m.estado_id
    WHERE m.horario_id = ?
    ORDER BY u.apellido, u.nombre
");
$stmt->bind_param("i", $horario_id);
$stmt->execute();
$alumnos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Asistencia global del horario
$stmt = $mysqli->prepare("
    SELECT 
        COUNT(*) AS total_registros,
        IFNULL(SUM(presente),0) AS presentes
    FROM asistencia a
    WHERE a.matricula_id IN (
        SELECT id FROM matriculas WHERE horario_id = ?
    )
");
$stmt->bind_param("i", $horario_id);
$stmt->execute();
$asist = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalReg = (int)($asist['total_registros'] ?? 0);
$totalPre = (int)($asist['presentes'] ?? 0);
$porcAsis = $totalReg > 0 ? round(($totalPre / $totalReg) * 100, 2) : 0;

// Tareas del horario
$stmt = $mysqli->prepare("
    SELECT 
        t.id,
        t.titulo,
        t.fecha_publicacion,
        t.fecha_entrega,
        t.valor_maximo,
        (SELECT COUNT(*) FROM tareas_entregas te WHERE te.tarea_id = t.id) AS total_entregas,
        (SELECT ROUND(AVG(te2.calificacion),2)
         FROM tareas_entregas te2
         WHERE te2.tarea_id = t.id AND te2.calificacion IS NOT NULL
        ) AS promedio_tarea
    FROM tareas t
    WHERE t.horario_id = ?
    ORDER BY t.fecha_publicacion ASC
");
$stmt->bind_param("i", $horario_id);
$stmt->execute();
$tareas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== Logo en base64 =====
$logoPath = __DIR__ . '/../assets/img/logo.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoData   = base64_encode(file_get_contents($logoPath));
    $logoBase64 = 'data:image/png;base64,' . $logoData;
}

$fechaGeneracion = date('d/m/Y H:i');

// ===== HTML DEL PDF =====
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de clase</title>
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
        .subtitle { font-size: 11px; margin: 2px 0 0 0; }
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
        <img src="<?= $logoBase64 ?>" style="width:110px; margin-bottom:6px;">
    <?php endif; ?>
    <div class="title">TwinTalk English Academy</div>
    <div class="subtitle">Reporte de clase</div>
</div>

<div class="section-title">Información de la clase</div>
<table>
    <tr><th>Curso</th><td><?= htmlspecialchars($horario['nombre_curso']) ?></td></tr>
    <tr><th>Día y hora</th><td><?= htmlspecialchars($horario['nombre_dia']) ?> (<?= substr($horario['hora_inicio'],0,5) ?> - <?= substr($horario['hora_fin'],0,5) ?>)</td></tr>
    <tr><th>Aula</th><td><?= htmlspecialchars($horario['aula']) ?></td></tr>
    <tr><th>Fechas</th><td><?= $horario['fecha_inicio'] ?> → <?= $horario['fecha_fin'] ?></td></tr>
    <?php if (!empty($horario['descripcion'])): ?>
    <tr><th>Descripción del curso</th><td><?= htmlspecialchars($horario['descripcion']) ?></td></tr>
    <?php endif; ?>
</table>

<div class="section-title">Resumen de asistencia</div>
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

<div class="section-title">Alumnos matriculados</div>
<?php if (empty($alumnos)): ?>
    <p>No hay alumnos matriculados en esta clase.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Estado matrícula</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($alumnos as $al): ?>
        <tr>
            <td><?= htmlspecialchars($al['nombre'].' '.$al['apellido']) ?></td>
            <td><?= htmlspecialchars($al['email']) ?></td>
            <td><?= htmlspecialchars($al['nombre_estado']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="section-title">Tareas de la clase</div>
<?php if (empty($tareas)): ?>
    <p>No hay tareas registradas para este horario.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Título</th>
            <th>Publicación</th>
            <th>Entrega</th>
            <th>Valor</th>
            <th>Entregas</th>
            <th>Promedio</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($tareas as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['titulo']) ?></td>
            <td><?= $t['fecha_publicacion'] ?></td>
            <td><?= $t['fecha_entrega'] ?></td>
            <td><?= (int)$t['valor_maximo'] ?></td>
            <td><?= (int)$t['total_entregas'] ?></td>
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

$nombreArchivo = "Reporte_Horario_{$horario_id}.pdf";
$dompdf->stream($nombreArchivo, ["Attachment" => true]);
exit;
