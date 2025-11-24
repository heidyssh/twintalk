<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); // solo estudiantes

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if (!$usuario_id) {
    header("Location: /twintalk/login.php");
    exit;
}

$hoy = date("Y-m-d");

// Traer TODAS las tareas de los horarios donde el estudiante está matriculado
$stmt = $mysqli->prepare("
    SELECT 
        t.id AS tarea_id,
        t.titulo,
        t.descripcion,
        t.fecha_publicacion,
        t.fecha_entrega,
        t.modalidad,
        t.permitir_atraso,
        c.nombre_curso,
        h.id AS horario_id,
        m.id AS matricula_id,
        te.id AS entrega_id,
        te.fecha_entrega AS fecha_entrega_real,
        te.calificacion
    FROM matriculas m
    JOIN horarios h      ON m.horario_id = h.id
    JOIN cursos c        ON h.curso_id = c.id
    JOIN tareas t        ON t.horario_id = h.id AND t.activo = 1
    LEFT JOIN tareas_entregas te 
           ON te.tarea_id = t.id 
          AND te.matricula_id = m.id
    WHERE m.estudiante_id = ?
      AND m.estado_id IN (1,2) -- Activa / Pendiente
    ORDER BY t.fecha_entrega IS NULL, t.fecha_entrega, t.fecha_publicacion
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$tareas = [];
while ($row = $result->fetch_assoc()) {
    $tareas[] = $row;
}
$stmt->close();

include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">
    <h1 class="h3 mb-3">
        <i class="fa-solid fa-calendar-day me-2"></i>
        Calendario de tareas
    </h1>
    <p class="text-muted mb-4">
        Aquí ves todas tus tareas por curso. Las <strong>entregadas</strong> se muestran <span class="text-decoration-line-through">tachadas</span>.
    </p>

    <?php if (empty($tareas)): ?>
        <div class="alert alert-info">
            No tienes tareas asignadas por el momento.
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($tareas as $t): 
                $fechaLimite      = $t['fecha_entrega'] ? date("d/m/Y", strtotime($t['fecha_entrega'])) : "Sin fecha límite";
                $fechaPublicacion = $t['fecha_publicacion'] ? date("d/m/Y H:i", strtotime($t['fecha_publicacion'])) : "-";
                
                $entregada = !empty($t['entrega_id']);
                $vencida   = !$entregada && $t['fecha_entrega'] && $t['fecha_entrega'] < $hoy;

                // Clases visuales
                if ($entregada) {
                    $claseTexto = "text-decoration-line-through text-muted";
                } elseif ($vencida) {
                    $claseTexto = "text-danger";
                } else {
                    $claseTexto = "";
                }

                // Estado
                if ($entregada && $t['calificacion'] !== null) {
                    $estado = "Entregada y calificada";
                    $badgeClass = "bg-success";
                } elseif ($entregada) {
                    $estado = "Entregada (pendiente de nota)";
                    $badgeClass = "bg-primary";
                } elseif ($vencida) {
                    $estado = "No entregada (vencida)";
                    $badgeClass = "bg-danger";
                } else {
                    $estado = "Pendiente";
                    $badgeClass = "bg-warning text-dark";
                }
            ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($estado) ?>
                                </span>
                                <small class="text-muted">
                                    Curso: <?= htmlspecialchars($t['nombre_curso']) ?>
                                </small>
                            </div>

                            <h5 class="card-title <?= $claseTexto ?> mb-1">
                                <?= htmlspecialchars($t['titulo']) ?>
                            </h5>

                            <?php if (!empty($t['descripcion'])): ?>
                                <p class="card-text small <?= $claseTexto ?>">
                                    <?= nl2br(htmlspecialchars($t['descripcion'])) ?>
                                </p>
                            <?php endif; ?>

                            <ul class="list-unstyled small mb-3 <?= $claseTexto ?>">
                                <li><i class="fa-regular fa-calendar-plus me-1"></i>
                                    Publicada: <?= $fechaPublicacion ?>
                                </li>
                                <li><i class="fa-regular fa-calendar-xmark me-1"></i>
                                    Fecha límite: <?= $fechaLimite ?>
                                </li>
                                <?php if ($entregada && $t['fecha_entrega_real']): ?>
                                    <li><i class="fa-regular fa-clock me-1"></i>
                                        Entregada: <?= date("d/m/Y H:i", strtotime($t['fecha_entrega_real'])) ?>
                                    </li>
                                <?php endif; ?>
                                <?php if ($entregada && $t['calificacion'] !== null): ?>
                                    <li><i class="fa-solid fa-star me-1"></i>
                                        Nota: <strong><?= number_format($t['calificacion'], 2) ?></strong>
                                    </li>
                                <?php endif; ?>
                            </ul>

                            <div class="mt-auto d-flex justify-content-between align-items-center">
                                <span class="badge bg-light text-secondary border">
                                    <?= $t['modalidad'] === 'grupo' ? 'Trabajo en grupo' : 'Trabajo individual' ?>
                                </span>
                                <?php if ($vencida && !$entregada): ?>
                                    <small class="text-danger">
                                        ⛔ Fuera de tiempo
                                    </small>
                                <?php elseif (!$entregada): ?>
                                    <small class="text-success">
                                        ✅ Aún puedes entregar
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">
                                        ✔ Tarea completada
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
