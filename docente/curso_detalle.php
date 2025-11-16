<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]);

$docenteId = $_SESSION['usuario_id'] ?? null;

if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}
$horario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$mensaje = "";
$error = "";

// Verificar que el horario pertenece a este docente
$stmt = $mysqli->prepare("
    SELECT h.*, c.nombre_curso, n.codigo_nivel, d.nombre_dia
    FROM horarios h
    JOIN cursos c ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.id = ? AND h.docente_id = ?
");
$stmt->bind_param("ii", $horario_id, $docente_id);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();

if (!$curso) {
    die("Curso no encontrado o no tienes permisos.");
}

// Registrar calificación rápida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_nota'])) {
    $matricula_id = (int)($_POST['matricula_id'] ?? 0);
    $puntaje      = (float)($_POST['puntaje'] ?? 0);
    $tipo_eval_id = (int)($_POST['tipo_evaluacion_id'] ?? 0);

    if ($matricula_id && $tipo_eval_id && $puntaje >= 0 && $puntaje <= 100) {
        $ins = $mysqli->prepare("
            INSERT INTO calificaciones (matricula_id, tipo_evaluacion_id, puntaje, fecha_evaluacion, publicado)
            VALUES (?, ?, ?, CURDATE(), 1)
        ");
        $ins->bind_param("iid", $matricula_id, $tipo_eval_id, $puntaje);
        if ($ins->execute()) {
            $mensaje = "Calificación registrada.";
        } else {
            $error = "Error al guardar calificación.";
        }
    } else {
        $error = "Datos de calificación inválidos.";
    }
}

// Estudiantes matriculados
$estudiantes = $mysqli->prepare("
    SELECT m.id AS matricula_id,
           u.nombre, u.apellido, u.email
    FROM matriculas m
    JOIN estudiantes e ON m.estudiante_id = e.id
    JOIN usuarios u ON e.id = u.id
    WHERE m.horario_id = ?
");
$estudiantes->bind_param("i", $horario_id);
$estudiantes->execute();
$res_est = $estudiantes->get_result();

// Tipos de evaluación
$tipos_eval = $mysqli->query("SELECT id, nombre_evaluacion FROM tipos_evaluacion ORDER BY nombre_evaluacion");

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3"><?= htmlspecialchars($curso['nombre_curso']) ?></h1>
<p class="small text-muted mb-2">
    Nivel <?= htmlspecialchars($curso['codigo_nivel']) ?> ·
    <?= htmlspecialchars($curso['nombre_dia']) ?> ·
    <?= substr($curso['hora_inicio'],0,5) ?> - <?= substr($curso['hora_fin'],0,5) ?> ·
    Aula <?= htmlspecialchars($curso['aula']) ?>
</p>

<?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card card-soft p-3 mt-3">
    <h2 class="h6 fw-bold mb-2">Estudiantes matriculados</h2>
    <div class="table-responsive table-rounded">
        <table class="table align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>Estudiante</th>
                <th>Correo</th>
                <th>Registrar nota</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($res_est->num_rows > 0): ?>
                <?php while ($row = $res_est->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nombre'] . " " . $row['apellido']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td>
                            <form class="row g-1 align-items-center" method="post">
                                <input type="hidden" name="matricula_id" value="<?= (int)$row['matricula_id'] ?>">
                                <div class="col-auto">
                                    <input type="number" step="0.01" name="puntaje" min="0" max="100"
                                           class="form-control form-control-sm" placeholder="Nota">
                                </div>
                                <div class="col-auto">
                                    <select name="tipo_evaluacion_id" class="form-select form-select-sm">
                                        <?php while ($t = $tipos_eval->fetch_assoc()): ?>
                                            <option value="<?= (int)$t['id'] ?>">
                                                <?= htmlspecialchars($t['nombre_evaluacion']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                        <?php
                                        // reset puntero para el siguiente loop
                                        $tipos_eval->data_seek(0);
                                        ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-sm btn-tt-primary" name="guardar_nota">
                                        Guardar
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3" class="text-muted">No hay estudiantes matriculados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
