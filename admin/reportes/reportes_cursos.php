<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";

require_role([1]); // admin

// Estadística por curso (global)
$sqlCursos = "
SELECT 
    c.id AS curso_id,
    c.nombre_curso,
    c.descripcion,
    COUNT(DISTINCT h.id) AS total_horarios,
    COUNT(DISTINCT m.id) AS total_matriculas,

    (SELECT COUNT(*)
     FROM tareas t
     INNER JOIN horarios hh ON t.horario_id = hh.id
     WHERE hh.curso_id = c.id
    ) AS total_tareas,

    (SELECT COUNT(*)
     FROM tareas_entregas te
     INNER JOIN tareas tt ON te.tarea_id = tt.id
     INNER JOIN horarios hh2 ON tt.horario_id = hh2.id
     WHERE hh2.curso_id = c.id
    ) AS total_entregas,

    (SELECT ROUND(AVG(cali.puntaje),2)
     FROM calificaciones cali
     INNER JOIN matriculas mm ON cali.matricula_id = mm.id
     INNER JOIN horarios hh3 ON mm.horario_id = hh3.id
     WHERE hh3.curso_id = c.id
    ) AS promedio_notas

FROM cursos c
LEFT JOIN horarios h ON h.curso_id = c.id
LEFT JOIN matriculas m ON m.horario_id = h.id

GROUP BY c.id, c.nombre_curso, c.descripcion
ORDER BY c.nombre_curso;
";

$res = $mysqli->query($sqlCursos);
$cursos = $res->fetch_all(MYSQLI_ASSOC);

include __DIR__ . "/../../includes/header.php";
?>

<div class="container mt-4">

    <!-- HEADER con estética similar a reportes_mensuales -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"
            style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">

            <div>
                <h2 class="mb-1 d-flex align-items-center" style="color:#b14f72;">
                    <i class="fa-solid fa-chart-pie me-2"></i>
                    Reporte por curso
                </h2>
                <small class="text-muted">
                    Resumen global de matrículas, tareas y rendimiento por curso.
                </small>
            </div>
            <div class="text-md-end">
                <span class="badge rounded-pill px-3 py-2" style="background: rgba(255,75,123,.08); color:#b14f72;">
                    <?= count($cursos) ?> curso<?= count($cursos) === 1 ? '' : 's' ?>
                </span>
            </div>
        </div>
    </div>
    <?php if (empty($cursos)): ?>
        <div class="alert alert-info">No hay cursos registrados.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($cursos as $c): ?>
                <div class="col-md-6">
                    <div class="card card-soft shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pb-0">
                            <h5 class="mb-0" style="color:#4b2e83;">
                                <?= htmlspecialchars($c['nombre_curso']) ?>
                            </h5>
                        </div>

                        <div class="card-body pt-2">
                            <?php if (!empty($c['descripcion'])): ?>
                                <p class="small text-muted mb-3">
                                    <?= htmlspecialchars($c['descripcion']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="row text-center g-2">
                                <div class="col-6">
                                    <div class="fw-bold"><?= $c['total_horarios'] ?></div>
                                    <div class="small text-muted">Horarios</div>
                                </div>
                                <div class="col-6">
                                    <div class="fw-bold"><?= $c['total_matriculas'] ?></div>
                                    <div class="small text-muted">Matriculados</div>
                                </div>
                                <div class="col-6">
                                    <div class="fw-bold"><?= $c['total_tareas'] ?></div>
                                    <div class="small text-muted">Tareas creadas</div>
                                </div>
                                <div class="col-6">
                                    <div class="fw-bold"><?= $c['total_entregas'] ?></div>
                                    <div class="small text-muted">Entregas recibidas</div>
                                </div>
                                <div class="col-12 mt-2">
                                    <div class="fw-bold">
                                        <?= $c['promedio_notas'] !== null ? $c['promedio_notas'] : '-' ?>
                                    </div>
                                    <div class="small text-muted">Promedio general</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light border-0 text-end">
                            <a href="reporte_curso_detalle.php?curso_id=<?= $c['curso_id'] ?>" class="btn btn-sm" style="
                            border:1px solid #ff4b7b;
                            color:#ff4b7b;
                            background-color:transparent;
                            border-radius:6px;
                            font-weight:500;
                        " onmouseover="this.style.backgroundColor='#ff4b7b'; this.style.color='#fff';"
                                onmouseout="this.style.backgroundColor='transparent'; this.style.color='#ff4b7b';">
                                Ver detalle
                            </a>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . "/../../includes/footer.php";
