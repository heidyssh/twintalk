<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]); 

$usuario_id = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error   = "";


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


$uploadDir     = __DIR__ . "/../uploads/avatars/";
$uploadUrlBase = "/twintalk/uploads/avatars/";


if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}


$stmt = $mysqli->prepare("SELECT nombre, apellido, email, telefono, foto_perfil FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$stmt->bind_result($nombre, $apellido, $email, $telefono, $foto_perfil);
$stmt->fetch();
$stmt->close();

$avatar_actual = $foto_perfil ?: "/twintalk/assets/img/avatars/avatar1.jpg";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    
    if (isset($_POST['actualizar_perfil'])) {
        $nuevo_nombre   = trim($_POST['nombre'] ?? '');
        $nuevo_apellido = trim($_POST['apellido'] ?? '');
        $nuevo_telefono = trim($_POST['telefono'] ?? '');

        if ($nuevo_nombre === '' || $nuevo_apellido === '') {
            $error = "Nombre y apellido son obligatorios.";
        } else {
            $stmt = $mysqli->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, telefono = ? WHERE id = ?");
            $stmt->bind_param("sssi", $nuevo_nombre, $nuevo_apellido, $nuevo_telefono, $usuario_id);
            if ($stmt->execute()) {
                $_SESSION['nombre']   = $nuevo_nombre;
                $_SESSION['apellido'] = $nuevo_apellido;
                $mensaje = "Perfil actualizado correctamente.";
                $nombre   = $nuevo_nombre;
                $apellido = $nuevo_apellido;
                $telefono = $nuevo_telefono;
            } else {
                $error = "Error al actualizar el perfil.";
            }
            $stmt->close();
        }
    }

    
    elseif (isset($_POST['cambiar_password'])) {
        $pass1 = $_POST['password']  ?? '';
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
                $mensaje = "Contraseña actualizada correctamente.";
            } else {
                $error = "Error al actualizar la contraseña.";
            }
            $stmt->close();
        }
    }

    
    elseif (isset($_POST['actualizar_avatar'])) {
        $avatar_sel = $_POST['avatar_seleccionado'] ?? '';
        if (in_array($avatar_sel, $lista_avatars)) {
            $stmt = $mysqli->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
            $stmt->bind_param("si", $avatar_sel, $usuario_id);
            if ($stmt->execute()) {
                $_SESSION['foto_perfil'] = $avatar_sel;
                $mensaje = "Avatar actualizado.";
                $avatar_actual = $avatar_sel;
            } else {
                $error = "Error al actualizar el avatar.";
            }
            $stmt->close();
        } else {
            $error = "Avatar seleccionado no válido.";
        }
    }

    
    elseif (isset($_POST['subir_avatar']) && isset($_FILES['avatar_file'])) {
        $file = $_FILES['avatar_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "Error al subir el archivo.";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = "El archivo supera el límite de 2MB.";
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
                $error = "Formato no permitido. Solo PNG o JPG.";
            } else {
                $nuevo_nombre = "avatar_" . $usuario_id . "_" . time() . "." . $ext;
                $destino      = $uploadDir . $nuevo_nombre;
                if (move_uploaded_file($file['tmp_name'], $destino)) {
                    $avatar_url = $uploadUrlBase . $nuevo_nombre;
                    $stmt = $mysqli->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
                    $stmt->bind_param("si", $avatar_url, $usuario_id);
                    if ($stmt->execute()) {
                        $_SESSION['foto_perfil'] = $avatar_url;
                        $mensaje = "Avatar subido y actualizado.";
                        $avatar_actual = $avatar_url;
                    } else {
                        $error = "Error al guardar el avatar en la base de datos.";
                    }
                    $stmt->close();
                } else {
                    $error = "No se pudo mover el archivo subido.";
                }
            }
        }
    }
}

