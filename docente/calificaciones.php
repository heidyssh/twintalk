<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); // Docente

$usuario_id = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error   = "";

// ------------------ 1. OBTENER CURSOS DEL DOCENTE ------------------
// En esta BD, docentes.id = usuarios.id (FK directa)
$docente_id = $usuario_id;

// Cursos donde este docente tiene horarios
$sqlCursos = "
    SELECT DISTINCT c.id AS curso_id, c.nombre_curso
    FROM horarios h
    INNER JOIN cursos c ON c.id = h.curso_id
    WHERE h.docente_id = ?
    ORDER BY c.nombre_curso
";
$stmt = $mysqli->prepare($sqlCursos);
$stmt->bind_param("i", $docente_id);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Curso seleccionado (por GET)
$curso_id = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : null;

// Tipo de evaluación seleccionado (opcional)
$tipo_evaluacion_id = isset($_GET['tipo_evaluacion_id']) ? (int)$_GET['tipo_evaluacion_id'] : null;

// ------------------ 2. GUARDAR CALIFICACIONES ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_calificaciones'])) {

    $curso_id            = (int)($_POST['curso_id'] ?? 0);
    $tipo_evaluacion_id  = (int)($_POST['tipo_evaluacion_id'] ?? 0);
    $notas               = $_POST['nota'] ?? []; // array [matricula_id => puntaje]

    if ($curso_id <= 0 || $tipo_evaluacion_id <= 0) {
        $error = "Faltan datos del curso o del tipo de evaluación.";
    } else {

        foreach ($notas as $matricula_id => $puntaje) {
            $matricula_id = (int)$matricula_id;
            $puntaje = trim($puntaje);

            if ($puntaje === '') {
                continue; // si está vacío, no hacemos nada
            }

            // Validación simple de nota
            if (!is_numeric($puntaje)) {
                continue;
            }

            // 1) Verificar si ya existe una calificación para esa matrícula + tipo_evaluación
            $sqlExiste = "
                SELECT id
                FROM calificaciones
                WHERE matricula_id = ? AND tipo_evaluacion_id = ?
                LIMIT 1
            ";
            $stmt = $mysqli->prepare($sqlExiste);
            $stmt->bind_param("ii", $matricula_id, $tipo_evaluacion_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res) {
                // 2a) Actualizar
                $calif_id = $res['id'];
                $sqlUpdate = "
                    UPDATE calificaciones
                    SET puntaje = ?, fecha_evaluacion = CURDATE(), publicado = 1
                    WHERE id = ?
                ";
                $stmt = $mysqli->prepare($sqlUpdate);
                $stmt->bind_param("di", $puntaje, $calif_id);
                $stmt->execute();
                $stmt->close();
            } else {
                // 2b) Insertar
                $sqlInsert = "
                    INSERT INTO calificaciones
                    (matricula_id, tipo_evaluacion_id, puntaje, fecha_evaluacion, publicado)
                    VALUES (?, ?, ?, CURDATE(), 1)
                ";
                $stmt = $mysqli->prepare($sqlInsert);
                $stmt->bind_param("iid", $matricula_id, $tipo_evaluacion_id, $puntaje);
                $stmt->execute();
                $stmt->close();
            }
        }

        $mensaje = "Calificaciones guardadas correctamente.";
        // Recargar por GET para evitar reenvío del formulario
        header("Location: calificaciones.php?curso_id={$curso_id}&tipo_evaluacion_id={$tipo_evaluacion_id}");
        exit;
    }
}

// ------------------ 3. LISTAR ESTUDIANTES DEL CURSO SELECCIONADO ------------------
$estudiantes_curso = [];
$tipos_evaluacion = [];

