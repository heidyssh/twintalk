<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]);

$docenteId = $_SESSION['usuario_id'] ?? null;

if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$sql = "
    SELECT 
        h.id AS horario_id,
        c.nombre_curso,
        c.descripcion,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        h.aula,
        h.fecha_inicio,
        h.fecha_fin,
        h.cupos_disponibles
    FROM horarios h
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.docente_id = ?
    ORDER BY d.numero_dia, h.hora_inicio
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $docenteId);
$stmt->execute();
$horarios = $stmt->get_result();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 fw-bold mt-3">Mis Cursos y Horarios</h1>
<p class="text-muted mb-3">Listado de los cursos y clases asignadas.</p>

<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>Curso</th>
            <th>Día</th>
            <th>Hora</th>
            <th>Aula</th>
            <th>Fechas</th>
            <th>Cupos</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $horarios->fetch_assoc()): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($row['nombre_curso']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($row['descripcion']); ?></small>
                </td>
                <td><?php echo htmlspecialchars($row['nombre_dia']); ?></td>
                <td><?php echo htmlspecialchars($row['hora_inicio'] . " - " . $row['hora_fin']); ?></td>
                <td><?php echo htmlspecialchars($row['aula'] ?? 'N/A'); ?></td>
                <td>
                    <?php echo htmlspecialchars($row['fecha_inicio']); ?>
                    <br>
                    <small class="text-muted">al <?php echo htmlspecialchars($row['fecha_fin']); ?></small>
                </td>
                <td><?php echo (int)$row['cupos_disponibles']; ?></td>
                <td>
                    <a href="estudiantes.php?horario_id=<?php echo $row['horario_id']; ?>" class="btn btn-sm btn-outline-secondary mb-1">
                        Estudiantes
                    </a>
                    <a href="calificaciones.php?horario_id=<?php echo $row['horario_id']; ?>" class="btn btn-sm btn-outline-primary mb-1">
                        Calificaciones
                    </a>
                    <a href="materiales.php?horario_id=<?php echo $row['horario_id']; ?>" class="btn btn-sm btn-outline-success mb-1">
                        Materiales
                    </a>
                    <a href="anuncios.php?horario_id=<?php echo $row['horario_id']; ?>" class="btn btn-sm btn-outline-warning mb-1">
                        Anuncios
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include __DIR__ . "/../includes/footer.php"; ?>
