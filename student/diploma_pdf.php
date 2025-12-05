<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); // solo estudiante

require_once __DIR__ . "/../vendor/autoload.php";
use Dompdf\Dompdf;

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$matricula_id = isset($_GET['matricula_id']) ? (int) $_GET['matricula_id'] : 0;

if ($usuario_id <= 0 || $matricula_id <= 0) {
    die("Solicitud no válida.");
}

// 1. Traer datos de la matrícula + NOTA FINAL (tareas + evaluaciones)
$sql = "
    SELECT 
        m.id AS matricula_id,
        u.nombre       AS est_nombre,
        u.apellido     AS est_apellido,
        u.email        AS est_email,
        c.nombre_curso,
        n.codigo_nivel,
        h.fecha_inicio,
        h.fecha_fin,
        em.nombre_estado,
ud.nombre      AS doc_nombre,
ud.apellido    AS doc_apellido,
d.id           AS docente_id,

        IFNULL(t_sum.suma_tareas, 0) AS suma_tareas,
        IFNULL(e_sum.suma_eval, 0)   AS suma_eval,
        (IFNULL(t_sum.suma_tareas, 0) + IFNULL(e_sum.suma_eval, 0)) AS nota_final

    FROM matriculas m
    JOIN estudiantes e        ON m.estudiante_id = e.id
    JOIN usuarios u           ON e.id = u.id
    JOIN horarios h           ON m.horario_id = h.id
    JOIN cursos c             ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN estados_matricula em ON m.estado_id = em.id
    JOIN docentes d           ON h.docente_id = d.id
    JOIN usuarios ud          ON d.id = ud.id

    LEFT JOIN (
        SELECT 
            te.matricula_id,
            SUM(te.calificacion) AS suma_tareas
        FROM tareas_entregas te
        INNER JOIN tareas t  ON t.id = te.tarea_id
        INNER JOIN horarios h ON h.id = t.horario_id
        WHERE te.calificacion IS NOT NULL
        GROUP BY te.matricula_id
    ) t_sum ON t_sum.matricula_id = m.id

    LEFT JOIN (
        SELECT 
            c.matricula_id,
            SUM(c.puntaje) AS suma_eval
        FROM calificaciones c
        WHERE c.puntaje IS NOT NULL
        GROUP BY c.matricula_id
    ) e_sum ON e_sum.matricula_id = m.id

    WHERE m.id = ? 
      AND m.estudiante_id = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    die("Error al preparar la consulta.");
}
$stmt->bind_param("ii", $matricula_id, $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$datos = $res->fetch_assoc();
$stmt->close();

if (!$datos) {
    die("No se encontró la matrícula solicitada.");
}

// Solo permitir diploma si está FINALIZADA
if ($datos['nombre_estado'] !== 'Finalizada') {
    die("Aún no puedes generar tu diploma porque este curso no está finalizado.");
}

// 2. Preparar datos
$nombreCompleto = $datos['est_nombre'] . " " . $datos['est_apellido'];
$cursoNombre = $datos['nombre_curso'];
$nivelCodigo = $datos['codigo_nivel'];
$docenteNombre = $datos['doc_nombre'] . " " . $datos['doc_apellido'];
$fechaInicio = date("d/m/Y", strtotime($datos['fecha_inicio']));
$fechaFin = date("d/m/Y", strtotime($datos['fecha_fin']));
$notaFinal = (float) ($datos['nota_final'] ?? 0);
$notaFinalTexto = $notaFinal > 0 ? number_format($notaFinal, 2) : "—";
$fechaEmision = date("d/m/Y");
// Logo
$logoPath = __DIR__ . '/../assets/img/logo.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoData = base64_encode(file_get_contents($logoPath));
    $logoBase64 = 'data:image/png;base64,' . $logoData;
}
// Firma Dirección Académica
$firmaPath = __DIR__ . '/../assets/img/firmadueña.png';
// Firma del docente (dinámica)
$docenteFirmaBase64 = '';
$docenteId = (int) $datos['docente_id'];

$firmaDocentePath = __DIR__ . "/../assets/img/firmas_docentes/firma_{$docenteId}.png";

if (file_exists($firmaDocentePath)) {
    $firmaDocenteData = base64_encode(file_get_contents($firmaDocentePath));
    $docenteFirmaBase64 = "data:image/png;base64,{$firmaDocenteData}";
}

$firmaBase64 = '';
if (file_exists($firmaPath)) {
    $firmaData = base64_encode(file_get_contents($firmaPath));
    $firmaBase64 = 'data:image/png;base64,' . $firmaData;
}

