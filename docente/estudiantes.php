<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_role([2]); // solo docentes

$docente_id = $_SESSION['usuario_id'] ?? 0;

if (!$docente_id) {
    header("Location: /twintalk/login.php");
    exit;
}

$horario_id = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;

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
        echo '<div class="alert alert-danger mt-4">Horario no encontrado o no pertenece a tu cuenta.</div>';
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
        ORDER BY u.apellido, u.nombre
    ";
    $stmtEst = $mysqli->prepare($sqlEst);
    $stmtEst->bind_param("i", $horario_id);
    $stmtEst->execute();
    $estudiantes = $stmtEst->get_result();
    $stmtEst->close();

    include __DIR__ . "/../includes/header.php";
    ?>

    <h1 class="h4 fw-bold mt-3">
        Estudiantes del curso: <?= htmlspecialchars($curso['nombre_curso']) ?>
    </h1>
    <p class="small text-muted mb-3">
        Nivel <?= htmlspecialchars($curso['codigo_nivel']) ?> ·
        <?= htmlspecialchars($curso['nombre_dia']) ?> ·
        <?= substr($curso['hora_inicio'],0,5) ?> - <?= substr($curso['hora_fin'],0,5) ?> ·
        Aula <?= htmlspecialchars($curso['aula'] ?: 'N/A') ?>
    </p>

    <div class="card card-soft p-3">
        <h2 class="h6 fw-bold mb-2">Estudiantes matriculados</h2>
        <div class="table-responsive table-rounded">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Estudiante</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Estado</th>
                    <th>Fecha matrícula</th>
                    <th></th>
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
                                <a href="estudiante_perfil.php?matricula_id=<?= (int)$e['matricula_id'] ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    Ver perfil
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-muted">No hay estudiantes matriculados en este horario.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <a href="estudiantes.php" class="btn btn-link px-0 mt-3">
        ‹ Volver a mis cursos / horarios
    </a>

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

<h1 class="h4 fw-bold mt-3">Estudiantes por curso</h1>
<p class="small text-muted mb-3">
    Selecciona un curso/horario para ver la lista de estudiantes matriculados.
</p>

<?php if ($horarios->num_rows == 0): ?>
    <div class="alert alert-warning">No tienes horarios asignados actualmente.</div>
<?php else: ?>
    <div class="table-responsive table-rounded">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Curso</th>
                <th>Horario</th>
                <th>Aula</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($h = $horarios->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($h['nombre_curso']) ?></strong><br>
                        <small class="text-muted">
                            <?= htmlspecialchars($h['nombre_nivel']) ?> (<?= htmlspecialchars($h['codigo_nivel']) ?>)
                        </small>
                    </td>
                    <td>
                        <?= htmlspecialchars($h['nombre_dia']) ?> ·
                        <?= substr($h['hora_inicio'],0,5) ?> - <?= substr($h['hora_fin'],0,5) ?>
                    </td>
                    <td><?= htmlspecialchars($h['aula'] ?: 'N/A') ?></td>
                    <td class="text-end">
                        <a href="estudiantes.php?horario_id=<?= (int)$h['horario_id'] ?>"
                           class="btn btn-sm btn-tt-primary">
                            Ver estudiantes
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . "/../includes/footer.php"; ?>
