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
        // 2.b) Guardar datos adicionales / información personal
    elseif (isset($_POST['guardar_info_personal'])) {

        $tipo_documento_id = (int)($_POST['tipo_documento_id'] ?? 0);
        $numero_documento  = trim($_POST['numero_documento'] ?? '');
        $fecha_nacimiento  = trim($_POST['fecha_nacimiento'] ?? '');
        $direccion         = trim($_POST['direccion'] ?? '');
        $ciudad            = trim($_POST['ciudad'] ?? '');
        $pais              = trim($_POST['pais'] ?? '');

        if ($tipo_documento_id <= 0) {
            $error = "Selecciona un tipo de documento.";
        } elseif ($numero_documento === '' || $fecha_nacimiento === '' || $direccion === '' || $ciudad === '' || $pais === '') {
            $error = "Completa todos los datos adicionales.";
        } else {

            // Convertir dd/mm/aaaa a aaaa-mm-dd si viene así
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha_nacimiento, $m)) {
                $fecha_nac_sql = "{$m[3]}-{$m[2]}-{$m[1]}";
            } else {
                $fecha_nac_sql = $fecha_nacimiento; // por si ya viene en formato SQL
            }

            // Ver si ya hay registro en informacion_personal
            $stmt = $mysqli->prepare("SELECT id FROM informacion_personal WHERE usuario_id = ? LIMIT 1");
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                // Actualizar
                $info_id = (int)$row['id'];
                $stmt = $mysqli->prepare("
                    UPDATE informacion_personal
                    SET tipo_documento_id = ?, numero_documento = ?, fecha_nacimiento = ?,
                        direccion = ?, ciudad = ?, pais = ?
                    WHERE id = ? AND usuario_id = ?
                ");
                $stmt->bind_param(
                    "isssssii",
                    $tipo_documento_id,
                    $numero_documento,
                    $fecha_nac_sql,
                    $direccion,
                    $ciudad,
                    $pais,
                    $info_id,
                    $usuario_id
                );
            } else {
                // Insertar nuevo
                $stmt = $mysqli->prepare("
                    INSERT INTO informacion_personal
                        (usuario_id, tipo_documento_id, numero_documento, fecha_nacimiento, direccion, ciudad, pais)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iisssss",
                    $usuario_id,
                    $tipo_documento_id,
                    $numero_documento,
                    $fecha_nac_sql,
                    $direccion,
                    $ciudad,
                    $pais
                );
            }

            if ($stmt->execute()) {
                $mensaje = "Datos adicionales guardados correctamente.";
            } else {
                $error = "Error al guardar los datos adicionales.";
            }
            $stmt->close();
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
        // 5) Gestionar contactos de emergencia
    elseif (isset($_POST['guardar_contacto'])) {
        $contacto_id        = (int)($_POST['contacto_id'] ?? 0);
        $nombre_contacto    = trim($_POST['nombre_contacto'] ?? '');
        $telefono_contacto  = trim($_POST['telefono_contacto'] ?? '');
        $parentesco         = trim($_POST['parentesco'] ?? '');
        $principal          = isset($_POST['principal']) ? 1 : 0;

        if ($nombre_contacto === '' || $telefono_contacto === '') {
            $error = "Nombre y teléfono del contacto son obligatorios.";
        } else {
            // Si se marca como principal, quitar principal de los demás
            if ($principal === 1) {
                $stmt = $mysqli->prepare("UPDATE contactos_emergencia SET principal = 0 WHERE estudiante_id = ?");
                $stmt->bind_param("i", $usuario_id);
                $stmt->execute();
                $stmt->close();
            }

            if ($contacto_id > 0) {
                // Actualizar
                $stmt = $mysqli->prepare("
                    UPDATE contactos_emergencia
                    SET nombre_contacto = ?, telefono_contacto = ?, parentesco = ?, principal = ?
                    WHERE id = ? AND estudiante_id = ?
                ");
                $stmt->bind_param("sssiii", $nombre_contacto, $telefono_contacto, $parentesco, $principal, $contacto_id, $usuario_id);
            } else {
                // Insertar nuevo
                $stmt = $mysqli->prepare("
                    INSERT INTO contactos_emergencia (estudiante_id, nombre_contacto, telefono_contacto, parentesco, principal)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isssi", $usuario_id, $nombre_contacto, $telefono_contacto, $parentesco, $principal);
            }

            if ($stmt->execute()) {
                $mensaje = "Contacto de emergencia guardado correctamente.";
            } else {
                $error = "Error al guardar el contacto de emergencia.";
            }
            $stmt->close();
        }
    }
    elseif (isset($_POST['eliminar_contacto'])) {
        $contacto_id = (int)($_POST['contacto_id'] ?? 0);
        if ($contacto_id > 0) {
            $stmt = $mysqli->prepare("DELETE FROM contactos_emergencia WHERE id = ? AND estudiante_id = ?");
            $stmt->bind_param("ii", $contacto_id, $usuario_id);
            if ($stmt->execute()) {
                $mensaje = "Contacto eliminado.";
            } else {
                $error = "No se pudo eliminar el contacto.";
            }
            $stmt->close();
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
// Cargar información personal (documentos, dirección, etc.)
$stmt = $mysqli->prepare("
    SELECT ip.*, td.tipo_documento
    FROM informacion_personal ip
    LEFT JOIN tipos_documento td ON td.id = ip.tipo_documento_id
    WHERE ip.usuario_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$info_personal = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Lista de tipos de documento para el combo
$tipos_documento = [];
$resTipos = $mysqli->query("SELECT id, tipo_documento FROM tipos_documento ORDER BY tipo_documento");
if ($resTipos) {
    while ($row = $resTipos->fetch_assoc()) {
        $tipos_documento[] = $row;
    }
}

// Cargar contactos de emergencia del estudiante
$stmt = $mysqli->prepare("
    SELECT id, nombre_contacto, telefono_contacto, parentesco, principal
    FROM contactos_emergencia
    WHERE estudiante_id = ?
    ORDER BY principal DESC, id ASC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$contactos_result = $stmt->get_result();
$contactos = [];
while ($row = $contactos_result->fetch_assoc()) {
    $contactos[] = $row;
}
$stmt->close();


include __DIR__ . "/../includes/header.php";
?>
<div class="container my-4">
    <?php if (!empty($_GET['completar'])): ?>
        <div class="alert alert-warning">
            <strong>Antes de continuar:</strong> por favor completa tus datos personales y de documento.
            Estos datos serán visibles para tus docentes y el administrador al momento de matricularte.
        </div>
    <?php endif; ?>
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
                    <div class="position-relative">
                        <input type="password"
                               name="password"
                               class="form-control pe-5"
                               id="perfil_password">
                        <button type="button"
                                class="btn btn-link p-0 border-0 position-absolute top-50 end-0 translate-middle-y me-3"
                                title="Mostrar/ocultar contraseña"
                                onclick="ttTogglePassword('perfil_password', this)">
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
                               id="perfil_password2">
                        <button type="button"
                                class="btn btn-link p-0 border-0 position-absolute top-50 end-0 translate-middle-y me-3"
                                title="Mostrar/ocultar contraseña"
                                onclick="ttTogglePassword('perfil_password2', this)">
                            <i class="fa-solid fa-eye small text-muted"></i>
                        </button>
                    </div>
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
</div> <!-- cierra row g-3 existente -->
<!-- Datos adicionales / documentos -->
<div class="row g-3 mt-3">
    <div class="col-md-8">
        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-3">Datos adicionales</h2>
            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipo de documento</label>
                        <select name="tipo_documento_id" class="form-select" required>
                            <option value="">Selecciona...</option>
                            <?php
                            $tipo_actual_id = $info_personal['tipo_documento_id'] ?? 0;
                            foreach ($tipos_documento as $td): ?>
                                <option value="<?= (int)$td['id'] ?>"
                                    <?= ($tipo_actual_id == $td['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($td['tipo_documento']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Número de documento</label>
                        <input type="text"
                               name="numero_documento"
                               class="form-control"
                               required
                               value="<?= htmlspecialchars($info_personal['numero_documento'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha de nacimiento</label>
                        <input type="date"
                               name="fecha_nacimiento"
                               class="form-control"
                               required
                               value="<?= htmlspecialchars($info_personal['fecha_nacimiento'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ciudad</label>
                        <input type="text"
                               name="ciudad"
                               class="form-control"
                               required
                               value="<?= htmlspecialchars($info_personal['ciudad'] ?? '') ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Dirección</label>
                        <textarea name="direccion"
                                  class="form-control"
                                  rows="2"
                                  required><?= htmlspecialchars($info_personal['direccion'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">País</label>
                        <input type="text"
                               name="pais"
                               class="form-control"
                               required
                               value="<?= htmlspecialchars($info_personal['pais'] ?? '') ?>">
                    </div>
                </div>
                <button class="btn btn-tt-primary btn-sm" name="guardar_info_personal">
                    Guardar datos adicionales
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Contactos de emergencia -->
<div class="row g-3 mt-3">
    <div class="col-md-8">
        <div class="card card-soft p-3">
            <h2 class="h6 fw-bold mb-3">Contactos de emergencia</h2>

            <?php if (!empty($contactos)): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
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
                        <?php foreach ($contactos as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['nombre_contacto']) ?></td>
                                <td><?= htmlspecialchars($c['telefono_contacto']) ?></td>
                                <td><?= htmlspecialchars($c['parentesco'] ?: '-') ?></td>
                                <td>
                                    <?php if ($c['principal']): ?>
                                        <span class="badge bg-success">Principal</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted">Secundario</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <!-- Botón rápido para cargar en el formulario -->
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            onclick="ttEditarContacto(
                                                <?= (int)$c['id'] ?>,
                                                '<?= htmlspecialchars($c['nombre_contacto'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($c['telefono_contacto'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($c['parentesco'] ?? '', ENT_QUOTES) ?>',
                                                <?= (int)$c['principal'] ?>
                                            )">
                                        Editar
                                    </button>

                                    <form method="post" class="d-inline"
                                          onsubmit="return confirm('¿Eliminar este contacto?');">
                                        <input type="hidden" name="contacto_id" value="<?= (int)$c['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" name="eliminar_contacto">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted small mb-2">
                    Aún no tienes contactos de emergencia registrados.
                </p>
            <?php endif; ?>

            <hr>

            <!-- Formulario para agregar/editar -->
            <form method="post">
                <input type="hidden" name="contacto_id" id="contacto_id" value="">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Nombre del contacto</label>
                        <input type="text" name="nombre_contacto" id="nombre_contacto" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono_contacto" id="telefono_contacto" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Parentesco</label>
                        <input type="text" name="parentesco" id="parentesco" class="form-control">
                    </div>
                    <div class="col-md-6 mb-2 d-flex align-items-center">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="principal" name="principal">
                            <label class="form-check-label" for="principal">
                                Marcar como contacto principal
                            </label>
                        </div>
                    </div>
                </div>
                <button class="btn btn-tt-primary btn-sm" name="guardar_contacto">
                    Guardar contacto
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="ttLimpiarContacto()">
                    Limpiar formulario
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function ttEditarContacto(id, nombre, telefono, parentesco, principal) {
    document.getElementById('contacto_id').value = id;
    document.getElementById('nombre_contacto').value = nombre;
    document.getElementById('telefono_contacto').value = telefono;
    document.getElementById('parentesco').value = parentesco;
    document.getElementById('principal').checked = principal === 1;
}
function ttLimpiarContacto() {
    document.getElementById('contacto_id').value = '';
    document.getElementById('nombre_contacto').value = '';
    document.getElementById('telefono_contacto').value = '';
    document.getElementById('parentesco').value = '';
    document.getElementById('principal').checked = false;
}

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
