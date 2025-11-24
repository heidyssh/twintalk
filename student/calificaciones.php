<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); // solo estudiantes

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if (!$usuario_id) {
    header("Location: /twintalk/login.php");
    exit;
}

// Traer tareas + entregas + cursos del estudiante
$stmt = $mysqli->prepare("
    SELECT 
        t.id AS tarea_id,
        t.titulo,
        t.descripcion,
        t.fecha_entrega,
        c.nombre_curso,
        h.id AS horario_id,
        m.id AS matricula_id,
        te.id AS entrega_id,
        te.fecha_entrega AS fecha_entrega_real,
        te.calificacion,
        te.comentarios_docente
    FROM matriculas m
    JOIN horarios h ON m.horario_id = h.id
    JOIN cursos c   ON h.curso_id = c.id
    JOIN tareas t   ON t.horario_id = h.id AND t.activo = 1
    LEFT JOIN tareas_entregas te
           ON te.tarea_id = t.id
          AND te.matricula_id = m.id
    WHERE m.estudiante_id = ?
      AND m.estado_id IN (1,2)
    ORDER BY c.nombre_curso, t.fecha_entrega IS NULL, t.fecha_entrega
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$tareas = [];
while ($row = $result->fetch_assoc()) {
    $tareas[] = $row;
}
$stmt->close();

// Calcular promedio de tareas calificadas
$sumaNotas = 0;
$conteoNotas = 0;
foreach ($tareas as $t) {
    if ($t['calificacion'] !== null) {
        $sumaNotas += (float)$t['calificacion'];
        $conteoNotas++;
    }
}
$promedio = $conteoNotas > 0 ? $sumaNotas / $conteoNotas : null;

include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">
    <h1 class="h3 mb-3">
        <i class="fa-solid fa-chart-line me-2"></i>
        Mis calificaciones de tareas
    </h1>
    <p class="text-muted mb-4">
        Aquí puedes ver cómo vas en cada tarea y tu promedio general de las tareas que ya fueron calificadas.
    </p>

    <?php if (empty($tareas)): ?>
        <div class="alert alert-info">
            Aún no tienes tareas registradas para mostrar calificaciones.
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Promedio general de tareas</h5>
                    <?php if ($promedio !== null): ?>
                        <p class="mb-0 fs-4 fw-bold text-primary">
                            <?= number_format($promedio, 2) ?>
                        </p>
                        <small class="text-muted">
                            Basado en <?= $conteoNotas ?> tarea(s) calificadas.
                        </small>
                    <?php else: ?>
                        <p class="mb-0 text-muted">
                            Aún no tienes tareas calificadas.
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <i class="fa-solid fa-star fs-1 text-warning"></i>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    Detalle por tarea
                </h5>
                <div class="table-responsive small">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Curso</th>
                                <th>Tarea</th>
                                <th>Fecha límite</th>
                                <th>Fecha entrega</th>
                                <th>Estado</th>
                                <th>Nota</th>
                                <th>Comentario docente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tareas as $t): 
                                $fechaLimite  = $t['fecha_entrega'] 
                                    ? date("d/m/Y", strtotime($t['fecha_entrega'])) 
                                    : "Sin fecha";
                                $fechaEntReal = $t['fecha_entrega_real'] 
                                    ? date("d/m/Y H:i", strtotime($t['fecha_entrega_real'])) 
                                    : "-";

                                $entregada = !empty($t['entrega_id']);

                                if ($entregada && $t['calificacion'] !== null) {
                                    $estado = "Entregada y calificada";
                                    $estadoClass = "text-success";
                                } elseif ($entregada) {
                                    $estado = "Entregada (sin nota)";
                                    $estadoClass = "text-primary";
                                } else {
                                    $estado = "No entregada";
                                    $estadoClass = "text-danger";
                                }

                                $nota = $t['calificacion'] !== null
                                    ? number_format($t['calificacion'], 2)
                                    : "-";
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($t['nombre_curso']) ?></td>
                                    <td><?= htmlspecialchars($t['titulo']) ?></td>
                                    <td><?= $fechaLimite ?></td>
                                    <td><?= $fechaEntReal ?></td>
                                    <td class="<?= $estadoClass ?>">
                                        <?= htmlspecialchars($estado) ?>
                                    </td>
                                    <td><strong><?= $nota ?></strong></td>
                                    <td>
                                        <?= $t['comentarios_docente'] 
                                            ? nl2br(htmlspecialchars($t['comentarios_docente'])) 
                                            : '<span class="text-muted">Sin comentarios</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
