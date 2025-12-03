<?php
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/auth.php";

require_role([1]); // admin

$curso_id = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;

if ($curso_id <= 0) {
    header("Location: reportes_cursos.php");
    exit;
}

// Info básica del curso
$stmt = $mysqli->prepare("
    SELECT c.id, c.nombre_curso, c.descripcion, n.codigo_nivel, n.nombre_nivel
    FROM cursos c
    LEFT JOIN niveles_academicos n ON c.nivel_id = n.id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $curso_id);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$curso) {
    header("Location: reportes_cursos.php");
    exit;
}

// Horarios del curso
$stmt = $mysqli->prepare("
    SELECT 
        h.id,
        h.aula,
        h.fecha_inicio,
        h.fecha_fin,
        ds.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        u.nombre AS nombre_docente,
        u.apellido AS apellido_docente
    FROM horarios h
    INNER JOIN dias_semana ds ON ds.id = h.dia_semana_id
    INNER JOIN docentes d     ON d.id = h.docente_id
    INNER JOIN usuarios u     ON u.id = d.id
    WHERE h.curso_id = ?
    ORDER BY ds.numero_dia, h.hora_inicio
");
$stmt->bind_param("i", $curso_id);
$stmt->execute();
$horariosRes = $stmt->get_result();
$horarios = [];
while ($row = $horariosRes->fetch_assoc()) {
    $horarios[$row['id']] = $row;
}
$stmt->close();

// Alumnos por horario
$alumnosPorHorario = [];
if (!empty($horarios)) {
    $stmt = $mysqli->prepare("
        SELECT 
            m.id AS matricula_id,
            m.horario_id,
            u.nombre,
            u.apellido,
            u.email,
            em.nombre_estado
        FROM matriculas m
        INNER JOIN estudiantes e ON e.id = m.estudiante_id
        INNER JOIN usuarios u    ON u.id = e.id
        INNER JOIN estados_matricula em ON em.id = m.estado_id
        WHERE m.horario_id = ?
        ORDER BY u.apellido, u.nombre
    ");

    foreach ($horarios as $hid => $hinfo) {
        $stmt->bind_param("i", $hid);
        $stmt->execute();
        $resAl = $stmt->get_result();
        $alumnosPorHorario[$hid] = $resAl->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// Tareas del curso (todas las de los horarios de este curso)
$stmt = $mysqli->prepare("
    SELECT 
        t.id,
        t.titulo,
        t.fecha_publicacion,
        t.fecha_entrega,
        t.valor_maximo,
        h.id AS horario_id,
        ds.nombre_dia,
        h.hora_inicio,
        (SELECT COUNT(*) FROM tareas_entregas te WHERE te.tarea_id = t.id) AS total_entregas
    FROM tareas t
    INNER JOIN horarios h   ON h.id = t.horario_id
    INNER JOIN dias_semana ds ON ds.id = h.dia_semana_id
    WHERE h.curso_id = ?
    ORDER BY t.fecha_publicacion DESC
");
$stmt->bind_param("i", $curso_id);
$stmt->execute();
$tareas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . "/../../includes/header.php";
?>

<div class="container mt-4">


    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">

            <!-- ENCABEZADO + BOTÓN PDF -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h3 class="mb-1 text-primary"><?= htmlspecialchars($curso['nombre_curso']) ?></h3>
                    <p class="text-muted mb-1">
                        Nivel: <?= htmlspecialchars($curso['codigo_nivel'] ?? '') ?> 
                        <?= htmlspecialchars($curso['nombre_nivel'] ?? '') ?>
                    </p>
                </div>

                <!-- BOTÓN PDF -->
                <a href="reporte_curso_pdf.php?curso_id=<?= $curso_id ?>" 
                   class="btn btn-sm btn-outline-danger" target="_blank">
                    <i class="fa-solid fa-file-pdf me-1"></i> Descargar PDF
                </a>
            </div>

            <!-- DESCRIPCIÓN DEL CURSO -->
            <?php if (!empty($curso['descripcion'])): ?>
                <p class="small text-muted mb-0"><?= htmlspecialchars($curso['descripcion']) ?></p>
            <?php endif; ?>

        </div>
    </div>


    <ul class="nav nav-tabs mb-3" id="reporteCursoTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-horarios" data-bs-toggle="tab" data-bs-target="#pane-horarios" type="button" role="tab">
                Horarios y alumnos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-tareas" data-bs-toggle="tab" data-bs-target="#pane-tareas" type="button" role="tab">
                Tareas del curso
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- TAB HORARIOS + ALUMNOS -->
        <div class="tab-pane fade show active" id="pane-horarios" role="tabpanel">
            <?php if (empty($horarios)): ?>
                <div class="alert alert-info">Este curso aún no tiene horarios registrados.</div>
            <?php else: ?>
                <?php foreach ($horarios as $hid => $h): ?>
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($h['nombre_dia']) ?></strong>
                                    <span class="text-muted">
                                        (<?= substr($h['hora_inicio'],0,5) ?> - <?= substr($h['hora_fin'],0,5) ?>)
                                    </span>
                                    <span class="badge bg-light text-muted ms-2">
                                        Aula: <?= htmlspecialchars($h['aula']) ?>
                                    </span>
                                </div>
                                <div class="text-end small text-muted">
                                    Docente: <?= htmlspecialchars($h['nombre_docente'].' '.$h['apellido_docente']) ?><br>
                                    Del <?= $h['fecha_inicio'] ?> al <?= $h['fecha_fin'] ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-bold">Alumnos matriculados</h6>
                            <?php if (empty($alumnosPorHorario[$hid])): ?>
                                <p class="small text-muted">No hay alumnos matriculados en este horario.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Email</th>
                                                <th>Estado matrícula</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($alumnosPorHorario[$hid] as $al): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($al['nombre'].' '.$al['apellido']) ?></td>
                                                    <td><?= htmlspecialchars($al['email']) ?></td>
                                                    <td><?= htmlspecialchars($al['nombre_estado']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- TAB TAREAS -->
        <div class="tab-pane fade" id="pane-tareas" role="tabpanel">
            <?php if (empty($tareas)): ?>
                <div class="alert alert-info">No hay tareas registradas para este curso.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Horario</th>
                                <th>Fecha publicación</th>
                                <th>Fecha entrega</th>
                                <th>Valor máx.</th>
                                <th>Entregas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tareas as $t): ?>
                                <tr>
                                    <td><?= htmlspecialchars($t['titulo']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($t['nombre_dia']) ?>
                                        (<?= substr($t['hora_inicio'],0,5) ?>)
                                        <span class="badge bg-light text-muted">H<?= $t['horario_id'] ?></span>
                                    </td>
                                    <td><?= $t['fecha_publicacion'] ?></td>
                                    <td><?= $t['fecha_entrega'] ?></td>
                                    <td><?= $t['valor_maximo'] ?></td>
                                    <td><?= $t['total_entregas'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include __DIR__ . "/../../includes/footer.php";
