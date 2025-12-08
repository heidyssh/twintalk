<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]); 

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


$uploadDirAvatar     = __DIR__ . "/../uploads/avatars/";
$uploadDirTitulo     = __DIR__ . "/../uploads/titulos/";

$uploadUrlAvatar     = "/twintalk/uploads/avatars/";
$uploadUrlTitulo     = "/twintalk/uploads/titulos/";

if (!is_dir($uploadDirAvatar)) mkdir($uploadDirAvatar, 0777, true);
if (!is_dir($uploadDirTitulo)) mkdir($uploadDirTitulo, 0777, true);



$stmt = $mysqli->prepare("SELECT * FROM usuarios WHERE id=?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $mysqli->prepare("SELECT * FROM informacion_personal WHERE usuario_id=?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $mysqli->prepare("SELECT * FROM docentes WHERE id=?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$docente = $stmt->get_result()->fetch_assoc();
$stmt->close();

$titulo_actual = $docente['titulo_id'] ?? null;


$titulos_academicos = $mysqli->query("SELECT id, nombre_titulo, nivel_titulo FROM titulos_academicos ORDER BY nombre_titulo ASC");

$avatar_actual = $usuario['foto_perfil'] ?: "/twintalk/assets/img/avatars/avatar1.jpg";




if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    
    if (isset($_POST['actualizar_perfil'])) {

        $nombre   = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        $ciudad   = trim($_POST['ciudad'] ?? '');
        $pais     = trim($_POST['pais'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');

        $especialidad = trim($_POST['especialidad'] ?? '');
        $titulo_id_post = $_POST['titulo_id'] ?? null;

        
        $stmt = $mysqli->prepare("UPDATE usuarios SET nombre=?, apellido=?, telefono=? WHERE id=?");
        $stmt->bind_param("sssi", $nombre, $apellido, $telefono, $usuario_id);
        $stmt->execute();
        $stmt->close();

        
        $stmt = $mysqli->prepare("SELECT id FROM informacion_personal WHERE usuario_id=? LIMIT 1");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existe) {
            $stmt = $mysqli->prepare("
                UPDATE informacion_personal
                SET ciudad=?, pais=?, direccion=?, fecha_nacimiento=?
                WHERE usuario_id=?
            ");
            $stmt->bind_param("ssssi", $ciudad, $pais, $direccion, $fecha_nacimiento, $usuario_id);
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO informacion_personal (usuario_id, ciudad, pais, direccion, fecha_nacimiento)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issss", $usuario_id, $ciudad, $pais, $direccion, $fecha_nacimiento);
        }
        $stmt->execute();
        $stmt->close();

        
        $stmt = $mysqli->prepare("UPDATE docentes SET especialidad=? WHERE id=?");
        $stmt->bind_param("si", $especialidad, $usuario_id);
        $stmt->execute();
        $stmt->close();

        
        if (!empty($titulo_id_post)) {
            $titulo_id = (int)$titulo_id_post;
            $stmt = $mysqli->prepare("UPDATE docentes SET titulo_id=? WHERE id=?");
            $stmt->bind_param("ii", $titulo_id, $usuario_id);
            $stmt->execute();
            $stmt->close();
        }

        $mensaje = "Datos actualizados correctamente.";
    }

    
    elseif (isset($_POST['cambiar_password'])) {

        $p1 = $_POST['password'] ?? "";
        $p2 = $_POST['password2'] ?? "";

        if ($p1 === "" || $p2 === "") $error = "Escribe ambas contraseñas.";
        elseif ($p1 !== $p2)           $error = "Las contraseñas no coinciden.";
        else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE usuarios SET password_hash=? WHERE id=?");
            $stmt->bind_param("si", $hash, $usuario_id);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Contraseña actualizada.";
        }
    }

    
    elseif (isset($_POST['elegir_avatar'])) {
        $avatar = $_POST['avatar_url'] ?? "";

        if (in_array($avatar, $lista_avatars)) {
            $stmt = $mysqli->prepare("UPDATE usuarios SET foto_perfil=? WHERE id=?");
            $stmt->bind_param("si", $avatar, $usuario_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['foto_perfil'] = $avatar;
            $mensaje = "Avatar actualizado.";
        }
    }

    
    elseif (isset($_POST['subir_avatar']) && isset($_FILES['avatar_file'])) {

        $file = $_FILES['avatar_file'];

        if ($file['error'] === UPLOAD_ERR_OK) {

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ["png", "jpg", "jpeg"])) {
                $error = "Formato inválido. Use PNG o JPG.";
            } else {

                $nombre = "doc_" . $usuario_id . "_" . time() . "." . $ext;
                $fs = $uploadDirAvatar . $nombre;
                $url = $uploadUrlAvatar . $nombre;

                move_uploaded_file($file['tmp_name'], $fs);

                $stmt = $mysqli->prepare("UPDATE usuarios SET foto_perfil=? WHERE id=?");
                $stmt->bind_param("si", $url, $usuario_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['foto_perfil'] = $url;
                $mensaje = "Avatar subido correctamente.";
            }
        }
    }

    
    elseif (isset($_POST['subir_titulo']) && isset($_FILES['titulo_archivo'])) {

        $file = $_FILES['titulo_archivo'];

        if ($file['error'] === UPLOAD_ERR_OK) {

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ["pdf", "jpg", "jpeg", "png"])) {
                $error = "Formato inválido. Solo PDF/JPG/PNG";
            } else {

                $filename = "titulo_" . $usuario_id . "_" . time() . "." . $ext;
                $fs = $uploadDirTitulo . $filename;
                $url = $uploadUrlTitulo . $filename;

                move_uploaded_file($file['tmp_name'], $fs);

                $stmt = $mysqli->prepare("UPDATE docentes SET archivo_titulo=? WHERE id=?");
                $stmt->bind_param("si", $url, $usuario_id);
                $stmt->execute();
                $stmt->close();

                $mensaje = "Título académico subido correctamente.";
            }
        }
    }

    header("Location: perfil.php");
    exit;
}



