<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]); // admin

$mensaje = "";
$error   = "";

// Cambiar rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario_id'], $_POST['rol_id'])) {
    $usuario_id = (int)$_POST['usuario_id'];
    $rol_id     = (int)$_POST['rol_id'];

    if ($usuario_id <= 0 || $rol_id <= 0) {
        $error = "Datos inválidos.";
    } else {
        // No permitir que el admin actual se quite su propio rol
        if ($usuario_id == ($_SESSION['usuario_id'] ?? 0) && $rol_id != 1) {
            $error = "No puedes quitarte tu propio rol de administrador.";
        } else {
            // Actualizar rol en usuarios
            $stmt = $mysqli->prepare("UPDATE usuarios SET rol_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $rol_id, $usuario_id);
            if ($stmt->execute()) {

                // Si ahora es DOCENTE, asegurarse que exista en docentes
                if ($rol_id == 2) {
                    $stmtCheck = $mysqli->prepare("SELECT id FROM docentes WHERE id = ?");
                    $stmtCheck->bind_param("i", $usuario_id);
                    $stmtCheck->execute();
                    $stmtCheck->store_result();
                    if ($stmtCheck->num_rows == 0) {
                        $stmtIns = $mysqli->prepare("
                            INSERT INTO docentes (id, fecha_contratacion, activo)
                            VALUES (?, CURDATE(), 1)
                        ");
                        $stmtIns->bind_param("i", $usuario_id);
                        $stmtIns->execute();
                        $stmtIns->close();
                    }
                    $stmtCheck->close();
                }

                // Si ahora es ESTUDIANTE, asegurarse que exista en estudiantes
                if ($rol_id == 3) {
                    $stmtCheck = $mysqli->prepare("SELECT id FROM estudiantes WHERE id = ?");
                    $stmtCheck->bind_param("i", $usuario_id);
                    $stmtCheck->execute();
                    $stmtCheck->store_result();
                    if ($stmtCheck->num_rows == 0) {
                        $nivel_default = "A1";
                        $stmtIns = $mysqli->prepare("
                            INSERT INTO estudiantes (id, nivel_actual, fecha_inscripcion)
                            VALUES (?, ?, CURDATE())
                        ");
                        $stmtIns->bind_param("is", $usuario_id, $nivel_default);
                        $stmtIns->execute();
                        $stmtIns->close();
                    }
                    $stmtCheck->close();
                }

                $mensaje = "Rol actualizado correctamente.";
            } else {
                $error = "No se pudo actualizar el rol.";
            }
            $stmt->close();
        }
    }
}

// Cargar usuarios + rol actual
$usuarios = $mysqli->query("
    SELECT u.id, u.nombre, u.apellido, u.email, u.rol_id, r.nombre_rol
    FROM usuarios u
    JOIN roles r ON u.rol_id = r.id
    ORDER BY u.fecha_registro DESC
");

// Cargar roles para el select
$roles = [];
$resRoles = $mysqli->query("SELECT id, nombre_rol FROM roles ORDER BY id ASC");
if ($resRoles) {
    while ($row = $resRoles->fetch_assoc()) {
        $roles[] = $row;
    }
}

include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">
    <h1 class="h4 fw-bold mb-3">Gestión de Usuarios y Roles</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Usuarios registrados</h5>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Rol actual</th>
                            <th>Cambiar rol</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($usuarios && $usuarios->num_rows > 0): ?>
                        <?php while ($u = $usuarios->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars($u['nombre_rol']) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                                        <select name="rol_id" class="form-select form-select-sm" style="max-width: 180px;">
                                            <?php foreach ($roles as $r): ?>
                                                <option value="<?= (int)$r['id'] ?>"
                                                    <?= $r['id'] == $u['rol_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($r['nombre_rol']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            Guardar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-muted">No hay usuarios registrados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="small text-muted mb-0">
                Al asignar rol <strong>docente</strong> se crea un registro en la tabla <code>docentes</code> (si no existe).  
                Al asignar rol <strong>estudiante</strong> se crea un registro en la tabla <code>estudiantes</code> (si no existe).
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