// 3. HTML
ob_start();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Diploma de finalización</title>
    <style>
        /* Márgenes de la hoja (poco margen para que se vea grande) */
        @page {
            margin: 18px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            background-color: #ffffff;
            color: #333;
            margin: 0;
            padding: 0;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .wrap {
            width: 100%;
            height: 100%;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .diploma-container {
            border: 4px solid #A45A6A;
            padding: 60px 50px;
            width: 90%;
            text-align: center;
            border-radius: 12px;
            page-break-inside: avoid;
        }

        .header-logo {
            margin-bottom: 8px;
        }

        .header-logo img {
            width: 110px;
        }

        .academy-name {
            font-size: 20px;
            font-weight: bold;
            color: #A45A6A;
        }

        .diploma-title {
            font-size: 24px;
            font-weight: bold;
            margin-top: 8px;
            letter-spacing: 2px;
        }

        .subtitle {
            font-size: 11px;
            margin-top: 4px;
            color: #777;
        }

        .student-label {
            margin-top: 24px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #555;
        }

        .student-name {
            font-size: 22px;
            font-weight: bold;
            margin-top: 8px;
            color: #333;
        }

        .course-text {
            margin-top: 16px;
            font-size: 12px;
            line-height: 1.5;
        }

        .highlight {
            font-weight: bold;
            color: #A45A6A;
        }

        .details {
            margin-top: 18px;
            font-size: 10px;
        }

        .details span {
            display: block;
            margin: 1px 0;
        }

        .fecha-emision {
            margin-top: 16px;
            font-size: 9.5px;
            color: #666;
        }

        .footer {
            margin-top: 30px;
            width: 100%;
            page-break-inside: avoid;
        }

        /* Fila de firmas bien alineadas */
        .signature-row {
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
        }

        .signature-block {
            flex: 1;
            text-align: center;
            padding: 0 40px;
        }

        .firma-spacer {
            height: 50px;
            /* mismo alto que la imagen de la firma */
            margin-bottom: 4px;
        }

        .firma-img {
            height: 50px;
            margin-bottom: 4px;
        }

        .signature-line {
            border-top: 1px solid #333;
            width: 75%;
            margin: 8px auto 4px auto;
        }

        .signature-name {
            font-size: 10px;
            font-weight: bold;
        }

        .signature-role {
            font-size: 9px;
            color: #555;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="diploma-container">
            <div class="header-logo">
                <?php if ($logoBase64): ?>
                    <img src="<?= $logoBase64 ?>" alt="TwinTalk English">
                <?php endif; ?>
            </div>

            <div class="academy-name">TwinTalk English Academy</div>
            <div class="diploma-title">Diploma de finalización</div>
            <div class="subtitle">Por la culminación satisfactoria de su curso de inglés</div>

            <div class="student-label">Se otorga el presente diploma a</div>
            <div class="student-name">
                <?= htmlspecialchars($nombreCompleto) ?>
            </div>

            <div class="course-text">
                por haber completado el curso
                <span class="highlight"><?= htmlspecialchars($cursoNombre) ?></span>
                correspondiente al nivel
                <span class="highlight"><?= htmlspecialchars($nivelCodigo) ?></span>,
                impartido en el período del
                <span class="highlight"><?= $fechaInicio ?></span>
                al
                <span class="highlight"><?= $fechaFin ?></span>.
            </div>

            <div class="details">
                <?php if ($notaFinal > 0): ?>
                    <span>Nota final obtenida: <strong><?= $notaFinalTexto ?></strong></span>
                <?php endif; ?>
                <span>Estudiante: <?= htmlspecialchars($nombreCompleto) ?>
                    (<?= htmlspecialchars($datos['est_email']) ?>)</span>
                <span>Docente responsable: <?= htmlspecialchars($docenteNombre) ?></span>
            </div>

            <div class="fecha-emision">
                Emitido en TwinTalk English Academy el día <?= $fechaEmision ?>.
            </div>

            <div class="footer">
                <table style="width:100%; margin-top:25px;">
                    <tr>
                        <!-- Columna izquierda: Docente -->
                        <td style="width:50%; text-align:center;">

                            <?php if ($docenteFirmaBase64): ?>
                                <img src="<?= $docenteFirmaBase64 ?>" class="firma-img">
                            <?php else: ?>
                                <div style="height:50px;"></div> <!-- mantiene alineación si no sube firma -->
                            <?php endif; ?>

                            <div class="signature-line"></div>
                            <div class="signature-name"><?= htmlspecialchars($docenteNombre) ?></div>
                            <div class="signature-role">Docente del curso</div>
                        </td>


                        <!-- Columna derecha: Dirección Académica -->
                        <td style="width:50%; text-align:center;">
                            <?php if ($firmaBase64): ?>
                                <img src="<?= $firmaBase64 ?>" class="firma-img">
                            <?php endif; ?>
                            <div class="signature-line"></div>
                            <div class="signature-name">Dirección Académica</div>
                            <div class="signature-role">TwinTalk English Academy</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
<?php
$html = ob_get_clean();

// 4. Generar PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); // horizontal tipo diploma
$dompdf->render();

$nombreArchivo = "Diploma_Matricula_" . $matricula_id . ".pdf";
$dompdf->stream($nombreArchivo, ["Attachment" => false]);
exit;
