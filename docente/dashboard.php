<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); // Docente

$docenteId = $_SESSION['usuario_id'] ?? null;

if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

// Cursos que imparte
$sqlCursos = "
    SELECT COUNT(DISTINCT h.curso_id) AS total_cursos
    FROM horarios h
    WHERE h.docente_id = ?
";
$stmtCursos = $mysqli->prepare($sqlCursos);
$stmtCursos->bind_param("i", $docenteId);
$stmtCursos->execute();
$resCursos = $stmtCursos->get_result()->fetch_assoc();
$stmtCursos->close();

$totalCursos = (int)($resCursos['total_cursos'] ?? 0);

// Estudiantes únicos
$sqlEst = "
    SELECT COUNT(DISTINCT m.estudiante_id) AS total_estudiantes
    FROM matriculas m
    INNER JOIN horarios h ON m.horario_id = h.id
    WHERE h.docente_id = ?
";
$stmtEst = $mysqli->prepare($sqlEst);
$stmtEst->bind_param("i", $docenteId);
$stmtEst->execute();
$resEst = $stmtEst->get_result()->fetch_assoc();
$stmtEst->close();

$totalEstudiantes = (int)($resEst['total_estudiantes'] ?? 0);

// Tareas creadas
$sqlTar = "
    SELECT COUNT(*) AS total_tareas
    FROM tareas
    WHERE docente_id = ? AND activo = 1
";
$stmtTar = $mysqli->prepare($sqlTar);
$stmtTar->bind_param("i", $docenteId);
$stmtTar->execute();
$resTar = $stmtTar->get_result()->fetch_assoc();
$stmtTar->close();

$totalTareas = (int)($resTar['total_tareas'] ?? 0);

// Últimos anuncios creados por este docente
$sqlAn = "
    SELECT COUNT(*) AS total_anuncios
    FROM anuncios
    WHERE docente_id = ?
";
$stmtAn = $mysqli->prepare($sqlAn);
$stmtAn->bind_param("i", $docenteId);
$stmtAn->execute();
$resAn = $stmtAn->get_result()->fetch_assoc();
$stmtAn->close();

$totalAnuncios = (int)($resAn['total_anuncios'] ?? 0);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-3">
    <h1 class="h4 fw-bold mb-3">Dashboard del docente</h1>
    <p class="text-muted small mb-4">
        Desde aquí puedes gestionar tus cursos, estudiantes, tareas, materiales y anuncios.
    </p>

    <!-- Tarjetas resumen -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="text-muted small mb-1">Cursos que impartes</h6>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="h3 mb-0 fw-bold"><?php echo $totalCursos; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="text-muted small mb-1">Estudiantes distintos</h6>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="h3 mb-0 fw-bold"><?php echo $totalEstudiantes; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="text-muted small mb-1">Tareas activas</h6>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="h3 mb-0 fw-bold"><?php echo $totalTareas; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="text-muted small mb-1">Anuncios creados</h6>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="h3 mb-0 fw-bold"><?php echo $totalAnuncios; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Accesos rápidos -->
    <div class="row g-3">
        <div class="col-md-4">
            <a href="/twintalk/docente/cursos.php" class="text-decoration-none">
                <div class="border rounded-4 p-3 h-100 bg-white">
                    <div class="d-flex align-items-center mb-1">
                        <i class="fa-solid fa-book-open me-2 text-primary"></i>
                        <span class="fw-semibold small">Mis cursos</span>
                    </div>
                    <p class="small text-muted mb-0">
                        Ver la lista de cursos que impartes y acceder a su detalle.
                    </p>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <!-- AQUÍ CORREGIMOS: botón "Estudiantes" va a docente/estudiantes.php -->
            <a href="/twintalk/docente/estudiantes.php" class="text-decoration-none">
                <div class="border rounded-4 p-3 h-100 bg-white">
                    <div class="d-flex align-items-center mb-1">
                        <i class="fa-solid fa-user-graduate me-2 text-success"></i>
                        <span class="fw-semibold small">Estudiantes</span>
                    </div>
                    <p class="small text-muted mb-0">
                        Ver el listado de estudiantes que tienes en tus cursos.
                    </p>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <!-- Aseguramos que Calificaciones vaya a calificaciones.php -->
            <a href="/twintalk/docente/calificaciones.php" class="text-decoration-none">
                <div class="border rounded-4 p-3 h-100 bg-white">
                    <div class="d-flex align-items-center mb-1">
                        <i class="fa-solid fa-list-check me-2 text-warning"></i>
                        <span class="fw-semibold small">Calificaciones</span>
                    </div>
                    <p class="small text-muted mb-0">
                        Ver y gestionar calificaciones de tus estudiantes.
                    </p>
                </div>
            </a>
        </div>

        <!-- NUEVA TARJETA: TAREAS -->
        <div class="col-md-4">
            <a href="/twintalk/docente/tareas.php" class="text-decoration-none">
                <div class="border rounded-4 p-3 h-100 bg-white">
                    <div class="d-flex align-items-center mb-1">
                        <i class="fa-solid fa-file-circle-check me-2 text-info"></i>
                        <span class="fw-semibold small">Tareas</span>
                    </div>
                    <p class="small text-muted mb-0">
                        Crear, ver y eliminar tareas asignadas a tus cursos.
                    </p>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="/twintalk/docente/materiales.php" class="text-decoration-none">
                <div class="border rounded-4 p-3 h-100 bg-white">
                    <div class="d-flex align-items-center mb-1">
                        <i class="fa-solid fa-folder-open me-2 text-secondary"></i>
                        <span class="fw-semibold small">Materiales</span>
                    </div>
                    <p class="small text-muted mb-0">
                        Gestionar archivos y recursos para tus cursos.
                    </p>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="/twintalk/docente/anuncios.php" class="text-decoration-none">
                <div class="border rounded-4 p-3 h-100 bg-white">
                    <div class="d-flex align-items-center mb-1">
                        <i class="fa-solid fa-bullhorn me-2 text-danger"></i>
                        <span class="fw-semibold small">Anuncios</span>
                    </div>
                    <p class="small text-muted mb-0">
                        Publicar y administrar anuncios para tus cursos.
                    </p>
                </div>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
