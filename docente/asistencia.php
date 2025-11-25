<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); // solo docentes

$docenteId = $_SESSION['usuario_id'] ?? null;
if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error   = "";

// -----------------------------
// 1. Recibir horario_id
// -----------------------------
$horario_id = isset($_GET['horario_id']) ? (int)$_GET['horario_id'] : 0;
if ($horario_id <= 0) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container mt-4">
            <div class="alert alert-danger">Horario no válido.</div>
          </div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// -----------------------------
// 2. Verificar que el horario pertenezca a este docente
// -----------------------------
$stmt = $mysqli->prepare("
    SELECT h.id,
           c.nombre_curso,
           c.descripcion,
           h.hora_inicio,
           h.hora_fin,
           h.aula
    FROM horarios h
    INNER JOIN cursos c ON h.curso_id = c.id
    WHERE h.id = ? AND h.docente_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $horario_id, $docenteId);
$stmt->execute();
$infoHorario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$infoHorario) {
    include __DIR__ . '/../includes/header.php';
    echo '<div class="container mt-4">
            <div class="alert alert-danger">
                No tienes permiso para gestionar la asistencia de este horario.
            </div>
          </div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// -----------------------------
// 3. Determinar fecha de clase
// -----------------------------
if (isset($_GET['fecha']) && $_GET['fecha'] !== '') {
    $fecha_clase = $_GET['fecha'];
} else {
    $fecha_clase = date("Y-m-d");
}

// Validar formato básico de la fecha (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_clase)) {
    $fecha_clase = date("Y-m-d");
}

