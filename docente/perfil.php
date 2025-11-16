<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); // Docente

$usuario_id = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error   = "";

// Avatares de ejemplo (los mismos que estudiante)
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

// Carpeta para avatares subidos
$uploadDir     = __DIR__ . "/../uploads/avatars/";
$uploadUrlBase = "/twintalk/uploads/avatars/";

// Aseguramos la carpeta
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

// Cargar datos básicos del usuario + rol
$sqlUser = "
    SELECT u.*, r.nombre_rol
    FROM usuarios u
    LEFT JOIN roles r ON u.rol_id = r.id
    WHERE u.id = ?
";
$stmt = $mysqli->prepare($sqlUser);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    die("Usuario no encontrado.");
}

// Cargar información adicional
$sqlInfo = "SELECT * FROM informacion_personal WHERE usuario_id = ?";
$stmt = $mysqli->prepare($sqlInfo);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// -------------------- MANEJO DE FORMULARIOS --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1) Actualizar datos personales
    if (isset($_POST['actualizar_perfil'])) {
        $nombre          = trim($_POST['nombre'] ?? '');
        $apellido        = trim($_POST['apellido'] ?? '');
        $telefono        = trim($_POST['telefono'] ?? '');
        $fecha_nacimiento= $_POST['fecha_nacimiento'] ?? null;
        $direccion       = trim($_POST['direccion'] ?? '');
        $ciudad          = trim($_POST['ciudad'] ?? '');
        $pais            = trim($_POST['pais'] ?? '');

        // Actualizar tabla usuarios
        $sqlUpUser = "UPDATE usuarios SET nombre = ?, apellido = ?, telefono = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sqlUpUser);
        $stmt->bind_param("sssi", $nombre, $apellido, $telefono, $usuario_id);
        if ($stmt->execute()) {
            $_SESSION['nombre']   = $nombre;
            $_SESSION['apellido'] = $apellido;
        }
        $stmt->close();

        // Insertar / actualizar informacion_personal
        if ($info) {
            $sqlUpInfo = "
                UPDATE informacion_personal
                SET direccion = ?, ciudad = ?, pais = ?, fecha_nacimiento = ?
                WHERE usuario_id = ?
            ";
            $stmt = $mysqli->prepare($sqlUpInfo);
            $stmt->bind_param("ssssi", $direccion, $ciudad, $pais, $fecha_nacimiento, $usuario_id);
        } else {
            $sqlUpInfo = "
                INSERT INTO informacion_personal
                (usuario_id, direccion, ciudad, pais, fecha_nacimiento)
                VALUES (?, ?, ?, ?, ?)
            ";
            $stmt = $mysqli->prepare($sqlUpInfo);
            $stmt->bind_param("issss", $usuario_id, $direccion, $ciudad, $pais, $fecha_nacimiento);
        }

        if ($stmt->execute()) {
            $mensaje = "Perfil actualizado correctamente.";
        } else {
            $error = "Error al actualizar la información adicional.";
        }
        $stmt->close();
    }

    // 2) Cambiar contraseña
    elseif (isset($_POST['cambiar_password'])) {
        $pass1 = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';

        if ($pass1 === '' || $pass2 === '') {
            $error = "Debes escribir la nueva contraseña dos veces.";
        } elseif ($pass1 !== $pass2) {
            $error = "Las contraseñas no coinciden.";
        } elseif (strlen($pass1) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres.";
        } else {
            $hash = password_hash($pass1, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $usuario_id);
            if ($stmt->execute()) {
                $mensaje = "Contraseña actualizada correctamente.";
            } else {
                $error = "Error al actualizar la contraseña.";
            }
            $stmt->close();
        }
    }

    // 3) Elegir uno de los avatares predefinidos
    elseif (isset($_POST['elegir_avatar']) && isset($_POST['avatar_url'])) {
        $avatar_url = $_POST['avatar_url'];

        if (!in_array($avatar_url, $lista_avatars)) {
            $error = "Avatar no válido.";
        } else {
            $stmt = $mysqli->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
            $stmt->bind_param("si", $avatar_url, $usuario_id);
            if ($stmt->execute()) {
                $_SESSION['foto_perfil'] = $avatar_url;
                $mensaje = "Avatar actualizado correctamente.";
            } else {
                $error = "Error al actualizar el avatar.";
            }
            $stmt->close();
        }
    }

    // 4) Subir avatar desde archivo
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
            } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB
                $error = "La imagen es muy pesada. Máximo 2MB.";
            } else {
                $ext      = $allowed[$file['type']];
                $filename = "docente_" . $usuario_id . "_" . time() . "." . $ext;

                $destinoFs  = $uploadDir . $filename;
                $destinoUrl = $uploadUrlBase . $filename;

                if (move_uploaded_file($file['tmp_name'], $destinoFs)) {
                    $stmt = $mysqli->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
                    $stmt->bind_param("si", $destinoUrl, $usuario_id);
                    if ($stmt->execute()) {
                        $_SESSION['foto_perfil'] = $destinoUrl;
                        $mensaje = "Avatar subido y actualizado correctamente.";
                    } else {
                        $error = "Se subió la imagen pero falló al guardar en la base de datos.";
                    }
                    $stmt->close();
                } else {
                    $error = "Error al mover el archivo subido.";
                }
            }
        } else {
            $error = "Error al subir el archivo (código: {$file['error']}).";
        }
    }

    // Recargar datos después de cambios
    header("Location: perfil.php");
    exit;
}

