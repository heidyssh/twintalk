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

// LISTA DE AVATARES PREDETERMINADOS
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

// CARPETAS
$uploadDirAvatar     = __DIR__ . "/../uploads/avatars/";
$uploadDirTitulo     = __DIR__ . "/../uploads/titulos/";

$uploadUrlAvatar     = "/twintalk/uploads/avatars/";
$uploadUrlTitulo     = "/twintalk/uploads/titulos/";

if (!is_dir($uploadDirAvatar)) mkdir($uploadDirAvatar, 0777, true);
if (!is_dir($uploadDirTitulo)) mkdir($uploadDirTitulo, 0777, true);


// =================  CARGAR DATOS =====================
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

// TÍTULOS ACADÉMICOS
$titulos_academicos = $mysqli->query("SELECT id, nombre_titulo, nivel_titulo FROM titulos_academicos ORDER BY nombre_titulo ASC");

$avatar_actual = $usuario['foto_perfil'] ?: "/twintalk/assets/img/avatars/avatar1.jpg";


// =====================  PROCESAR FORMULARIOS =====================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- ACTUALIZAR DATOS BÁSICOS ----------
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

        // Actualizar usuarios
        $stmt = $mysqli->prepare("UPDATE usuarios SET nombre=?, apellido=?, telefono=? WHERE id=?");
        $stmt->bind_param("sssi", $nombre, $apellido, $telefono, $usuario_id);
        $stmt->execute();
        $stmt->close();

        // Actualizar / insertar informacion_personal
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

        // Actualizar docente
        $stmt = $mysqli->prepare("UPDATE docentes SET especialidad=? WHERE id=?");
        $stmt->bind_param("si", $especialidad, $usuario_id);
        $stmt->execute();
        $stmt->close();

        // Título académico
        if (!empty($titulo_id_post)) {
            $titulo_id = (int)$titulo_id_post;
            $stmt = $mysqli->prepare("UPDATE docentes SET titulo_id=? WHERE id=?");
            $stmt->bind_param("ii", $titulo_id, $usuario_id);
            $stmt->execute();
            $stmt->close();
        }

        $mensaje = "Datos actualizados correctamente.";
    }

    // ---------- CAMBIAR CONTRASEÑA ----------
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

    // ---------- AVATAR PREDETERMINADO ----------
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

    // ---------- SUBIR AVATAR PROPIO ----------
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

    // ---------- SUBIR ARCHIVO DE TÍTULO ----------
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


// ===================== INTERFAZ =====================
include __DIR__ . '/../includes/header.php';
?>

<style>
.avatar-preview {
    width: 90px; height: 90px; border-radius:50%; object-fit:cover;
}
.avatar-option img {
    width:45px;height:45px;border-radius:50%;object-fit:cover;border:2px solid #ddd;
}
</style>

<div class="container my-4">

<h1 class="h4 fw-bold">Mi perfil docente</h1>

<?php if ($mensaje): ?><div class="alert alert-success"><?= $mensaje ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>


<div class="row g-4">

    <!-- ============ COLUMNA IZQUIERDA ============ -->
    <div class="col-lg-4">

        <!-- Perfil Básico -->
        <div class="card card-soft p-3 text-center mb-3">
            <img src="<?= htmlspecialchars($avatar_actual) ?>" class="avatar-preview mb-2">
            <h5 class="mb-0"><?= htmlspecialchars($usuario['nombre']." ".$usuario['apellido']) ?></h5>
            <p class="text-muted small mb-1"><?= htmlspecialchars($usuario['email']) ?></p>
            <p class="small">Teléfono: <?= htmlspecialchars($usuario['telefono'] ?? 'No registrado') ?></p>
        </div>

        <!-- Cambio de contraseña -->
        <div class="card card-soft p-3 mb-3">
            <h2 class="h6 fw-bold mb-3">Cambiar contraseña</h2>

            <form method="post">
                <label>Nueva contraseña</label>
                <input type="password" name="password" class="form-control mb-2">

                <label>Repetir contraseña</label>
                <input type="password" name="password2" class="form-control mb-3">

                <button class="btn btn-outline-primary w-100" name="cambiar_password">
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
                        <label>Nombre</label>
                        <input class="form-control" name="nombre" value="<?= $usuario['nombre'] ?>">
                    </div>

                    <div class="col-md-6 mb-2">
                        <label>Apellido</label>
                        <input class="form-control" name="apellido" value="<?= $usuario['apellido'] ?>">
                    </div>

                    <div class="col-md-6 mb-2">
                        <label>Correo</label>
                        <input class="form-control" value="<?= $usuario['email'] ?>" disabled>
                    </div>

                    <div class="col-md-6 mb-2">
                        <label>Teléfono</label>
                        <input class="form-control" name="telefono" value="<?= $usuario['telefono'] ?>">
                    </div>

                    <div class="col-md-6 mb-2">
                        <label>Ciudad</label>
                        <input class="form-control" name="ciudad" value="<?= $info['ciudad'] ?? '' ?>">
                    </div>

                    <div class="col-md-6 mb-2">
                        <label>País</label>
                        <input class="form-control" name="pais" value="<?= $info['pais'] ?? '' ?>">
                    </div>

                    <div class="col-md-12 mb-2">
                        <label>Dirección</label>
                        <textarea name="direccion" class="form-control" rows="2"><?= $info['direccion'] ?? '' ?></textarea>
                    </div>

                    <div class="col-md-6 mb-2">
                        <label>Fecha nacimiento</label>
                        <input type="date" class="form-control" name="fecha_nacimiento"
                               value="<?= $info['fecha_nacimiento'] ?? '' ?>">
                    </div>

                    <div class="col-md-6 mb-2">
                        <label>Especialidad</label>
                        <input class="form-control" name="especialidad"
                               value="<?= $docente['especialidad'] ?? '' ?>">
                    </div>

                </div>

                <button class="btn btn-tt-primary mt-2" name="actualizar_perfil">Guardar cambios</button>
            </form>

        </div>


        <!-- ============ SUBIR TÍTULO ACADÉMICO (ARCHIVO) ============ -->
        <div class="card card-soft p-3 mb-3">

            <h2 class="h6 fw-bold mb-3">Título académico (archivo)</h2>

            <?php if (!empty($docente['archivo_titulo'])): ?>
                <p class="small">
                    Archivo actual:
                    <a href="<?= $docente['archivo_titulo'] ?>" target="_blank">Ver archivo</a>
                </p>
            <?php else: ?>
                <p class="small text-muted">No has subido tu título académico.</p>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="file" name="titulo_archivo" class="form-control mb-2">
                <button class="btn btn-sm btn-tt-primary" name="subir_titulo">Subir título</button>
            </form>

        </div>


        <!-- ============ AVATARES ============ -->
        <div class="card card-soft p-3">

            <h2 class="h6 fw-bold mb-3">Avatar</h2>

            <div class="mb-2">
                <img src="<?= htmlspecialchars($avatar_actual) ?>" class="avatar-preview">
            </div>

            <!-- AVATARES PREDETERMINADOS -->
<form method="post" class="mb-3">
    <div class="d-flex flex-wrap gap-2 mb-2">
        <?php foreach($lista_avatars as $av): ?>
            <label class="avatar-option">
                <input type="radio" name="avatar_url" value="<?= $av ?>">
                <img src="<?= $av ?>">
            </label>
        <?php endforeach; ?>
    </div>

    <button class="btn btn-outline-secondary btn-sm mt-3" name="elegir_avatar">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
