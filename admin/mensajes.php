<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";

require_role([1]); 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$rol_id     = $_SESSION['rol_id'] ?? 0;

if ($usuario_id <= 0) {
    header("Location: /twintalk/login.php");
    exit;
}

$mensaje = "";
$error   = "";




$view = isset($_GET['view']) ? $_GET['view'] : 'inbox'; 
$view = ($view === 'sent') ? 'sent' : 'inbox';


$reply_id             = isset($_GET['reply_id']) ? (int)$_GET['reply_id'] : 0;
$reply_destinatario   = null;
$reply_asunto         = "";
$reply_contenido_cita = "";


$baseUrl = "/twintalk/admin/mensajes.php";




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    
    if ($accion === 'enviar_mensaje') {
        $destinatario_id = isset($_POST['destinatario_id']) ? (int)$_POST['destinatario_id'] : 0;
        $asunto          = trim($_POST['asunto'] ?? '');
        $contenido       = trim($_POST['contenido'] ?? '');
        $reply_de        = isset($_POST['reply_de']) ? (int)$_POST['reply_de'] : 0;

        if ($destinatario_id <= 0 || $contenido === '') {
            $error = "Debes seleccionar un destinatario y escribir un mensaje.";
        } else {
            
            $archivo_url    = null;
            $tamano_archivo = null;

            if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $tmp_name        = $_FILES['archivo']['tmp_name'];
                $nombre_original = $_FILES['archivo']['name'];
                $tamano_archivo  = (int)$_FILES['archivo']['size'];

                $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
                $ext_permitidas = ['pdf','doc','docx','ppt','pptx','xls','xlsx','jpg','jpeg','png','zip','rar'];

                if (!in_array($ext, $ext_permitidas)) {
                    $error = "Tipo de archivo no permitido.";
                } else {
                    
                    $dir_subida = dirname(__DIR__) . "/uploads/mensajes";
                    if (!is_dir($dir_subida)) {
                        @mkdir($dir_subida, 0777, true);
                    }

                    $nombre_unico = "msg_" . uniqid() . "." . $ext;
                    $ruta_fisica  = $dir_subida . "/" . $nombre_unico;
                    $ruta_bd      = "/twintalk/uploads/mensajes/" . $nombre_unico;

                    if (move_uploaded_file($tmp_name, $ruta_fisica)) {
                        $archivo_url = $ruta_bd;
                    } else {
                        $error = "No se pudo guardar el archivo adjunto.";
                    }
                }
            }

            if ($error === "") {
                
                if ($reply_de > 0 && $asunto !== "" && stripos($asunto, "re:") !== 0) {
                    $asunto = "Re: " . $asunto;
                }

                $stmt = $mysqli->prepare("
                    INSERT INTO mensajes (remitente_id, destinatario_id, asunto, contenido, archivo_url, tamano_archivo)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iisssi",
                    $usuario_id,
                    $destinatario_id,
                    $asunto,
                    $contenido,
                    $archivo_url,
                    $tamano_archivo
                );

                if ($stmt->execute()) {
                    $mensaje = "Mensaje enviado correctamente.";
                } else {
                    $error = "Error al enviar el mensaje: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    
    if ($accion === 'eliminar_mensaje') {
        $mensaje_id = isset($_POST['mensaje_id']) ? (int)$_POST['mensaje_id'] : 0;

        if ($mensaje_id > 0) {
            
            $stmt = $mysqli->prepare("
                SELECT archivo_url
                FROM mensajes
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->bind_param("i", $mensaje_id);
            $stmt->execute();
            $res   = $stmt->get_result();
            $datos = $res->fetch_assoc();
            $stmt->close();

            if ($datos) {
                $archivo_url = $datos['archivo_url'];

                $stmtDel = $mysqli->prepare("DELETE FROM mensajes WHERE id = ?");
                $stmtDel->bind_param("i", $mensaje_id);

                if ($stmtDel->execute()) {
                    $mensaje = "Mensaje eliminado correctamente.";

                    
                    if ($archivo_url && strpos($archivo_url, "/twintalk/uploads/mensajes/") === 0) {
                        $root       = dirname(__DIR__); 
                        $rel_path   = str_replace("/twintalk", "", $archivo_url);
                        $ruta_fisica = $root . $rel_path;
                        if (file_exists($ruta_fisica)) {
                            @unlink($ruta_fisica);
                        }
                    }
                } else {
                    $error = "No se pudo eliminar el mensaje.";
                }
                $stmtDel->close();
            } else {
                $error = "El mensaje no existe.";
            }
        }
    }
}




if ($reply_id > 0) {
    $stmt = $mysqli->prepare("
        SELECT m.id, m.asunto, m.contenido, m.remitente_id,
               u.nombre, u.apellido, u.email
        FROM mensajes m
        INNER JOIN usuarios u ON u.id = m.remitente_id
        WHERE m.id = ? AND m.destinatario_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $reply_id, $usuario_id);
    $stmt->execute();
    $resReply = $stmt->get_result();
    if ($rowRep = $resReply->fetch_assoc()) {
        $reply_destinatario = $rowRep['remitente_id'];
        $reply_asunto = (stripos($rowRep['asunto'], "re:") === 0)
            ? $rowRep['asunto']
            : "Re: " . $rowRep['asunto'];

        $reply_contenido_cita = "\n\n----- Mensaje anterior de " .
            $rowRep['nombre'] . " " . $rowRep['apellido'] . " (" . $rowRep['email'] . ") -----\n" .
            $rowRep['contenido'];
    }
    $stmt->close();
}




$usuarios_opciones = [];
$stmt = $mysqli->prepare("
    SELECT id, nombre, apellido, email
    FROM usuarios
    WHERE id <> ?
    ORDER BY nombre, apellido
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resUsers = $stmt->get_result();
while ($u = $resUsers->fetch_assoc()) {
    $usuarios_opciones[] = $u;
}
$stmt->close();




$stmt = $mysqli->prepare("
    SELECT m.id, m.asunto, m.contenido, m.leido, m.fecha_envio,
           u.nombre AS remitente_nombre,
           u.apellido AS remitente_apellido,
           u.email AS remitente_email,
           m.archivo_url
    FROM mensajes m
    INNER JOIN usuarios u ON u.id = m.remitente_id
    WHERE m.destinatario_id = ?
    ORDER BY m.fecha_envio DESC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resInbox = $stmt->get_result();

$stmt2 = $mysqli->prepare("
    SELECT m.id, m.asunto, m.contenido, m.leido, m.fecha_envio,
           u.nombre AS destinatario_nombre,
           u.apellido AS destinatario_apellido,
           u.email AS destinatario_email,
           m.archivo_url
    FROM mensajes m
    INNER JOIN usuarios u ON u.id = m.destinatario_id
    WHERE m.remitente_id = ?
    ORDER BY m.fecha_envio DESC
");
$stmt2->bind_param("i", $usuario_id);
$stmt2->execute();
$resSent = $stmt2->get_result();

include __DIR__ . "/../includes/header.php";
?>

<div class="container mt-4">

    <!-- Título principal -->
    <h1 class="h4 mb-3 fw-bold" style="color:#A45A6A;">
        <i class="fa-regular fa-envelope me-2"></i>
        Mensajes internos
        <small class="text-muted" style="font-size:0.8rem;"> · Admin</small>
    </h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Tabs bandeja -->
    <ul class="nav nav-pills mb-3" style="gap:6px;">
        <li class="nav-item">
            <a class="nav-link <?= $view === 'inbox' ? 'active' : '' ?>"
               href="<?= $baseUrl ?>?view=inbox"
               style="<?= $view==='inbox'
                    ? 'background:#A45A6A;border-color:#A45A6A;'
                    : 'color:#A45A6A;border:1px solid #A45A6A;background:#fff;' ?>">
                <i class="fa-regular fa-folder-open me-1"></i>
                Bandeja de entrada
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $view === 'sent' ? 'active' : '' ?>"
               href="<?= $baseUrl ?>?view=sent"
               style="<?= $view==='sent'
                    ? 'background:#A45A6A;border-color:#A45A6A;'
                    : 'color:#A45A6A;border:1px solid #A45A6A;background:#fff;' ?>">
                <i class="fa-regular fa-paper-plane me-1"></i>
                Enviados
            </a>
        </li>
    </ul>

    <div class="row">
        <!-- Columna izquierda: nuevo mensaje -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0" style="border-radius:12px;border-left:4px solid #A45A6A;">
                <div class="card-body">
                    <h5 class="card-title mb-1" style="color:#A45A6A;">
                        <i class="fa-regular fa-pen-to-square me-1"></i>
                        <?= $reply_destinatario ? 'Responder mensaje' : 'Nuevo mensaje' ?>
                    </h5>
                    <p class="small text-muted mb-3">
                        Redacta un mensaje interno a docentes, estudiantes o administración.
                    </p>

                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="enviar_mensaje">
                        <input type="hidden" name="reply_de" value="<?= $reply_id ?>">

                        <!-- Destinatario -->
                        <div class="mb-3">
                            <label class="form-label small">Para</label>
                            <select name="destinatario_id" class="form-select form-select-sm"
                                    style="border-color:#E0B6C1;" required>
                                <option value="">-- Selecciona destinatario --</option>
                                <?php foreach ($usuarios_opciones as $u): ?>
                                    <?php
                                    $selected = "";
                                    if ($reply_destinatario && $reply_destinatario == $u['id']) {
                                        $selected = "selected";
                                    }
                                    ?>
                                    <option value="<?= $u['id'] ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($u['nombre'] . " " . $u['apellido'] . " - " . $u['email']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Asunto -->
                        <div class="mb-3">
                            <label class="form-label small">Asunto</label>
                            <input type="text" name="asunto" class="form-control form-control-sm"
                                   style="border-color:#E0B6C1;"
                                   value="<?= htmlspecialchars($reply_asunto) ?>">
                        </div>

                        <!-- Contenido -->
                        <div class="mb-3">
                            <label class="form-label small">Mensaje</label>
                            <textarea name="contenido" rows="5"
                                      class="form-control form-control-sm"
                                      style="border-color:#E0B6C1;" required><?=
                                htmlspecialchars($reply_contenido_cita)
                            ?></textarea>
                        </div>

                        <!-- Archivo -->
                        <div class="mb-3">
                            <label class="form-label small">Adjuntar archivo (opcional)</label>
                            <input type="file" name="archivo" class="form-control form-control-sm"
                                   style="border-color:#E0B6C1;">
                            <div class="form-text">
                                Extensiones típicas: pdf, doc, docx, ppt, jpg, png, zip, rar.
                            </div>
                        </div>

                        <button type="submit"
                                class="btn w-100"
                                style="background:#A45A6A;color:#fff;border-radius:999px;">
                            <i class="fa-regular fa-paper-plane me-1"></i>
                            Enviar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Columna derecha: bandeja -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0" style="border-radius:12px;">
                <div class="card-body">

                    <?php if ($view === 'inbox'): ?>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="card-title mb-0" style="color:#A45A6A;">
                                    <i class="fa-regular fa-folder-open me-1"></i>
                                    Bandeja de entrada
                                </h5>
                                <small class="text-muted">Mensajes recibidos.</small>
                            </div>
                        </div>

                        <?php if ($resInbox->num_rows === 0): ?>
                            <p class="text-muted mb-0">No tienes mensajes recibidos.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead style="background:#FBE9F0;color:#A45A6A;">
                                        <tr>
                                            <th>De</th>
                                            <th>Asunto</th>
                                            <th>Adjunto</th>
                                            <th>Fecha</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($m = $resInbox->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($m['remitente_nombre'] . " " . $m['remitente_apellido']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($m['remitente_email']) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?= $m['asunto'] ? htmlspecialchars($m['asunto']) : '(Sin asunto)' ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?= nl2br(htmlspecialchars(mb_strimwidth($m['contenido'], 0, 80, '...'))) ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($m['archivo_url'])): ?>
                                                    <a href="<?= htmlspecialchars($m['archivo_url']) ?>" target="_blank"
                                                       class="btn btn-sm"
                                                       style="border:1px solid #A45A6A;color:#A45A6A;">
                                                        <i class="fa-solid fa-paperclip"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($m['fecha_envio']) ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?= $baseUrl ?>?view=inbox&reply_id=<?= $m['id'] ?>"
                                                   class="btn btn-sm me-1"
                                                   style="border:1px solid #A45A6A;color:#A45A6A;">
                                                    <i class="fa-solid fa-reply"></i>
                                                </a>
                                                <form method="post" class="d-inline"
                                                      onsubmit="return confirm('¿Eliminar este mensaje?');">
                                                    <input type="hidden" name="accion" value="eliminar_mensaje">
                                                    <input type="hidden" name="mensaje_id" value="<?= $m['id'] ?>">
                                                    <button type="submit" class="btn btn-sm"
                                                            style="border:1px solid #d9534f;color:#d9534f;">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="card-title mb-0" style="color:#A45A6A;">
                                    <i class="fa-regular fa-paper-plane me-1"></i>
                                    Mensajes enviados
                                </h5>
                                <small class="text-muted">Historial de mensajes enviados.</small>
                            </div>
                        </div>

                        <?php if ($resSent->num_rows === 0): ?>
                            <p class="text-muted mb-0">No has enviado mensajes.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead style="background:#FBE9F0;color:#A45A6A;">
                                        <tr>
                                            <th>Para</th>
                                            <th>Asunto</th>
                                            <th>Adjunto</th>
                                            <th>Fecha</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($m = $resSent->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($m['destinatario_nombre'] . " " . $m['destinatario_apellido']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($m['destinatario_email']) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?= $m['asunto'] ? htmlspecialchars($m['asunto']) : '(Sin asunto)' ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?= nl2br(htmlspecialchars(mb_strimwidth($m['contenido'], 0, 80, '...'))) ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($m['archivo_url'])): ?>
                                                    <a href="<?= htmlspecialchars($m['archivo_url']) ?>" target="_blank"
                                                       class="btn btn-sm"
                                                       style="border:1px solid #A45A6A;color:#A45A6A;">
                                                        <i class="fa-solid fa-paperclip"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($m['fecha_envio']) ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <form method="post" class="d-inline"
                                                      onsubmit="return confirm('¿Eliminar este mensaje?');">
                                                    <input type="hidden" name="accion" value="eliminar_mensaje">
                                                    <input type="hidden" name="mensaje_id" value="<?= $m['id'] ?>">
                                                    <button type="submit" class="btn btn-sm"
                                                            style="border:1px solid #d9534f;color:#d9534f;">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

</div>


<?php
include __DIR__ . "/../includes/footer.php";
