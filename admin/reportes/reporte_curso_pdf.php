<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";
require_role([1]); 

require_once __DIR__ . "/../../vendor/autoload.php";
use Dompdf\Dompdf;

$curso_id = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;

if ($curso_id <= 0) {
    die("Curso no válido.");
}


$stmt = $mysqli->prepare("
    SELECT c.id, c.nombre_curso, c.descripcion, n.codigo_nivel, n.nombre_nivel
    FROM cursos c
    LEFT JOIN niveles_academicos n ON c.nivel_id = n.id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $curso_id);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$curso) {
    die("Curso no encontrado.");
}


$stmt = $mysqli->prepare("
    SELECT 
        h.id,
        h.aula,
        h.fecha_inicio,
        h.fecha_fin,
        ds.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        u.nombre AS nombre_docente,
        u.apellido AS apellido_docente,
        COUNT(DISTINCT m.id) AS total_matriculas
    FROM horarios h
    INNER JOIN dias_semana ds ON ds.id = h.dia_semana_id
    INNER JOIN docentes d     ON d.id = h.docente_id
    INNER JOIN usuarios u     ON u.id = d.id
    LEFT JOIN matriculas m    ON m.horario_id = h.id
    WHERE h.curso_id = ?
    GROUP BY h.id, h.aula, h.fecha_inicio, h.fecha_fin, ds.nombre_dia,
             h.hora_inicio, h.hora_fin, u.nombre, u.apellido
    ORDER BY ds.numero_dia, h.hora_inicio
");
$stmt->bind_param("i", $curso_id);
$stmt->execute();
$horariosRes = $stmt->get_result();
$horarios = $horariosRes->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$stmt = $mysqli->prepare("
    SELECT 
        t.id,
        t.titulo,
        t.fecha_publicacion,
        t.fecha_entrega,
        t.valor_maximo,
        h.id AS horario_id,
        ds.nombre_dia,
        h.hora_inicio,
        (SELECT COUNT(*) FROM tareas_entregas te WHERE te.tarea_id = t.id) AS total_entregas
    FROM tareas t
    INNER JOIN horarios h   ON h.id = t.horario_id
    INNER JOIN dias_semana ds ON ds.id = h.dia_semana_id
    WHERE h.curso_id = ?
    ORDER BY t.fecha_publicacion DESC
");
$stmt->bind_param("i", $curso_id);
$stmt->execute();
$tareas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


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
    <title>Reporte de curso</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        .header {
            text-align: center;
            border-bottom: 2px solid #A45A6A;
            padding-bottom: 8px;
            margin-bottom: 15px;
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
            margin-top: 12px;
            margin-bottom: 6px;
            border-left: 4px solid #A45A6A;
            padding-left: 5px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
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
    <div class="subtitle">Reporte de curso</div>
</div>

<div class="section-title">Información del curso</div>
<table>
    <tr><th>Nombre</th><td><?= htmlspecialchars($curso['nombre_curso']) ?></td></tr>
    <tr><th>Nivel</th><td><?= htmlspecialchars(($curso['codigo_nivel'] ?? '').' '.($curso['nombre_nivel'] ?? '')) ?></td></tr>
    <?php if (!empty($curso['descripcion'])): ?>
    <tr><th>Descripción</th><td><?= htmlspecialchars($curso['descripcion']) ?></td></tr>
    <?php endif; ?>
</table>

<div class="section-title">Horarios y docentes</div>
<?php if (empty($horarios)): ?>
    <p>No hay horarios registrados para este curso.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Día / Hora</th>
            <th>Docente</th>
            <th>Aula</th>
            <th>Fechas</th>
            <th>Alumnos</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($horarios as $h): ?>
        <tr>
            <td><?= htmlspecialchars($h['nombre_dia']) ?> (<?= substr($h['hora_inicio'],0,5) ?> - <?= substr($h['hora_fin'],0,5) ?>)</td>
            <td><?= htmlspecialchars($h['nombre_docente'].' '.$h['apellido_docente']) ?></td>
            <td><?= htmlspecialchars($h['aula']) ?></td>
            <td><?= $h['fecha_inicio'] ?> → <?= $h['fecha_fin'] ?></td>
            <td><?= (int)$h['total_matriculas'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="section-title">Tareas del curso</div>
<?php if (empty($tareas)): ?>
    <p>No hay tareas registradas para este curso.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Título</th>
            <th>Horario</th>
            <th>Publicación</th>
            <th>Entrega</th>
            <th>Valor</th>
            <th>Entregas</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($tareas as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['titulo']) ?></td>
            <td><?= htmlspecialchars($t['nombre_dia']) ?> (<?= substr($t['hora_inicio'],0,5) ?>)</td>
            <td><?= $t['fecha_publicacion'] ?></td>
            <td><?= $t['fecha_entrega'] ?></td>
            <td><?= (int)$t['valor_maximo'] ?></td>
            <td><?= (int)$t['total_entregas'] ?></td>
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

$nombreArchivo = "Reporte_Curso_{$curso_id}.pdf";
$dompdf->stream($nombreArchivo, ["Attachment" => true]);
exit;
