<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([2]); // rol docente

$docenteId = $_SESSION['usuario_id'] ?? null;

if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$cursos = $mysqli->prepare("
    SELECT h.id AS horario_id, c.nombre_curso, n.codigo_nivel,
           d.nombre_dia, h.hora_inicio, h.hora_fin, h.aula
    FROM horarios h
    JOIN cursos c ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.docente_id = ?
    ORDER BY h.fecha_inicio DESC
");
$cursos->bind_param("i", $docente_id);
$cursos->execute();
$res_cursos = $cursos->get_result();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Panel del docente</h1>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Mis cursos</h6>
                <p class="mb-2 small">Ver todos los horarios que tengo asignados.</p>
                <a href="cursos.php" class="btn btn-sm btn-primary">Ver cursos</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Estudiantes</h6>
                <p class="mb-2 small">Ver alumnos por curso.</p>
                <a href="cursos.php" class="btn btn-sm btn-outline-primary">
                    Ver lista
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Calificaciones</h6>
<p class="mb-2 small">Registrar y consultar notas por curso.</p>
<a href="calificaciones.php" class="btn btn-sm btn-outline-primary">
    Ver Calificaciones
</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Anuncios y materiales</h6>
                <p class="mb-2 small">Publicar anuncios y subir recursos.</p>
                <a href="anuncios.php" class="btn btn-sm btn-outline-primary mb-1">
                    Anuncios
                </a>
                <a href="materiales.php" class="btn btn-sm btn-outline-secondary">
                    Materiales
                </a>
            </div>
        </div>
    </div>
</div>
<p class="text-muted mb-3">
    Administra tus cursos, registra calificaciones y publica anuncios.
</p>

<div class="card card-soft p-3">
    <h2 class="h6 fw-bold mb-2">Mis cursos</h2>
    <div class="table-responsive table-rounded mt-2">
        <table class="table align-middle">
            <thead class="table-light">
            <tr>
                <th>Curso</th>
                <th>Nivel</th>
                <th>Día</th>
                <th>Hora</th>
                <th>Aula</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($res_cursos->num_rows > 0): ?>
                <?php while ($row = $res_cursos->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nombre_curso']) ?></td>
                        <td><?= htmlspecialchars($row['codigo_nivel']) ?></td>
                        <td><?= htmlspecialchars($row['nombre_dia']) ?></td>
                        <td><?= substr($row['hora_inicio'],0,5) ?> - <?= substr($row['hora_fin'],0,5) ?></td>
                        <td><?= htmlspecialchars($row['aula']) ?></td>
                        <td>
                            <a href="curso_detalle.php?id=<?= (int)$row['horario_id'] ?>"
                               class="btn btn-sm btn-tt-primary">
                                Ver curso
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-muted">Aún no tienes cursos asignados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
