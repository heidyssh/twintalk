<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); // Docente

$docenteId = $_SESSION['usuario_id'] ?? null;
if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error = "";

// -----------------------------
// 1. Parámetros de navegación
// -----------------------------
$view = isset($_GET['view']) ? $_GET['view'] : 'evaluaciones'; // evaluaciones | tareas
$curso_id = isset($_GET['curso_id']) ? (int) $_GET['curso_id'] : 0;
$tipo_evaluacion_id = isset($_GET['tipo_evaluacion_id']) ? (int) $_GET['tipo_evaluacion_id'] : 0;
$tarea_id = isset($_GET['tarea_id']) ? (int) $_GET['tarea_id'] : 0;

// Normalizar vista
if ($view !== 'evaluaciones' && $view !== 'tareas') {
    $view = 'evaluaciones';
}

// -----------------------------------------------------
// 2. Cursos del docente (para ambos modos)
// -----------------------------------------------------
$sqlCursos = "
    SELECT DISTINCT c.id AS curso_id, c.nombre_curso
    FROM horarios h
    INNER JOIN cursos c ON c.id = h.curso_id
    WHERE h.docente_id = ?
    ORDER BY c.nombre_curso
";
$stmt = $mysqli->prepare($sqlCursos);
$stmt->bind_param("i", $docenteId);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Si no hay curso seleccionado y el docente tiene al menos uno, usamos el primero
if ($curso_id <= 0 && !empty($cursos)) {
    $curso_id = (int) $cursos[0]['curso_id'];
}