// Recargar info adicional (por si cambió)
$sqlInfo = "SELECT * FROM informacion_personal WHERE usuario_id = ?";
$stmt = $mysqli->prepare($sqlInfo);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 fw-bold mt-3">Mi perfil docente</h1>
<p class="text-muted">
    Rol: <?= htmlspecialchars($usuario['nombre_rol'] ?? 'Docente') ?>
</p>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- COLUMNA IZQUIERDA -->
    <div class="col-lg-4">
        <!-- Perfil básico -->
       <div class="card card-soft mb-3" style="padding: 20px 10px;">
    <div class="card-body text-center p-2">
        <div class="mb-2">
            <img src="<?= htmlspecialchars($usuario['foto_perfil'] ?? $_SESSION['foto_perfil'] ?? '/twintalk/assets/img/default_user.png') ?>"
                 class="rounded-circle"
                 style="width:95px;height:95px;object-fit:cover;">
        </div>

        <h5 class="card-title mb-1" style="font-size: 1rem;">
            <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?>
        </h5>

        <p class="text-muted mb-1" style="font-size: 0.85rem;">
            <?= htmlspecialchars($usuario['email']) ?>
        </p>

        <p class="small mb-0" style="font-size: 0.8rem;">
            Teléfono: <?= htmlspecialchars($usuario['telefono'] ?? 'No registrado') ?>
        </p>
    </div>
</div>

        <!-- Cambio de contraseña (debajo del avatar) -->
        <div class="card card-soft">
            <div class="card-body">
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
                    <button class="btn btn-outline-primary btn-sm w-100" name="cambiar_password">
                        Actualizar contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- COLUMNA DERECHA -->
    <div class="col-lg-8">
        <div class="card card-soft h-60">
            <div class="card-body">
                <!-- DATOS PERSONALES -->
                <h2 class="h6 fw-bold mb-3">Datos personales</h2>
                <form method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre</label>
                            <input class="form-control" name="nombre"
                                   value="<?= htmlspecialchars($usuario['nombre']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Apellido</label>
                            <input class="form-control" name="apellido"
                                   value="<?= htmlspecialchars($usuario['apellido']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo (solo lectura)</label>
                            <input class="form-control" value="<?= htmlspecialchars($usuario['email']) ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input class="form-control" name="telefono"
                                   value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" class="form-control"
                                   value="<?= htmlspecialchars($info['fecha_nacimiento'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ciudad</label>
                            <input type="text" name="ciudad" class="form-control"
                                   value="<?= htmlspecialchars($info['ciudad'] ?? '') ?>">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Dirección</label>
                            <textarea name="direccion" class="form-control"
                                      rows="2"><?= htmlspecialchars($info['direccion'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">País</label>
                            <input type="text" name="pais" class="form-control"
                                   value="<?= htmlspecialchars($info['pais'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mb-3" name="actualizar_perfil">
                        Guardar cambios
                    </button>
                </form>

                <hr class="my-4">

                <!-- AVATARES -->
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <h2 class="h6 fw-bold mb-2">Elegir avatar</h2>
                        <form method="post">
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($lista_avatars as $avatar): ?>
                                    <label class="avatar-option">
                                        <input type="radio" name="avatar_url" value="<?= htmlspecialchars($avatar) ?>" required>
                                        <img src="<?= htmlspecialchars($avatar) ?>" class="rounded-circle"
                                             style="width:45px;height:45px;object-fit:cover;border:2px solid #ddd;">
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <br>
                            <button class="btn btn-sm btn-outline-secondary" name="elegir_avatar">
                                Usar avatar seleccionado
                            </button>
                        </form>
                    </div>

                    <div class="col-md-5 mb-3">
                        <h2 class="h6 fw-bold mb-2">Subir mi propio avatar</h2>
                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-2">
                                <input type="file" name="avatar_file" class="form-control form-control-sm"
                                       accept="image/png, image/jpeg">
                                <small class="text-muted d-block mt-1">
                                    PNG o JPG · Máx: 2MB
                                </small>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary w-100" name="subir_avatar">
                                Subir y usar esta imagen
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
