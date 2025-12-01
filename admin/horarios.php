<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]); // solo admin

$mensaje = "";
$error = "";

// Curso filtrado opcional
$curso_id_filtro = isset($_GET['curso_id']) && ctype_digit($_GET['curso_id'])
    ? (int) $_GET['curso_id']
    : 0;

// Cursos activos
$cursos_data = [];
$res_cursos = $mysqli->query("SELECT id, nombre_curso FROM cursos WHERE activo = 1 ORDER BY nombre_curso");
if ($res_cursos) {
    while ($c = $res_cursos->fetch_assoc()) {
        $cursos_data[] = $c;
    }
}

// DOCENTES: todos los usuarios con rol 2 (docente)
$docentes_data = [];
$res_doc = $mysqli->query("
    SELECT u.id, u.nombre, u.apellido
    FROM usuarios u
    WHERE u.rol_id = 2 AND u.activo = 1
    ORDER BY u.nombre, u.apellido
");
if ($res_doc) {
    while ($d = $res_doc->fetch_assoc()) {
        $docentes_data[] = $d;
    }
}

// Días de semana
$dias_data = [];
$res_dias = $mysqli->query("SELECT id, nombre_dia FROM dias_semana ORDER BY numero_dia ASC");
if ($res_dias) {
    while ($d = $res_dias->fetch_assoc()) {
        $dias_data[] = $d;
    }
}

// Crear horario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_horario'])) {
    $curso_id = (int) ($_POST['curso_id'] ?? 0);
    $docente_id = (int) ($_POST['docente_id'] ?? 0);
    $dia_semana_id = (int) ($_POST['dia_semana_id'] ?? 0);
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $aula = trim($_POST['aula'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $cupos = (int) ($_POST['cupos_disponibles'] ?? 0);

    // Calcular fecha_fin automáticamente: 3 meses después de fecha_inicio
    if ($fecha_inicio !== '') {
        $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . ' +3 months'));
    } else {
        $fecha_fin = '';
    }

    if (
        $curso_id <= 0 || $docente_id <= 0 || $dia_semana_id <= 0 ||
        $hora_inicio === '' || $hora_fin === '' || $fecha_inicio === '' ||
        $cupos <= 0
    ) {
        $error = "Completa todos los campos obligatorios del horario.";
    } else {
        // 🔗 Asegurar que el docente también exista en la tabla `docentes`
        $stmtCheck = $mysqli->prepare("SELECT id FROM docentes WHERE id = ?");
        $stmtCheck->bind_param("i", $docente_id);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows == 0) {
            // Crear registro mínimo en docentes (titulo_id y especialidad pueden quedar NULL)
            $stmtInsDoc = $mysqli->prepare("
                INSERT INTO docentes (id, fecha_contratacion, activo)
                VALUES (?, CURDATE(), 1)
            ");
            $stmtInsDoc->bind_param("i", $docente_id);
            $stmtInsDoc->execute();
            $stmtInsDoc->close();
        }
        $stmtCheck->close();

        // Crear horario (ya puede referenciar a docentes.id sin romper FK)
        $stmt = $mysqli->prepare("
            INSERT INTO horarios
                (curso_id, docente_id, dia_semana_id, hora_inicio, hora_fin, aula,
                 fecha_inicio, fecha_fin, cupos_disponibles, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param(
            "iiisssssi",
            $curso_id,
            $docente_id,
            $dia_semana_id,
            $hora_inicio,
            $hora_fin,
            $aula,
            $fecha_inicio,
            $fecha_fin,
            $cupos
        );

        if ($stmt->execute()) {
            $mensaje = "Horario creado correctamente y vinculado al docente.";
        } else {
            $error = "Error al crear el horario: " . $mysqli->error;
        }
        $stmt->close();
    }
}

// Desactivar horario
if (isset($_GET['desactivar']) && ctype_digit($_GET['desactivar'])) {
    $id_h = (int) $_GET['desactivar'];
    $stmt = $mysqli->prepare("UPDATE horarios SET activo = 0 WHERE id = ?");
    $stmt->bind_param("i", $id_h);
    if ($stmt->execute()) {
        $mensaje = "Horario desactivado.";
    } else {
        $error = "No se pudo desactivar el horario.";
    }
    $stmt->close();
}

// Listar horarios
$sqlHorarios = "
    SELECT h.*, c.nombre_curso,
           u.nombre AS docente_nombre, u.apellido AS docente_apellido,
           d2.nombre_dia
    FROM horarios h
    JOIN cursos c       ON h.curso_id = c.id
    JOIN usuarios u     ON h.docente_id = u.id
    JOIN dias_semana d2 ON h.dia_semana_id = d2.id
";
if ($curso_id_filtro > 0) {
    $sqlHorarios .= " WHERE h.curso_id = " . $curso_id_filtro;
}
$sqlHorarios .= " ORDER BY h.fecha_inicio DESC, h.hora_inicio ASC";

$horarios = $mysqli->query($sqlHorarios);

include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">
    <h1 class="h4 fw-bold mb-3">Gestión de Horarios</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Formulario de nuevo horario -->
        <div class="col-lg-4">
            <div class="card card-soft p-3">
                <h5 class="card-title mb-3">Nuevo horario</h5>
                <form method="post">
                    <div class="mb-2">
                        <label class="form-label">Curso *</label>
                        <select name="curso_id" class="form-select" required>
                            <option value="">Selecciona un curso</option>
                            <?php foreach ($cursos_data as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= $curso_id_filtro == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nombre_curso']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Docente *</label>
                        <select name="docente_id" class="form-select" required>
                            <option value="">Selecciona un docente</option>
                            <?php foreach ($docentes_data as $d): ?>
                                <option value="<?= (int) $d['id'] ?>">
                                    <?= htmlspecialchars($d['nombre'] . " " . $d['apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Si el docente fue creado como usuario con rol Docente, aparecerá aquí.
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Día de la semana *</label>
                        <select name="dia_semana_id" class="form-select" required>
                            <option value="">Selecciona un día</option>
                            <?php foreach ($dias_data as $d): ?>
                                <option value="<?= (int) $d['id'] ?>">
                                    <?= htmlspecialchars($d['nombre_dia']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Hora inicio *</label>
                            <input type="time" name="hora_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Hora fin *</label>
                            <input type="time" name="hora_fin" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Aula</label>
                        <input type="text" name="aula" class="form-control" placeholder="Ej: Aula 1">
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Fecha inicio *</label>
                            <input type="date" name="fecha_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Fecha fin (automática)</label>
                            <input type="date" name="fecha_fin" class="form-control" readonly>
                            <div class="form-text">
                                Se calculará automáticamente a 3 meses de la fecha de inicio.
                            </div>
                        </div>
                    </div>


                    <div class="mb-2">
                        <label class="form-label">Cupos disponibles *</label>
                        <input type="number" name="cupos_disponibles" min="1" class="form-control" value="10" required>
                    </div>

                    <div class="mt-3 text-end">
                        <button type="submit" name="crear_horario" class="btn btn-tt-primary btn-sm">
                            Crear horario
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Listado de horarios -->
        <div class="col-lg-8">
            <div class="card card-soft p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Horarios creados</h5>
                </div>

                <form method="get" class="row g-2 mb-3">
                    <div class="col-md-6">
                        <select name="curso_id" class="form-select">
                            <option value="0">Todos los cursos</option>
                            <?php foreach ($cursos_data as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= $curso_id_filtro == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nombre_curso']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            Filtrar
                        </button>
                    </div>
                    <div class="col-md-3">
                        <a href="horarios.php" class="btn btn-outline-secondary w-100">
                            Limpiar filtro
                        </a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Curso</th>
                                <th>Docente</th>
                                <th>Día</th>
                                <th>Hora</th>
                                <th>Fechas</th>
                                <th>Cupos</th>
                                <th>Estado</th>
                                <th>Acc.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($horarios && $horarios->num_rows > 0): ?>
                                <?php while ($h = $horarios->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($h['nombre_curso']) ?></td>
                                        <td><?= htmlspecialchars($h['docente_nombre'] . " " . $h['docente_apellido']) ?></td>
                                        <td><?= htmlspecialchars($h['nombre_dia']) ?></td>
                                        <td><?= htmlspecialchars(substr($h['hora_inicio'], 0, 5) . " - " . substr($h['hora_fin'], 0, 5)) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($h['fecha_inicio']) ?><br>
                                            <span class="small text-muted">hasta</span><br>
                                            <?= htmlspecialchars($h['fecha_fin']) ?>
                                        </td>
                                        <td><?= (int) $h['cupos_disponibles'] ?></td>
                                        <td>
                                            <?php if ($h['activo']): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($h['activo']): ?>
                                                <a href="horarios.php?desactivar=<?= (int) $h['id'] ?>"
                                                    class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirm('¿Desactivar este horario?');">
                                                    Desactivar
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-muted">No hay horarios registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <p class="small text-muted mb-0">
                    Los horarios activos son los que verá el docente en su panel y el estudiante en la matrícula.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>