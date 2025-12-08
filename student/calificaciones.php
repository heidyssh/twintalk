<?php 
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); 

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if (!$usuario_id) {
    header("Location: /twintalk/login.php");
    exit;
}




$sqlCursos = "
    SELECT 
        h.id  AS horario_id,
        c.id  AS curso_id,
        c.nombre_curso
    FROM matriculas m
    INNER JOIN horarios h ON h.id = m.horario_id
    INNER JOIN cursos   c ON c.id = h.curso_id
    WHERE m.estudiante_id = ?
      AND m.estado_id IN (1,2,4) -- activa, pendiente, finalizada
    ORDER BY c.nombre_curso
";
$stmt = $mysqli->prepare($sqlCursos);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();




$horario_id_seleccionado = isset($_GET['horario_id']) ? (int) $_GET['horario_id'] : 0;
$matricula_id      = null;
$tareas            = [];
$evaluaciones      = [];
$nota_tareas       = 0.0;
$nota_eval         = 0.0;
$nota_general      = null; 
$promedio_oficial  = null; 

if ($horario_id_seleccionado > 0) {

    
    $sqlMat = "
        SELECT m.id AS matricula_id
        FROM matriculas m
        INNER JOIN horarios h ON h.id = m.horario_id
        WHERE m.estudiante_id = ?
          AND h.id = ?
        LIMIT 1
    ";
    $stmt = $mysqli->prepare($sqlMat);
    $stmt->bind_param("ii", $usuario_id, $horario_id_seleccionado);
    $stmt->execute();
    $resMat = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($resMat) {
        $matricula_id = (int) $resMat['matricula_id'];

        
        $sqlTareas = "
            SELECT 
                t.id AS tarea_id,
                t.titulo,
                t.descripcion,
                t.fecha_publicacion,
                t.fecha_entrega,
                t.valor_maximo,
                te.id AS entrega_id,
                te.fecha_entrega AS fecha_entrega_real,
                te.calificacion,
                te.comentarios_docente
            FROM tareas t
            INNER JOIN horarios h ON h.id = t.horario_id
            INNER JOIN matriculas m ON m.horario_id = h.id
            LEFT JOIN tareas_entregas te
                   ON te.tarea_id = t.id
                  AND te.matricula_id = m.id
            WHERE m.id = ?
              AND h.id = ?
              AND t.activo = 1
            ORDER BY t.fecha_entrega IS NULL, t.fecha_entrega, t.titulo
        ";
        $stmt = $mysqli->prepare($sqlTareas);
        $stmt->bind_param("ii", $matricula_id, $horario_id_seleccionado);
        $stmt->execute();
        $tareas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $sqlNotasTareas = "
            SELECT 
                SUM(te.calificacion) AS suma_tareas,
                COUNT(*) AS cantidad_tareas
            FROM tareas_entregas te
            INNER JOIN tareas t  ON t.id = te.tarea_id
            INNER JOIN horarios h ON h.id = t.horario_id
            WHERE te.matricula_id = ?
              AND h.id = ?
              AND te.calificacion IS NOT NULL
        ";
        $stmt = $mysqli->prepare($sqlNotasTareas);
        $stmt->bind_param("ii", $matricula_id, $horario_id_seleccionado);
        $stmt->execute();
        $rowT = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $suma_tareas     = $rowT && $rowT['suma_tareas'] !== null ? (float) $rowT['suma_tareas'] : 0.0;
        $cantidad_tareas = $rowT && $rowT['cantidad_tareas'] !== null ? (int)  $rowT['cantidad_tareas'] : 0;
        $nota_tareas     = $suma_tareas;

        $sqlNotasEval = "
            SELECT 
                SUM(c.puntaje) AS suma_eval,
                COUNT(*)       AS cantidad_eval
            FROM calificaciones c
            WHERE c.matricula_id = ?
              AND c.puntaje IS NOT NULL
        ";
        $stmt = $mysqli->prepare($sqlNotasEval);
        $stmt->bind_param("i", $matricula_id);
        $stmt->execute();
        $rowE = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $suma_eval     = $rowE && $rowE['suma_eval'] !== null ? (float) $rowE['suma_eval'] : 0.0;
        $cantidad_eval = $rowE && $rowE['cantidad_eval'] !== null ? (int)  $rowE['cantidad_eval'] : 0;
        $nota_eval     = $suma_eval;

        
        $sqlEvalDetalle = "
            SELECT 
                c.id,
                c.puntaje,
                c.fecha_evaluacion,
                c.comentarios,
                te.nombre_evaluacion
            FROM calificaciones c
            INNER JOIN tipos_evaluacion te ON te.id = c.tipo_evaluacion_id
            WHERE c.matricula_id = ?
              AND c.puntaje IS NOT NULL
            ORDER BY c.fecha_evaluacion, c.id
        ";
        $stmt = $mysqli->prepare($sqlEvalDetalle);
        $stmt->bind_param("i", $matricula_id);
        $stmt->execute();
        $evaluaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        
        $nota_general = $nota_tareas + $nota_eval;
        $total_items  = $cantidad_tareas + $cantidad_eval;

        if ($total_items > 0) {
            $promedio_oficial = $nota_general / $total_items;
        }
    }
}

