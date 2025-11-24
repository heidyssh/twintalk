<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_role([1]); // solo admin

$mensaje = "";
$error   = "";

// -----------------------------------------------------
// Helper: obtener id de un estado por nombre
// -----------------------------------------------------
function obtenerEstadoIdPorNombre($mysqli, $nombre) {
    $stmt = $mysqli->prepare("SELECT id FROM estados_matricula WHERE nombre_estado = ? LIMIT 1");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? (int)$res['id'] : null;
}

// -----------------------------------------------------
// Helper: obtener precio vigente del curso
// -----------------------------------------------------
function obtenerPrecioCursoActual($mysqli, $curso_id) {
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
    $stmt->bind_param("i", $curso_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? (float)$res['precio'] : 0.0;
}

// -----------------------------------------------------
// Registrar pago (parcial o total) de una matrícula
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_pago') {

    $matricula_id   = (int)($_POST['matricula_id'] ?? 0);
    $metodo_pago_id = (int)($_POST['metodo_pago_id'] ?? 0);
    $monto_nuevo    = isset($_POST['monto_pagado']) ? (float)$_POST['monto_pagado'] : 0.0;

    if ($matricula_id <= 0 || $metodo_pago_id <= 0 || $monto_nuevo <= 0) {
        $error = "Debes seleccionar método de pago y un monto válido.";
    } else {

        // Obtener datos actuales de la matrícula (incluyendo monto_pagado y curso)
        $sqlDatos = "
            SELECT 
                m.monto_pagado,
                m.estado_id,
                h.curso_id
            FROM matriculas m
            INNER JOIN horarios h ON h.id = m.horario_id
            WHERE m.id = ?
            LIMIT 1
        ";
        $stmt = $mysqli->prepare($sqlDatos);
        $stmt->bind_param("i", $matricula_id);
        $stmt->execute();
        $datosMat = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$datosMat) {
            $error = "Matrícula no encontrada.";
        } else {
            $monto_actual = $datosMat['monto_pagado'] !== null ? (float)$datosMat['monto_pagado'] : 0.0;
            $estado_actual_id = (int)$datosMat['estado_id'];
            $curso_id = (int)$datosMat['curso_id'];

            $precio_curso = obtenerPrecioCursoActual($mysqli, $curso_id);
            if ($precio_curso <= 0) {
                $error = "No se encontró un precio vigente para este curso.";
            } else {
                $nuevo_total = $monto_actual + $monto_nuevo;

                // Actualizar registro de matrícula con nuevo total y método de pago
                $sqlUpdate = "
                    UPDATE matriculas
                    SET monto_pagado = ?, 
                        metodo_pago_id = ?
                    WHERE id = ?
                ";
                $stmt = $mysqli->prepare($sqlUpdate);
                $stmt->bind_param("dii", $nuevo_total, $metodo_pago_id, $matricula_id);
                $stmt->execute();
                $stmt->close();

                // Actualizar estado según si ya pagó todo o no
                $estado_activa_id    = obtenerEstadoIdPorNombre($mysqli, "Activa");
                $estado_pendiente_id = obtenerEstadoIdPorNombre($mysqli, "Pendiente");

                if ($nuevo_total >= $precio_curso && $estado_activa_id) {
                    $sqlEstado = "UPDATE matriculas SET estado_id = ? WHERE id = ?";
                    $stmt = $mysqli->prepare($sqlEstado);
                    $stmt->bind_param("ii", $estado_activa_id, $matricula_id);
                    $stmt->execute();
                    $stmt->close();
                    $mensaje = "Pago registrado. Matrícula pagada completamente.";
                } else {
                    // Sigue quedando saldo pendiente
                    if ($estado_pendiente_id && $estado_actual_id != $estado_activa_id) {
                        $sqlEstado = "UPDATE matriculas SET estado_id = ? WHERE id = ?";
                        $stmt = $mysqli->prepare($sqlEstado);
                        $stmt->bind_param("ii", $estado_pendiente_id, $matricula_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $mensaje = "Pago parcial registrado. El alumno aún tiene saldo pendiente.";
                }

                // (Opcional) enviar correo de confirmación
                // enviarCorreoConfirmacionPago($mysqli, $matricula_id);

                header("Location: pagos.php");
                exit;
            }
        }
    }
}

// -----------------------------------------------------
// Listar matrículas con saldo pendiente
// -----------------------------------------------------
$sqlPend = "
    SELECT 
        m.id AS matricula_id,
        u.nombre,
        u.apellido,
        u.email,
        c.nombre_curso,
        h.id AS horario_id,
        m.fecha_matricula,
        m.monto_pagado,
        c.id AS curso_id
    FROM matriculas m
    INNER JOIN estudiantes est ON est.id = m.estudiante_id
    INNER JOIN usuarios   u   ON u.id = est.id
    INNER JOIN horarios   h   ON h.id = m.horario_id
    INNER JOIN cursos     c   ON c.id = h.curso_id
    WHERE m.estado_id IN (
        COALESCE((SELECT id FROM estados_matricula WHERE nombre_estado = 'Pendiente' LIMIT 1), 0),
        COALESCE((SELECT id FROM estados_matricula WHERE nombre_estado = 'Activa' LIMIT 1), 0)
    )
    ORDER BY m.fecha_matricula DESC
";
$pendientes = $mysqli->query($sqlPend);

// Métodos de pago disponibles
$metodos_pago = $mysqli->query("SELECT id, nombre_metodo FROM metodos_pago ORDER BY nombre_metodo")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">
    <h1 class="h3 mb-3">
        <i class="fa-solid fa-money-check-dollar me-2"></i>
        Gestión de pagos
    </h1>
    <p class="text-muted mb-4">
        Registra pagos de matrícula. Si el alumno no paga el total, el sistema mostrará el saldo pendiente hasta completarse.
    </p>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card card-soft">
        <div class="card-header bg-white">
            <h2 class="h6 fw-bold mb-0">Matrículas con saldo pendiente</h2>
        </div>
        <div class="card-body p-0">
            <?php if (!$pendientes || $pendientes->num_rows === 0): ?>
                <p class="text-muted small m-3">
                    No hay matrículas pendientes de pago.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Alumno</th>
                                <th>Curso</th>
                                <th>Horario</th>
                                <th>Fecha matrícula</th>
                                <th class="text-center">Precio curso</th>
                                <th class="text-center">Pagado</th>
                                <th class="text-center">Saldo pendiente</th>
                                <th class="text-center">Registrar pago</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($m = $pendientes->fetch_assoc()): ?>
                            <?php
                                $precio_curso = obtenerPrecioCursoActual($mysqli, (int)$m['curso_id']);
                                $pagado = $m['monto_pagado'] !== null ? (float)$m['monto_pagado'] : 0.0;
                                $saldo  = max($precio_curso - $pagado, 0);
                                if ($precio_curso <= 0 || $saldo <= 0) {
                                    // No mostrar si no hay precio o ya está totalmente pagado
                                    continue;
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold small mb-0">
                                        <?= htmlspecialchars($m['apellido'] . ", " . $m['nombre']) ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars($m['email']) ?>
                                    </div>
                                </td>
                                <td class="small">
                                    <?= htmlspecialchars($m['nombre_curso']) ?>
                                </td>
                                <td class="small">
                                    #<?= (int)$m['horario_id'] ?>
                                </td>
                                <td class="small">
                                    <?= date("d/m/Y H:i", strtotime($m['fecha_matricula'])) ?>
                                </td>
                                <td class="text-center small">
                                    L. <?= number_format($precio_curso, 2) ?>
                                </td>
                                <td class="text-center small">
                                    L. <?= number_format($pagado, 2) ?>
                                </td>
                                <td class="text-center small">
                                    <span class="fw-bold text-danger">
                                        L. <?= number_format($saldo, 2) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <form method="post" class="row g-1 align-items-center">
                                        <input type="hidden" name="accion" value="registrar_pago">
                                        <input type="hidden" name="matricula_id" value="<?= (int)$m['matricula_id'] ?>">

                                        <div class="col-12 mb-1">
                                            <select name="metodo_pago_id" class="form-select form-select-sm">
                                                <option value="">Método...</option>
                                                <?php foreach ($metodos_pago as $mp): ?>
                                                    <option value="<?= $mp['id'] ?>">
                                                        <?= htmlspecialchars($mp['nombre_metodo']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 mb-1">
                                            <input type="number" step="0.01" min="0.01"
                                                   name="monto_pagado"
                                                   class="form-control form-control-sm"
                                                   placeholder="Monto a registrar">
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                                Registrar
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