include __DIR__ . '/../includes/header.php';
?>

<style>
    .tt-profile-page .tt-header-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #b14f72;
    }
    .tt-profile-page .tt-header-subtitle {
        font-size: 0.9rem;
        color: #6c757d;
    }
    .tt-profile-page .card-soft {
        border-radius: 14px;
        border: 1px solid #f1e3ea;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        background-color: #fff;
    }
    .tt-profile-page .btn-tt-primary {
        background-color: #b14f72;
        border-color: #b14f72;
        color: #fff;
        border-radius: 10px;
        font-size: 0.9rem;
        padding-inline: 1rem;
        transition: all 0.15s ease-in-out;
    }
    .tt-profile-page .btn-tt-primary:hover {
        background-color: #8f3454;
        border-color: #8f3454;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(177,79,114,0.35);
    }
    .tt-profile-page .btn-tt-outline {
        border-radius: 999px;
        border: 1px solid #b14f72;
        color: #b14f72;
        background-color: #fff;
        font-size: 0.85rem;
        padding-inline: 0.9rem;
        transition: all 0.15s ease-in-out;
    }
    .tt-profile-page .btn-tt-outline:hover {
        background-color: #b14f72;
        color: #fff;
        box-shadow: 0 3px 8px rgba(177,79,114,0.35);
    }
    .avatar-preview {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #f1e3ea;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }
    .avatar-option {
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .avatar-option input {
        display: none;
    }
    .avatar-option img {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #ddd;
        transition: all 0.15s ease-in-out;
    }
    .avatar-option input:checked + img {
        border-color: #b14f72;
        box-shadow: 0 0 0 3px rgba(177,79,114,0.25);
        transform: translateY(-1px);
    }
</style>

<div class="container my-4 tt-profile-page">

    <!-- Header bonito -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
             style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">
            <div>
                <h1 class="tt-header-title mb-1">
                    <i class="fa-solid fa-user-tie me-2"></i>
                    Mi perfil docente
                </h1>
                <p class="tt-header-subtitle mb-0">
                    Actualiza tus datos personales, contraseña, avatar y título académico.
                </p>
            </div>
            <div class="text-md-end">
                <a href="/twintalk/docente/dashboard.php" class="btn btn-sm btn-tt-outline">
                    <i class="fa-solid fa-arrow-left me-1"></i>
                    Volver al dashboard
                </a>
            </div>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success border-0 shadow-sm py-2 small mb-3">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm py-2 small mb-3">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>


    <div class="row g-4">

        <!-- ============ COLUMNA IZQUIERDA ============ -->
        <div class="col-lg-4">

            <!-- Perfil Básico -->
            <div class="card card-soft p-3 text-center mb-3">
                <img src="<?= htmlspecialchars($avatar_actual) ?>" class="avatar-preview mb-2" alt="Avatar docente">
                <h5 class="mb-0">
                    <?= htmlspecialchars($usuario['nombre']." ".$usuario['apellido']) ?>
                </h5>
                <p class="text-muted small mb-1"><?= htmlspecialchars($usuario['email']) ?></p>
                <p class="small mb-0">
                    Teléfono:
                    <?= htmlspecialchars($usuario['telefono'] ?? 'No registrado') ?>
                </p>
            </div>

            <!-- Cambio de contraseña -->
            <div class="card card-soft p-3 mb-3">
                <h2 class="h6 fw-bold mb-3">Cambiar contraseña</h2>

                <form method="post">
                    <label class="small mb-1">Nueva contraseña</label>
                    <input type="password" name="password" class="form-control form-control-sm mb-2">

                    <label class="small mb-1">Repetir contraseña</label>
                    <input type="password" name="password2" class="form-control form-control-sm mb-3">

                    <button class="btn btn-tt-outline w-100" name="cambiar_password">
                        Actualizar contraseña
                    </button>
                </form>
            </div>

        </div>

        <!-- ============ COLUMNA DERECHA ============ -->
        <div class="col-lg-8">

            <!-- DATOS PERSONALES -->
            <div class="card card-soft p-3 mb-3">
                <h2 class="h6 fw-bold mb-3">Datos personales</h2>

                <form method="post">

                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="small mb-1">Nombre</label>
                            <input class="form-control form-control-sm" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>">
                        </div>

                        <div class="col-md-6 mb-2">
                            <label class="small mb-1">Apellido</label>
                            <input class="form-control form-control-sm" name="apellido" value="<?= htmlspecialchars($usuario['apellido']) ?>">
                        </div>

                        <div class="col-md-6 mb-2">
                            <label class="small mb-1">Correo</label>
                            <input class="form-control form-control-sm" value="<?= htmlspecialchars($usuario['email']) ?>" disabled>
                        </div>

                        <div class="col-md-6 mb-2">
                            <label class="small mb-1">Teléfono</label>
                            <input class="form-control form-control-sm" name="telefono" value="<?= htmlspecialchars($usuario['telefono']) ?>">
                        </div>

                        <div class="col-md-6 mb-2">
                            <label class="small mb-1">Ciudad</label>
                            <input class="form-control form-control-sm" name="ciudad" value="<?= htmlspecialchars($info['ciudad'] ?? '') ?>">
                        </div>

                        <div class="col-md-6 mb-2">
                            <label class="small mb-1">País</label>
                            <input class="form-control form-control-sm" name="pais" value="<?= htmlspecialchars($info['pais'] ?? '') ?>">
                        </div>

                        <div class="col-md-12 mb-2">
                            <label class="small mb-1">Dirección</label>
                            <textarea name="direccion" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($info['direccion'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6 mb-2">
                            <label class="small mb-1">Fecha nacimiento</label>
                            <input type="date" class="form-control form-control-sm" name="fecha_nacimiento"
                                   value="<?= htmlspecialchars($info['fecha_nacimiento'] ?? '') ?>">
                        </div>

                        <div class="col-md-6 mb-2">
                            <label class="small mb-1">Especialidad</label>
                            <input class="form-control form-control-sm" name="especialidad"
                                   value="<?= htmlspecialchars($docente['especialidad'] ?? '') ?>">
                        </div>

                    </div>

                    <button class="btn btn-tt-primary mt-2" name="actualizar_perfil">Guardar cambios</button>
                </form>

            </div>


            <!-- ============ SUBIR TÍTULO ACADÉMICO (ARCHIVO) ============ -->
            <div class="card card-soft p-3 mb-3">

                <h2 class="h6 fw-bold mb-3">Título académico (archivo)</h2>

                <?php if (!empty($docente['archivo_titulo'])): ?>
                    <p class="small mb-2">
                        Archivo actual:
                        <a href="<?= htmlspecialchars($docente['archivo_titulo']) ?>" target="_blank">Ver archivo</a>
                    </p>
                <?php else: ?>
                    <p class="small text-muted mb-2">No has subido tu título académico.</p>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="titulo_archivo" class="form-control form-control-sm mb-2">
                    <button class="btn btn-sm btn-tt-primary" name="subir_titulo">Subir título</button>
                </form>

            </div>


            <!-- ============ AVATARES ============ -->
            <div class="card card-soft p-3">

                <h2 class="h6 fw-bold mb-3">Avatar</h2>

                <div class="mb-3 d-flex align-items-center gap-3">
                    <div>
                        <img src="<?= htmlspecialchars($avatar_actual) ?>" class="avatar-preview" alt="Avatar actual">
                    </div>
                    <div class="small text-muted">
                        Este es tu avatar actual. Puedes elegir uno de la galería o subir tu propia foto.
                    </div>
                </div>

                <!-- AVATARES PREDETERMINADOS -->
                <form method="post" class="mb-3">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php foreach($lista_avatars as $av): ?>
                            <label class="avatar-option">
                                <input type="radio" name="avatar_url" value="<?= $av ?>">
                                <img src="<?= $av ?>" alt="Avatar">
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <button class="btn btn-tt-outline btn-sm mt-2" name="elegir_avatar">
                        Usar avatar seleccionado
                    </button>
                </form>

                <!-- SUBIR AVATAR PROPIO -->
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="avatar_file" class="form-control form-control-sm mb-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" name="subir_avatar">
                        Subir y usar mi foto
                    </button>
                </form>

            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
