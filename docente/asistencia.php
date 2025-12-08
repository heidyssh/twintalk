<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); // solo docentes

$docente_id = $_SESSION['usuario_id'] ?? 0;
if ($docente_id <= 0) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error   = "";

// -----------------------------
// 1) Obtener horarios del docente
// -----------------------------
$stmtHor = $mysqli->prepare("
    SELECT 
        h.id,
        c.nombre_curso,
        ds.nombre_dia,
        ds.numero_dia,
        h.hora_inicio,
        h.hora_fin,
        h.aula,
        h.fecha_inicio,
        h.fecha_fin
    FROM horarios h
    INNER JOIN cursos c       ON h.curso_id = c.id
    INNER JOIN dias_semana ds ON h.dia_semana_id = ds.id
    WHERE h.docente_id = ?
      AND h.activo = 1
    ORDER BY ds.numero_dia, h.hora_inicio
");
$stmtHor->bind_param("i", $docente_id);
$stmtHor->execute();
$resHorarios = $stmtHor->get_result();

$horarios_docente = [];
while ($row = $resHorarios->fetch_assoc()) {
    $horarios_docente[] = $row;
}
$stmtHor->close();

// Si no tiene horarios
if (empty($horarios_docente)) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container py-4">
            <div class="alert alert-warning">
                No tienes horarios asignados actualmente.
            </div>
          </div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// -----------------------------
// 2) Horario seleccionado
// -----------------------------
$horario_id_seleccionado = isset($_REQUEST['horario_id'])
    ? (int)$_REQUEST['horario_id']
    : (int)$horarios_docente[0]['id'];

$horario_valido = false;
$horario_actual = $horarios_docente[0];

foreach ($horarios_docente as $h) {
    if ((int)$h['id'] === $horario_id_seleccionado) {
        $horario_valido = true;
        $horario_actual = $h;
        break;
    }
}

if (!$horario_valido) {
    $error = "Horario no válido. Se seleccionó el primer horario disponible.";
    $horario_id_seleccionado = (int)$horarios_docente[0]['id'];
    $horario_actual = $horarios_docente[0];
}

// -----------------------------
// 3) Generar LISTA DE FECHAS DE CLASE (solo días de clase)
// -----------------------------
$lista_fechas = [];

try {
    $inicio   = new DateTime($horario_actual['fecha_inicio']);
    $fin      = new DateTime($horario_actual['fecha_fin']);
    $numero_dia_clase = (int)$horario_actual['numero_dia']; // 1=lunes..7=domingo

    for ($d = clone $inicio; $d <= $fin; $d->modify('+1 day')) {
        if ((int)$d->format('N') === $numero_dia_clase) {
            $lista_fechas[] = $d->format('Y-m-d');
        }
    }
} catch (Exception $e) {
    $error = "Error al generar el calendario de asistencia.";
}

if (empty($lista_fechas)) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container py-4">
            <div class="alert alert-danger">
                No se pudo generar el calendario de asistencia para este horario.
            </div>
          </div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// -----------------------------
// 4) Determinar FECHA seleccionada
//    - Siempre debe ser una de las fechas de clase
// -----------------------------
$hoy = date('Y-m-d');
$fecha_clase = null;

if (isset($_REQUEST['fecha_clase']) && in_array($_REQUEST['fecha_clase'], $lista_fechas, true)) {
    $fecha_clase = $_REQUEST['fecha_clase'];
} else {
    // Por defecto, si hay alguna fecha <= hoy, usamos la última (la más reciente);
    // si todas son futuras, usamos la primera.
    foreach ($lista_fechas as $f) {
        if ($f <= $hoy) {
            $fecha_clase = $f;
        }
    }
    if ($fecha_clase === null) {
        $fecha_clase = $lista_fechas[0];
    }
}

// -----------------------------
// 5) Guardar asistencia (para la fecha seleccionada)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_asistencia') {

    $fecha_post = $_POST['fecha_clase'] ?? '';
    // Seguridad: solo aceptar fechas que pertenezcan a la lista de fechas de clase
    if (!in_array($fecha_post, $lista_fechas, true)) {
        $error = "La fecha seleccionada no es válida para este horario.";
    } else {
        $fecha_clase = $fecha_post;

        // Obtener las matrículas del horario
        $stmtMat = $mysqli->prepare("
            SELECT 
                m.id AS matricula_id
            FROM matriculas m
            INNER JOIN estados_matricula em ON m.estado_id = em.id
            WHERE m.horario_id = ?
              AND em.nombre_estado <> 'Cancelada'
        ");
        $stmtMat->bind_param("i", $horario_id_seleccionado);
        $stmtMat->execute();
        $resMat = $stmtMat->get_result();

        $matriculas_ids = [];
        while ($row = $resMat->fetch_assoc()) {
            $matriculas_ids[] = (int)$row['matricula_id'];
        }
        $stmtMat->close();

        $presentes = $_POST['presente'] ?? [];

        $stmtAsis = $mysqli->prepare("
            INSERT INTO asistencia (matricula_id, fecha_clase, presente, observaciones)
            VALUES (?, ?, ?, NULL)
            ON DUPLICATE KEY UPDATE
                presente = VALUES(presente),
                fecha_registro = CURRENT_TIMESTAMP()
        ");

        foreach ($matriculas_ids as $mat_id) {
            // Si no está marcado el checkbox, lo consideramos FALTA (0)
            $presente = isset($presentes[$mat_id]) ? 1 : 0;
            $stmtAsis->bind_param("isi", $mat_id, $fecha_clase, $presente);
            $stmtAsis->execute();
        }
        $stmtAsis->close();

        $mensaje = "Asistencia guardada correctamente para la fecha $fecha_clase.";
    }
}

// -----------------------------
// 6) Cargar asistencia del día seleccionado + estudiantes
// -----------------------------
$asistencia_map = [];
$estudiantes    = [];

// Asistencia ya registrada para esa fecha
$stmtAsisDia = $mysqli->prepare("
    SELECT 
        matricula_id,
        presente
    FROM asistencia
    WHERE fecha_clase = ?
");
$stmtAsisDia->bind_param("s", $fecha_clase);
$stmtAsisDia->execute();
$resAsisDia = $stmtAsisDia->get_result();
while ($row = $resAsisDia->fetch_assoc()) {
    $asistencia_map[(int)$row['matricula_id']] = (int)$row['presente'];
}
$stmtAsisDia->close();

// Estudiantes del horario
$stmtEst = $mysqli->prepare("
    SELECT 
        m.id AS matricula_id,
        u.nombre,
        u.apellido,
        u.email
    FROM matriculas m
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    INNER JOIN estudiantes e       ON m.estudiante_id = e.id
    INNER JOIN usuarios u          ON e.id = u.id
    WHERE m.horario_id = ?
      AND em.nombre_estado <> 'Cancelada'
    ORDER BY u.apellido, u.nombre
");
$stmtEst->bind_param("i", $horario_id_seleccionado);
$stmtEst->execute();
$resEst = $stmtEst->get_result();
while ($row = $resEst->fetch_assoc()) {
    $estudiantes[] = $row;
}
$stmtEst->close();

// -----------------------------
// 7) Resumen de asistencias / faltas por alumno (REGLA 7 FALTAS)
// -----------------------------
$resumen = [];

$stmtResumen = $mysqli->prepare("
    SELECT 
        m.id AS matricula_id,
        u.nombre,
        u.apellido,
        u.email,
        SUM(CASE WHEN a.presente = 1 THEN 1 ELSE 0 END) AS asistencias,
        SUM(CASE WHEN a.presente = 0 THEN 1 ELSE 0 END) AS faltas
    FROM matriculas m
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    INNER JOIN estudiantes e ON m.estudiante_id = e.id
    INNER JOIN usuarios u    ON e.id = u.id
    LEFT JOIN asistencia a   ON a.matricula_id = m.id
    WHERE m.horario_id = ?
      AND em.nombre_estado = 'Activa'
    GROUP BY m.id, u.nombre, u.apellido, u.email
    ORDER BY u.apellido, u.nombre
");

$stmtResumen->bind_param("i", $horario_id_seleccionado);
$stmtResumen->execute();
$resRes = $stmtResumen->get_result();
while ($row = $resRes->fetch_assoc()) {
    $faltas = (int)$row['faltas'];
    $estado_examen = ($faltas >= 7)
        ? "SIN derecho a exámenes"
        : "Con derecho a exámenes";

    $row['estado_examen'] = $estado_examen;
    $row['faltas']        = $faltas;
    $resumen[] = $row;
}
$stmtResumen->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">

    <!-- Encabezado estilo TwinTalk -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
             style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">
            <div>
                <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                    Registro de asistencia
                </h1>
                <small class="text-muted">
                    Marca los estudiantes presentes en la clase. <br>
                    Este curso se imparte los 
                    <strong><?= htmlspecialchars($horario_actual['nombre_dia']) ?></strong>
                    del <?= htmlspecialchars($horario_actual['fecha_inicio']) ?> al <?= htmlspecialchars($horario_actual['fecha_fin']) ?>.
                </small>
            </div>
            <div class="text-md-end">
                <span class="badge rounded-pill text-bg-light border">
                    Docente
                </span>
            </div>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success border-0 shadow-sm mb-3">
            <?= $mensaje ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Filtros en tarjeta -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 fw-semibold mb-3">Filtros de asistencia</h2>
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small text-muted">Horario</label>
                    <select name="horario_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($horarios_docente as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= ($h['id'] == $horario_id_seleccionado ? 'selected' : '') ?>>
                                <?= htmlspecialchars($h['nombre_curso']) ?>
                                (<?= htmlspecialchars($h['nombre_dia']) ?>
                                <?= substr($h['hora_inicio'],0,5) ?> - <?= substr($h['hora_fin'],0,5) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted">Fecha de la clase</label>
                    <select name="fecha_clase" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($lista_fechas as $f): 
                            $texto = date('d/m/Y', strtotime($f));
                            if ($f === $hoy) {
                                $texto .= " (Hoy)";
                            }
                        ?>
                            <option value="<?= $f ?>" <?= ($f === $fecha_clase ? 'selected' : '') ?>>
                                <?= $texto ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de asistencia de la FECHA seleccionada -->
    <form method="post">
        <input type="hidden" name="accion" value="guardar_asistencia">
        <input type="hidden" name="horario_id" value="<?= $horario_id_seleccionado ?>">
        <input type="hidden" name="fecha_clase" value="<?= $fecha_clase ?>">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center"
                 style="background:#fdf3f7;">
                <span class="fw-semibold" style="color:#b14f72;">
                    Lista de estudiantes – 
                    <?= htmlspecialchars($horario_actual['nombre_curso']) ?> 
                    (<?= htmlspecialchars($horario_actual['nombre_dia']) ?>)
                </span>
                <small class="text-muted">
                    Fecha de clase: <?= $fecha_clase ?>
                </small>
            </div>

            <div class="card-body p-0">
                <table class="table table-sm mb-0 table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Estudiante</th>
                            <th>Correo</th>
                            <th class="text-center" style="width:120px;">Presente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($estudiantes as $est): 
                            $mat = $est['matricula_id'];
                            // Si no hay registro previo, por defecto lo mostramos sin marcar
                            $presente = $asistencia_map[$mat] ?? null;
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($est['nombre'].' '.$est['apellido']) ?></td>
                            <td><?= htmlspecialchars($est['email']) ?></td>
                            <td class="text-center">
                                <input 
                                    type="checkbox" 
                                    class="form-check-input"
                                    name="presente[<?= $mat ?>]" 
                                    <?= ($presente === 1 ? 'checked' : '') ?>
                                >
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($estudiantes)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">
                                No hay estudiantes matriculados en este horario.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer text-end">
                <button type="submit" 
                        class="btn btn-sm shadow-sm"
                        style="background:#b14f72; color:#fff; border-radius:8px; border:none;">
                    Guardar asistencia
                </button>
            </div>
        </div>
    </form>

    <!-- Resumen de faltas y derecho a exámenes -->
    <div class="card border-0 shadow-sm">
        <div class="card-header" style="background:#fdf3f7;">
            <span class="fw-semibold" style="color:#b14f72;">
                Resumen de asistencias / faltas – Regla de 7 faltas
            </span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Estudiante</th>
                        <th>Correo</th>
                        <th class="text-center">Asistencias</th>
                        <th class="text-center">Faltas</th>
                        <th class="text-center">Estado para exámenes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $j = 1; foreach ($resumen as $r): ?>
                        <tr>
                            <td><?= $j++ ?></td>
                            <td><?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td class="text-center"><?= (int)$r['asistencias'] ?></td>
                            <td class="text-center"><?= (int)$r['faltas'] ?></td>
                            <td class="text-center">
                                <?php if ($r['faltas'] >= 7): ?>
                                    <span class="badge rounded-pill" style="background:#f44336;">
                                        SIN derecho a exámenes
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill" style="background:#4caf50;">
                                        Con derecho a exámenes
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($resumen)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">
                                No hay registros de asistencia para este horario aún.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
