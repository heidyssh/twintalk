<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>TwinTalk English</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Estilos propios -->
    <link rel="stylesheet" href="/twintalk/assets/css/styles.css">
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">

        <!-- Logo -->
        <a class="navbar-brand d-flex align-items-center" href="/twintalk/index.php">
            <img src="/twintalk/assets/img/logo.png" height="36" class="me-2">
            <span class="fw-bold text-secondary">TwinTalk English</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNavbar" aria-controls="mainNavbar"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

                <?php if (!isset($_SESSION['usuario_id'])): ?>
                    <!-- MENU PÚBLICO (solo si NO está logueado) -->
                    <li class="nav-item"><a class="nav-link" href="/twintalk/index.php#inicio">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="/twintalk/index.php#sobre">Nosotros</a></li>
                    <li class="nav-item"><a class="nav-link" href="/twintalk/index.php#programas">Programas</a></li>
                    <li class="nav-item"><a class="nav-link" href="/twintalk/index.php#contacto">Contacto</a></li>

                    <li class="nav-item ms-lg-3">
                        <a href="/twintalk/login.php" class="btn btn-primary rounded-pill px-3">Iniciar sesión</a>
                    </li>

                <?php else: ?>
                    <!-- MENU CUANDO ESTÁ LOGUEADO -->

                    <li class="nav-item dropdown ms-lg-3">
                        <a class="nav-link dropdown-toggle d-flex align-items-center"
                           href="#" id="userMenu" role="button"
                           data-bs-toggle="dropdown">

                            <!-- Avatar -->
                            <img src="<?= htmlspecialchars($_SESSION['foto_perfil'] ?? '/twintalk/assets/img/default_user.png') ?>"
                                 class="rounded-circle me-2"
                                 style="width:32px;height:32px;object-fit:cover;">

                            <span><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end">

                            <!-- MENU ADMIN -->
<?php if ($_SESSION['rol_id'] == 1): ?>
    <li>
        <a class="dropdown-item" href="/twintalk/admin/dashboard.php">
            <i class="fa-solid fa-gauge-high me-1"></i> Panel administrador
        </a>
    </li>

    <li>
        <a class="dropdown-item" href="/twintalk/admin/perfil.php">
            <i class="fa-solid fa-user me-1"></i> Mi perfil
        </a>
    </li>

    <li>
        <a class="dropdown-item" href="/twintalk/admin/docentes.php">
            <i class="fa-solid fa-chalkboard-user me-1"></i> Docentes
        </a>
    </li>

    <li>
        <a class="dropdown-item" href="/twintalk/admin/mensajes_interes.php">
            <i class="fa-solid fa-inbox me-1"></i> Mensajes de interés
        </a>
    </li>
<li class="nav-item">
    <a class="nav-link" href="/twintalk/zoom/index.php">
        🎥 Clases Online
    </a>
</li>

<?php endif; ?>


                            <!-- MENU DOCENTE -->
                            <?php if ($_SESSION['rol_id'] == 2): ?>
                                <li>
                                    <a class="dropdown-item" href="/twintalk/docente/dashboard.php">
                                        <i class="fa-solid fa-school me-1"></i> Panel docente
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/twintalk/docente/perfil.php">
                                        <i class="fa-solid fa-user me-1"></i> Mi perfil
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/twintalk/docente/mensajes.php">
                                        <i class="fa-solid fa-envelope me-1"></i> Mensajes
                                    </a>
                                </li>
<li class="nav-item">
    <a class="nav-link" href="/twintalk/zoom/index.php">
        🎥 Clases Online
    </a>
</li>

                            <?php endif; ?>

                            <!-- MENU ESTUDIANTE -->
                            <?php if ($_SESSION['rol_id'] == 3): ?>
                                <li>
                                    <a class="dropdown-item" href="/twintalk/student/dashboard.php">
                                        <i class="fa-solid fa-graduation-cap me-1"></i> Panel estudiante
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/twintalk/student/perfil.php">
                                        <i class="fa-solid fa-user me-1"></i> Mi perfil
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/twintalk/student/mensajes.php">
                                        <i class="fa-solid fa-envelope me-1"></i> Mensajes
                                    </a>
                                </li>
  <li class="nav-item">
    <a class="nav-link" href="/twintalk/zoom/index.php">
        🎥 Clases Online
    </a>
</li>

                            <?php endif; ?>

                            <li><hr class="dropdown-divider"></li>

                            <li>
                                <a class="dropdown-item text-danger" href="/twintalk/logout.php">
                                    <i class="fa-solid fa-right-from-bracket me-1"></i> Cerrar sesión
                                </a>
                            </li>

                        </ul>
                    </li>

                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>

<div class="container py-4">
