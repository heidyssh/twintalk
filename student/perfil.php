<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); // estudiante

$usuario_id = $_SESSION['usuario_id'];
$mensaje = "";
$error = "";

// Avatares de ejemplo (predeterminados)
$lista_avatars = [
    "/twintalk/assets/img/avatars/avatar1.jpg",
    "/twintalk/assets/img/avatars/avatar2.jpg",
    "/twintalk/assets/img/avatars/avatar3.jpg",
    "/twintalk/assets/img/avatars/avatar4.jpg",
    "/twintalk/assets/img/avatars/avatar5.jpg",
    "/twintalk/assets/img/avatars/avatar6.jpg",
    "/twintalk/assets/img/avatars/avatar7.jpg",
    "/twintalk/assets/img/avatars/avatar8.jpg",
    "/twintalk/assets/img/avatars/avatar9.jpg",
    "/twintalk/assets/img/avatars/avatar10.jpg",
    "/twintalk/assets/img/avatars/avatar11.jpg",
    "/twintalk/assets/img/avatars/avatar12.jpg",
    "/twintalk/assets/img/avatars/avatar13.jpg",
    "/twintalk/assets/img/avatars/avatar14.jpg",
    "/twintalk/assets/img/avatars/avatar15.jpg",
    "/twintalk/assets/img/avatars/avatar16.jpg",
    "/twintalk/assets/img/avatars/avatar17.jpg",
    "/twintalk/assets/img/avatars/avatar19.jpg",
    "/twintalk/assets/img/avatars/avatar20.jpg",
    "/twintalk/assets/img/avatars/avatar21.jpg",
    "/twintalk/assets/img/avatars/avatar22.jpg",
    "/twintalk/assets/img/avatars/avatar23.jpg",
    "/twintalk/assets/img/avatars/avatar24.jpg",
    "/twintalk/assets/img/avatars/avatar25.jpg",
    "/twintalk/assets/img/avatars/avatar26.jpg",
    "/twintalk/assets/img/avatars/avatar27.jpg",
    "/twintalk/assets/img/avatars/avatar28.jpg",
];

// Carpeta para avatares subidos por usuario
$uploadDir = __DIR__ . "/../uploads/avatars/";
$uploadUrlBase = "/twintalk/uploads/avatars/";

// Asegurar que la carpeta exista (por si acaso)
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1) Actualizar datos personales
    if (isset($_POST['actualizar_perfil'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        $stmt = $mysqli->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, telefono = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nombre, $apellido, $telefono, $usuario_id);
        if ($stmt->execute()) {
            $_SESSION['nombre'] = $nombre;
            $_SESSION['apellido'] = $apellido;
            $mensaje = "Perfil actualizado.";
        } else {
            $error = "Error al actualizar perfil.";
        }
    }

    // 2) Cambiar contraseña
    elseif (isset($_POST['cambiar_password'])) {
        $pass1 = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';
        if ($pass1 === '' || $pass2 === '') {
            $error = "Debes escribir la nueva contraseña dos veces.";
        } elseif ($pass1 !== $pass2) {
            $error = "Las contraseñas no coinciden.";
        } else {
            $hash = password_hash($pass1, PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $usuario_id);
            if ($stmt->execute()) {
                $mensaje = "Contraseña actualizada.";
            } else {
                $error = "Error al actualizar contraseña.";
            }
        }
    }

    // 3) Seleccionar avatar predeterminado
    elseif (isset($_POST['seleccionar_avatar'])) {
        $avatar = $_POST['avatar'] ?? '';
        if (in_array($avatar, $lista_avatars, true)) {
            $stmt = $mysqli->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
            $stmt->bind_param("si", $avatar, $usuario_id);
            if ($stmt->execute()) {
                $_SESSION['foto_perfil'] = $avatar;
                $mensaje = "Avatar actualizado.";
            } else {
                $error = "Error al actualizar avatar.";
            }
        } else {
            $error = "Avatar seleccionado no válido.";
        }
    }

    // 4) Subir avatar desde la computadora
    elseif (isset($_POST['subir_avatar']) && isset($_FILES['avatar_file'])) {
        $file = $_FILES['avatar_file'];

        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = [
                'image/png'  => 'png',
                'image/jpeg' => 'jpg',
                'image/jpg'  => 'jpg',
            ];

            if (!array_key_exists($file['type'], $allowed)) {
                $error = "Formato de imagen no permitido. Usa PNG o JPG.";
            } elseif ($file['size'] > 2 * 1024 * 1024) { // 2 MB
                $error = "La imagen es muy pesada. Máximo 2MB.";
            } else {
                $ext = $allowed[$file['type']];
                $filename = "user_" . $usuario_id . "_" . time() . "." . $ext;

                $destinoFs = $uploadDir . $filename;         // ruta física
                $destinoUrl = $uploadUrlBase . $filename;    // ruta para guardar en BD

                if (move_uploaded_file($file['tmp_name'], $destinoFs)) {
                    // Guardar ruta en BD
                    $stmt = $mysqli->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
                    $stmt->bind_param("si", $destinoUrl, $usuario_id);
                    if ($stmt->execute()) {
                         $_SESSION['foto_perfil'] = $destinoUrl;  // 🔴 ACTUALIZA SESIÓN
                        $mensaje = "Avatar subido y actualizado correctamente.";
                    } else {
                        $error = "Se subió la imagen pero falló al guardar en la base de datos.";
                    }
                } else {
                    $error = "No se pudo guardar el archivo en el servidor.";
                }
            }
        } else {
            $error = "Ocurrió un error al subir la imagen (código: {$file['error']}).";
        }
    }
}

