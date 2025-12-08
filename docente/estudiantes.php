<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_role([2]); // solo docentes

$docente_id = $_SESSION['usuario_id'] ?? 0;

if (!$docente_id) {
    header("Location: /twintalk/login.php");
    exit;
}

$horario_id = isset($_GET['horario_id']) ? (int) $_GET['horario_id'] : 0;

/*
 * MODO 1: SIN horario_id  -> listar todos los horarios del docente
 * MODO 2: CON horario_id  -> listar estudiantes de ese horario
 */

// =========================
// MODO 2: Estudiantes de un horario
// =========================
if ($horario_id > 0) {

    // Verificar que el horario pertenece a este docente y traer info del curso
    $sqlCurso = "
        SELECT
            h.id AS horario_id,
            c.nombre_curso,
            c.descripcion,
            n.codigo_nivel,
            n.nombre_nivel,
            d.nombre_dia,
            h.hora_inicio,
            h.hora_fin,
            h.aula
        FROM horarios h
        INNER JOIN cursos c ON h.curso_id = c.id
        INNER JOIN niveles_academicos n ON c.nivel_id = n.id
        INNER JOIN dias_semana d ON h.dia_semana_id = d.id
        WHERE h.id = ? AND h.docente_id = ?
        LIMIT 1
    ";
    $stmtCurso = $mysqli->prepare($sqlCurso);
    $stmtCurso->bind_param("ii", $horario_id, $docente_id);
    $stmtCurso->execute();
    $resCurso = $stmtCurso->get_result();
    $curso = $resCurso->fetch_assoc();
    $stmtCurso->close();

    if (!$curso) {
        include __DIR__ . "/../includes/header.php";
        echo '<div class="container py-4">
                <div class="alert alert-danger">Horario no encontrado o no pertenece a tu cuenta.</div>
              </div>';
        include __DIR__ . "/../includes/footer.php";
        exit;
    }

// Traer estudiantes matriculados en este horario
$sqlEst = "
    SELECT
        m.id AS matricula_id,
        u.nombre,
        u.apellido,
        u.email,
        u.telefono,
        e.nivel_actual,
        m.fecha_matricula,
        em.nombre_estado
    FROM matriculas m
    INNER JOIN estudiantes e ON m.estudiante_id = e.id
    INNER JOIN usuarios u ON e.id = u.id
    INNER JOIN estados_matricula em ON m.estado_id = em.id
    WHERE m.horario_id = ?
      AND em.nombre_estado IN ('Activa','Pendiente')
    ORDER BY u.apellido, u.nombre
";



    $stmtEst = $mysqli->prepare($sqlEst);
    $stmtEst->bind_param("i", $horario_id);
    $stmtEst->execute();
    $estudiantes = $stmtEst->get_result();
    $stmtEst->close();

    include __DIR__ . "/../includes/header.php";
    ?>

    <div class="container my-4">

        <!-- Encabezado con estética TwinTalk -->
        <div class="card card-soft border-0 shadow-sm mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
                style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">
                <div>
                    <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                        Estudiantes del curso
                    </h1>
                    <small class="text-muted">
                        <?= htmlspecialchars($curso['nombre_curso']) ?><br>
                        Nivel <?= htmlspecialchars($curso['codigo_nivel']) ?>
                        (<?= htmlspecialchars($curso['nombre_nivel']) ?>) ·
                        <?= htmlspecialchars($curso['nombre_dia']) ?> ·
                        <?= substr($curso['hora_inicio'], 0, 5) ?> - <?= substr($curso['hora_fin'], 0, 5) ?> ·
                        Aula <?= htmlspecialchars($curso['aula'] ?: 'N/A') ?>
                    </small>
                </div>
                <div class="text-md-end">
                    <span class="badge rounded-pill text-bg-light border">
                        Docente
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabla de estudiantes -->
        <div class="card card-soft border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-3">Estudiantes matriculados</h2>
                <div class="table-responsive table-rounded">
                    <table class="table table-sm align-middle mb-0 table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Estudiante</th>
                                <th>Correo</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th>Fecha matrícula</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($estudiantes->num_rows > 0): ?>
                                <?php while ($e = $estudiantes->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($e['nombre'] . " " . $e['apellido']) ?></strong><br>
                                            <small class="text-muted">
                                                Nivel actual: <?= htmlspecialchars($e['nivel_actual'] ?: 'N/D') ?>
                                            </small>
                                        </td>
                                        <td><?= htmlspecialchars($e['email']) ?></td>
                                        <td><?= htmlspecialchars($e['telefono'] ?: 'N/D') ?></td>
                                        <td><?= htmlspecialchars($e['nombre_estado']) ?></td>
                                        <td>
                                            <?= $e['fecha_matricula']
                                                ? date('d/m/Y', strtotime($e['fecha_matricula']))
                                                : 'N/D' ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="estudiante_perfil.php?matricula_id=<?= (int) $e['matricula_id'] ?>"
                                                class="btn btn-sm shadow-sm"
                                                style="background:#b14f72; color:#fff; border-radius:8px; border:none;">
                                                Ver perfil
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-muted text-center py-3">
                                        No hay estudiantes matriculados en este horario.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php
    include __DIR__ . "/../includes/footer.php";
    exit;
}

// =========================
// MODO 1: Lista de horarios del docente
// =========================

// Obtener todos los horarios que imparte este docente
$sqlHor = "
    SELECT 
        h.id AS horario_id,
        c.nombre_curso,
        c.descripcion,
        n.codigo_nivel,
        n.nombre_nivel,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        h.aula
    FROM horarios h
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN niveles_academicos n ON c.nivel_id = n.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.docente_id = ?
    ORDER BY c.nombre_curso, d.nombre_dia, h.hora_inicio
";
$stmtHor = $mysqli->prepare($sqlHor);
$stmtHor->bind_param("i", $docente_id);
$stmtHor->execute();
$horarios = $stmtHor->get_result();
$stmtHor->close();

include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">

    <!-- Encabezado general -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
            style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">
            <div>
                <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                    Estudiantes por curso
                </h1>
                <small class="text-muted">
                    Selecciona un curso/horario para ver la lista de estudiantes matriculados.
                </small>
            </div>
            <div class="text-md-end">
                <span class="badge rounded-pill text-bg-light border">
                    Docente
                </span>
            </div>
        </div>
    </div>

    <?php if ($horarios->num_rows == 0): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            No tienes horarios asignados actualmente.
        </div>
    <?php else: ?>
        <div class="card card-soft border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive table-rounded">
                    <table class="table table-sm align-middle mb-0 table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Curso</th>
                                <th>Horario</th>
                                <th>Aula</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($h = $horarios->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($h['nombre_curso']) ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($h['nombre_nivel']) ?>
                                            (<?= htmlspecialchars($h['codigo_nivel']) ?>)
                                        </small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($h['nombre_dia']) ?> ·
                                        <?= substr($h['hora_inicio'], 0, 5) ?> - <?= substr($h['hora_fin'], 0, 5) ?>
                                    </td>
                                    <td><?= htmlspecialchars($h['aula'] ?: 'N/A') ?></td>
                                    <td class="text-end">
                                        <a href="estudiantes.php?horario_id=<?= (int) $h['horario_id'] ?>"
                                            class="btn btn-sm shadow-sm"
                                            style="background:#b14f72; color:#fff; border-radius:8px; border:none;">
                                            Ver estudiantes
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>