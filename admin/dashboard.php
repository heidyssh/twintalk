<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]);

$nombre_admin = $_SESSION['nombre'] ?? 'Administrador';

// Helper rápido para contar
function tt_contar($mysqli, $sql) {
    $res = $mysqli->query($sql);
    if ($res && ($row = $res->fetch_assoc())) {
        return (int)($row['c'] ?? 0);
    }
    return 0;
}

$total_usuarios    = tt_contar($mysqli, "SELECT COUNT(*) AS c FROM usuarios");
$total_estudiantes = tt_contar($mysqli, "SELECT COUNT(*) AS c FROM estudiantes");
$total_docentes    = tt_contar($mysqli, "SELECT COUNT(*) AS c FROM docentes");
$total_cursos      = tt_contar($mysqli, "SELECT COUNT(*) AS c FROM cursos");
$total_matriculas  = tt_contar($mysqli, "SELECT COUNT(*) AS c FROM matriculas");

$total_anuncios         = 0;
$total_materiales       = 0;
$total_horarios_activos = 0;

$resExtras = $mysqli->query("
    SELECT
        (SELECT COUNT(*) FROM anuncios)   AS total_anuncios,
        (SELECT COUNT(*) FROM materiales) AS total_materiales,
        (SELECT COUNT(*) FROM horarios WHERE activo = 1) AS total_horarios_activos
");
if ($resExtras && ($rowE = $resExtras->fetch_assoc())) {
    $total_anuncios         = (int)($rowE['total_anuncios'] ?? 0);
    $total_materiales       = (int)($rowE['total_materiales'] ?? 0);
    $total_horarios_activos = (int)($rowE['total_horarios_activos'] ?? 0);
}

$ultimos_usuarios = $mysqli->query("
    SELECT u.id, u.nombre, u.apellido, u.email, u.fecha_registro, r.nombre_rol
    FROM usuarios u
    JOIN roles r ON u.rol_id = r.id
    ORDER BY u.fecha_registro DESC
    LIMIT 5
");

$proximos_horarios = $mysqli->query("
    SELECT h.*, c.nombre_curso,
           u.nombre AS docente_nombre, u.apellido AS docente_apellido,
           d.nombre_dia
    FROM horarios h
    JOIN cursos c       ON h.curso_id = c.id
    JOIN usuarios u     ON h.docente_id = u.id
    JOIN dias_semana d  ON h.dia_semana_id = d.id
    WHERE h.activo = 1
    ORDER BY h.fecha_inicio ASC, h.hora_inicio ASC
    LIMIT 5
");

include __DIR__ . "/../includes/header.php";
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="hero-card p-4 p-md-5">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <span class="hero-pill mb-2">
                        <i class="fa-solid fa-shield-halved me-1"></i>
                        Panel de administración
                    </span>
                    <h1 class="h3 h-md-2 fw-bold mb-2">
                        Hola, <?= htmlspecialchars($nombre_admin) ?> 👋
                    </h1>
                    <p class="text-muted mb-3 mb-md-4">
                        Desde aquí controlas usuarios, cursos, docentes y matrículas
                        de <strong>TwinTalk English</strong>.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="usuarios.php" class="btn btn-tt-primary btn-sm">
                            <i class="fa-solid fa-users-gear me-1"></i> Gestionar usuarios
                        </a>
                        <a href="cursos.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                            <i class="fa-solid fa-book-open me-1"></i> Cursos y niveles
                        </a>
                        <a href="horarios.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                            <i class="fa-regular fa-calendar-days me-1"></i> Horarios
                        </a>
                        <a href="perfil.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                            <i class="fa-regular fa-id-badge me-1"></i> Mi perfil
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-soft bg-white p-3 p-md-4 h-100">
                        <p class="small text-muted mb-1">Resumen rápido</p>
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-3">
                                <span class="badge rounded-pill bg-light text-secondary">
                                    <i class="fa-solid fa-user-graduate me-1"></i> Estudiantes
                                </span>
                            </div>
                            <div class="ms-auto fw-bold fs-5"><?= $total_estudiantes ?></div>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-3">
                                <span class="badge rounded-pill bg-light text-secondary">
                                    <i class="fa-solid fa-chalkboard-user me-1"></i> Docentes
                                </span>
                            </div>
                            <div class="ms-auto fw-bold fs-5"><?= $total_docentes ?></div>
                        </div>
                        <div class="d-flex align-items-center mb-1">
                            <div class="me-3">
                                <span class="badge rounded-pill bg-light text-secondary">
                                    <i class="fa-solid fa-layer-group me-1"></i> Cursos activos
                                </span>
                            </div>
                            <div class="ms-auto fw-bold fs-5"><?= $total_cursos ?></div>
                        </div>
                        <hr class="my-2">
                        <p class="small text-muted mb-0">
                            Total de usuarios en el sistema: <strong><?= $total_usuarios ?></strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tarjetas de métricas principales con enlaces -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <a href="usuarios.php" class="text-decoration-none text-reset">
            <div class="card card-soft p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="small text-muted">Matrículas</span>
                    <i class="fa-solid fa-receipt text-secondary"></i>
                </div>
                <p class="display-6 fw-bold mb-0"><?= $total_matriculas ?></p>
                <p class="small text-muted mb-0">
                    Registros de matrícula. Gestiona estudiantes desde <strong>Usuarios</strong>.
                </p>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="horarios.php" class="text-decoration-none text-reset">
            <div class="card card-soft p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="small text-muted">Horarios activos</span>
                    <i class="fa-regular fa-clock text-secondary"></i>
                </div>
                <p class="display-6 fw-bold mb-0"><?= $total_horarios_activos ?></p>
                <p class="small text-muted mb-0">
                    Grupos abiertos. Adminístralos desde <strong>Horarios</strong>.
                </p>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="cursos.php" class="text-decoration-none text-reset">
            <div class="card card-soft p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="small text-muted">Materiales</span>
                    <i class="fa-solid fa-file-circle-check text-secondary"></i>
                </div>
                <p class="display-6 fw-bold mb-0"><?= $total_materiales ?></p>
                <p class="small text-muted mb-0">
                    Recursos subidos por docentes, organizados por curso.
                </p>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="cursos.php" class="text-decoration-none text-reset">
            <div class="card card-soft p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="small text-muted">Anuncios</span>
                    <i class="fa-solid fa-bullhorn text-secondary"></i>
                </div>
                <p class="display-6 fw-bold mb-0"><?= $total_anuncios ?></p>
                <p class="small text-muted mb-0">
                    Comunicados de los cursos. Relacionados con los horarios y grupos.
                </p>
            </div>
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Columna izquierda -->
    <div class="col-lg-6">
        <div class="card card-soft p-3 mb-3">
            <h2 class="h6 fw-bold mb-2">
                <i class="fa-solid fa-bolt me-1 text-warning"></i> Accesos rápidos
            </h2>
            <p class="small text-muted mb-3">
                Atajos a las secciones que más vas a usar como administrador.
            </p>
            <div class="row g-2">
                <div class="col-6">
                    <a href="usuarios.php" class="text-decoration-none">
                        <div class="border rounded-4 p-3 h-100 bg-white">
                            <div class="d-flex align-items-center mb-1">
                                <i class="fa-solid fa-users-gear me-2 text-primary"></i>
                                <span class="fw-semibold small">Usuarios y roles</span>
                            </div>
                            <p class="small text-muted mb-0">
                                Crear cuentas, activar/desactivar y cambiar roles.
                            </p>
                        </div>
                    </a>
                </div>
                <div class="col-6">
                    <a href="cursos.php" class="text-decoration-none">
                        <div class="border rounded-4 p-3 h-100 bg-white">
                            <div class="d-flex align-items-center mb-1">
                                <i class="fa-solid fa-book-open-reader me-2 text-success"></i>
                                <span class="fw-semibold small">Gestión de cursos</span>
                            </div>
                            <p class="small text-muted mb-0">
                                Crear y actualizar cursos y niveles académicos.
                            </p>
                        </div>
                    </a>
                </div>
                <div class="col-6">
                    <a href="horarios.php" class="text-decoration-none">
                        <div class="border rounded-4 p-3 h-100 bg-white">
                            <div class="d-flex align-items-center mb-1">
                                <i class="fa-regular fa-calendar-days me-2 text-info"></i>
                                <span class="fw-semibold small">Asignar horarios</span>
                            </div>
                            <p class="small text-muted mb-0">
                                Vincular cursos con docentes y definir grupos.
                            </p>
                        </div>
                    </a>
                </div>
                <div class="col-6">
    <a href="/twintalk/index.php?public=1" class="text-decoration-none">
        <div class="border rounded-4 p-3 h-100 bg-white">
            <div class="d-flex align-items-center mb-1">
                <i class="fa-solid fa-globe me-2 text-secondary"></i>
                <span class="fw-semibold small">Vista pública</span>
            </div>
            <p class="small text-muted mb-0">
                Ir al sitio principal que ven los visitantes.
            </p>
        </div>
    </a>
</div>
            </div>
        </div>

        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-2">
                <i class="fa-regular fa-user me-1 text-primary"></i> Últimos usuarios registrados
            </h2>
            <div class="table-responsive">
                <table class="table table-sm table-borderless align-middle mb-0">
                    <tbody>
                    <?php if ($ultimos_usuarios && $ultimos_usuarios->num_rows > 0): ?>
                        <?php while ($u = $ultimos_usuarios->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2 bg-light d-flex align-items-center justify-content-center">
                                            <i class="fa-regular fa-circle-user text-secondary"></i>
                                        </div>
                                        <div>
                                            <div class="small fw-semibold">
                                                <?= htmlspecialchars($u['nombre'] . " " . $u['apellido']) ?>
                                            </div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars($u['email']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-light text-secondary">
                                        <?= htmlspecialchars($u['nombre_rol']) ?>
                                    </span>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars(substr($u['fecha_registro'], 0, 10)) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td class="small text-muted">
                                Aún no hay usuarios registrados aparte del administrador.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Columna derecha -->
    <div class="col-lg-6">
        <div class="card card-soft p-3 mb-3">
            <h2 class="h6 fw-bold mb-2">
                <i class="fa-regular fa-calendar-check me-1 text-success"></i> Próximas clases / grupos
            </h2>
            <p class="small text-muted mb-2">
                Horarios activos ordenados por fecha de inicio y hora.
            </p>

            <?php if ($proximos_horarios && $proximos_horarios->num_rows > 0): ?>
                <div class="list-group list-group-flush">
                    <?php while ($h = $proximos_horarios->fetch_assoc()): ?>
                        <a href="horarios.php?curso_id=<?= (int)$h['curso_id'] ?>" class="list-group-item px-0 text-decoration-none text-reset">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="fw-semibold small">
                                        <?= htmlspecialchars($h['nombre_curso']) ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars($h['nombre_dia']) ?>
                                        ·
                                        <?= htmlspecialchars(substr($h['hora_inicio'], 0, 5)) ?>
                                        -
                                        <?= htmlspecialchars(substr($h['hora_fin'], 0, 5)) ?>
                                        <?php if (!empty($h['aula'])): ?>
                                            · Aula <?= htmlspecialchars($h['aula']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted">
                                        Docente:
                                        <?= htmlspecialchars(($h['docente_nombre'] ?? '') . " " . ($h['docente_apellido'] ?? '')) ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-secondary mb-1">
                                        Inicio: <?= htmlspecialchars($h['fecha_inicio']) ?>
                                    </span>
                                    <div class="small text-muted">
                                        Cupos: <?= (int)$h['cupos_disponibles'] ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-0">
                    Aún no has creado horarios activos. Hazlo desde el módulo de <strong>Horarios</strong>.
                </p>
            <?php endif; ?>
        </div>

        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-2">
                <i class="fa-solid fa-chart-line me-1 text-info"></i> Estado general del sistema
            </h2>
            <p class="small text-muted mb-3">
                Vista rápida de cómo se está utilizando TwinTalk English.
            </p>

            <div class="row g-2">
                <div class="col-6">
                    <a href="usuarios.php" class="text-decoration-none text-reset">
                        <div class="border rounded-4 p-3 bg-white h-100">
                            <div class="small text-muted mb-1">Usuarios activos</div>
                            <div class="fw-bold fs-5 mb-1"><?= $total_usuarios ?></div>
                            <div class="small text-muted">
                                Suma de administradores, docentes y estudiantes.
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6">
                    <a href="cursos.php" class="text-decoration-none text-reset">
                        <div class="border rounded-4 p-3 bg-white h-100">
                            <div class="small text-muted mb-1">Cursos registrados</div>
                            <div class="fw-bold fs-5 mb-1"><?= $total_cursos ?></div>
                            <div class="small text-muted">
                                Todos los cursos configurados en el sistema.
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6">
                    <a href="cursos.php" class="text-decoration-none text-reset">
                        <div class="border rounded-4 p-3 bg-white h-100">
                            <div class="small text-muted mb-1">Materiales subidos</div>
                            <div class="fw-bold fs-5 mb-1"><?= $total_materiales ?></div>
                            <div class="small text-muted">
                                Recursos de clase vinculados a cursos y horarios.
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6">
                    <a href="cursos.php" class="text-decoration-none text-reset">
                        <div class="border rounded-4 p-3 bg-white h-100">
                            <div class="small text-muted mb-1">Anuncios publicados</div>
                            <div class="fw-bold fs-5 mb-1"><?= $total_anuncios ?></div>
                            <div class="small text-muted">
                                Comunicados que ven estudiantes y docentes.
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
