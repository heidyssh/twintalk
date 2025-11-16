<?php
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/auth.php";

if (isset($_SESSION['usuario_id'])) {
    redirect_by_role();
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errores[] = "Ingresa tu correo y contraseña.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Correo electrónico inválido.";
    } else {
        $stmt = $mysqli->prepare("SELECT id, password_hash, rol_id, activo FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            if ((int)$row['activo'] !== 1) {
                $errores[] = "Tu cuenta está inactiva. Contacta a la administración.";
            } elseif (!password_verify($password, $row['password_hash'])) {
                $errores[] = "Correo o contraseña incorrectos.";
            } else {
                // Login correcto
                $_SESSION['usuario_id'] = (int)$row['id'];
                $_SESSION['rol_id']     = (int)$row['rol_id'];

                redirect_by_role();
                exit;
            }
        } else {
            $errores[] = "Correo o contraseña incorrectos.";
        }
    }
}

include __DIR__ . "/includes/header.php";
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-5">
        <div class="card card-soft p-4">
            <h1 class="h4 fw-bold mb-3 text-center">Iniciar sesión</h1>

            <?php if (isset($_GET['registro']) && $_GET['registro'] === 'ok'): ?>
                <div class="alert alert-success rounded-4 small">
                    🎉 ¡Tu cuenta fue creada con éxito! Ahora inicia sesión con tu correo y contraseña.
                </div>
            <?php endif; ?>

            <?php if ($errores): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errores as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <div class="input-group">
                        <input
                            type="password"
                            name="password"
                            id="login_password"
                            class="form-control"
                            minlength="8"
                            required
                        >
                        <button type="button"
                                class="btn btn-outline-secondary"
                                onclick="ttTogglePassword('login_password', this)">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button class="btn btn-tt-primary w-100 mt-2">Ingresar</button>

                <p class="small text-center text-muted mt-2 mb-0">
                    ¿Aún no tienes cuenta?
                    <a href="/twintalk/register.php" class="text-decoration-none">Regístrate aquí</a>.
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
