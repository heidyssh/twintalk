<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]);

$usuario_id = $_SESSION['usuario_id'];
$mensaje = "";
$error = "";

// ================= AVATARES =================
$lista_avatars = [
    "/twintalk/assets/img/avatars/avatar1.jpg","/twintalk/assets/img/avatars/avatar2.jpg",
    "/twintalk/assets/img/avatars/avatar3.jpg","/twintalk/assets/img/avatars/avatar4.jpg",
    "/twintalk/assets/img/avatars/avatar5.jpg","/twintalk/assets/img/avatars/avatar6.jpg",
    "/twintalk/assets/img/avatars/avatar7.jpg","/twintalk/assets/img/avatars/avatar8.jpg",
    "/twintalk/assets/img/avatars/avatar9.jpg","/twintalk/assets/img/avatars/avatar10.jpg",
    "/twintalk/assets/img/avatars/avatar11.jpg","/twintalk/assets/img/avatars/avatar12.jpg",
    "/twintalk/assets/img/avatars/avatar13.jpg","/twintalk/assets/img/avatars/avatar14.jpg",
    "/twintalk/assets/img/avatars/avatar15.jpg","/twintalk/assets/img/avatars/avatar16.jpg",
    "/twintalk/assets/img/avatars/avatar17.jpg","/twintalk/assets/img/avatars/avatar19.jpg",
    "/twintalk/assets/img/avatars/avatar20.jpg","/twintalk/assets/img/avatars/avatar21.jpg",
    "/twintalk/assets/img/avatars/avatar22.jpg","/twintalk/assets/img/avatars/avatar23.jpg",
    "/twintalk/assets/img/avatars/avatar24.jpg","/twintalk/assets/img/avatars/avatar25.jpg",
    "/twintalk/assets/img/avatars/avatar26.jpg","/twintalk/assets/img/avatars/avatar27.jpg",
    "/twintalk/assets/img/avatars/avatar28.jpg",
];

// ================= RUTAS =================
$uploadDirAvatar = __DIR__ . "/../uploads/avatars/";
$uploadDirDocumento = __DIR__ . "/../uploads/documentos/";
$urlAvatar = "/twintalk/uploads/avatars/";
$urlDocumento = "/twintalk/uploads/documentos/";

if (!is_dir($uploadDirAvatar)) mkdir($uploadDirAvatar, 0777, true);
if (!is_dir($uploadDirDocumento)) mkdir($uploadDirDocumento, 0777, true);

