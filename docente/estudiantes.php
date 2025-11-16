<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]);

$docenteId = $_SESSION['usuario_id'] ?? null;

if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$horario_id = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;
if ($horario_id <= 0) {
    die("Horario no válido.");
}

// Verificar que el horario pertenezca al docente
$sqlChk = "SELECT h.id, c.nombre_curso FROM horarios h INNER JOIN cursos c ON h.curso_id = c.id WHERE h.id = ? AND h.docente_id = ?";
$stmt = $mysqli->prepare($sqlChk);
$stmt->bind_param("ii", $horario_id, $docenteId);
$stmt->execute();
$horarioInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$horarioInfo) {
    die("No tienes permiso para ver este horario.");
}

// Obtener estudiantes de ese horario
$sqlEst = "
    SELECT 
        m.id AS matricula_id,
        u.nombre,
        u.apellido,
        u.email,
        e.nivel_actual,
        m.fecha_matricula
    FROM matriculas m
    INNER JOIN estudiantes e ON m.estudiante_id = e.id
    INNER JOIN usuarios u ON e.id = u.id
    WHERE m.horario_id = ?
    ORDER BY u.apellido, u.nombre
";
$stmt = $mysqli->prepare($sqlEst);
$stmt->bind_param("i", $horario_id);
$stmt->execute();
$estudiantes = $stmt->get_result();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 fw-bold mt-3">Estudiantes del curso</h1>
<p class="text-muted mb-2">
    Curso: <strong><?php echo htmlspecialchars($horarioInfo['nombre_curso']); ?></strong><br>
    Horario ID: <?php echo (int)$horario_id; ?>
</p>

<a href="cursos.php" class="btn btn-sm btn-secondary mb-3">&larr; Volver a mis cursos</a>

<table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Nivel actual</th>
            <th>Email</th>
            <th>Fecha matrícula</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $estudiantes->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['apellido'] . ", " . $row['nombre']); ?></td>
                <td><?php echo htmlspecialchars($row['nivel_actual'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['fecha_matricula']); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include __DIR__ . "/../includes/footer.php"; ?>
