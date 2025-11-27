<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_role([1]); // solo admin

$mensaje = "";
$error   = "";

// Marcar como leído
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'marcar_leido') {
    $id = (int)($_POST['mensaje_id'] ?? 0);
    if ($id > 0) {
        $stmt = $mysqli->prepare("UPDATE mensajes_interes SET leido = 1 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $mensaje = "Mensaje marcado como leído.";
            } else {
                $error = "No se pudo actualizar el mensaje.";
            }
            $stmt->close();
        } else {
            $error = "No se pudo preparar la actualización.";
        }
    }
}

// Traer mensajes
$result = $mysqli->query("
    SELECT id, nombre, email, telefono, programa, mensaje, fecha_envio, leido
    FROM mensajes_interes
    ORDER BY fecha_envio DESC
");

include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">
    <h1 class="mb-3">
        <i class="fa-solid fa-inbox me-2"></i>
        Mensajes de interés de la plataforma
    </h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-success small"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted small">
                Aquí se muestran los mensajes enviados desde el formulario de contacto del sitio público.
            </p>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Nombre</th>
                            <th>Contacto</th>
                            <th>Programa de interés</th>
                            <th>Mensaje</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="small">
                                        <?= htmlspecialchars($row['fecha_envio']) ?>
                                    </td>
                                    <td class="small">
                                        <?= htmlspecialchars($row['nombre']) ?>
                                    </td>
                                    <td class="small">
                                        <div>
                                            <i class="fa-solid fa-envelope me-1 text-primary"></i>
                                            <a href="mailto:<?= htmlspecialchars($row['email']) ?>">
                                                <?= htmlspecialchars($row['email']) ?>
                                            </a>
                                        </div>
                                        <?php if (!empty($row['telefono'])): ?>
                                            <div>
                                                <i class="fa-solid fa-phone me-1 text-success"></i>
                                                <?= htmlspecialchars($row['telefono']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?= htmlspecialchars($row['programa'] ?: '—') ?>
                                    </td>
                                    <td class="small" style="max-width: 320px;">
                                        <div class="text-wrap">
                                            <?= nl2br(htmlspecialchars($row['mensaje'])) ?>
                                        </div>
                                    </td>
                                    <td class="small">
                                        <?php if ((int)$row['leido'] === 1): ?>
                                            <span class="badge bg-success">Leído</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Nuevo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if ((int)$row['leido'] === 0): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="accion" value="marcar_leido">
                                                <input type="hidden" name="mensaje_id" value="<?= (int)$row['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    Marcar leído
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted small">
                                    No hay mensajes registrados.
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