include __DIR__ . "/../includes/header.php";
?>

<style>
.card-soft{
    border-radius: 12px;
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
.badge-soft-danger{
    background:#fdecea;
    color:#b02a37;
    border:1px solid #f5c2c7;
}
</style>

<div class="container my-4">
    <div class="card card-soft border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
             style="background:linear-gradient(90deg,#fbe9f0,#ffffff);">
            <div>
                <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                    <i class="fa-solid fa-chart-line me-2"></i>
                    Mis calificaciones
                </h1>
                <p class="small text-muted mb-0">
                    Selecciona un curso para ver tus tareas, tus evaluaciones y tu <strong>nota general</strong>.
                </p>
            </div>
            <div>
                <a href="/twintalk/student/dashboard.php" class="btn btn-outline-secondary btn-sm">
                    ← Volver al panel
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        
        <div class="col-md-4 mb-3">
            <div class="card card-soft shadow-sm border-0 p-3">
                <h2 class="h6 fw-bold mb-3">Mis cursos</h2>
                <?php if (empty($cursos)): ?>
                    <p class="text-muted small mb-0">Aún no estás matriculado en ningún curso.</p>
                <?php else: ?>
                    <form method="get">
                        <div class="mb-2">
                            <label class="form-label small">Selecciona un curso</label>
                            <select name="horario_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">-- Selecciona --</option>
                                <?php foreach ($cursos as $c): ?>
                                    <option value="<?= $c['horario_id'] ?>" <?= ($horario_id_seleccionado == $c['horario_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nombre_curso']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-tt-primary btn-sm w-100">
                            Ver calificaciones
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-8 mb-3">
            <?php if (!$horario_id_seleccionado || !$matricula_id): ?>
                <div class="alert alert-info mb-0">
                    Selecciona un curso en el panel de la izquierda para ver tus calificaciones.
                </div>
            <?php else: ?>

                
                <div class="card card-soft shadow-sm border-0 mb-3">
                    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div>
                            <h2 class="h6 fw-bold mb-1">Nota general del curso</h2>
                            <p class="small text-muted mb-0">
                                <strong>Nota general</strong> = suma de tus <strong>tareas</strong> +
                                tus <strong>evaluaciones</strong> (quiz, exámenes, etc.).  
                                El mínimo para aprobar es <strong>70 puntos</strong>.
                            </p>
                        </div>
                        <div class="text-end">
                            <?php if ($nota_general !== null): ?>
                                <?php
                                $aprobado   = ($nota_general >= 70);
                                $badgeClass = $aprobado ? "badge-soft-success" : "badge-soft-danger";
                                $textoEstado = $aprobado ? "Aprobado (≥ 70)" : "En riesgo (&lt; 70)";
                                ?>
                                <div class="display-6 fw-bold mb-0">
                                    <?= number_format($nota_general, 2) ?>
                                </div>
                                <span class="badge <?= $badgeClass ?> mt-1">
                                    <?= $textoEstado ?>
                                </span>
                                <?php if ($promedio_oficial !== null): ?>
                                    <div class="small text-muted mt-1">
                                        Promedio: <?= number_format($promedio_oficial, 2) ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">
                                    Aún no hay suficientes calificaciones para calcular tu nota.
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card card-soft shadow-sm border-0 mb-3">
                    <div class="card-header bg-white border-0 pb-0">
                        <h2 class="h6 fw-bold mb-2">Tareas del curso seleccionado</h2>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($tareas)): ?>
                            <p class="text-muted small m-3">
                                No hay tareas registradas para este curso todavía.
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tarea</th>
                                            <th class="text-center">Publicada</th>
                                            <th class="text-center">Fecha límite</th>
                                            <th class="text-center">Fecha entrega</th>
                                            <th class="text-center">Nota</th>
                                            <th>Comentario docente</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tareas as $t): ?>
                                            <?php
                                            $fecha_pub = $t['fecha_publicacion']
                                                ? date("d/m/Y", strtotime($t['fecha_publicacion']))
                                                : "—";
                                            $fecha_lim = $t['fecha_entrega']
                                                ? date("d/m/Y", strtotime($t['fecha_entrega']))
                                                : "Sin fecha";
                                            $fecha_entrega_real = $t['fecha_entrega_real']
                                                ? date("d/m/Y H:i", strtotime($t['fecha_entrega_real']))
                                                : null;

                                            $estado = "Pendiente";
                                            $clase_estado = "badge bg-secondary";
                                            if ($t['entrega_id']) {
                                                $estado = "Entregada";
                                                $clase_estado = "badge bg-info";
                                            }
                                            if ($t['calificacion'] !== null) {
                                                $estado = "Calificada";
                                                $clase_estado = "badge bg-success";
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold small mb-0">
                                                        <?= htmlspecialchars($t['titulo']) ?>
                                                    </div>
                                                    <?php if (!empty($t['descripcion'])): ?>
                                                        <div class="small text-muted">
                                                            <?= nl2br(htmlspecialchars($t['descripcion'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center small"><?= $fecha_pub ?></td>
                                                <td class="text-center small"><?= $fecha_lim ?></td>
                                                <td class="text-center small">
                                                    <?= $fecha_entrega_real ? $fecha_entrega_real : '<span class="text-muted">Sin entregar</span>' ?>
                                                </td>
                                                <td class="text-center small">
                                                    <?php if ($t['calificacion'] !== null): ?>
                                                        <span class="fw-bold">
                                                            <?= number_format($t['calificacion'], 2) ?>
                                                        </span>
                                                        <?php if ((int) $t['valor_maximo'] > 0): ?>
                                                            <div class="text-muted">
                                                                de <?= (int) $t['valor_maximo'] ?> pts
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted d-block">Sin calificar</span>
                                                        <?php if ((int) $t['valor_maximo'] > 0): ?>
                                                            <div class="text-muted">
                                                                Valor: <?= (int) $t['valor_maximo'] ?> pts
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <div class="mt-1">
                                                        <span class="<?= $clase_estado ?> small"><?= $estado ?></span>
                                                    </div>
                                                </td>
                                                <td class="small">
                                                    <?php
                                                    if ($t['calificacion'] !== null) {
                                                        echo $t['comentarios_docente']
                                                            ? nl2br(htmlspecialchars($t['comentarios_docente']))
                                                            : '<span class="text-muted">Sin comentarios</span>';
                                                    } else {
                                                        echo '<span class="text-muted">En espera de calificación</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                
                <div class="card card-soft shadow-sm border-0">
                    <div class="card-header bg-white border-0 pb-0">
                        <h2 class="h6 fw-bold mb-2">Evaluaciones del curso (quiz, exámenes, etc.)</h2>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($evaluaciones)): ?>
                            <p class="text-muted small m-3">
                                No hay evaluaciones registradas para este curso todavía.
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tipo de evaluación</th>
                                            <th class="text-center">Fecha</th>
                                            <th class="text-center">Nota</th>
                                            <th>Comentario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($evaluaciones as $e): ?>
                                            <?php
                                            $fecha_eval = $e['fecha_evaluacion']
                                                ? date("d/m/Y", strtotime($e['fecha_evaluacion']))
                                                : "—";
                                            ?>
                                            <tr>
                                                <td class="small">
                                                    <?= htmlspecialchars($e['nombre_evaluacion']) ?>
                                                </td>
                                                <td class="text-center small"><?= $fecha_eval ?></td>
                                                <td class="text-center small">
                                                    <?= number_format($e['puntaje'], 2) ?>
                                                </td>
                                                <td class="small">
                                                    <?= $e['comentarios']
                                                        ? nl2br(htmlspecialchars($e['comentarios']))
                                                        : '<span class="text-muted">Sin comentarios</span>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
