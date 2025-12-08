<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/auth.php";

if (isset($_SESSION['usuario_id'])) {
    redirect_by_role();
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($nombre === '' || $apellido === '' || $email === '' || $password === '' || $telefono === '') {
        $errores[] = "Todos los campos marcados son obligatorios.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Correo electrónico inválido.";
    }

    
    if (strlen($password) < 12) {
        $errores[] = "La contraseña debe tener al menos 12 caracteres.";
    }
    if (
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/\d/', $password) ||
        !preg_match('/[\W_]/', $password)
    ) {
        $errores[] = "La contraseña debe incluir mayúsculas, minúsculas, números y caracteres especiales.";
    }
    if ($password !== $password2) {
        $errores[] = "Las contraseñas no coinciden.";
    }

    if (empty($errores)) {
        
        $check = $mysqli->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errores[] = "Ya existe una cuenta con ese correo.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $rol_estudiante = 3; 

            $stmt = $mysqli->prepare("
                INSERT INTO usuarios (
                    email, password_hash, rol_id, nombre, apellido, telefono, fecha_registro, activo)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
            ");
            $stmt->bind_param("ssisss", $email, $hash, $rol_estudiante, $nombre, $apellido, $telefono);

            if ($stmt->execute()) {
                $usuario_id = $stmt->insert_id;

                
                $nivel_actual = "A1";
                $ins_est = $mysqli->prepare("
                    INSERT INTO estudiantes (id, nivel_actual, fecha_inscripcion)
                    VALUES (?, ?, CURDATE())
                ");
                $ins_est->bind_param("is", $usuario_id, $nivel_actual);
                $ins_est->execute();

                header("Location: /twintalk/login.php?registered=1");
                exit;
            } else {
                $errores[] = "Error al crear la cuenta. Intenta de nuevo.";
            }
        }
    }
}

include __DIR__ . "/includes/header.php";
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-7 col-lg-6">
        <div class="card card-soft p-4">
            <h1 class="h4 fw-bold mb-3 text-center">Registro de estudiante</h1>

            <?php if ($errores): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errores as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required
                               value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Apellido *</label>
                        <input type="text" name="apellido" class="form-control" required
                               value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo electrónico *</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Teléfono *</label>
                    <input type="text" name="telefono" class="form-control" required
                           value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contraseña *</label>
                        <div class="position-relative">
                            <input
                                type="password"
                                name="password"
                                id="reg_password"
                                class="form-control pe-5"
                                minlength="12"
                                required
                                pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}"
                                title="Mínimo 12 caracteres e incluir mayúsculas, minúsculas, números y caracteres especiales"
                                placeholder="Mínimo 12 caracteres, con mayúsculas, minúsculas, números y símbolos"
                            >
                            <button type="button"
                                    class="btn btn-link p-0 border-0 position-absolute top-50 end-0 translate-middle-y me-3"
                                    title="Mostrar/ocultar contraseña"
                                    onclick="ttTogglePassword('reg_password', this)">
                                <i class="fa-solid fa-eye small text-muted"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">
                            La contraseña debe tener al menos 12 caracteres e incluir mayúsculas, minúsculas, números y caracteres especiales.
                        </small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Confirmar contraseña *</label>
                        <div class="position-relative">
                            <input
                                type="password"
                                name="password2"
                                id="reg_password2"
                                class="form-control pe-5"
                                minlength="12"
                                required
                                placeholder="Repite tu contraseña"
                            >
                            <button type="button"
                                    class="btn btn-link p-0 border-0 position-absolute top-50 end-0 translate-middle-y me-3"
                                    title="Mostrar/ocultar contraseña"
                                    onclick="ttTogglePassword('reg_password2', this)">
                                <i class="fa-solid fa-eye small text-muted"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button class="btn btn-tt-primary w-100 mt-2">Crear cuenta</button>
                <p class="small text-center text-muted mt-2 mb-0">
                    Al registrarte, se creará tu perfil de <strong>estudiante</strong>.
                </p>
            </form>
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

<?php include __DIR__ . "/includes/footer.php"; ?>
