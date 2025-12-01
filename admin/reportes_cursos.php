<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

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

include __DIR__ . "/../includes/header.php";
?>

<div class="container mt-4">
    <h2 class="mb-4 text-center">
        <i class="fa-solid fa-chart-pie me-2"></i>
        Reporte por curso
    </h2>

    <?php if (empty($cursos)): ?>
        <div class="alert alert-info">No hay cursos registrados.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($cursos as $c): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 rounded-3 h-100">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0 text-primary"><?= htmlspecialchars($c['nombre_curso']) ?></h5>
                        </div>
                        <div class="card-body pt-2">
                            <?php if (!empty($c['descripcion'])): ?>
                                <p class="small text-muted"><?= htmlspecialchars($c['descripcion']) ?></p>
                            <?php endif; ?>
                            <div class="row text-center">
                                <div class="col-6 mb-2">
                                    <div class="fw-bold"><?= $c['total_horarios'] ?></div>
                                    <div class="small text-muted">Horarios</div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="fw-bold"><?= $c['total_matriculas'] ?></div>
                                    <div class="small text-muted">Matriculados</div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="fw-bold"><?= $c['total_tareas'] ?></div>
                                    <div class="small text-muted">Tareas</div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="fw-bold"><?= $c['total_entregas'] ?></div>
                                    <div class="small text-muted">Entregas</div>
                                </div>
                                <div class="col-12 mt-1">
                                    <div class="fw-bold">
                                        <?= $c['promedio_notas'] !== null ? $c['promedio_notas'] : '—' ?>
                                    </div>
                                    <div class="small text-muted">Promedio de notas</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light border-0 text-end">
                            <a href="reporte_curso_detalle.php?curso_id=<?= $c['curso_id'] ?>" class="btn btn-sm btn-outline-secondary">
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
include __DIR__ . "/../includes/footer.php";
