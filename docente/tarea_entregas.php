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
    INNER JOIN cursos c   ON h.curso_id = c.id
    WHERE t.id = ? AND t.docente_id = ?
    LIMIT 1
";
$stmtT = $mysqli->prepare($sqlTarea);
$stmtT->bind_param("ii", $tarea_id, $docenteId);
$stmtT->execute();
$resT  = $stmtT->get_result();
$tarea = $resT->fetch_assoc();
$stmtT->close();

if (!$tarea) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger mt-4">No tienes acceso a esta tarea o no existe.</div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// 2) Guardar calificaciones (un form por entrega)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'calificar') {
    $entrega_id   = (int)($_POST['entrega_id'] ?? 0);
    $nota_txt     = trim($_POST['calificacion'] ?? '');
    $comentario   = trim($_POST['comentarios'] ?? '');
    $aplicarTodos = isset($_POST['aplicar_todos']) ? 1 : 0;

    if ($entrega_id <= 0 || $nota_txt === '') {
        $error = "Debes indicar una calificación válida.";
    } else {
        $nota = (float)$nota_txt;

        // 2.1) Actualizar la entrega seleccionada, validando que pertenezca a este docente
        $sqlUpd = "
            UPDATE tareas_entregas te
            INNER JOIN tareas t ON te.tarea_id = t.id
            SET te.calificacion       = ?,
                te.comentarios_docente = ?,
                te.fecha_calificacion  = NOW()
            WHERE te.id = ? 
              AND te.tarea_id = ? 
              AND t.docente_id = ?
        ";
        $stmtU = $mysqli->prepare($sqlUpd);
        $stmtU->bind_param("dsdii", $nota, $comentario, $entrega_id, $tarea_id, $docenteId);

        if ($stmtU->execute()) {
            $mensaje = "Calificación guardada correctamente.";

            // 2.2) Si la tarea es de grupo y se marcó "aplicar a todos"
            if ($aplicarTodos && $tarea['modalidad'] === 'grupo') {
                $sqlUpdAll = "
                    UPDATE tareas_entregas te
                    INNER JOIN tareas t ON te.tarea_id = t.id
                    SET te.calificacion       = ?,
                        te.comentarios_docente = ?,
                        te.fecha_calificacion  = NOW()
                    WHERE te.tarea_id = ?
                      AND t.docente_id = ?
                      AND te.id <> ?
                ";
                $stmtAll = $mysqli->prepare($sqlUpdAll);
                $stmtAll->bind_param("dsdii", $nota, $comentario, $tarea_id, $docenteId, $entrega_id);
                $stmtAll->execute();
                $stmtAll->close();
            }
        } else {
            $error = "Error al guardar la calificación: " . $stmtU->error;
        }
        $stmtU->close();
    }
}

// 3) Listar entregas de la tarea
$sqlEnt = "
    SELECT 
        te.id              AS entrega_id,
        te.archivo_url,
        te.fecha_entrega,
        te.calificacion,
        te.comentarios_docente,
        u.nombre,
        u.apellido,
        u.email
    FROM tareas_entregas te
    INNER JOIN matriculas m ON te.matricula_id = m.id
    INNER JOIN estudiantes e ON m.estudiante_id = e.id
    INNER JOIN usuarios u    ON e.id = u.id
    WHERE te.tarea_id = ?
    ORDER BY te.fecha_entrega ASC
";
$stmtE = $mysqli->prepare($sqlEnt);
$stmtE->bind_param("i", $tarea_id);
$stmtE->execute();
$entregas = $stmtE->get_result();
$stmtE->close();

// 4) Estudiantes matriculados en ese horario que NO han entregado
$sqlNo = "
    SELECT 
        m.id AS matricula_id,
        u.nombre,
        u.apellido,
        u.email
    FROM matriculas m
    INNER JOIN estudiantes e ON m.estudiante_id = e.id
    INNER JOIN usuarios u    ON e.id = u.id
    WHERE m.horario_id = ?
      AND m.estado_id = 1
      AND NOT EXISTS (
          SELECT 1 
          FROM tareas_entregas te
          WHERE te.matricula_id = m.id
            AND te.tarea_id = ?
      )
    ORDER BY u.apellido, u.nombre
