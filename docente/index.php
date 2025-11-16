<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]);

$docenteId = $_SESSION['usuario_id'] ?? null;

if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

// Como en la BD, docentes.id = usuarios.id
$docenteId = $docenteUsuarioId;

// Total de horarios (clases) asignadas al docente
$sqlHorarios = "
    SELECT COUNT(*) AS total
    FROM horarios
    WHERE docente_id = ?
";
$stmt = $mysqli->prepare($sqlHorarios);
$stmt->bind_param("i", $docenteId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$total_horarios = $res['total'] ?? 0;
$stmt->close();

// Total de estudiantes distintos en todos sus horarios
$sqlEstudiantes = "
    SELECT COUNT(DISTINCT m.estudiante_id) AS total
    FROM matriculas m
    INNER JOIN horarios h ON m.horario_id = h.id
    WHERE h.docente_id = ?
";
$stmt = $mysqli->prepare($sqlEstudiantes);
$stmt->bind_param("i", $docenteId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$total_estudiantes = $res['total'] ?? 0;
$stmt->close();

// Total de calificaciones registradas por cursos del docente
$sqlCalificaciones = "
    SELECT COUNT(c.id) AS total
    FROM calificaciones c
    INNER JOIN matriculas m ON c.matricula_id = m.id
    INNER JOIN horarios h ON m.horario_id = h.id
    WHERE h.docente_id = ?
";
$stmt = $mysqli->prepare($sqlCalificaciones);
$stmt->bind_param("i", $docenteId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$total_calificaciones = $res['total'] ?? 0;
$stmt->close();

// Total de anuncios creados por el docente
$sqlAnuncios = "
    SELECT COUNT(*) AS total
    FROM anuncios
    WHERE docente_id = ?
";
$stmt = $mysqli->prepare($sqlAnuncios);
$stmt->bind_param("i", $docenteId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$total_anuncios = $res['total'] ?? 0;
$stmt->close();

// Total de materiales subidos
$sqlMateriales = "
    SELECT COUNT(*) AS total
    FROM materiales
    WHERE docente_id = ?
";
$stmt = $mysqli->prepare($sqlMateriales);
$stmt->bind_param("i", $docenteId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$total_materiales = $res['total'] ?? 0;
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 fw-bold mt-3">Panel del Docente</h1>
<p class="text-muted mb-4">
    Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Docente'); ?>.

<div class="row g-3">
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Cursos / Horarios</h6>
                <h3><?php echo (int)$total_horarios; ?></h3>
                <a href="cursos.php" class="btn btn-sm btn-primary mt-2">Ver cursos</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Estudiantes</h6>
                <h3><?php echo (int)$total_estudiantes; ?></h3>
                <small class="text-muted">Matriculados en tus cursos</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Calificaciones</h6>
                <h3><?php echo (int)$total_calificaciones; ?></h3>
                <a href="cursos.php" class="btn btn-sm btn-outline-primary mt-2">Gestionar notas</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Anuncios</h6>
                <h3><?php echo (int)$total_anuncios; ?></h3>
                <a href="anuncios.php" class="btn btn-sm btn-outline-primary mt-2">Ver / publicar</a>
            </div>
        </div>
    </div>

    <div class="col-md-3 mt-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Materiales</h6>
                <h3><?php echo (int)$total_materiales; ?></h3>
                <a href="materiales.php" class="btn btn-sm btn-outline-primary mt-2">Ver / subir</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
