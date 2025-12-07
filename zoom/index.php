<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

// PERMITIDO PARA ADMIN - DOCENTE - ESTUDIANTE
require_role([1, 2, 3]);

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$rol_id     = $_SESSION['rol_id'] ?? 0;

if (!$usuario_id || !$rol_id) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error   = "";

// ----------------------------------------------------------
// ACCIONES POST
// ----------------------------------------------------------

// 1) ADMIN: guardar / actualizar enlace de REUNIÓN GENERAL DOCENTES
if (
    $rol_id == 1 &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) &&
    $_POST['accion'] === 'guardar_reunion_docentes'
) {
    $url = trim($_POST['zoom_docentes_url'] ?? '');

    if ($url === '') {
        $error = "Debes ingresar un enlace válido para la reunión de docentes.";
    } else {
        // Desactivar reuniones anteriores
        $mysqli->query("UPDATE zoom_enlaces SET activo = 0 WHERE tipo = 'reunion_docentes'");

        // Insertar nueva
        $stmt = $mysqli->prepare("
            INSERT INTO zoom_enlaces (tipo, horario_id, url, activo)
            VALUES ('reunion_docentes', NULL, ?, 1)
        ");
        $stmt->bind_param("s", $url);
        $stmt->execute();
        $stmt->close();

        $mensaje = "Enlace de reunión de docentes actualizado correctamente.";
    }
}

// 2) DOCENTE: guardar / actualizar enlace por CLASE (horario)
if (
    $rol_id == 2 &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) &&
    $_POST['accion'] === 'guardar_clase'
) {
    $horario_id = isset($_POST['horario_id']) ? (int)$_POST['horario_id'] : 0;
    $url        = trim($_POST['zoom_url'] ?? '');

    if ($horario_id <= 0) {
        $error = "Horario no válido.";
    } elseif ($url === '') {
        $error = "Debes ingresar un enlace válido para la clase.";
    } else {
        // Verificar que el horario sea del DOCENTE LOGUEADO
        $stmt = $mysqli->prepare("
            SELECT id
            FROM horarios
            WHERE id = ? AND docente_id = ?
        ");
        $stmt->bind_param("ii", $horario_id, $usuario_id);
        $stmt->execute();
        $resHor = $stmt->get_result();
        $stmt->close();

        if (!$resHor->num_rows) {
            $error = "No puedes gestionar Zoom para un horario que no te pertenece.";
        } else {
            // Ver si ya existe un enlace activo para ese horario
            $stmt = $mysqli->prepare("
                SELECT id
                FROM zoom_enlaces
                WHERE tipo = 'clase' AND horario_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->bind_param("i", $horario_id);
            $stmt->execute();
            $resZoom = $stmt->get_result();
            $stmt->close();

            if ($rowZoom = $resZoom->fetch_assoc()) {
                // Actualizar
                $zoom_id = (int)$rowZoom['id'];
                $stmt = $mysqli->prepare("
                    UPDATE zoom_enlaces
                    SET url = ?, activo = 1
                    WHERE id = ?
                ");
                $stmt->bind_param("si", $url, $zoom_id);
                $stmt->execute();
                $stmt->close();
            } else {
                // Insertar nuevo
                $stmt = $mysqli->prepare("
                    INSERT INTO zoom_enlaces (tipo, horario_id, url, activo)
                    VALUES ('clase', ?, ?, 1)
                ");
                $stmt->bind_param("is", $horario_id, $url);
                $stmt->execute();
                $stmt->close();
            }

            $mensaje = "Enlace de Zoom guardado para la clase seleccionada.";
        }
    }
}

// 3) SOLO ADMIN: FINALIZAR reunión general de docentes
if (
    $rol_id == 1 &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) &&
    $_POST['accion'] === 'finalizar_reunion_docentes'
) {
    $stmt = $mysqli->prepare("
        UPDATE zoom_enlaces
        SET activo = 0
        WHERE tipo = 'reunion_docentes' AND activo = 1
    ");
    $stmt->execute();
    $stmt->close();

    $mensaje = "La reunión general de docentes ha sido finalizada.";
}

// 4) DOCENTE: FINALIZAR enlace Zoom de una CLASE específica
if (
    $rol_id == 2 &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) &&
    $_POST['accion'] === 'finalizar_clase'
) {
    $horario_id = isset($_POST['horario_id']) ? (int)$_POST['horario_id'] : 0;

    if ($horario_id > 0) {
        // Validar que el horario sea del docente logueado
        $stmt = $mysqli->prepare("
            SELECT id
            FROM horarios
            WHERE id = ? AND docente_id = ?
        ");
        $stmt->bind_param("ii", $horario_id, $usuario_id);
        $stmt->execute();
        $resHor = $stmt->get_result();
        $stmt->close();

        if (!$resHor->num_rows) {
            $error = "No puedes finalizar Zoom de un horario que no te pertenece.";
        } else {
            $stmt = $mysqli->prepare("
                UPDATE zoom_enlaces
                SET activo = 0
                WHERE tipo = 'clase' AND horario_id = ? AND activo = 1
            ");
            $stmt->bind_param("i", $horario_id);
            $stmt->execute();
            $stmt->close();

            $mensaje = "El enlace de Zoom de esa clase ha sido finalizado.";
        }
    } else {
        $error = "Horario no válido.";
    }
}

// ----------------------------------------------------------
// CONSULTAS PARA MOSTRAR INFO SEGÚN ROL
// ----------------------------------------------------------

// ADMIN y DOCENTE pueden ver reunión general de docentes (si existe activa)
$zoomReunionDocentes = null;
if ($rol_id == 1 || $rol_id == 2) {
    $sql = "
        SELECT url, fecha_creacion
        FROM zoom_enlaces
        WHERE tipo = 'reunion_docentes' AND activo = 1
        ORDER BY id DESC
        LIMIT 1
    ";
    $res = $mysqli->query($sql);
    $zoomReunionDocentes = $res ? $res->fetch_assoc() : null;
}

// DOCENTE: horarios que imparte + enlace Zoom por clase (si existe)
$horariosDocente = [];
if ($rol_id == 2) {
    $sql = "
        SELECT 
            h.id,
            c.nombre_curso,
            n.codigo_nivel,
            d.nombre_dia,
            h.hora_inicio,
            h.hora_fin,
            ze.url AS zoom_url
        FROM horarios h
        INNER JOIN cursos c              ON h.curso_id = c.id
        INNER JOIN niveles_academicos n  ON c.nivel_id = n.id
        INNER JOIN dias_semana d         ON h.dia_semana_id = d.id
        LEFT JOIN zoom_enlaces ze        ON ze.horario_id = h.id
                                         AND ze.tipo = 'clase'
                                         AND ze.activo = 1
        WHERE h.docente_id = ?
        ORDER BY d.numero_dia, h.hora_inicio
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $resH = $stmt->get_result();
    while ($row = $resH->fetch_assoc()) {
        $horariosDocente[] = $row;
    }
    $stmt->close();
}

// ESTUDIANTE: clases donde está matriculado + enlace Zoom de esa clase
$clasesEstudiante = [];
if ($rol_id == 3) {
    $sql = "
        SELECT 
            m.id AS matricula_id,
            h.id AS horario_id,
            c.nombre_curso,
            n.codigo_nivel,
            d.nombre_dia,
            h.hora_inicio,
            h.hora_fin,
            ze.url AS zoom_url
        FROM matriculas m
        INNER JOIN horarios h              ON m.horario_id = h.id
        INNER JOIN cursos c               ON h.curso_id = c.id
        INNER JOIN niveles_academicos n   ON c.nivel_id = n.id
        INNER JOIN dias_semana d          ON h.dia_semana_id = d.id
        LEFT JOIN zoom_enlaces ze         ON ze.horario_id = h.id
                                          AND ze.tipo = 'clase'
                                          AND ze.activo = 1
        WHERE m.estudiante_id = ?
          AND m.estado_id = 1  -- solo matrículas ACTIVAS
        ORDER BY d.numero_dia, h.hora_inicio
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $resC = $stmt->get_result();
    while ($row = $resC->fetch_assoc()) {
        $clasesEstudiante[] = $row;
    }
    $stmt->close();
}

include __DIR__ . "/../includes/header.php";
?>

<div class="container mt-4">

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

<div class="card shadow-sm mb-4" 
     style="border:2px solid #ff4b7b; border-radius:10px;">
    
    <div style="background:#ff4b7b; color:white; padding:12px 18px; border-radius:8px 8px 0 0;">
        <h4 class="mb-0" style="font-weight:600;">🎥 Clases Online / Zoom</h4>
    </div>
    
</div>

        

    <?php if ($rol_id == 1): ?>
        <!-- ADMIN: gestionar reunión general de docentes -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong>🛠 Reunión general de docentes</strong>
            </div>
            <div class="card-body">
                <?php if ($zoomReunionDocentes): ?>
                    <p><b>Enlace activo:</b></p>
                    <a href="<?php echo htmlspecialchars($zoomReunionDocentes['url']); ?>"
                       target="_blank"
                       class="btn btn-success mb-2">
                        Unirme a la reunión de docentes
                    </a>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="accion" value="finalizar_reunion_docentes">
                        <button type="submit" class="btn btn-outline-danger mb-2">
                            Finalizar reunión
                        </button>
                    </form>
                    <p class="text-muted mb-0">
                        Última actualización: <?php echo htmlspecialchars($zoomReunionDocentes['fecha_creacion']); ?>
                    </p>
                <?php else: ?>
                    <p class="text-muted">
                        No hay ninguna reunión de docentes activa en este momento.
                    </p>
                <?php endif; ?>

                <hr>

                <form method="post">
                    <input type="hidden" name="accion" value="guardar_reunion_docentes">
                    <div class="mb-3">
                        <label class="form-label">URL de la reunión (Zoom / Meet / Teams)</label>
                        <input type="text" name="zoom_docentes_url" class="form-control"
                               placeholder="https://zoom.us/j/xxxxx">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        Guardar / Activar reunión de docentes
                    </button>
                    <p class="text-muted mt-2 mb-0">
                        Cuando guardes un enlace, los docentes lo verán en esta misma sección
                        bajo su usuario.
                    </p>
                </form>
            </div>
        </div>

    <?php elseif ($rol_id == 2): ?>
        <!-- DOCENTE: ver y finalizar reunión de docentes + gestionar clases -->
<?php if ($zoomReunionDocentes): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <strong>🛠 Reunión general de docentes (convocada por Admin)</strong>
        </div>
        <div class="card-body">
            <p><b>Enlace activo:</b></p>
            <a href="<?php echo htmlspecialchars($zoomReunionDocentes['url']); ?>"
               target="_blank"
               class="btn btn-success mb-2">
                Unirme a la reunión de docentes
            </a>
            <p class="text-muted mb-0">
                La reunión solo puede ser finalizada por administración.
            </p>
        </div>
    </div>
<?php endif; ?>


        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <strong>👨‍🏫 Zoom por clases que impartes</strong>
            </div>
            <div class="card-body">
                <?php if (!$horariosDocente): ?>
                    <p class="text-muted mb-0">
                        No tienes horarios asignados actualmente.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Curso</th>
                                    <th>Nivel</th>
                                    <th>Día</th>
                                    <th>Horario</th>
                                    <th>Zoom</th>
                                    <th>Configurar</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($horariosDocente as $h): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($h['nombre_curso']); ?></td>
                                    <td><?php echo htmlspecialchars($h['codigo_nivel']); ?></td>
                                    <td><?php echo htmlspecialchars($h['nombre_dia']); ?></td>
                                    <td>
                                        <?php echo substr($h['hora_inicio'], 0, 5); ?>
                                        -
                                        <?php echo substr($h['hora_fin'], 0, 5); ?>
                                    </td>
                                    <td>
                                        <?php if ($h['zoom_url']): ?>
                                            <a href="<?php echo htmlspecialchars($h['zoom_url']); ?>"
                                               target="_blank"
                                               class="btn btn-outline-success btn-sm mb-1">
                                                Unirme
                                            </a>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="accion" value="finalizar_clase">
                                                <input type="hidden" name="horario_id" value="<?php echo (int)$h['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm mb-1">
                                                    Finalizar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sin enlace activo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" class="d-flex">
                                            <input type="hidden" name="accion" value="guardar_clase">
                                            <input type="hidden" name="horario_id" value="<?php echo (int)$h['id']; ?>">
                                            <input type="text"
                                                   name="zoom_url"
                                                   class="form-control form-control-sm me-2"
                                                   placeholder="https://zoom.us/j/xxxxx">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                Guardar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted mb-0">
                        Solo los estudiantes matriculados en cada horario verán su respectivo enlace activo.
                    </p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($rol_id == 3): ?>
        <!-- ESTUDIANTE: ver enlaces Zoom SOLO de sus matrículas activas -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <strong>👨‍🎓 Clases Online de tus cursos</strong>
            </div>
            <div class="card-body">
                <?php if (!$clasesEstudiante): ?>
                    <p class="text-muted mb-0">
                        No tienes matrículas activas actualmente.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Curso</th>
                                    <th>Nivel</th>
                                    <th>Día</th>
                                    <th>Horario</th>
                                    <th>Zoom</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($clasesEstudiante as $c): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['nombre_curso']); ?></td>
                                    <td><?php echo htmlspecialchars($c['codigo_nivel']); ?></td>
                                    <td><?php echo htmlspecialchars($c['nombre_dia']); ?></td>
                                    <td>
                                        <?php echo substr($c['hora_inicio'], 0, 5); ?>
                                        -
                                        <?php echo substr($c['hora_fin'], 0, 5); ?>
                                    </td>
                                    <td>
                                        <?php if ($c['zoom_url']): ?>
                                            <a href="<?php echo htmlspecialchars($c['zoom_url']); ?>"
                                               target="_blank"
                                               class="btn btn-success btn-sm">
                                                Unirme a la clase
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                Tu docente no ha activado Zoom para esta clase.
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted mb-0">
                        Si no ves el botón de Zoom, espera que tu docente active el enlace.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
include __DIR__ . "/../includes/footer.php";
