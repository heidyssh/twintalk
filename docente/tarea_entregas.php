<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]);

$docenteId = $_SESSION['usuario_id'] ?? null;
if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$tarea_id = isset($_GET['tarea_id']) ? (int) $_GET['tarea_id'] : 0;
if ($tarea_id <= 0) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger mt-4">Tarea no válida.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$mensaje = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'calificar') {
    $matricula_id = (int) ($_POST['matricula_id'] ?? 0);
    $calificacion = $_POST['calificacion'] !== '' ? floatval($_POST['calificacion']) : null;
    $comentarios = trim($_POST['comentarios'] ?? '');


    $sqlCheck = "SELECT id FROM tareas_entregas WHERE tarea_id = ? AND matricula_id = ? LIMIT 1";
    $stmtC = $mysqli->prepare($sqlCheck);
    $stmtC->bind_param("ii", $tarea_id, $matricula_id);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    $existe = $resC->fetch_assoc();
    $stmtC->close();

    if ($existe) {

        $sqlU = "
            UPDATE tareas_entregas
            SET calificacion = ?, comentarios_docente = ?, fecha_calificacion = NOW()
            WHERE id = ?
        ";
        $stmtU = $mysqli->prepare($sqlU);


        $entregaId = (int) $existe['id'];

        $stmtU->bind_param("dsi", $calificacion, $comentarios, $entregaId);
        $stmtU->execute();
        $stmtU->close();

    } else {

        $sqlI = "
            INSERT INTO tareas_entregas (
                tarea_id,
                matricula_id,
                archivo_url,
                calificacion,
                comentarios_docente,
                fecha_calificacion
            )
            VALUES (?, ?, '', ?, ?, NOW())
        ";
        $stmtI = $mysqli->prepare($sqlI);
        $stmtI->bind_param("iids", $tarea_id, $matricula_id, $calificacion, $comentarios);
        $stmtI->execute();
        $stmtI->close();
    }

    $mensaje = "Calificación guardada correctamente.";

}

$sqlTarea = "
    SELECT t.*, c.nombre_curso, h.hora_inicio, h.hora_fin
    FROM tareas t
    INNER JOIN horarios h ON t.horario_id = h.id
    INNER JOIN cursos c ON h.curso_id = c.id
    WHERE t.id = ?
";
$stmtT = $mysqli->prepare($sqlTarea);
$stmtT->bind_param("i", $tarea_id);
$stmtT->execute();
$tarea = $stmtT->get_result()->fetch_assoc();
$stmtT->close();

if (!$tarea) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger mt-4">La tarea no existe.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$sqlAlumnos = "
    SELECT 
        m.id AS matricula_id,
        u.nombre,
        u.apellido,
        u.email,
        te.id AS entrega_id,
        te.archivo_url,
        te.fecha_entrega,
        te.calificacion,
        te.comentarios_docente
    FROM matriculas m
    INNER JOIN usuarios u ON m.estudiante_id = u.id
    LEFT JOIN tareas_entregas te 
        ON te.matricula_id = m.id 
       AND te.tarea_id = ?
    WHERE m.horario_id = ?
    ORDER BY u.nombre ASC, u.apellido ASC
";
$stmtA = $mysqli->prepare($sqlAlumnos);
$horarioId = (int) $tarea['horario_id'];
$stmtA->bind_param("ii", $tarea_id, $horarioId);
$stmtA->execute();
$alumnos = $stmtA->get_result();
$stmtA->close();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 fw-bold mt-3">Entregas de Tarea</h1>

<p class="text-muted mb-3">
    <strong><?= htmlspecialchars($tarea['titulo']) ?></strong><br>
    Curso: <?= htmlspecialchars($tarea['nombre_curso']) ?><br>
    Hora: <?= substr($tarea['hora_inicio'], 0, 5) ?> - <?= substr($tarea['hora_fin'], 0, 5) ?>
</p>

<?php if ($mensaje): ?>
    <div class="alert alert-success small"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<div class="card card-soft">
    <div class="card-header bg-white border-bottom py-2 px-3">
        <strong>Lista de alumnos</strong>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Correo</th>
                        <th>Archivo</th>
                        <th>Entrega</th>
                        <th>Calificación</th>
                    </tr>
                </thead>
                <tbody>

                    <?php while ($a = $alumnos->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['nombre'] . " " . $a['apellido']) ?></td>
                            <td><?= htmlspecialchars($a['email']) ?></td>

                            <td>
                                <?php if (!empty($a['archivo_url'])): ?>
                                    <a href="<?= $a['archivo_url'] ?>" target="_blank" class="small">
                                        Ver archivo
                                    </a>
                                <?php else: ?>
                                    <span class="text-danger fw-semibold">No entregó</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($a['fecha_entrega'])): ?>
                                    <span class="small text-muted">
                                        <?= date('d/m/Y H:i', strtotime($a['fecha_entrega'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="small text-danger">---</span>
                                <?php endif; ?>
                            </td>

                            <td style="min-width:200px;">
                                <form method="post">
                                    <input type="hidden" name="accion" value="calificar">
                                    <input type="hidden" name="matricula_id" value="<?= $a['matricula_id'] ?>">

                                    <input type="number" name="calificacion" step="0.01" min="0" max="100"
                                        value="<?= $a['calificacion'] ?>" class="form-control form-control-sm mb-1">

                                    <textarea name="comentarios" class="form-control form-control-sm mb-1"
                                        placeholder="Comentario..."><?= htmlspecialchars($a['comentarios_docente']) ?></textarea>

                                    <button class="btn btn-sm btn-primary w-100">
                                        Guardar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                </tbody>
            </table>
        </div>
    </div>
</div>

<a href="tareas.php?horario_id=<?= $tarea['horario_id'] ?>" class="btn btn-link px-0 mt-3">
    ‹ Volver a tareas
</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>