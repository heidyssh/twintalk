<?php
// student/cursos_disponibles.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Solo estudiantes
require_role([3]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if (!$usuario_id) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error   = "";

/**
 * Obtener ID de un estado de matrícula por nombre (Activa, Pendiente, Finalizada, etc.)
 */
function obtenerEstadoIdPorNombre(mysqli $mysqli, string $nombre): ?int {
    $stmt = $mysqli->prepare("SELECT id FROM estados_matricula WHERE nombre_estado = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? (int)$res['id'] : null;
}

/**
 * Obtener el nivel máximo FINALIZADO del estudiante (según niveles_academicos.id)
 * Devuelve el id de nivel (niveles_academicos.id) o null si no tiene cursos finalizados.
 */
function obtenerNivelMaximoFinalizado(mysqli $mysqli, int $estudiante_id): ?int {
    $sql = "
        SELECT MAX(na.id) AS max_nivel
        FROM matriculas m
        JOIN horarios h           ON m.horario_id = h.id
        JOIN cursos c             ON h.curso_id = c.id
        JOIN niveles_academicos na ON c.nivel_id = na.id
        JOIN estados_matricula em ON m.estado_id = em.id
        WHERE m.estudiante_id = ?
          AND em.nombre_estado = 'Finalizada'
    ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $estudiante_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($res && $res['max_nivel'] !== null) {
        return (int)$res['max_nivel'];
    }
    return null;
}

/**
 * Verifica si el estudiante tiene pagos pendientes:
 * - Matrículas en estado 'Pendiente'
 * - O matrículas 'Activa' sin monto_pagado o sin metodo_pago.
 */
function tienePagosPendientes(mysqli $mysqli, int $estudiante_id): bool {
    $sql = "
        SELECT COUNT(*) AS total
        FROM matriculas m
        JOIN estados_matricula em ON m.estado_id = em.id
        WHERE m.estudiante_id = ?
          AND (
                em.nombre_estado = 'Pendiente'
             OR (
                    em.nombre_estado = 'Activa'
                AND (m.monto_pagado IS NULL OR m.monto_pagado = 0 OR m.metodo_pago_id IS NULL)
             )
          )
    ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("i", $estudiante_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $res && (int)$res['total'] > 0;
}

/**
 * Obtener precio vigente de un curso (precios_cursos.activo = 1)
 */
function obtenerPrecioCursoActual(mysqli $mysqli, int $curso_id): ?float {
    $stmt = $mysqli->prepare("
        SELECT precio
        FROM precios_cursos
        WHERE curso_id = ?
          AND activo = 1
          AND fecha_inicio_vigencia <= CURDATE()
          AND (fecha_fin_vigencia IS NULL OR fecha_fin_vigencia >= CURDATE())
        ORDER BY fecha_inicio_vigencia DESC
        LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param("i", $curso_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $res ? (float)$res['precio'] : null;
}

// En esta BD, estudiantes.id = usuarios.id (FK)
$estudiante_id = (int)$usuario_id;

// ---------------------------------------------
// PROCESAR MATRÍCULA (POST)
// ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'matricular') {
    $horario_id = isset($_POST['horario_id']) ? (int)$_POST['horario_id'] : 0;

    if ($horario_id <= 0) {
        $error = "Horario inválido.";
    } else {
        // 1) Verificar si ya está matriculado en ese horario
        $stmt = $mysqli->prepare("
            SELECT m.id, em.nombre_estado
            FROM matriculas m
            JOIN estados_matricula em ON m.estado_id = em.id
            WHERE m.estudiante_id = ?
              AND m.horario_id = ?
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $estudiante_id, $horario_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $existe = $res->fetch_assoc();
            $stmt->close();

            if ($existe) {
                $error = "Ya tienes una matrícula para este curso (estado: " . htmlspecialchars($existe['nombre_estado']) . ").";
            } else {
                // 2) Verificar si tiene pagos pendientes en otros cursos
                if (tienePagosPendientes($mysqli, $estudiante_id)) {
                    $error = "No puedes matricular un nuevo curso porque tienes pagos pendientes en un curso anterior. Por favor, ponte al día con tu pago.";
                } else {
                    // 3) Obtener info del horario y curso (nivel, cupos, fechas)
                    $sqlHorario = "
                        SELECT 
                            h.id AS horario_id,
                            h.cupos_disponibles,
                            h.activo,
                            c.id   AS curso_id,
                            c.nombre_curso,
                            c.nivel_id,
                            n.codigo_nivel,
                            n.nombre_nivel
                        FROM horarios h
                        JOIN cursos c             ON h.curso_id = c.id
                        JOIN niveles_academicos n ON c.nivel_id = n.id
                        WHERE h.id = ?
                        LIMIT 1
                    ";
                    $stmtH = $mysqli->prepare($sqlHorario);
                    if ($stmtH) {
                        $stmtH->bind_param("i", $horario_id);
                        $stmtH->execute();
                        $resH = $stmtH->get_result();
                        $horario = $resH->fetch_assoc();
                        $stmtH->close();

                        if (!$horario) {
                            $error = "No se encontró el horario seleccionado.";
                        } elseif (!(int)$horario['activo']) {
                            $error = "Este horario no está activo actualmente.";
                        } else {
                            // 4) Validar cupos
                            if ((int)$horario['cupos_disponibles'] <= 0) {
                                $error = "No hay cupos disponibles en este horario.";
                            } else {
                                $curso_nivel_id = (int)$horario['nivel_id'];

                                // 5) Validar requisitos por nivel
                                $nivel_max_finalizado = obtenerNivelMaximoFinalizado($mysqli, $estudiante_id);
                                if ($nivel_max_finalizado === null) {
                                    // Solo se permite matricular nivel inicial (id = 1) si nunca ha finalizado nada
                                    if ($curso_nivel_id > 1) {
                                        $error = "Debes iniciar desde el nivel principiante antes de matricular este curso.";
                                    }
                                } else {
                                    // No puede saltarse niveles: solo siguiente nivel o repetir nivel actual
                                    if ($curso_nivel_id > $nivel_max_finalizado + 1) {
                                        $error = "No puedes matricular este curso aún. Debes completar y estar al día con el nivel anterior.";
                                    }
                                }

                                if ($error === "") {
                                    // 6) Crear matrícula en estado Pendiente, sin pago todavía
                                    $estado_pendiente_id = obtenerEstadoIdPorNombre($mysqli, 'Pendiente');
                                    if (!$estado_pendiente_id) {
                                        $error = "No se encontró el estado de matrícula 'Pendiente'. Contacta al administrador.";
                                    } else {
                                        $stmtIns = $mysqli->prepare("
                                            INSERT INTO matriculas (
                                                estudiante_id,
                                                horario_id,
                                                fecha_matricula,
                                                estado_id,
                                                metodo_pago_id,
                                                monto_pagado,
                                                fecha_vencimiento
                                            )
                                            VALUES (?, ?, NOW(), ?, NULL, NULL, NULL)
                                        ");
                                        if ($stmtIns) {
                                            $stmtIns->bind_param("iii", $estudiante_id, $horario_id, $estado_pendiente_id);
                                            if ($stmtIns->execute()) {
                                                $mensaje = "Te has matriculado correctamente. Tu matrícula está pendiente de pago. El administrador registrará tu pago cuando lo realices en el banco.";
                                            } else {
                                                $error = "No se pudo registrar la matrícula. Intenta de nuevo.";
                                            }
                                            $stmtIns->close();
                                        } else {
                                            $error = "Error al preparar el registro de matrícula.";
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $error = "Error al consultar el horario.";
                    }
                }
            }
        } else {
            $error = "Error al validar tu matrícula.";
        }
    }
}

// ---------------------------------------------
// LISTADO DE CURSOS / HORARIOS DISPONIBLES
// ---------------------------------------------
$sqlCursos = "
    SELECT 
        h.id          AS horario_id,
        h.aula,
        h.hora_inicio,
        h.hora_fin,
        h.fecha_inicio,
        h.fecha_fin,
        h.cupos_disponibles,
        h.activo,
        c.id          AS curso_id,
        c.nombre_curso,
        c.descripcion,
        n.id          AS nivel_id,
        n.codigo_nivel,
        n.nombre_nivel,
        ds.nombre_dia,
        -- precio vigente
        pc.precio     AS precio_actual
    FROM horarios h
    JOIN cursos c             ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana ds       ON h.dia_semana_id = ds.id
    LEFT JOIN (
        SELECT curso_id, precio
        FROM precios_cursos
        WHERE activo = 1
          AND fecha_inicio_vigencia <= CURDATE()
          AND (fecha_fin_vigencia IS NULL OR fecha_fin_vigencia >= CURDATE())
    ) pc ON pc.curso_id = c.id
    WHERE h.activo = 1
    ORDER BY n.id ASC, c.nombre_curso ASC, ds.numero_dia ASC, h.hora_inicio ASC
";
$cursos_disp = $mysqli->query($sqlCursos);

// Obtener nivel máximo finalizado y bandera de pagos pendientes para mostrar info
$nivel_max_finalizado = obtenerNivelMaximoFinalizado($mysqli, $estudiante_id);
$tiene_pagos_pend     = tienePagosPendientes($mysqli, $estudiante_id);

include __DIR__ . '/../includes/header.php';
?>

<style>
.card-soft{
    border-radius:12px;
}
.btn-tt-primary{
    background-color:#A45A6A;
    border-color:#A45A6A;
    color:#fff;
}
.btn-tt-primary:hover{
    background-color:#8c4b59;
    border-color:#8c4b59;
    color:#fff;
}
.badge-soft-primary{
    background:#f4e5ef;
    color:#7b2f4a;
    border:1px solid #e3bfd7;
}
.bg-soft-primary{
    background:#f4e5ef;
}
.badge-pill-small{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.05em;
}
</style>

<div class="container py-4">

    <!-- Cabecera con degradado -->
    <div class="card card-soft border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
             style="background:linear-gradient(90deg,#fbe9f0,#ffffff);">
            <div>
                <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                    <i class="fa-solid fa-graduation-cap me-2"></i>
                    Cursos disponibles
                </h1>
                <p class="small text-muted mb-0">
                    Elige el curso y horario en el que deseas matricularte.  
                    Debes estar al día con tus pagos para avanzar de nivel.
                </p>
            </div>
            <div>
                <a href="/twintalk/student/dashboard.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left-long me-1"></i>
                    Volver al panel
                </a>
            </div>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Badges de estado del alumno -->
    <div class="mb-3">
        <?php if ($nivel_max_finalizado === null): ?>
            <span class="badge badge-soft-primary badge-pill-small me-2">
                Nivel actual: Principiante (sin cursos finalizados)
            </span>
        <?php else: ?>
            <span class="badge badge-soft-primary badge-pill-small me-2">
                Nivel máximo finalizado: ID <?= (int)$nivel_max_finalizado ?>
            </span>
        <?php endif; ?>

        <?php if ($tiene_pagos_pend): ?>
            <span class="badge bg-warning text-dark badge-pill-small">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                Tienes pagos pendientes. No podrás matricular nuevos cursos hasta estar al día.
            </span>
        <?php endif; ?>
    </div>

    <?php if ($cursos_disp && $cursos_disp->num_rows > 0): ?>
        <div class="row g-3">
            <?php while ($c = $cursos_disp->fetch_assoc()): ?>
                <?php
                    $curso_nivel_id = (int)$c['nivel_id'];
                    $bloqueado_por_nivel = false;
                    $razon_bloqueo = "";

                    if ($nivel_max_finalizado === null) {
                        if ($curso_nivel_id > 1) {
                            $bloqueado_por_nivel = true;
                            $razon_bloqueo = "Debes empezar desde el nivel principiante antes de tomar este curso.";
                        }
                    } else {
                        if ($curso_nivel_id > $nivel_max_finalizado + 1) {
                            $bloqueado_por_nivel = true;
                            $razon_bloqueo = "Aún no has completado el nivel anterior requerido.";
                        }
                    }

                    $bloqueado_por_pago = $tiene_pagos_pend;
                    $sin_cupos = ((int)$c['cupos_disponibles'] <= 0);

                    $precio_mostrar = $c['precio_actual'] !== null
                        ? "L " . number_format($c['precio_actual'], 2)
                        : "Por definir";
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-soft h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body d-flex flex-column">

                            <!-- Encabezado: nombre + nivel + día/horario -->
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="card-title mb-0">
                                        <?= htmlspecialchars($c['nombre_curso']) ?>
                                    </h5>
                                    <span class="badge bg-soft-primary text-dark badge-pill-small mt-1">
                                        Nivel <?= htmlspecialchars($c['codigo_nivel']) ?> ·
                                        <?= htmlspecialchars($c['nombre_nivel']) ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-muted small">
                                        <?= htmlspecialchars($c['nombre_dia']) ?>
                                    </span>
                                    <div class="small text-muted">
                                        <?= substr($c['hora_inicio'], 0, 5) ?> - <?= substr($c['hora_fin'], 0, 5) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Descripción -->
                            <?php if (!empty($c['descripcion'])): ?>
                                <p class="card-text small text-muted mb-2">
                                    <?= nl2br(htmlspecialchars($c['descripcion'])) ?>
                                </p>
                            <?php endif; ?>

                            <!-- Info básica -->
                            <div class="small mb-2">
                                <strong>Precio:</strong> <?= $precio_mostrar ?><br>
                                <strong>Aula:</strong> <?= htmlspecialchars($c['aula'] ?? 'Por asignar') ?><br>
                                <strong>Cupos disponibles:</strong>
                                <?= (int)$c['cupos_disponibles'] ?>
                            </div>

                            <!-- Acciones / estado -->
                            <div class="mt-auto pt-2 border-top">
                                <?php if ($sin_cupos): ?>
                                    <button class="btn btn-sm btn-outline-secondary w-100" disabled>
                                        <i class="fa-solid fa-circle-xmark me-1"></i> Sin cupos
                                    </button>

                                <?php elseif ($bloqueado_por_pago): ?>
                                    <button class="btn btn-sm btn-outline-warning w-100" disabled>
                                        <i class="fa-solid fa-lock me-1"></i> Tienes pagos pendientes
                                    </button>
                                    <p class="small text-muted mt-1 mb-0">
                                        Regulariza tus pagos para poder matricular nuevos cursos.
                                    </p>

                                <?php elseif ($bloqueado_por_nivel): ?>
                                    <button class="btn btn-sm btn-outline-secondary w-100" disabled>
                                        <i class="fa-solid fa-lock me-1"></i> Nivel no disponible aún
                                    </button>
                                    <p class="small text-muted mt-1 mb-0">
                                        <?= htmlspecialchars($razon_bloqueo) ?>
                                    </p>

                                <?php else: ?>
                                    <form method="post">
                                        <input type="hidden" name="accion" value="matricular">
                                        <input type="hidden" name="horario_id" value="<?= (int)$c['horario_id'] ?>">
                                        <button type="submit" class="btn btn-tt-primary btn-sm w-100">
                                            <i class="fa-solid fa-check me-1"></i>
                                            Matricularme en este horario
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">Por el momento no hay cursos disponibles para matrícula.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