if ($curso_id) {
    // Tipos de evaluación (pueden ser generales; si luego quieres por curso,
    // se le añade relación extra)
    $sqlTipos = "SELECT id, nombre_evaluacion FROM tipos_evaluacion ORDER BY nombre_evaluacion";
    $tipos_evaluacion = $mysqli->query($sqlTipos)->fetch_all(MYSQLI_ASSOC);

    // Estudiantes matriculados en este curso con este docente
    $sqlEst = "
        SELECT 
            m.id AS matricula_id,
            u.nombre,
            u.apellido,
            u.email,
            est.nivel_actual
        FROM matriculas m
        INNER JOIN estudiantes est ON est.id = m.estudiante_id
        INNER JOIN usuarios u ON u.id = est.id
        INNER JOIN horarios h ON h.id = m.horario_id
        WHERE h.curso_id = ? 
          AND h.docente_id = ?
        ORDER BY u.apellido, u.nombre
    ";
    $stmt = $mysqli->prepare($sqlEst);
    $stmt->bind_param("ii", $curso_id, $docente_id);
    $stmt->execute();
    $estudiantes_curso = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Si ya hay tipo_evaluación seleccionado, cargamos calificaciones existentes
    $calificaciones_existentes = [];
    if ($tipo_evaluacion_id) {
        $sqlCal = "
            SELECT matricula_id, puntaje
            FROM calificaciones
            WHERE tipo_evaluacion_id = ?
              AND matricula_id IN (
                  SELECT m.id
                  FROM matriculas m
                  INNER JOIN horarios h ON h.id = m.horario_id
                  WHERE h.curso_id = ? AND h.docente_id = ?
              )
        ";
        $stmt = $mysqli->prepare($sqlCal);
        $stmt->bind_param("iii", $tipo_evaluacion_id, $curso_id, $docente_id);
        $stmt->execute();
        $resCal = $stmt->get_result();
        while ($row = $resCal->fetch_assoc()) {
            $calificaciones_existentes[$row['matricula_id']] = $row['puntaje'];
        }
        $stmt->close();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 fw-bold mt-3">Calificaciones</h1>
<p class="text-muted">Gestiona las calificaciones por curso.</p>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-3">Mis cursos</h2>
            <?php if (empty($cursos)): ?>
                <p class="text-muted small mb-0">No tienes cursos asignados.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($cursos as $c): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center
                            <?= ($curso_id == $c['curso_id']) ? 'active text-white' : '' ?>">
                            <a href="calificaciones.php?curso_id=<?= $c['curso_id'] ?>"
                               class="<?= ($curso_id == $c['curso_id']) ? 'text-white' : '' ?>"
                               style="text-decoration:none;">
                                <?= htmlspecialchars($c['nombre_curso']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-8 mb-3">
        <div class="card card-soft p-3">
            <?php if (!$curso_id): ?>
                <p class="text-muted">Selecciona un curso para ver y registrar calificaciones.</p>
            <?php else: ?>
                <h2 class="h6 fw-bold mb-3">
                    Curso seleccionado:
                    <?php
                    foreach ($cursos as $c) {
                        if ($c['curso_id'] == $curso_id) {
                            echo htmlspecialchars($c['nombre_curso']);
                            break;
                        }
                    }
                    ?>
                </h2>

                <?php if (empty($estudiantes_curso)): ?>
                    <p class="text-muted">No hay estudiantes matriculados en este curso.</p>
                <?php else: ?>

                    <!-- Filtro tipo de evaluación -->
                    <form method="get" class="row g-2 mb-3">
                        <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                        <div class="col-md-8">
                            <label class="form-label small mb-1">Tipo de evaluación</label>
                            <select name="tipo_evaluacion_id" class="form-select form-select-sm" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($tipos_evaluacion as $te): ?>
                                    <option value="<?= $te['id'] ?>"
                                        <?= ($tipo_evaluacion_id == $te['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($te['nombre_evaluacion']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-sm btn-outline-primary w-100">
                                Aplicar
                            </button>
                        </div>
                    </form>

                    <?php if ($tipo_evaluacion_id): ?>
                        <form method="post">
                            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                            <input type="hidden" name="tipo_evaluacion_id" value="<?= $tipo_evaluacion_id ?>">

                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Estudiante</th>
                                            <th>Email</th>
                                            <th class="text-center" style="width:120px;">Nota</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($estudiantes_curso as $est): 
                                            $mat_id = $est['matricula_id'];
                                            $nota_val = $calificaciones_existentes[$mat_id] ?? '';
                                        ?>
                                            <tr>
                                                <td>
                                                    <?= htmlspecialchars($est['apellido'] . ', ' . $est['nombre']) ?>
                                                    <br>
                                                    <small class="text-muted">Nivel: <?= htmlspecialchars($est['nivel_actual'] ?? '-') ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($est['email']) ?></td>
                                                <td class="text-center">
                                                    <input type="text"
                                                           name="nota[<?= $mat_id ?>]"
                                                           class="form-control form-control-sm text-center"
                                                           value="<?= htmlspecialchars($nota_val) ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" name="guardar_calificaciones"
                                    class="btn btn-primary btn-sm mt-2">
                                Guardar calificaciones
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted small">Selecciona un tipo de evaluación para ingresar notas.</p>
                    <?php endif; ?>

                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