// -----------------------------
// 4. Procesar envío de asistencia (POST)
// -----------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fecha_clase_post = $_POST['fecha_clase'] ?? date("Y-m-d");
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_clase_post)) {
        $fecha_clase_post = date("Y-m-d");
    }
    $fecha_clase = $fecha_clase_post; // mantener la fecha seleccionada

    if (!isset($_POST['asistencia']) || !is_array($_POST['asistencia']) || count($_POST['asistencia']) === 0) {
        $error = "No se recibió información de asistencia.";
    } else {
        foreach ($_POST['asistencia'] as $matricula_id => $valor) {
            $matricula_id = (int)$matricula_id;
            $presente     = ($valor == "1") ? 1 : 0;

            // Verificar si ya existe registro de asistencia para ese día
            $check = $mysqli->prepare("
                SELECT id
                FROM asistencia
                WHERE matricula_id = ? AND fecha_clase = ?
                LIMIT 1
            ");
            $check->bind_param("is", $matricula_id, $fecha_clase_post);
            $check->execute();
            $resCheck = $check->get_result();
            $rowAsis  = $resCheck->fetch_assoc();
            $check->close();

            if ($rowAsis) {
                // Actualizar
                $update = $mysqli->prepare("
                    UPDATE asistencia
                    SET presente = ?
                    WHERE id = ?
                ");
                $asis_id = (int)$rowAsis['id'];
                $update->bind_param("ii", $presente, $asis_id);
                $update->execute();
                $update->close();
            } else {
                // Insertar
                $insert = $mysqli->prepare("
                    INSERT INTO asistencia (matricula_id, fecha_clase, presente)
                    VALUES (?, ?, ?)
                ");
                $insert->bind_param("isi", $matricula_id, $fecha_clase_post, $presente);
                $insert->execute();
                $insert->close();
            }
        }

        $mensaje = "Asistencia registrada correctamente para la fecha $fecha_clase_post.";
    }
}

// -----------------------------
// 5. Obtener estudiantes matriculados en ese horario
// -----------------------------
$stmtEst = $mysqli->prepare("
    SELECT m.id AS matricula_id,
           u.nombre,
           u.apellido,
           u.foto_perfil
    FROM matriculas m
    INNER JOIN estudiantes e ON m.estudiante_id = e.id
    INNER JOIN usuarios u ON e.id = u.id
    WHERE m.horario_id = ?
      AND m.estado_id = 1
    ORDER BY u.nombre, u.apellido
");
$stmtEst->bind_param("i", $horario_id);
$stmtEst->execute();
$resEst = $stmtEst->get_result();

$matriculas_ids = [];
while ($row = $resEst->fetch_assoc()) {
    $matriculas_ids[] = $row['matricula_id'];
    $listaEstudiantes[] = $row;
}
$stmtEst->close();

// Si no hay estudiantes, mostramos mensaje
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">Registro de asistencia</h4>
            <small class="text-muted">
                Curso: <strong><?= htmlspecialchars($infoHorario['nombre_curso']) ?></strong>
                &middot; Aula: <?= htmlspecialchars($infoHorario['aula']) ?>
            </small>
        </div>

        <!-- Selección de fecha -->
        <form method="get" class="d-flex align-items-center gap-2">
            <input type="hidden" name="horario_id" value="<?= $horario_id ?>">
            <label class="form-label mb-0 small">Fecha:</label>
            <input type="date"
                   name="fecha"
                   class="form-control form-control-sm"
                   value="<?= htmlspecialchars($fecha_clase) ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                Ir
            </button>
        </form>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($listaEstudiantes)): ?>
        <div class="alert alert-warning mt-3">
            No hay estudiantes matriculados en este horario con matrícula activa.
        </div>
    <?php else: ?>

        <?php
        // -----------------------------
        // 6. Cargar asistencia existente para esa fecha
        // -----------------------------
        $asistencia_existente = [];

        if (!empty($matriculas_ids)) {
            $ids_in = implode(',', array_map('intval', $matriculas_ids));

            $sqlAsis = "
                SELECT matricula_id, presente
                FROM asistencia
                WHERE fecha_clase = ?
                  AND matricula_id IN ($ids_in)
            ";
            $stmtAsis = $mysqli->prepare($sqlAsis);
            $stmtAsis->bind_param("s", $fecha_clase);
            $stmtAsis->execute();
            $resAsis = $stmtAsis->get_result();
            while ($rowA = $resAsis->fetch_assoc()) {
                $asistencia_existente[(int)$rowA['matricula_id']] = (int)$rowA['presente'];
            }
            $stmtAsis->close();
        }
        ?>

        <form method="post" class="mt-3">

            <input type="hidden" name="fecha_clase" value="<?= htmlspecialchars($fecha_clase) ?>">

            <div class="card shadow-sm">
                <div class="card-body">

                    <p class="mb-3">
                        Marca la asistencia de los estudiantes para la fecha:
                        <strong><?= htmlspecialchars($fecha_clase) ?></strong>
                    </p>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:70px;">Foto</th>
                                    <th>Estudiante</th>
                                    <th style="width:200px;">Asistencia</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($listaEstudiantes as $est): 
                                $mat_id = (int)$est['matricula_id'];
                                // valor por defecto: presente (1) si no hay registro
                                $valor_actual = $asistencia_existente[$mat_id] ?? 1;
                            ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($est['foto_perfil'])): ?>
                                            <img src="<?= htmlspecialchars($est['foto_perfil']) ?>"
                                                 class="rounded-circle"
                                                 alt="Foto"
                                                 width="48"
                                                 height="48">
                                        <?php else: ?>
                                            <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center"
                                                 style="width:48px;height:48px;">
                                                <i class="fa-solid fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($est['nombre'] . ' ' . $est['apellido']) ?></strong>
                                    </td>
                                    <td>
                                        <select name="asistencia[<?= $mat_id ?>]" class="form-select form-select-sm">
                                            <option value="1" <?= $valor_actual == 1 ? 'selected' : '' ?>>
                                                Presente
                                            </option>
                                            <option value="0" <?= $valor_actual == 0 ? 'selected' : '' ?>>
                                                Ausente
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3 w-100">
                        Guardar asistencia
                    </button>

                </div>
            </div>

        </form>

    <?php endif; ?>

</div>

<?php
include __DIR__ . '/../includes/footer.php';