include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">
    <!-- HEADER bonito con gradiente -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
             style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">
            <div>
                <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                    Mi perfil
                </h1>
                <small class="text-muted">
                    Administra tus datos personales, contraseña y avatar de administrador.
                </small>
            </div>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Tarjeta de info + avatar -->
        <div class="col-md-4">
            <div class="card card-soft shadow-sm border-0">
                <div class="card-body text-center">
                    <img src="<?= htmlspecialchars($avatar_actual) ?>"
                         class="rounded-circle mb-3"
                         style="width: 120px; height: 120px; object-fit: cover;"
                         alt="Avatar">
                    <h5 class="mb-0" style="color:#4b2e83;">
                        <?= htmlspecialchars($nombre . " " . $apellido) ?>
                    </h5>
                    <p class="text-muted mb-1">Administrador</p>
                    <p class="small text-muted mb-0">
                        <?= htmlspecialchars($email) ?><br>
                        Tel: <?= htmlspecialchars($telefono ?? 'N/D') ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Formulario de datos + password + avatar -->
        <div class="col-md-8">
            <!-- Datos personales -->
            <div class="card card-soft shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3" style="color:#4b2e83;">Datos personales</h5>
                    <form method="post">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="nombre" class="form-control"
                                       value="<?= htmlspecialchars($nombre) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellido</label>
                                <input type="text" name="apellido" class="form-control"
                                       value="<?= htmlspecialchars($apellido) ?>" required>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control"
                                   value="<?= htmlspecialchars($telefono) ?>">
                        </div>
                        <div class="mt-3 text-end">
                            <button type="submit" name="actualizar_perfil" class="btn btn-sm"
                                style="
                                    background-color:#ff4b7b;
                                    border:1px solid #ff4b7b;
                                    color:white;
                                    font-weight:500;
                                    border-radius:6px;
                                    padding:6px 14px;
                                "
                                onmouseover="this.style.backgroundColor='#e84372'"
                                onmouseout="this.style.backgroundColor='#ff4b7b'"
                            >
                                Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cambio de contraseña -->
            <div class="card card-soft shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3" style="color:#4b2e83;">Cambio de contraseña</h2>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Nueva contraseña</label>
                            <div class="position-relative">
                                <input type="password"
                                       name="password"
                                       class="form-control pe-5"
                                       id="admin_password">
                                <button type="button"
                                        class="btn btn-link p-0 border-0 position-absolute top-50 end-0 translate-middle-y me-3"
                                        title="Mostrar/ocultar contraseña"
                                        onclick="ttTogglePassword('admin_password', this)">
                                    <i class="fa-solid fa-eye small text-muted"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Repetir nueva contraseña</label>
                            <div class="position-relative">
                                <input type="password"
                                       name="password2"
                                       class="form-control pe-5"
                                       id="admin_password2">
                                <button type="button"
                                        class="btn btn-link p-0 border-0 position-absolute top-50 end-0 translate-middle-y me-3"
                                        title="Mostrar/ocultar contraseña"
                                        onclick="ttTogglePassword('admin_password2', this)">
                                    <i class="fa-solid fa-eye small text-muted"></i>
                                </button>
                            </div>
                        </div>
                        <button class="btn btn-sm w-100"
                                name="cambiar_password"
                                style="
                                    border:1px solid #ff4b7b;
                                    color:#ff4b7b;
                                    background-color:transparent;
                                    border-radius:6px;
                                    padding:6px 10px;
                                    font-weight:500;
                                "
                                onmouseover="this.style.backgroundColor='#ff4b7b'; this.style.color='#fff';"
                                onmouseout="this.style.backgroundColor='transparent'; this.style.color='#ff4b7b';"
                        >
                            Actualizar contraseña
                        </button>
                    </form>
                </div>
            </div>

            <!-- Avatar -->
            <div class="card card-soft shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3" style="color:#4b2e83;">Avatar</h5>
                    <form method="post">
                        <div class="row g-2">
                            <?php foreach ($lista_avatars as $avatar): ?>
                                <div class="col-3 col-md-2 text-center">
                                    <label class="d-block">
                                        <input type="radio" name="avatar_seleccionado"
                                               value="<?= htmlspecialchars($avatar) ?>"
                                               class="form-check-input mb-1"
                                               <?= $avatar === $avatar_actual ? 'checked' : '' ?>>
                                        <img src="<?= htmlspecialchars($avatar) ?>"
                                             class="rounded-circle"
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="submit" name="actualizar_avatar" class="btn btn-sm"
                                style="
                                    border:1px solid #ff4b7b;
                                    color:#ff4b7b;
                                    background-color:transparent;
                                    border-radius:6px;
                                    padding:6px 14px;
                                    font-weight:500;
                                "
                                onmouseover="this.style.backgroundColor='#ff4b7b'; this.style.color='#fff';"
                                onmouseout="this.style.backgroundColor='transparent'; this.style.color='#ff4b7b';"
                            >
                                Usar avatar seleccionado
                            </button>
                        </div>
                    </form>

                    <hr>

                    <form method="post" enctype="multipart/form-data">
                        <label class="form-label">Subir tu propia imagen</label>
                        <input type="file" name="avatar_file" class="form-control form-control-sm"
                               accept="image/png, image/jpeg">
                        <small class="text-muted">Máx. 2MB. Formatos: PNG, JPG.</small>
                        <div class="mt-2 text-end">
                            <button type="submit" name="subir_avatar" class="btn btn-sm"
                                style="
                                    border:1px solid #6c757d;
                                    color:#6c757d;
                                    background-color:transparent;
                                    border-radius:6px;
                                    padding:6px 14px;
                                    font-weight:500;
                                "
                                onmouseover="this.style.backgroundColor='#6c757d'; this.style.color='#fff';"
                                onmouseout="this.style.backgroundColor='transparent'; this.style.color='#6c757d';"
                            >
                                Subir y usar esta imagen
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function ttTogglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.add('fa-eye');
            icon.classList.remove('fa-eye-slash');
        }
    }
}
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
