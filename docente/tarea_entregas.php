<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); // Docente

$docenteId = $_SESSION['usuario_id'] ?? null;
if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$tarea_id = isset($_GET['tarea_id']) ? (int)$_GET['tarea_id'] : 0;
if ($tarea_id <= 0) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger mt-4">Tarea no válida.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$mensaje = "";
$error   = "";

// 1) Cargar info de la tarea y validar que sea de este docente
$sqlTarea = "
    SELECT t.*, c.nombre_curso, h.id AS horario_id
    FROM tareas t
    INNER JOIN horarios h ON t.horario_id = h.id
    INNER JOIN cursos c ON h.curso_id = c.id
    WHERE t.id = ? AND t.docente_id = ?
    LIMIT 1
";
$stmtT = $mysqli->prepare($sqlTarea);
$stmtT->bind_param("ii", $tarea_id, $docenteId);
$stmtT->execute();
$resT = $stmtT->get_result();
$tarea = $resT->fetch_assoc();
$stmtT->close();

if (!$tarea) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger mt-4">No se encontró la tarea o no te pertenece.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// 2) Guardar calificaciones (un form por entrega)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'calificar') {
    $entrega_id  = (int)($_POST['entrega_id'] ?? 0);
    $nota        = trim($_POST['calificacion'] ?? '');
    $comentario  = trim($_POST['comentarios_docente'] ?? '');

    if ($entrega_id > 0) {
        $sqlUpd = "
            UPDATE tareas_entregas
            SET calificacion = ?, comentarios_docente = ?, fecha_calificacion = NOW()
            WHERE id = ?
        ";
        $stmtU = $mysqli->prepare($sqlUpd);
        $nota_param = ($nota === '') ? null : $nota;
        $stmtU->bind_param("dsi", $nota_param, $comentario, $entrega_id);
        if ($stmtU->execute()) {
            $mensaje = "Calificación guardada.";
        } else {
            $error = "Error al guardar la calificación: " . $stmtU->error;
        }
        $stmtU->close();
    }
}

// 3) Listar entregas de la tarea
$sqlEnt = "
    SELECT 
        te.*,
        u.nombre,
        u.apellido,
        u.email
    FROM tareas_entregas te
    INNER JOIN matriculas m ON te.matricula_id = m.id
    INNER JOIN estudiantes e ON m.estudiante_id = e.id
    INNER JOIN usuarios u ON e.id = u.id
    WHERE te.tarea_id = ?
    ORDER BY te.fecha_entrega ASC
";
$stmtE = $mysqli->prepare($sqlEnt);
$stmtE->bind_param("i", $tarea_id);
$stmtE->execute();
$entregas = $stmtE->get_result();
$stmtE->close();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 fw-bold mt-3">Entregas de la tarea</h1>

<a href="tareas.php?horario_id=<?= $tarea['horario_id'] ?>" class="btn btn-sm btn-secondary mb-3">
    &larr; Volver a tareas
</a>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title mb-1"><?= htmlspecialchars($tarea['titulo']) ?></h5>
        <p class="mb-1 text-muted">
            Curso: <?= htmlspecialchars($tarea['nombre_curso']) ?>
        </p>
        <?php if ($tarea['fecha_entrega']): ?>
            <p class="mb-1">
                Fecha de entrega: <strong><?= htmlspecialchars($tarea['fecha_entrega']) ?></strong>
            </p>
        <?php endif; ?>
        <?php if ($tarea['archivo_instrucciones']): ?>
            <p class="mb-0">
                Archivo de instrucciones:
                <a href="<?= htmlspecialchars($tarea['archivo_instrucciones']) ?>" target="_blank">Ver</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<h5>Entregas de los estudiantes</h5>

<table class="table table-striped table-sm align-middle">
    <thead>
        <tr>
            <th>Estudiante</th>
            <th>Archivo</th>
            <th>Fecha entrega</th>
            <th>Nota</th>
            <th>Comentarios</th>
            <th>Guardar</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($entregas->num_rows === 0): ?>
            <tr><td colspan="6" class="text-muted">Aún no hay entregas para esta tarea.</td></tr>
        <?php else: ?>
            <?php while ($e = $entregas->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?></strong><br>
                        <span class="small text-muted"><?= htmlspecialchars($e['email']) ?></span>
                    </td>
                    <td>
                        <a href="<?= htmlspecialchars($e['archivo_url']) ?>" target="_blank">
                            Ver archivo
                        </a><br>
                        <small class="text-muted">
                            <?= number_format(($e['tamano_archivo'] ?? 0) / 1024, 2) ?> KB
                        </small>
                    </td>
                    <td><?= htmlspecialchars($e['fecha_entrega']) ?></td>
                    <td>
                        <form method="post" class="d-flex flex-column gap-1">
                            <input type="hidden" name="accion" value="calificar">
                            <input type="hidden" name="entrega_id" value="<?= $e['id'] ?>">
                            <input type="number" step="0.01" name="calificacion"
                                   value="<?= htmlspecialchars($e['calificacion']) ?>"
                                   class="form-control form-control-sm" placeholder="Nota">
                    </td>
                    <td>
                            <textarea name="comentarios_docente" rows="2"
                                      class="form-control form-control-sm"
                                      placeholder="Comentario opcional"><?= htmlspecialchars($e['comentarios_docente']) ?></textarea>
                    </td>
                    <td>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Guardar
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