// -----------------------------------------------------
// 3. ACCIONES POST
//    A) Guardar calificaciones de evaluación general
//    B) Guardar calificaciones de una tarea específica
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Acción genérica (extender fechas de tareas)
    $accion = $_POST['accion'] ?? '';

    // 1) Extender fecha de una tarea para TODOS los alumnos del curso
    if ($accion === 'extender_general_tarea') {
        $tarea_id = (int) ($_POST['tarea_id'] ?? 0);
        $curso_id = (int) ($_POST['curso_id'] ?? 0);
        $nueva_fecha = $_POST['nueva_fecha'] ?? '';

        if ($tarea_id > 0 && $curso_id > 0 && $nueva_fecha !== '') {

            // Borrar extensiones generales anteriores de esta tarea
            $del = $mysqli->prepare("
            DELETE FROM tareas_extensiones
            WHERE tarea_id = ? AND matricula_id IS NULL
        ");
            $del->bind_param("i", $tarea_id);
            $del->execute();
            $del->close();

            // Insertar la NUEVA fecha general
            $stmt = $mysqli->prepare("
            INSERT INTO tareas_extensiones (tarea_id, matricula_id, nueva_fecha)
            VALUES (?, NULL, ?)
        ");
            $stmt->bind_param("is", $tarea_id, $nueva_fecha);
            if ($stmt->execute()) {
                $mensaje = "Se extendió la fecha de la tarea para toda la clase.";
            } else {
                $error = "No se pudo guardar la extensión general.";
            }
            $stmt->close();

            // (Opcional) actualizar la fecha base de la tarea
            $stmt = $mysqli->prepare("UPDATE tareas SET fecha_entrega = ? WHERE id = ?");
            $stmt->bind_param("si", $nueva_fecha, $tarea_id);
            $stmt->execute();
            $stmt->close();

            header("Location: calificaciones.php?view=tareas&curso_id={$curso_id}&tarea_id={$tarea_id}");
            exit;
        } else {
            $error = "Datos inválidos para extensión general.";
        }
    }

    // 2) Extender fecha SOLO para un alumno (matricula específica)
    if ($accion === 'extender_tarea_alumno') {
        $tarea_id = (int) ($_POST['tarea_id'] ?? 0);
        $curso_id = (int) ($_POST['curso_id'] ?? 0);
        $matricula_id = (int) ($_POST['matricula_id'] ?? 0);
        $nueva_fecha = $_POST['nueva_fecha'] ?? '';

        if ($tarea_id > 0 && $curso_id > 0 && $matricula_id > 0 && $nueva_fecha !== '') {

            // Borrar extensiones previas para este alumno en esta tarea
            $del = $mysqli->prepare("
            DELETE FROM tareas_extensiones
            WHERE tarea_id = ? AND matricula_id = ?
        ");
            $del->bind_param("ii", $tarea_id, $matricula_id);
            $del->execute();
            $del->close();

            // Insertar la nueva fecha del alumno
            $stmt = $mysqli->prepare("
            INSERT INTO tareas_extensiones (tarea_id, matricula_id, nueva_fecha)
            VALUES (?, ?, ?)
        ");
            $stmt->bind_param("iis", $tarea_id, $matricula_id, $nueva_fecha);
            if ($stmt->execute()) {
                $mensaje = "Se extendió la fecha para ese alumno.";
            } else {
                $error = "No se pudo guardar la extensión individual.";
            }
            $stmt->close();

            header("Location: calificaciones.php?view=tareas&curso_id={$curso_id}&tarea_id={$tarea_id}");
            exit;
        } else {
            $error = "Datos inválidos para extensión individual.";
        }
    }

    // 2) (bloque duplicado que ya tenías – lo dejo igual para no tocar lógica)
    if ($accion === 'extender_tarea_alumno') {
        $tarea_id = (int) ($_POST['tarea_id'] ?? 0);
        $curso_id = (int) ($_POST['curso_id'] ?? 0);
        $matricula_id = (int) ($_POST['matricula_id'] ?? 0);
        $nueva_fecha = $_POST['nueva_fecha'] ?? '';

        if ($tarea_id > 0 && $curso_id > 0 && $matricula_id > 0 && $nueva_fecha !== '') {

            $stmt = $mysqli->prepare("
                INSERT INTO tareas_extensiones (tarea_id, matricula_id, nueva_fecha)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $tarea_id, $matricula_id, $nueva_fecha);
            if ($stmt->execute()) {
                $mensaje = "Se extendió la fecha para ese alumno.";
            } else {
                $error = "No se pudo guardar la extensión individual.";
            }
            $stmt->close();

            header("Location: calificaciones.php?view=tareas&curso_id={$curso_id}&tarea_id={$tarea_id}");
            exit;
        } else {
            $error = "Datos inválidos para extensión individual.";
        }
    }

    // A) Guardar calificaciones generales (quiz, examen, etc.)
    if (isset($_POST['guardar_calificaciones'])) {

        $curso_id = (int) ($_POST['curso_id'] ?? 0);
        $tipo_evaluacion_id = (int) ($_POST['tipo_evaluacion_id'] ?? 0);
        $notas = $_POST['nota'] ?? []; // [matricula_id => puntaje]

        if ($curso_id <= 0 || $tipo_evaluacion_id <= 0) {
            $error = "Faltan datos del curso o del tipo de evaluación.";
        } else {

            foreach ($notas as $matricula_id => $puntaje) {
                $matricula_id = (int) $matricula_id;
                $puntaje = trim($puntaje);

                if ($puntaje === '') {
                    continue;
                }
                if (!is_numeric($puntaje)) {
                    continue;
                }
                $puntaje = (float) $puntaje;

                // Verificar si ya existe una calificación
                $sqlExiste = "
                    SELECT id
                    FROM calificaciones
                    WHERE matricula_id = ? AND tipo_evaluacion_id = ?
                    LIMIT 1
                ";
                $stmt = $mysqli->prepare($sqlExiste);
                $stmt->bind_param("ii", $matricula_id, $tipo_evaluacion_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($row) {
                    // Actualizar
                    $calif_id = (int) $row['id'];
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
                    // Insertar
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
            header("Location: calificaciones.php?view=evaluaciones&curso_id={$curso_id}&tipo_evaluacion_id={$tipo_evaluacion_id}");
            exit;
        }
    }

    // B) Guardar calificaciones de una tarea
    if (isset($_POST['guardar_notas_tarea'])) {

        $tarea_id = (int) ($_POST['tarea_id'] ?? 0);
        $curso_id = (int) ($_POST['curso_id'] ?? 0);
        $calificaciones = $_POST['calificacion'] ?? [];        // [matricula_id => nota]
        $comentarios = $_POST['comentario'] ?? [];           // [matricula_id => texto]

        if ($tarea_id <= 0 || $curso_id <= 0) {
            $error = "Tarea o curso no válidos.";
        } else {

            foreach ($calificaciones as $matricula_id => $nota) {
                $matricula_id = (int) $matricula_id;
                $nota = trim($nota);
                $comentario = isset($comentarios[$matricula_id]) ? trim($comentarios[$matricula_id]) : '';

                if ($nota === '' && $comentario === '') {
                    continue; // nada que guardar
                }

                // Sólo calificamos si existe una entrega
                $sqlEntrega = "
                    SELECT id
                    FROM tareas_entregas
                    WHERE tarea_id = ? AND matricula_id = ?
                    LIMIT 1
                ";
                $stmt = $mysqli->prepare($sqlEntrega);
                $stmt->bind_param("ii", $tarea_id, $matricula_id);
                $stmt->execute();
                $rowEnt = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$rowEnt) {
                    continue;
                }

                $entrega_id = (int) $rowEnt['id'];

                $notaFloat = null;
                if ($nota !== '' && is_numeric($nota)) {
                    $notaFloat = (float) $nota;
                }

                // Preparamos valores seguros para bind_param
                $calificacion_param = $notaFloat !== null ? $notaFloat : 0.0;
                $comentario_param = $comentario;

                $sqlUpdate = "
                    UPDATE tareas_entregas
                    SET calificacion = ?, 
                        comentarios_docente = ?, 
                        fecha_calificacion = NOW()
                    WHERE id = ?
                ";
                $stmt = $mysqli->prepare($sqlUpdate);
                $stmt->bind_param("dsi", $calificacion_param, $comentario_param, $entrega_id);
                $stmt->execute();
                $stmt->close();
            }

            $mensaje = "Notas de la tarea actualizadas correctamente.";
            header("Location: calificaciones.php?view=tareas&curso_id={$curso_id}&tarea_id={$tarea_id}");
            exit;
        }
    }
}

// -----------------------------------------------------
// 4. Datos para vista "Evaluaciones generales"
// -----------------------------------------------------
$tipos_evaluacion = [];
$estudiantes_curso_eval = [];
$calif_existentes_eval = [];
$historial_evaluaciones = [];  // 🔹 para mostrar la tabla de evaluaciones

if ($view === 'evaluaciones' && $curso_id > 0) {

    // Tipos de evaluación
    $sqlTipos = "SELECT id, nombre_evaluacion FROM tipos_evaluacion ORDER BY nombre_evaluacion";
    $tipos_evaluacion = $mysqli->query($sqlTipos)->fetch_all(MYSQLI_ASSOC);

    // Estudiantes matriculados en este curso con este docente
    $sqlEst = "
        SELECT 
            m.id AS matricula_id,
            u.nombre,
            u.apellido,
            u.email
        FROM matriculas m
        INNER JOIN estudiantes est ON est.id = m.estudiante_id
        INNER JOIN usuarios u      ON u.id = est.id
        INNER JOIN horarios h      ON h.id = m.horario_id
        WHERE h.curso_id = ?
          AND h.docente_id = ?
        ORDER BY u.apellido, u.nombre
    ";
    $stmt = $mysqli->prepare($sqlEst);
    $stmt->bind_param("ii", $curso_id, $docenteId);
    $stmt->execute();
    $estudiantes_curso_eval = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calificaciones existentes para el tipo de evaluación seleccionado (para rellenar inputs)
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
        $stmt->bind_param("iii", $tipo_evaluacion_id, $curso_id, $docenteId);
        $stmt->execute();
        $resCal = $stmt->get_result();
        while ($row = $resCal->fetch_assoc()) {
            $calif_existentes_eval[$row['matricula_id']] = $row['puntaje'];
        }
        $stmt->close();
    }

    // Historial de TODAS las evaluaciones del curso
    $sqlHist = "
        SELECT 
            c.id,
            c.matricula_id,
            c.puntaje,
            c.fecha_evaluacion,
            c.comentarios,
            te.nombre_evaluacion,
            u.nombre,
            u.apellido
        FROM calificaciones c
        INNER JOIN tipos_evaluacion te ON te.id = c.tipo_evaluacion_id
        INNER JOIN matriculas m        ON m.id = c.matricula_id
        INNER JOIN horarios h          ON h.id = m.horario_id
        INNER JOIN estudiantes est     ON est.id = m.estudiante_id
        INNER JOIN usuarios u          ON u.id = est.id
        WHERE h.curso_id = ?
          AND h.docente_id = ?
        ORDER BY c.fecha_evaluacion DESC, te.nombre_evaluacion, u.apellido, u.nombre
    ";
    $stmt = $mysqli->prepare($sqlHist);
    $stmt->bind_param("ii", $curso_id, $docenteId);
    $stmt->execute();
    $historial_evaluaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// -----------------------------------------------------
// 5. Datos para vista "Tareas y entregas"
// -----------------------------------------------------
$tareas_curso = [];
$tarea_seleccionada = null;
$entregas_tarea = [];

if ($view === 'tareas' && $curso_id > 0) {

    // Tareas del curso (a través de horarios del docente)
    $sqlT = "
        SELECT t.*
        FROM tareas t
        INNER JOIN horarios h ON h.id = t.horario_id
        WHERE h.curso_id = ?
          AND h.docente_id = ?
          AND t.activo = 1
        ORDER BY t.fecha_entrega ASC, t.titulo ASC
    ";
    $stmt = $mysqli->prepare($sqlT);
    $stmt->bind_param("ii", $curso_id, $docenteId);
    $stmt->execute();
    $tareas_curso = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($tarea_id > 0) {
        // Información de la tarea seleccionada
        $sqlInfo = "
            SELECT t.*, h.curso_id
            FROM tareas t
            INNER JOIN horarios h ON h.id = t.horario_id
            WHERE t.id = ?
              AND h.curso_id = ?
              AND h.docente_id = ?
            LIMIT 1
        ";
        $stmt = $mysqli->prepare($sqlInfo);
        $stmt->bind_param("iii", $tarea_id, $curso_id, $docenteId);
        $stmt->execute();
        $tarea_seleccionada = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($tarea_seleccionada) {
            // Entregas por estudiante (incluyendo los que no han entregado)
            $sqlEnt = "
                SELECT 
                    m.id AS matricula_id,
                    u.nombre,
                    u.apellido,
                    u.email,
                    te.id AS entrega_id,
                    te.archivo_url,
                    te.fecha_entrega,
                    te.calificacion,
                    te.comentarios_docente
                FROM matriculas m
                INNER JOIN estudiantes est ON est.id = m.estudiante_id
                INNER JOIN usuarios u      ON u.id = est.id
                INNER JOIN horarios h      ON h.id = m.horario_id
                LEFT JOIN tareas_entregas te
                       ON te.matricula_id = m.id
                      AND te.tarea_id = ?
                WHERE h.curso_id = ?
                  AND h.docente_id = ?
                ORDER BY u.apellido, u.nombre
            ";
            $stmt = $mysqli->prepare($sqlEnt);
            $stmt->bind_param("iii", $tarea_id, $curso_id, $docenteId);
            $stmt->execute();
            $entregas_tarea = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    .tt-calif-page .tt-header-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #A45A6A;
    }
    .tt-calif-page .tt-header-subtitle {
        font-size: 0.9rem;
        color: #666;
    }
    .tt-calif-page .card-soft {
        border-radius: 12px;
        border: 1px solid #eee;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        background-color: #fff;
    }
    .tt-calif-page .nav-pills .nav-link {
        border-radius: 999px;
        font-size: 0.9rem;
        padding: 0.35rem 0.9rem;
        color: #444;
    }
    .tt-calif-page .nav-pills .nav-link.active {
        background-color: #A45A6A;
        color: #fff;
        font-weight: 600;
    }
    .tt-calif-page .tt-card-title {
        font-weight: 600;
        color: #333;
    }
    .tt-calif-page table.table thead {
        background-color: #f5f0f2;
    }
    .tt-calif-page table.table thead th {
        border-bottom: 2px solid #e2d6da;
        font-size: 0.85rem;
    }
    .tt-calif-page table.table tbody td {
        font-size: 0.85rem;
        vertical-align: middle;
    }
    .tt-calif-page .btn-tt-primary {
        background-color: #A45A6A;
        border-color: #A45A6A;
        color: #fff;
    }
    .tt-calif-page .btn-tt-primary:hover {
        background-color: #8c4158;
        border-color: #8c4158;
        color: #fff;
    }
    .tt-calif-page .btn-tt-outline {
        border-color: #A45A6A;
        color: #A45A6A;
    }
    .tt-calif-page .btn-tt-outline:hover {
        background-color: #A45A6A;
        color: #fff;
    }
    .tt-calif-page .tt-section-block {
        margin-top: 1.5rem;
    }
</style>

<div class="container mt-4 tt-calif-page">

    <div class="mb-3">
        <h1 class="tt-header-title">
            <i class="fa-solid fa-graduation-cap me-2"></i>
            Calificaciones de docente
        </h1>
        <p class="tt-header-subtitle mb-0">
            Administra las notas de tus cursos. Usa <strong>Evaluaciones</strong> para exámenes/quiz/proyectos
            y <strong>Tareas y entregas</strong> para calificar tareas enviadas por los estudiantes.
        </p>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Tabs para ordenar la sección -->
    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $view === 'evaluaciones' ? 'active' : '' ?>"
               href="calificaciones.php?view=evaluaciones&curso_id=<?= $curso_id ?>">
                <i class="fa-solid fa-file-pen me-1"></i> Evaluaciones generales
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $view === 'tareas' ? 'active' : '' ?>"
               href="calificaciones.php?view=tareas&curso_id=<?= $curso_id ?>">
                <i class="fa-solid fa-list-check me-1"></i> Tareas y entregas
            </a>
        </li>
    </ul>

    <div class="row">
        <!-- Columna izquierda: cursos -->
        <div class="col-md-3 mb-3">
            <div class="card card-soft p-3">
                <h2 class="h6 fw-bold mb-3">Mis cursos</h2>
                <?php if (empty($cursos)): ?>
                    <p class="text-muted small mb-0">No tienes cursos asignados.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($cursos as $c): ?>
                            <?php
                            $isActive = ($curso_id == $c['curso_id']);
                            $url = "calificaciones.php?view=" . urlencode($view) . "&curso_id=" . (int) $c['curso_id'];
                            if ($view === 'evaluaciones' && $tipo_evaluacion_id) {
                                $url .= "&tipo_evaluacion_id={$tipo_evaluacion_id}";
                            }
                            if ($view === 'tareas' && $tarea_id) {
                                $url .= "&tarea_id={$tarea_id}";
                            }
                            ?>
                            <a href="<?= $url ?>"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $isActive ? 'active text-white' : '' ?>">
                                <span><?= htmlspecialchars($c['nombre_curso']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Columna derecha: contenido según vista -->
        <div class="col-md-9 mb-3">

            <?php if (!$curso_id): ?>
                <div class="alert alert-info">Selecciona un curso para comenzar.</div>
            <?php else: ?>

                <?php if ($view === 'evaluaciones'): ?>

                    <!-- Vista: Evaluaciones generales -->
                    <div class="card card-soft mb-3">
                        <div class="card-header bg-white">
                            <div class="d-flex flex-column">
                                <span class="tt-card-title">Evaluaciones generales</span>
                                <small class="text-muted">
                                    Usa esta sección para notas de exámenes, quices, proyectos, participación, etc.
                                </small>
                            </div>
                        </div>
                        <div class="card-body">

                            <?php if (empty($estudiantes_curso_eval)): ?>
                                <p class="text-muted small mb-0">
                                    No hay estudiantes matriculados en este curso.
                                </p>
                            <?php else: ?>

                                <!-- Bloque de filtros arriba -->
                                <div class="tt-section-block">
                                    <form method="get" class="row g-2">
                                        <input type="hidden" name="view" value="evaluaciones">
                                        <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                        <div class="col-md-8">
                                            <label class="form-label small mb-1">Tipo de evaluación</label>
                                            <select name="tipo_evaluacion_id" class="form-select form-select-sm">
                                                <option value="">-- Selecciona un tipo --</option>
                                                <?php foreach ($tipos_evaluacion as $te): ?>
                                                    <option value="<?= $te['id'] ?>" <?= ($tipo_evaluacion_id == $te['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($te['nombre_evaluacion']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button class="btn btn-sm btn-tt-outline w-100">
                                                Aplicar
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Tabla de captura de notas, más abajo para que respire -->
                                <?php if ($tipo_evaluacion_id): ?>
                                    <div class="tt-section-block">
                                        <form method="post" class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead>
                                                <tr>
                                                    <th>Estudiante</th>
                                                    <th class="text-center" style="width: 140px;">Nota</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($estudiantes_curso_eval as $est):
                                                    $mat_id = $est['matricula_id'];
                                                    $nota_val = isset($calif_existentes_eval[$mat_id])
                                                        ? $calif_existentes_eval[$mat_id]
                                                        : "";
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold small mb-0">
                                                                <?= htmlspecialchars($est['apellido'] . ", " . $est['nombre']) ?>
                                                            </div>
                                                            <div class="small text-muted">
                                                                <?= htmlspecialchars($est['email']) ?>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <input type="text" name="nota[<?= $mat_id ?>]"
                                                                   class="form-control form-control-sm text-center"
                                                                   value="<?= htmlspecialchars($nota_val) ?>">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>

                                            <div class="text-end mt-3">
                                                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                                <input type="hidden" name="tipo_evaluacion_id" value="<?= $tipo_evaluacion_id ?>">
                                                <button type="submit" name="guardar_calificaciones"
                                                        class="btn btn-tt-primary btn-sm">
                                                    Guardar calificaciones
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="tt-section-block">
                                        <p class="text-muted small mb-0">
                                            Elige un tipo de evaluación para ingresar o modificar las notas.
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <!-- Historial, aún más abajo -->
                                <?php if (!empty($historial_evaluaciones)): ?>
                                    <div class="tt-section-block">
                                        <h6 class="fw-bold mb-2">Historial de evaluaciones del curso</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Tipo</th>
                                                    <th>Estudiante</th>
                                                    <th class="text-center">Nota</th>
                                                    <th>Comentario</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($historial_evaluaciones as $h): ?>
                                                    <tr>
                                                        <td class="small">
                                                            <?= $h['fecha_evaluacion']
                                                                ? htmlspecialchars(date('d/m/Y', strtotime($h['fecha_evaluacion'])))
                                                                : '—' ?>
                                                        </td>
                                                        <td class="small">
                                                            <?= htmlspecialchars($h['nombre_evaluacion']) ?>
                                                        </td>
                                                        <td class="small">
                                                            <?= htmlspecialchars($h['apellido'] . ', ' . $h['nombre']) ?>
                                                        </td>
                                                        <td class="text-center small">
                                                            <?= number_format($h['puntaje'], 2) ?>
                                                        </td>
                                                        <td class="small">
                                                            <?= $h['comentarios']
                                                                ? nl2br(htmlspecialchars($h['comentarios']))
                                                                : '<span class="text-muted">Sin comentarios</span>'; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php endif; ?>

                        </div>
                    </div>

                <?php elseif ($view === 'tareas'): ?>

                    <!-- Vista: Tareas y entregas -->
                    <div class="row">
                        <div class="col-lg-5 mb-3">
                            <div class="card card-soft h-100">
                                <div class="card-header bg-white">
                                    <span class="tt-card-title">Tareas del curso</span><br>
                                    <small class="text-muted">
                                        Selecciona una tarea para ver y calificar las entregas por estudiante.
                                    </small>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($tareas_curso)): ?>
                                        <p class="text-muted small m-3">
                                            No hay tareas activas para este curso.
                                        </p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($tareas_curso as $t):
                                                $isSel = ($tarea_id == $t['id']);
                                                $urlT = "calificaciones.php?view=tareas&curso_id={$curso_id}&tarea_id={$t['id']}";
                                                $fecha_limite = $t['fecha_entrega'] ? date('d/m/Y', strtotime($t['fecha_entrega'])) : 'Sin límite';
                                                ?>
                                                <a href="<?= $urlT ?>"
                                                   class="list-group-item list-group-item-action <?= $isSel ? 'active text-white' : '' ?>">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <div class="fw-semibold small mb-0">
                                                                <?= htmlspecialchars($t['titulo']) ?>
                                                            </div>
                                                            <?php if (!empty($t['descripcion'])): ?>
                                                                <div class="small <?= $isSel ? '' : 'text-muted' ?>">
                                                                    <?= htmlspecialchars(substr($t['descripcion'], 0, 60)) ?>
                                                                    <?= strlen($t['descripcion']) > 60 ? '…' : '' ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="badge bg-light text-muted small">
                                                                Vence: <?= $fecha_limite ?>
                                                            </span>
                                                            <div class="small <?= $isSel ? '' : 'text-muted' ?>">
                                                                Valor: <?= (int) $t['valor_maximo'] ?> pts
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7 mb-3">
                            <div class="card card-soft h-100">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="tt-card-title">Entregas por estudiante</span><br>
                                        <small class="text-muted">
                                            Aquí calificas esta tarea alumno por alumno.
                                        </small>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (!$tarea_id || !$tarea_seleccionada): ?>
                                        <p class="text-muted small m-3">
                                            Selecciona una tarea en el panel izquierdo para ver las entregas.
                                        </p>
                                    <?php else: ?>

                                        <?php
                                        $fecha_pub = $tarea_seleccionada['fecha_publicacion']
                                            ? date('d/m/Y H:i', strtotime($tarea_seleccionada['fecha_publicacion']))
                                            : '—';

                                        // Fecha base
                                        $fechaBase = $tarea_seleccionada['fecha_entrega']; // Y-m-d o null
                                        $fechaFinal = $fechaBase;

                                        // ¿Hay extensión GENERAL para esta tarea?
                                        $extStmt = $mysqli->prepare("
        SELECT MAX(nueva_fecha) AS max_fecha
        FROM tareas_extensiones
        WHERE tarea_id = ?
          AND matricula_id IS NULL
    ");
                                        $extStmt->bind_param("i", $tarea_seleccionada['id']);
                                        $extStmt->execute();
                                        $extData = $extStmt->get_result()->fetch_assoc();
                                        $extStmt->close();

                                        $hay_extension_general = !empty($extData['max_fecha']);

                                        if ($hay_extension_general) {
                                            if (empty($fechaFinal) || $extData['max_fecha'] > $fechaFinal) {
                                                $fechaFinal = $extData['max_fecha'];
                                            }
                                        }

                                        $fecha_lim_mostrar = $fechaFinal
                                            ? date('d/m/Y', strtotime($fechaFinal))
                                            : 'Sin límite';
                                        ?>
                                        <div class="border-bottom small p-3">
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                <div>
                                                    <div class="fw-semibold mb-1">
                                                        <?= htmlspecialchars($tarea_seleccionada['titulo']) ?>
                                                    </div>
                                                    <div class="text-muted">
                                                        Publicada: <?= $fecha_pub ?> ·
                                                        Fecha límite actual: <strong><?= $fecha_lim_mostrar ?></strong> ·
                                                        Valor: <?= (int) $tarea_seleccionada['valor_maximo'] ?> pts

                                                        <?php if ($hay_extension_general): ?>
                                                            <span class="badge bg-info-subtle text-info border ms-1">
                                                                Fecha extendida
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Form compacto para extender fecha a TODA la clase -->
                                                <form method="post" class="d-flex align-items-center gap-2">
                                                    <input type="hidden" name="accion" value="extender_general_tarea">
                                                    <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                                    <input type="hidden" name="tarea_id" value="<?= (int) $tarea_seleccionada['id'] ?>">

                                                    <label class="small mb-0">Extender para toda la clase:</label>

                                                    <input type="date" name="nueva_fecha"
                                                           class="form-control form-control-sm" required>

                                                    <button type="submit"
                                                            class="btn btn-sm btn-tt-outline">
                                                        <i class="fa-solid fa-calendar-plus me-1"></i> Aplicar
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        <?php if (empty($entregas_tarea)): ?>
                                            <p class="text-muted small m-3">
                                                No hay estudiantes matriculados o entregas para esta tarea.
                                            </p>
                                        <?php else: ?>

                                            <form method="post" class="table-responsive">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead>
                                                    <tr>
                                                        <th>Estudiante</th>
                                                        <th class="text-center">Entrega</th>
                                                        <th class="text-center" style="width:90px;">Nota</th>
                                                        <th>Comentario</th>
                                                        <th class="text-center" style="width:170px;">Extender fecha</th>
                                                    </tr>
                                                    </thead>

                                                    <tbody>
                                                    <?php foreach ($entregas_tarea as $e):
                                                        // Buscar extensión específica para este alumno (si existe)
                                                        $extAlumnoFecha = null;
                                                        $stmtExtAl = $mysqli->prepare("
                                                            SELECT MAX(nueva_fecha) AS max_fecha
                                                            FROM tareas_extensiones
                                                            WHERE tarea_id = ?
                                                            AND matricula_id = ?
                                                        ");
                                                        $stmtExtAl->bind_param("ii", $tarea_id, $e['matricula_id']);
                                                        $stmtExtAl->execute();
                                                        $extAlData = $stmtExtAl->get_result()->fetch_assoc();
                                                        $stmtExtAl->close();

                                                        if (!empty($extAlData['max_fecha'])) {
                                                            $extAlumnoFecha = $extAlData['max_fecha']; // Y-m-d
                                                        }
                                                        ?>

                                                        <tr>
                                                            <td>
                                                                <div class="fw-semibold small mb-0">
                                                                    <?= htmlspecialchars($e['apellido'] . ", " . $e['nombre']) ?>
                                                                </div>
                                                                <div class="small text-muted">
                                                                    <?= htmlspecialchars($e['email']) ?>
                                                                </div>
                                                            </td>

                                                            <td class="text-center small">
                                                                <?php if ($e['entrega_id']): ?>
                                                                    <div class="d-flex flex-column align-items-center">
                                                                        <a href="<?= htmlspecialchars($e['archivo_url']) ?>"
                                                                           target="_blank"
                                                                           class="small">
                                                                            Ver archivo
                                                                        </a>
                                                                        <span class="text-muted">
                                                                            <?= date('d/m/Y H:i', strtotime($e['fecha_entrega'])) ?>
                                                                        </span>
                                                                    </div>

                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Sin entrega</span>

                                                                <?php endif; ?>
                                                            </td>

                                                            <td class="text-center">
                                                                <?php if ($e['entrega_id']): ?>
                                                                    <input type="text"
                                                                           name="calificacion[<?= (int) $e['matricula_id'] ?>]"
                                                                           class="form-control form-control-sm text-center"
                                                                           value="<?= $e['calificacion'] !== null ? htmlspecialchars($e['calificacion']) : '' ?>">

                                                                <?php else: ?>
                                                                    <span class="text-muted small">—</span>

                                                                <?php endif; ?>
                                                            </td>

                                                            <td>
                                                                <?php if ($e['entrega_id']): ?>
                                                                    <textarea name="comentario[<?= (int) $e['matricula_id'] ?>]"
                                                                              class="form-control form-control-sm"
                                                                              rows="1"
                                                                              placeholder="Comentario opcional..."><?= htmlspecialchars($e['comentarios_docente'] ?? '') ?></textarea>

                                                                <?php else: ?>
                                                                    <span class="text-muted small">
                                                                        No puedes calificar si no hay entrega.
                                                                    </span>

                                                                <?php endif; ?>
                                                            </td>

                                                            <!-- Extender fecha solo para este alumno -->
                                                            <td class="text-center">
                                                                <form method="post"
                                                                      class="d-flex flex-column align-items-center gap-1">

                                                                    <input type="hidden" name="accion" value="extender_tarea_alumno">
                                                                    <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                                                    <input type="hidden" name="tarea_id" value="<?= $tarea_id ?>">
                                                                    <input type="hidden" name="matricula_id"
                                                                           value="<?= (int) $e['matricula_id'] ?>">

                                                                    <input type="date" name="nueva_fecha"
                                                                           class="form-control form-control-sm text-center"
                                                                           style="max-width: 150px;"
                                                                           value="<?= $extAlumnoFecha ? htmlspecialchars($extAlumnoFecha) : '' ?>"
                                                                           required>

                                                                    <button class="btn btn-outline-secondary btn-sm" type="submit">
                                                                        <i class="fa-solid fa-calendar-check me-1"></i>
                                                                        Guardar
                                                                    </button>

                                                                </form>
                                                            </td>

                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>

                                                <div class="text-end mt-3 px-3 pb-3">
                                                    <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                                                    <input type="hidden" name="tarea_id" value="<?= $tarea_id ?>">
                                                    <button type="submit" name="guardar_notas_tarea"
                                                            class="btn btn-tt-primary btn-sm">
                                                        Guardar notas de la tarea
                                                    </button>
                                                </div>
                                            </form>
                                        <?php endif; ?>

                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; // view tareas ?>

            <?php endif; // curso_id ?>

        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