// Obtener datos del usuario
$stmt = $mysqli->prepare("SELECT nombre, apellido, email, telefono, foto_perfil FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// Avatar actual
$avatar_actual = $usuario['foto_perfil'] ?: "/twintalk/assets/img/avatars/avatar1.png";

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Mi perfil</h1>
<div class="mb-3">
   <a href="/twintalk/student/dashboard.php" 
   class="btn btn-outline-secondary btn-sm">
   ← Regresar
</a>
</div>
<?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3 mt-2">
    <!-- Datos personales -->
    <div class="col-md-6">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6 fw-bold mb-3">Datos personales</h2>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input class="form-control" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Apellido</label>
                    <input class="form-control" name="apellido" value="<?= htmlspecialchars($usuario['apellido']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo (solo lectura)</label>
                    <input class="form-control" value="<?= htmlspecialchars($usuario['email']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Teléfono</label>
                    <input class="form-control" name="telefono" value="<?= htmlspecialchars($usuario['telefono']) ?>">
                </div>
                <button class="btn btn-tt-primary" name="actualizar_perfil">Guardar cambios</button>
            </form>
        </div>
    </div>

    <!-- Contraseña + Avatares -->
    <div class="col-md-6">
        <!-- Cambio de contraseña -->
        <div class="card card-soft p-3 mb-3">
            <h2 class="h6 fw-bold mb-3">Cambio de contraseña</h2>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Repetir nueva contraseña</label>
                    <input type="password" name="password2" class="form-control">
                </div>
                <button class="btn btn-outline-secondary" name="cambiar_password">Actualizar contraseña</button>
            </form>
        </div>

        <!-- Avatares -->
        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-3">Avatar</h2>

            <div class="mb-3 text-center">
                <span class="small text-muted d-block mb-1">Avatar actual:</span>
                <img src="<?= htmlspecialchars($avatar_actual) ?>" class="avatar-preview" alt="Avatar actual">
            </div>

            <!-- Elegir avatar predeterminado -->
            <form method="post" class="mb-3">
                <span class="small text-muted d-block mb-1">Elegir un avatar predeterminado:</span>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php foreach ($lista_avatars as $av): ?>
                        <label class="border rounded-3 p-1" style="cursor:pointer;">
                            <input type="radio" name="avatar" value="<?= htmlspecialchars($av) ?>"
                                   class="form-check-input me-1" <?= $av === $avatar_actual ? 'checked' : '' ?>>
                            <img src="<?= htmlspecialchars($av) ?>" class="avatar-option" alt="Avatar">
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-sm btn-tt-primary" name="seleccionar_avatar">
                    Guardar avatar predeterminado
                </button>
            </form>

            <!-- Subir avatar propio -->
            <form method="post" enctype="multipart/form-data">
                <span class="small text-muted d-block mb-1">O subir tu propia foto:</span>
                <div class="mb-2">
                    <input type="file" name="avatar_file" class="form-control form-control-sm"
                           accept="image/png, image/jpeg">
                    <small class="text-muted">Formatos permitidos: PNG, JPG. Máx: 2MB.</small>
                </div>
                <button class="btn btn-sm btn-outline-secondary" name="subir_avatar">
                    Subir y usar esta imagen
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
