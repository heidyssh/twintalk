<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); 

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if (!$usuario_id) {
    header("Location: /twintalk/login.php");
    exit;
}

$hoy = date("Y-m-d");


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

<style>
.card-soft{
    border-radius:12px;
}
.btn-tt-primary{
    background-color:#A45A6A;
    border-color:#A45A6A;
    color:#fff;
}
.btn-tt-primary:hover{
    background-color:#8c4b59;
    border-color:#8c4b59;
    color:#fff;
}
.badge-soft-success{
    background:#e9f7ef;
    color:#1e7e34;
    border:1px solid #c7e6d2;
}
.badge-soft-info{
    background:#e8f4fd;
    color:#0b5ed7;
    border:1px solid #cfe2ff;
}
.badge-soft-danger{
    background:#fdecea;
    color:#b02a37;
    border:1px solid #f5c2c7;
}
.badge-soft-warning{
    background:#fff4e5;
    color:#8a5a11;
    border:1px solid #ffe0b2;
}
.tarea-header-pill{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.05em;
}
</style>

<div class="container my-4">

    <!-- Cabecera con degradado -->
    <div class="card card-soft border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
             style="background:linear-gradient(90deg,#fbe9f0,#ffffff);">
            <div>
                <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                    <i class="fa-solid fa-calendar-day me-2"></i>
                    Calendario de tareas
                </h1>
                <p class="small text-muted mb-0">
                    Aquí ves todas tus tareas por curso. Las <strong>entregadas</strong> se muestran
                    <span class="text-decoration-line-through">tachadas</span> para que distingas rápido lo pendiente.
                </p>
            </div>
            <div>
                <a href="/twintalk/student/dashboard.php" class="btn btn-outline-secondary btn-sm">
                    ← Volver al panel
                </a>
            </div>
        </div>
    </div>

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

                
                if ($entregada) {
                    $claseTexto = "text-decoration-line-through text-muted";
                } elseif ($vencida) {
                    $claseTexto = "text-danger";
                } else {
                    $claseTexto = "";
                }

                
                if ($entregada && $t['calificacion'] !== null) {
                    $estado = "Entregada y calificada";
                    $badgeClass = "badge-soft-success";
                } elseif ($entregada) {
                    $estado = "Entregada (pendiente de nota)";
                    $badgeClass = "badge-soft-info";
                } elseif ($vencida) {
                    $estado = "No entregada (vencida)";
                    $badgeClass = "badge-soft-danger";
                } else {
                    $estado = "Pendiente";
                    $badgeClass = "badge-soft-warning";
                }
            ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card card-soft h-100 shadow-sm border-0">
                        <div class="card-body d-flex flex-column">

                            <!-- Encabezado pill: estado + curso -->
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge <?= $badgeClass ?> tarea-header-pill">
                                    <?= htmlspecialchars($estado) ?>
                                </span>
                                <small class="text-muted text-end ms-2">
                                    <i class="fa-solid fa-book-open me-1"></i>
                                    <?= htmlspecialchars($t['nombre_curso']) ?>
                                </small>
                            </div>

                            <!-- Título -->
                            <h5 class="card-title <?= $claseTexto ?> mb-1" style="font-size:15px;">
                                <?= htmlspecialchars($t['titulo']) ?>
                            </h5>

                            <!-- Descripción -->
                            <?php if (!empty($t['descripcion'])): ?>
                                <p class="card-text small <?= $claseTexto ?> mb-2">
                                    <?= nl2br(htmlspecialchars($t['descripcion'])) ?>
                                </p>
                            <?php endif; ?>

                            <!-- Fechas y nota -->
                            <ul class="list-unstyled small mb-3 <?= $claseTexto ?>">
                                <li class="mb-1">
                                    <i class="fa-regular fa-calendar-plus me-1"></i>
                                    <strong>Publicada:</strong> <?= $fechaPublicacion ?>
                                </li>
                                <li class="mb-1">
                                    <i class="fa-regular fa-calendar-xmark me-1"></i>
                                    <strong>Fecha límite:</strong> <?= $fechaLimite ?>
                                </li>
                                <?php if ($entregada && $t['fecha_entrega_real']): ?>
                                    <li class="mb-1">
                                        <i class="fa-regular fa-clock me-1"></i>
                                        <strong>Entregada:</strong>
                                        <?= date("d/m/Y H:i", strtotime($t['fecha_entrega_real'])) ?>
                                    </li>
                                <?php endif; ?>
                                <?php if ($entregada && $t['calificacion'] !== null): ?>
                                    <li class="mb-1">
                                        <i class="fa-solid fa-star me-1"></i>
                                        <strong>Nota:</strong>
                                        <span><?= number_format($t['calificacion'], 2) ?></span>
                                    </li>
                                <?php endif; ?>
                            </ul>

                            <!-- Pie: modalidad + estado corto -->
                            <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="badge bg-light text-secondary border small">
                                    <i class="fa-solid fa-users-rectangle me-1"></i>
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
