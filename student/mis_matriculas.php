<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]);

$usuario_id = $_SESSION['usuario_id'];
$mensaje = "";
$error = "";

// Helper para obtener id de estado por nombre
function obtenerEstadoId($mysqli, $nombre)
{
    $stmt = $mysqli->prepare("SELECT id FROM estados_matricula WHERE nombre_estado = ? LIMIT 1");
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        return (int) $row['id'];
    }
    return null;
}

// Procesar retiro de matrícula
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retirar_matricula'])) {
    $matricula_id = (int) ($_POST['matricula_id'] ?? 0);

    // Verificar que la matrícula pertenece al estudiante y está Activa
    $stmt = $mysqli->prepare("
        SELECT m.id, m.horario_id, em.nombre_estado
        FROM matriculas m
        INNER JOIN estados_matricula em ON m.estado_id = em.id
        WHERE m.id = ? AND m.estudiante_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $matricula_id, $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $mat = $res->fetch_assoc();
    $stmt->close();

    if (!$mat) {
        $error = "Matrícula no válida.";
    } elseif (!in_array($mat['nombre_estado'], ['Activa', 'Pendiente'])) {
    $error = "Solo puedes retirarte de matrículas activas o pendientes.";
    } else {
        $estado_cancelada = obtenerEstadoId($mysqli, 'Cancelada');
        if ($estado_cancelada === null) {
            $error = "No se encontró el estado 'Cancelada' en la tabla estados_matricula.";
        } else {
            // Cambiar estado a Cancelada
            $upd = $mysqli->prepare("UPDATE matriculas SET estado_id = ? WHERE id = ?");
            $upd->bind_param("ii", $estado_cancelada, $matricula_id);
            if ($upd->execute()) {
                // Liberar cupo del horario
                $upd2 = $mysqli->prepare("UPDATE horarios SET cupos_disponibles = cupos_disponibles + 1 WHERE id = ?");
                $upd2->bind_param("i", $mat['horario_id']);
                $upd2->execute();
                $upd2->close();

                $mensaje = "Te has retirado de la clase correctamente.";
            } else {
                $error = "No se pudo actualizar la matrícula.";
            }
            $upd->close();
        }
    }
}

// Consulta de historial con NOTA FINAL (tareas + evaluaciones)
$stmt = $mysqli->prepare("
   SELECT 
        m.id AS matricula_id,
        h.id AS horario_id,
        c.nombre_curso,
        n.codigo_nivel,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        em.id AS estado_id,
        em.nombre_estado,
        m.fecha_matricula,
        m.monto_pagado,
        mp.nombre_metodo,

        IFNULL(t_sum.suma_tareas, 0) AS suma_tareas,
        IFNULL(e_sum.suma_eval, 0)   AS suma_eval,
        (IFNULL(t_sum.suma_tareas, 0) + IFNULL(e_sum.suma_eval, 0)) AS nota_final


    FROM matriculas m
    JOIN horarios h ON m.horario_id = h.id
    JOIN cursos c ON h.curso_id = c.id
    JOIN niveles_academicos n ON c.nivel_id = n.id
    JOIN dias_semana d ON h.dia_semana_id = d.id
    JOIN estados_matricula em ON m.estado_id = em.id
    LEFT JOIN metodos_pago mp ON m.metodo_pago_id = mp.id

    LEFT JOIN (
        SELECT 
            te.matricula_id,
            SUM(te.calificacion) AS suma_tareas
        FROM tareas_entregas te
        INNER JOIN tareas t  ON t.id = te.tarea_id
        INNER JOIN horarios h ON h.id = t.horario_id
        WHERE te.calificacion IS NOT NULL
        GROUP BY te.matricula_id
    ) t_sum ON t_sum.matricula_id = m.id

    LEFT JOIN (
        SELECT 
            c.matricula_id,
            SUM(c.puntaje) AS suma_eval
        FROM calificaciones c
        WHERE c.puntaje IS NOT NULL
        GROUP BY c.matricula_id
    ) e_sum ON e_sum.matricula_id = m.id

    WHERE m.estudiante_id = ?
    ORDER BY m.fecha_matricula DESC
");

$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">
    <!-- Encabezado con gradiente, igual estilo que curso_detalle.php -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body" style="background: linear-gradient(90deg, #fbe9f0, #ffffff); border-radius: 0.75rem;">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                        Mis matrículas e historial académico
                    </h1>
                    <p class="text-muted mb-0 small">
                        Aquí puedes ver tus cursos inscritos, su estado, el detalle de pago y tu nota final por curso.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-soft py-2 small mb-3">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-soft py-2 small mb-3">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Card principal con la tabla de matrículas -->
    <div class="card card-soft border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive table-rounded">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Curso</th>
                            <th>Nivel</th>
                            <th>Día</th>
                            <th>Hora</th>
                            <th>Estado</th>
                            <th>Pago</th>
                            <th>Nota final</th>
                            <th>Fecha matrícula</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nombre_curso']) ?></td>
                                    <td><?= htmlspecialchars($row['codigo_nivel']) ?></td>
                                    <td><?= htmlspecialchars($row['nombre_dia']) ?></td>
                                    <td><?= substr($row['hora_inicio'], 0, 5) ?> - <?= substr($row['hora_fin'], 0, 5) ?></td>
                                    <td>
                                        <?php
                                        $estado = $row['nombre_estado'];
                                        $badgeClass = 'bg-secondary';
                                        if ($estado === 'Activa') {
                                            $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                        } elseif ($estado === 'Pendiente') {
                                            $badgeClass = 'bg-warning-subtle text-warning border border-warning-subtle';
                                        } elseif ($estado === 'Cancelada') {
                                            $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                        } elseif ($estado === 'Finalizada') {
                                            $badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                                        }
                                        ?>
                                        <span class="badge rounded-pill px-3 py-1 small <?= $badgeClass ?>">
                                            <?= htmlspecialchars($estado) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['monto_pagado'] !== null): ?>
                                            L <?= number_format($row['monto_pagado'], 2) ?>
                                            <?php if (!empty($row['nombre_metodo'])): ?>
                                                <span class="text-muted small d-block">
                                                    (<?= htmlspecialchars($row['nombre_metodo']) ?>)
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">Pendiente de pago</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((float) $row['nota_final'] > 0): ?>
                                            <?= number_format((float) $row['nota_final'], 2) ?>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin notas</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="small">
                                            <?= htmlspecialchars($row['fecha_matricula']) ?>
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <div
                                            class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-1">
                                            <?php if (in_array($row['nombre_estado'], ['Activa', 'Pendiente'])): ?>
                                                <!-- Botón para retirarse de la clase (si está Activa o Pendiente) -->
                                                <form method="post" class="d-inline"
                                                    onsubmit="return confirm('¿Seguro que deseas retirarte de esta clase?');">
                                                    <input type="hidden" name="matricula_id"
                                                        value="<?= (int) $row['matricula_id'] ?>">
                                                    <button type="submit" name="retirar_matricula"
                                                        class="btn btn-sm btn-outline-danger">
                                                        Retirarme
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Ver curso -->
                                            <a href="curso_detalle.php?horario_id=<?= (int) $row['horario_id'] ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                Ver curso
                                            </a>

                                            <!-- Ver diploma solo si la matrícula está finalizada -->
                                            <?php if ($row['nombre_estado'] === 'Finalizada'): ?>
                                                <a href="diploma_pdf.php?matricula_id=<?= (int) $row['matricula_id'] ?>"
                                                    class="btn btn-sm btn-success" target="_blank">
                                                    Ver diploma
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-muted small">
                                    Aún no tienes matrículas registradas.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>