// ================= PETICIONES =================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------ DATOS PERSONALES ------
    if (isset($_POST['actualizar_perfil'])) {
        $stmt = $mysqli->prepare("UPDATE usuarios SET nombre=?, apellido=?, telefono=? WHERE id=?");
        $stmt->bind_param("sssi", $_POST['nombre'], $_POST['apellido'], $_POST['telefono'], $usuario_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['nombre'] = $_POST['nombre'];
        $_SESSION['apellido'] = $_POST['apellido'];
        $mensaje = "Datos actualizados.";
    }

    // ------ CONTRASEÑA ------
    elseif (isset($_POST['cambiar_password'])) {
        if ($_POST['password'] === "" || $_POST['password2'] === "")
            $error = "Debes completar ambas contraseñas.";
        elseif ($_POST['password'] !== $_POST['password2'])
            $error = "Las contraseñas no coinciden.";
        else {
            $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare("UPDATE usuarios SET password_hash=? WHERE id=?");
            $stmt->bind_param("si", $hash, $usuario_id);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Contraseña actualizada.";
        }
    }

    // ------ AVATAR SELECCIONADO ------
    elseif (isset($_POST['seleccionar_avatar'])) {
        $avatar = $_POST['avatar'];
        if (in_array($avatar, $lista_avatars)) {
            $stmt = $mysqli->prepare("UPDATE usuarios SET foto_perfil=? WHERE id=?");
            $stmt->bind_param("si", $avatar, $usuario_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['foto_perfil'] = $avatar;
            $mensaje = "Avatar actualizado.";
        }
    }

    // ------ AVATAR SUBIDO ------
    elseif (isset($_POST['subir_avatar']) && !empty($_FILES['avatar_file']['name'])) {
        $file = $_FILES['avatar_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ["jpg","jpeg","png"])) $error = "Formato inválido.";
        else {
            $nombre = "user_{$usuario_id}_" . time() . ".$ext";
            move_uploaded_file($file['tmp_name'], $uploadDirAvatar.$nombre);
            $ruta = $urlAvatar . $nombre;

            $stmt = $mysqli->prepare("UPDATE usuarios SET foto_perfil=? WHERE id=?");
            $stmt->bind_param("si", $ruta, $usuario_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['foto_perfil'] = $ruta;
            $mensaje = "Avatar subido.";
        }
    }

    // ------ DATOS ADICIONALES ------
    elseif (isset($_POST['guardar_info'])) {

        $stmt = $mysqli->prepare("SELECT id FROM informacion_personal WHERE usuario_id=? LIMIT 1");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existe) {
            $stmt = $mysqli->prepare("
                UPDATE informacion_personal
                SET numero_documento=?, fecha_nacimiento=?, direccion=?, ciudad=?, pais=?
                WHERE usuario_id=?
            ");
            $stmt->bind_param("sssssi",
                $_POST['numero_documento'], $_POST['fecha_nacimiento'], $_POST['direccion'],
                $_POST['ciudad'], $_POST['pais'], $usuario_id
            );
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO informacion_personal 
                (usuario_id, numero_documento, fecha_nacimiento, direccion, ciudad, pais)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssss",
                $usuario_id, $_POST['numero_documento'], $_POST['fecha_nacimiento'],
                $_POST['direccion'], $_POST['ciudad'], $_POST['pais']
            );
        }

        $stmt->execute();
        $stmt->close();
        $mensaje = "Datos adicionales guardados.";
    }

    // ------ DOCUMENTO ------
    elseif (isset($_POST['subir_documento'])) {
        $file = $_FILES['documento_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ["pdf","jpg","jpeg","png"])) {
            $error = "Formato inválido.";
        } else {
            $nombre = "doc_{$usuario_id}_" . time() . ".$ext";
            move_uploaded_file($file['tmp_name'], $uploadDirDocumento.$nombre);
            $ruta = $urlDocumento . $nombre;

            $stmt = $mysqli->prepare("
                UPDATE informacion_personal SET archivo_documento=? WHERE usuario_id=?
            ");
            $stmt->bind_param("si", $ruta, $usuario_id);
            $stmt->execute();
            $stmt->close();

            $mensaje = "Documento subido.";
        }
    }

    // ------ CONTACTOS ------
    elseif (isset($_POST['guardar_contacto'])) {
        $principal = isset($_POST['principal']) ? 1 : 0;
        if ($principal)
            $mysqli->query("UPDATE contactos_emergencia SET principal=0 WHERE estudiante_id=$usuario_id");

        $stmt = $mysqli->prepare("
            INSERT INTO contactos_emergencia (estudiante_id, nombre_contacto, telefono_contacto, parentesco, principal)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssi",
            $usuario_id, $_POST['nombre_contacto'], $_POST['telefono_contacto'],
            $_POST['parentesco'], $principal
        );
        $stmt->execute();
        $stmt->close();
        $mensaje = "Contacto guardado.";
    }

    elseif (isset($_POST['eliminar_contacto'])) {
        $stmt = $mysqli->prepare("DELETE FROM contactos_emergencia WHERE id=? AND estudiante_id=?");
        $stmt->bind_param("ii", $_POST['contacto_id'], $usuario_id);
        $stmt->execute();
        $stmt->close();
        $mensaje = "Contacto eliminado.";
    }
}

// ================= CARGA DE DATOS =================

$stmt = $mysqli->prepare("SELECT * FROM usuarios WHERE id=?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

$avatar_actual = $usuario['foto_perfil'];

$stmt = $mysqli->prepare("SELECT * FROM informacion_personal WHERE usuario_id=?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<style>
.section-title { font-weight: bold; font-size: 15px; margin-bottom: 12px; }
.avatar-option img { width:50px;height:50px;border-radius:50%;object-fit:cover;cursor:pointer;border:2px solid transparent; }
.avatar-option img:hover { border-color:#0d6efd; }
.avatar-preview { width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid #ddd; }
.card-soft { border-radius: 12px; }
</style>

<div class="container my-4">

<a href="/twintalk/student/dashboard.php" class="btn btn-outline-secondary btn-sm mb-3">← Regresar</a>

<?php if ($mensaje): ?><div class="alert alert-success"><?= $mensaje ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<h1 class="h4 fw-bold mb-3">Mi perfil</h1>


<!-- ====================================================== -->
<!-- FILA 1 — DATOS PERSONALES + CONTRASEÑA -->
<!-- ====================================================== -->
<div class="row g-3">

    <!-- DATOS PERSONALES -->
    <div class="col-md-6">
        <div class="card card-soft p-3">
            <div class="section-title">Datos personales</div>

            <form method="post">
                <label>Nombre</label>
                <input name="nombre" class="form-control mb-2" value="<?= $usuario['nombre'] ?>">

                <label>Apellido</label>
                <input name="apellido" class="form-control mb-2" value="<?= $usuario['apellido'] ?>">

                <label>Correo</label>
                <input class="form-control mb-2" value="<?= $usuario['email'] ?>" disabled>

                <label>Teléfono</label>
                <input name="telefono" class="form-control mb-3" value="<?= $usuario['telefono'] ?>">

                <button class="btn btn-tt-primary btn-sm" name="actualizar_perfil">Guardar</button>
            </form>
        </div>
    </div>

    <!-- CONTRASEÑA -->
    <div class="col-md-6">
        <div class="card card-soft p-3">
            <div class="section-title">Cambiar contraseña</div>

            <form method="post">
                <label>Nueva contraseña</label>
                <input type="password" name="password" class="form-control mb-2">

                <label>Repetir contraseña</label>
                <input type="password" name="password2" class="form-control mb-3">

                <button class="btn btn-outline-secondary btn-sm" name="cambiar_password">Actualizar</button>
            </form>
        </div>
    </div>

</div>


<!-- ====================================================== -->
<!-- FILA 2 — AVATAR COMPLETO -->
<!-- ====================================================== -->
<div class="row g-3 mt-2">
    <div class="col-md-12">
        <div class="card card-soft p-3">

            <div class="section-title text-center">Avatar</div>

            <div class="text-center mb-3">
                <img src="<?= $avatar_actual ?>" class="avatar-preview">
            </div>

            <!-- Avatar predeterminado -->
            <form method="post" class="text-center mb-4">
                <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                    <?php foreach ($lista_avatars as $av): ?>
                        <label class="avatar-option">
                            <input type="radio" name="avatar" value="<?= $av ?>" <?= $av == $avatar_actual ? "checked" : "" ?> hidden>
                            <img src="<?= $av ?>">
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-tt-primary btn-sm mt-2" name="seleccionar_avatar">Usar avatar seleccionado</button>
            </form>

            <!-- Subir avatar -->
            <form method="post" enctype="multipart/form-data" class="text-center">
                <input type="file" name="avatar_file" class="form-control mb-2" style="max-width:300px;margin:auto;">
                <button class="btn btn-outline-secondary btn-sm" name="subir_avatar">Subir foto</button>
            </form>

        </div>
    </div>
</div>


<!-- ====================================================== -->
<!-- FILA 3 — DATOS ADICIONALES + DOCUMENTOS -->
<!-- ====================================================== -->
<div class="row g-3 mt-2">

    <!-- Datos adicionales -->
    <div class="col-md-6">
        <div class="card card-soft p-3">
            <div class="section-title">Datos adicionales</div>

            <form method="post">
                <label>Número de documento</label>
                <input name="numero_documento" class="form-control mb-2" value="<?= $info['numero_documento'] ?? '' ?>">

                <label>Fecha nacimiento</label>
                <input type="date" name="fecha_nacimiento" class="form-control mb-2" value="<?= $info['fecha_nacimiento'] ?? '' ?>">

                <label>Ciudad</label>
                <input name="ciudad" class="form-control mb-2" value="<?= $info['ciudad'] ?? '' ?>">

                <label>País</label>
                <input name="pais" class="form-control mb-2" value="<?= $info['pais'] ?? '' ?>">

                <label>Dirección</label>
                <textarea name="direccion" class="form-control mb-2"><?= $info['direccion'] ?? '' ?></textarea>

                <button class="btn btn-tt-primary btn-sm mt-2" name="guardar_info">Guardar</button>
            </form>
        </div>
    </div>

    <!-- Documento -->
    <div class="col-md-6">
        <div class="card card-soft p-3">
            <div class="section-title">Documento personal</div>

            <?php if (!empty($info['archivo_documento'])): ?>
                <p class="small">
                    Actual:
                    <a href="<?= $info['archivo_documento'] ?>" target="_blank">Ver documento</a>
                </p>
            <?php else: ?>
                <p class="text-muted small">No has subido un documento.</p>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="file" name="documento_file" class="form-control mb-2">
                <button class="btn btn-tt-primary btn-sm" name="subir_documento">Subir documento</button>
            </form>
        </div>
    </div>

</div>


<!-- ====================================================== -->
<!-- FILA 4 — CONTACTOS DE EMERGENCIA -->
<!-- ====================================================== -->
<div class="row g-3 mt-2 mb-4">
    <div class="col-md-12">
        <div class="card card-soft p-3">

            <div class="section-title">Contactos de emergencia</div>

            <?php
            $stmt = $mysqli->prepare("SELECT * FROM contactos_emergencia WHERE estudiante_id=?");
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $contactos = $stmt->get_result();
            ?>

            <?php if ($contactos->num_rows > 0): ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Parentesco</th>
                            <th>Principal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($c = $contactos->fetch_assoc()): ?>
                        <tr>
                            <td><?= $c['nombre_contacto'] ?></td>
                            <td><?= $c['telefono_contacto'] ?></td>
                            <td><?= $c['parentesco'] ?></td>
                            <td><?= $c['principal'] ? "<span class='badge bg-success'>Sí</span>" : "No" ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="contacto_id" value="<?= $c['id'] ?>">
                                    <button name="eliminar_contacto" class="btn btn-sm btn-outline-danger">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted small">No hay contactos registrados.</p>
            <?php endif; ?>

            <hr>

            <!-- Nuevo contacto -->
            <form method="post">
                <div class="row g-2">

                    <div class="col-md-4">
                        <label>Nombre</label>
                        <input name="nombre_contacto" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label>Teléfono</label>
                        <input name="telefono_contacto" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label>Parentesco</label>
                        <input name="parentesco" class="form-control">
                    </div>

                    <div class="col-md-1 d-flex align-items-end">
                        <input type="checkbox" name="principal" class="form-check-input">
                    </div>

                </div>

                <button class="btn btn-tt-primary btn-sm mt-3" name="guardar_contacto">
                    Guardar contacto
                </button>
            </form>

        </div>
    </div>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>