";
$stmtNo = $mysqli->prepare($sqlNo);
$stmtNo->bind_param("ii", $tarea['horario_id'], $tarea_id);
$stmtNo->execute();
$noEntregas = $stmtNo->get_result();
$stmtNo->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1">
                Entregas de tarea
            </h1>
            <p class="text-muted mb-0">
                Curso: <strong><?= htmlspecialchars($tarea['nombre_curso']) ?></strong> |
                Modalidad: 
                <span class="badge bg-primary">
                    <?= htmlspecialchars($tarea['modalidad']) ?>
                </span>
            </p>
        </div>
        <a href="/twintalk/docente/tareas.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Volver a tareas
        </a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Info de la tarea -->
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title mb-1"><?= htmlspecialchars($tarea['titulo']) ?></h5>
            <?php if (!empty($tarea['descripcion'])): ?>
                <p class="mb-2"><?= nl2br(htmlspecialchars($tarea['descripcion'])) ?></p>
            <?php endif; ?>

            <?php if (!empty($tarea['fecha_entrega'])): ?>
                <p class="mb-1">
                    Fecha de entrega: 
                    <strong><?= htmlspecialchars($tarea['fecha_entrega']) ?></strong>
                </p>
            <?php endif; ?>

            <?php if (!empty($tarea['archivo_instrucciones'])): ?>
                <p class="mb-0">
                    Archivo de instrucciones:
                    <a href="<?= htmlspecialchars($tarea['archivo_instrucciones']) ?>" target="_blank">
                        Ver archivo
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Entregas -->
    <h5 class="mb-2">Entregas de los estudiantes</h5>

    <div class="card mb-4">
        <div class="card-body p-0">
            <?php if ($entregas->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Estudiante</th>
                                <th>Email</th>
                                <th>Archivo</th>
                                <th>Fecha entrega</th>
                                <th>Calificación</th>
                                <th>Comentarios</th>
                                <th style="width: 220px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($e = $entregas->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?></td>
                                    <td class="small"><?= htmlspecialchars($e['email']) ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($e['archivo_url']) ?>" target="_blank" class="btn btn-link btn-sm">
                                            Ver archivo
                                        </a>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($e['fecha_entrega']) ?></td>
                                    <td>
                                        <?php if ($e['calificacion'] !== null): ?>
                                            <span class="badge bg-success">
                                                <?= htmlspecialchars($e['calificacion']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sin nota</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?= nl2br(htmlspecialchars($e['comentarios_docente'] ?? '')) ?>
                                    </td>
                                    <td>
                                        <form method="post" class="row g-1 align-items-center">
                                            <input type="hidden" name="accion" value="calificar">
                                            <input type="hidden" name="entrega_id" value="<?= (int)$e['entrega_id'] ?>">

                                            <div class="col-4">
                                                <input type="number"
                                                       name="calificacion"
                                                       step="0.01"
                                                       min="0"
                                                       max="100"
                                                       class="form-control form-control-sm"
                                                       value="<?= htmlspecialchars($e['calificacion'] ?? '') ?>"
                                                       placeholder="Nota" required>
                                            </div>
                                            <div class="col-5">
                                                <input type="text"
                                                       name="comentarios"
                                                       class="form-control form-control-sm"
                                                       placeholder="Comentario"
                                                       value="<?= htmlspecialchars($e['comentarios_docente'] ?? '') ?>">
                                            </div>
                                            <div class="col-3 d-flex flex-column gap-1">
                                                <?php if ($tarea['modalidad'] === 'grupo'): ?>
                                                    <div class="form-check mb-1">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="aplicar_todos" 
                                                               id="aplicarTodos<?= (int)$e['entrega_id'] ?>">
                                                        <label class="form-check-label small" for="aplicarTodos<?= (int)$e['entrega_id'] ?>">
                                                            Nota a todos
                                                        </label>
                                                    </div>
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    Guardar
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3">
                    <span class="text-muted">No hay entregas registradas aún.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Estudiantes sin entrega -->
    <h5 class="mb-2">Estudiantes sin entrega</h5>
    <div class="card">
        <div class="card-body p-0">
            <?php if ($noEntregas->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Estudiante</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ne = $noEntregas->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ne['nombre'] . ' ' . $ne['apellido']) ?></td>
                                    <td class="small"><?= htmlspecialchars($ne['email']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-3">
                    <span class="text-muted">Todos los estudiantes han enviado la tarea.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
