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
        h.hora_inicio,
        h.hora_fin,
        h.aula
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
// 2) Horario seleccionado y fecha
// -----------------------------
$horario_id_seleccionado = isset($_REQUEST['horario_id']) ? (int)$_REQUEST['horario_id'] : (int)$horarios_docente[0]['id'];
$fecha_clase             = isset($_REQUEST['fecha_clase']) ? $_REQUEST['fecha_clase'] : date('Y-m-d');

$horario_valido = false;
foreach ($horarios_docente as $h) {
    if ((int)$h['id'] === $horario_id_seleccionado) {
        $horario_valido = true;
        break;
    }
}

if (!$horario_valido) {
    $error = "Horario no válido.";
    $horario_id_seleccionado = (int)$horarios_docente[0]['id'];
}

// -----------------------------
// 3) Guardar asistencia
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_asistencia') {

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
        $presente = isset($presentes[$mat_id]) ? 1 : 0;
        $stmtAsis->bind_param("isi", $mat_id, $fecha_clase, $presente);
        $stmtAsis->execute();
    }

    $stmtAsis->close();

    $mensaje = "Asistencia guardada correctamente.";
}

// -----------------------------
// 4) Cargar asistencia y alumnos
// -----------------------------
$asistencia_map = [];
$estudiantes = [];

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

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">

    <h3 class="mb-1">Registro de asistencia</h3>
    <p class="text-muted">Marca los estudiantes presentes en la clase.</p>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= $mensaje ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- Filtros -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label">Horario</label>
            <select name="horario_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($horarios_docente as $h): ?>
                    <option value="<?= $h['id'] ?>" <?= ($h['id'] == $horario_id_seleccionado ? 'selected' : '') ?>>
                        <?= $h['nombre_curso'] ?> (<?= $h['nombre_dia'] ?> <?= substr($h['hora_inicio'],0,5) ?> - <?= substr($h['hora_fin'],0,5) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Fecha de la clase</label>
            <input type="date" name="fecha_clase" class="form-control" value="<?= $fecha_clase ?>" onchange="this.form.submit()">
        </div>

        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-outline-secondary w-100">
                <i class="fa-solid fa-rotate"></i> Actualizar
            </button>
        </div>
    </form>

    <!-- Lista de alumnos -->
    <form method="post">
        <input type="hidden" name="accion" value="guardar_asistencia">
        <input type="hidden" name="horario_id" value="<?= $horario_id_seleccionado ?>">
        <input type="hidden" name="fecha_clase" value="<?= $fecha_clase ?>">

        <div class="card shadow-sm">
            <div class="card-header">
                <i class="fa-solid fa-user-check"></i> Lista de estudiantes
            </div>

            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Estudiante</th>
                            <th>Correo</th>
                            <th class="text-center">Presente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($estudiantes as $est): 
                            $mat = $est['matricula_id'];
                            $presente = $asistencia_map[$mat] ?? 0;
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= $est['nombre'].' '.$est['apellido'] ?></td>
                            <td><?= $est['email'] ?></td>
                            <td class="text-center">
                                <input 
                                    type="checkbox" 
                                    name="presente[<?= $mat ?>]" 
                                    <?= $presente ? 'checked' : '' ?>
                                >
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ÚNICO BOTÓN -->
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Guardar asistencia
                </button>
            </div>
        </div>
    </form>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
