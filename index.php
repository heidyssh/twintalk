<?php
// Redirigir a dashboards si ya está logueado (a menos que venga en modo público)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['usuario_id']) && !isset($_GET['public'])) {
    if ($_SESSION['rol_id'] == 1) {
        header("Location: /twintalk/admin/dashboard.php");
        exit;
    } elseif ($_SESSION['rol_id'] == 2) {
        header("Location: /twintalk/docente/dashboard.php");
        exit;
    } else {
        header("Location: /twintalk/student/dashboard.php");
        exit;
    }
}

require_once __DIR__ . "/config/db.php";

// -------------------------
// Manejo del formulario de contacto
// -------------------------
$contacto_ok    = "";
$contacto_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_contacto'])) {
    $nombre   = trim($_POST['nombre']   ?? "");
    $email    = trim($_POST['email']    ?? "");
    $telefono = trim($_POST['telefono'] ?? "");
    $programa = trim($_POST['programa'] ?? "");
    $mensaje  = trim($_POST['mensaje']  ?? "");

    if ($nombre === "" || $email === "" || $mensaje === "") {
        $contacto_error = "Por favor completa al menos tu nombre, correo y mensaje.";
    } else {
        $stmt = $mysqli->prepare("
            INSERT INTO mensajes_interes (nombre, email, telefono, programa, mensaje)
            VALUES (?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param("sssss", $nombre, $email, $telefono, $programa, $mensaje);
            if ($stmt->execute()) {
                $contacto_ok = "¡Gracias por escribirnos! Tu mensaje ha sido enviado y la administración lo revisará pronto.";
            } else {
                $contacto_error = "Ocurrió un error al guardar tu mensaje. Intenta de nuevo más tarde.";
            }
            $stmt->close();
        } else {
            $contacto_error = "No se pudo preparar el registro del mensaje.";
        }
    }
}

// Cursos activos que se mostrarán como programas
$cursos = $mysqli->query("
    SELECT c.id, c.nombre_curso, c.descripcion,
           c.duracion_horas, c.capacidad_maxima,
           n.codigo_nivel, n.nombre_nivel
    FROM cursos c
    JOIN niveles_academicos n ON c.nivel_id = n.id
    WHERE c.activo = 1
    ORDER BY c.fecha_creacion DESC
    LIMIT 6
");

include __DIR__ . "/includes/header.php";
?>

<!-- HERO -->
<section id="inicio" class="section-hero">
    <div class="row align-items-center gy-4 hero-card p-4 p-lg-5">
        <div class="col-lg-6">
            <span class="hero-pill mb-2">
                🌎 Academia de inglés · La Ceiba, Atlántida
            </span>
            <h1 class="hero-title display-5 mb-3">
                TwinTalk English<br>
                <span class="text-gradient">¡vive el inglés, no solo lo traduzcas!</span>
            </h1>
            <p class="lead text-muted">
                Aprende inglés desde nivel <strong>A1</strong> hasta <strong>B1/B2</strong>
                con clases dinámicas, docentes apasionados y un ambiente que te motiva a hablar
                desde el primer día. 💬✨
            </p>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <a href="#cursos" class="btn btn-tt-primary">
                    Ver cursos y niveles
                </a>
                <a href="/twintalk/register.php" class="btn btn-outline-secondary rounded-pill">
                    Crear mi cuenta
                </a>
                <a href="/twintalk/login.php" class="btn btn-link p-0 ms-2">
                    Ya tengo cuenta
                </a>
            </div>

            <div class="mt-4">
                <div class="row g-2 small text-muted">
                    <div class="col-sm-6">
                        <div class="border rounded-4 p-2 bg-white h-100 d-flex">
                            <div class="me-2 fs-4">👧👦</div>
                            <div>
                                <div class="fw-semibold">Kids & Teens</div>
                                <div>Programas pensados para niños y jóvenes que quieren despegar en inglés.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="border rounded-4 p-2 bg-white h-100 d-flex">
                            <div class="me-2 fs-4">🎓💼</div>
                            <div>
                                <div class="fw-semibold">Universitarios y adultos</div>
                                <div>Refuerza tu inglés para la U, el trabajo o proyectos personales.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LADO DERECHO: TARJETA VISUAL -->
        <div class="col-lg-6 text-center">
            <div class="card card-soft p-3 p-md-4 d-inline-block position-relative overflow-hidden">
                <div class="position-absolute top-0 start-0 m-2 small badge-level">
                    💻 Plataforma académica
                </div>
                <img src="/twintalk/assets/img/logo.png"
                     alt="TwinTalk English"
                     class="img-fluid mb-3"
                     style="max-height:200px;">
                <p class="small text-muted mb-1">
                    <i class="fa-solid fa-location-dot me-1 text-primary"></i>
                    La Ceiba, Atlántida · Honduras
                </p>
                <p class="small mb-3">
                    <i class="fa-solid fa-envelope me-1 text-primary"></i>
                    <a href="mailto:twintalk39@gmail.com" class="text-decoration-none">
                        twintalk39@gmail.com
                    </a>
                </p>
                <p class="small mb-2">
                    📚 Desde A1 hasta B1/B2 · Grupos pequeños · Acompañamiento constante
                </p>
                <div class="d-flex justify-content-center gap-2 small text-muted">
                    <span>📅 Horarios flexibles</span>
                    <span>·</span>
                    <span>📲 Seguimiento en línea</span>
                </div>
            </div>
        </div>
    </div>
</section>

<hr class="section-divider">

<!-- SOBRE NOSOTROS -->
<section id="sobre" class="section-padding">
    <div class="row g-4 align-items-stretch">
        <div class="col-lg-6">
            <h2 class="section-title">Sobre TwinTalk English 💙</h2>
            <p class="text-muted">
                TwinTalk English es una academia de inglés ubicada en La Ceiba, Atlántida. Nuestro enfoque
                está en la comunicación real: que nuestros estudiantes se sientan capaces de hablar, escuchar,
                leer y escribir en inglés con seguridad y sin miedo a equivocarse.
            </p>
            <p class="text-muted mb-0">
                Aquí no solo llenas cuadernos: practicas, te expresas, preguntas y conectas el inglés con tu vida
                diaria, tus estudios y tus metas profesionales.
            </p>
        </div>
        <div class="col-lg-6">
            <div class="card card-soft p-3 h-100">
                <h3 class="h6 fw-bold mb-2">¿Qué hace diferente a TwinTalk? ✨</h3>
                <ul class="small text-muted mb-0">
                    <li>Clases dinámicas donde hablas inglés desde la primera sesión.</li>
                    <li>Grupos reducidos para que <strong>sí tengas participación</strong>.</li>
                    <li>Programas alineados a niveles A1, A2, B1 y más.</li>
                    <li>Plataforma en línea para ver horarios, materiales, anuncios y calificaciones.</li>
                    <li>Acompañamiento cercano de docentes que se preocupan por tu avance.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<hr class="section-divider">

<!-- PROGRAMAS / CURSOS -->
<section id="cursos" class="section-padding">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">Cursos disponibles 📚</h2>
        <span class="small text-muted">La oferta puede variar según el período académico.</span>
    </div>

    <div class="row g-3">
        <?php if ($cursos && $cursos->num_rows > 0): ?>
            <?php while ($curso = $cursos->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-soft h-100 p-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="badge-level">
                                Nivel <?= htmlspecialchars($curso['codigo_nivel']) ?>
                            </span>
                            <span class="small text-muted">
                                ⏱ <?= (int)$curso['duracion_horas'] ?> h
                            </span>
                        </div>
                        <h5 class="fw-bold mb-2"><?= htmlspecialchars($curso['nombre_curso']) ?></h5>
                        <p class="small text-muted mb-3">
                            <?= nl2br(htmlspecialchars(substr($curso['descripcion'], 0, 140))) ?><?= strlen($curso['descripcion']) > 140 ? '…' : '' ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">
                                <?= htmlspecialchars($curso['nombre_nivel']) ?>
                            </span>
                            <a href="/twintalk/login.php?curso=<?= (int)$curso['id'] ?>"
                               class="btn btn-sm btn-tt-primary">
                                Quiero este curso 🚀
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card card-soft p-3">
                    <p class="small text-muted mb-1">
                        Aún no hay cursos activos registrados en el sistema.
                    </p>
                    <p class="small text-muted mb-0">
                        Cuando se creen cursos desde el panel de administración, aparecerán aquí para los visitantes. 😊
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-4">
        <a href="/twintalk/register.php" class="btn btn-tt-primary">
            Crear mi cuenta y matricularme ✍️
        </a>
        <p class="small text-muted mt-2 mb-0">
            Con tu usuario podrás ver tus horarios, materiales, anuncios y calificaciones dentro de la plataforma.
        </p>
    </div>
</section>

<hr class="section-divider">

<!-- CÓMO FUNCIONA -->
<section id="como-funciona" class="section-padding">
    <h2 class="section-title mb-3">¿Cómo empiezo a estudiar en TwinTalk? 🚀</h2>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card card-soft p-3 h-100">
                <div class="d-flex align-items-center mb-2">
                    <span class="step-badge me-2">1</span>
                    <h3 class="h6 fw-bold mb-0">Crea tu cuenta</h3>
                </div>
                <p class="small text-muted mb-0">
                    Regístrate en la plataforma con tus datos básicos y crea tu usuario para empezar tu camino en inglés. 📝
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-soft p-3 h-100">
                <div class="d-flex align-items-center mb-2">
                    <span class="step-badge me-2">2</span>
                    <h3 class="h6 fw-bold mb-0">Matrícula y horario</h3>
                </div>
                <p class="small text-muted mb-0">
                    Te asignamos a un curso y horario según tu nivel y disponibilidad. Todo queda registrado en tu perfil. 📅
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-soft p-3 h-100">
                <div class="d-flex align-items-center mb-2">
                    <span class="step-badge me-2">3</span>
                    <h3 class="h6 fw-bold mb-0">Clases y progreso</h3>
                </div>
                <p class="small text-muted mb-0">
                    Asiste a clases, descarga materiales, revisa anuncios y mira tus calificaciones desde cualquier dispositivo. 📲
                </p>
            </div>
        </div>
    </div>
</section>

<hr class="section-divider">

<!-- MISIÓN / VISIÓN -->
<section id="misionvision" class="section-padding">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">Misión y visión 🎯</h2>
        <span class="small text-muted">El corazón de TwinTalk English.</span>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card card-soft p-3 h-100">
                <h3 class="h5 fw-bold text-secondary mb-2">Nuestra misión</h3>
                <p class="small text-muted mb-0">
                    Formar estudiantes seguros y competentes en el idioma inglés, desarrollando habilidades
                    comunicativas a través de clases creativas, prácticas y cercanas a su realidad en La Ceiba
                    y la región. Queremos que el inglés sea una herramienta real para sus estudios, trabajo
                    y sueños. 🌟
                </p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-soft p-3 h-100">
                <h3 class="h5 fw-bold text-secondary mb-2">Nuestra visión</h3>
                <p class="small text-muted mb-0">
                    Ser la academia de inglés de referencia en La Ceiba, Atlántida, reconocida por su calidad,
                    acompañamiento humano y resultados claros en el aprendizaje de nuestros estudiantes;
                    una comunidad donde aprender inglés se sienta motivador, cercano y alcanzable para todos. 👑
                </p>
            </div>
        </div>
    </div>
</section>

<hr class="section-divider">

<!-- SOBRE LA FUNDADORA -->
<section id="fundadora" class="section-padding">
    <div class="row g-4 align-items-center">
        <div class="col-lg-5">
            <div class="card card-soft p-3 text-center h-100">
                <img src="/twintalk/assets/img/dueña.jpg"
                     alt="Kiara Saunders, fundadora de TwinTalk English"
                     class="img-fluid rounded-4 mb-3"
                     style="max-height:320px; object-fit:cover;">
                <p class="small text-muted mb-0">
                    Kiara Saunders · Fundadora y directora de TwinTalk English
                </p>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card card-soft p-3 h-100">
                <h2 class="section-title h4 mb-2">La historia detrás de TwinTalk English 💡</h2>
                <p class="small text-muted mb-2">
                    TwinTalk English nació como un sueño de emprendimiento de <strong>Kiara Saunders</strong>, quien
                    comenzó dando clases personalizadas de inglés a estudiantes que querían mejorar sus oportunidades
                    académicas y laborales en La Ceiba.
                </p>
                <p class="small text-muted mb-2">
                    Al ver que muchos alumnos tenían miedo de hablar, pero muchísimo potencial, decidió crear una
                    academia pequeña, cercana y humana, donde cada estudiante fuera escuchado, acompañado y motivado
                    a <strong>perder el miedo al inglés</strong> paso a paso.
                </p>
                <p class="small text-muted mb-0">
                    Hoy, TwinTalk English es un espacio donde niños, jóvenes y adultos pueden aprender en grupos
                    reducidos, con metodologías prácticas y un ambiente de confianza. La visión de Kiara es que
                    más personas de la región puedan acceder a becas, mejores trabajos y experiencias internacionales
                    gracias al inglés. ✈️📚
                </p>
            </div>
        </div>
    </div>
</section>

<hr class="section-divider">

<!-- CONTACTO (con mensajes guardados en la BD) -->
<section id="contacto" class="section-padding bg-light">
    <div class="row g-4 align-items-stretch">

        <!-- Columna: Información de contacto -->
        <div class="col-lg-5">
            <div class="card card-soft h-100 p-3 p-md-4">
                <h2 class="section-title mb-3">Contáctanos 📩</h2>

                <?php if ($contacto_ok): ?>
                    <div class="alert alert-success small">
                        <?= htmlspecialchars($contacto_ok) ?>
                    </div>
                <?php elseif ($contacto_error): ?>
                    <div class="alert alert-danger small">
                        <?= htmlspecialchars($contacto_error) ?>
                    </div>
                <?php endif; ?>

                <p class="text-muted small">
                    Si necesitas más información sobre horarios, precios o niveles,
                    puedes escribirnos o visitarnos. ¡Con gusto te orientamos! 🙂
                </p>

                <div class="d-flex mb-3">
                    <div class="me-3 mt-1">
                        <span class="btn btn-sm btn-outline-primary rounded-circle">
                            <i class="fa-solid fa-location-dot"></i>
                        </span>
                    </div>
                    <div>
                        <div class="fw-semibold small">Ubicación</div>
                        <div class="text-muted small">
                            La Ceiba, Atlántida, Honduras
                        </div>
                    </div>
                </div>

                <div class="d-flex mb-3">
                    <div class="me-3 mt-1">
                        <span class="btn btn-sm btn-outline-success rounded-circle">
                            <i class="fa-brands fa-whatsapp"></i>
                        </span>
                    </div>
                    <div>
                        <div class="fw-semibold small">WhatsApp</div>
                        <div class="text-muted small">
                            +504 0000-0000
                            <!-- Cambia al número real -->
                        </div>
                    </div>
                </div>

                <div class="d-flex mb-3">
                    <div class="me-3 mt-1">
                        <span class="btn btn-sm btn-outline-danger rounded-circle">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                    </div>
                    <div>
                        <div class="fw-semibold small">Correo electrónico</div>
                        <div class="text-muted small">
                            <a href="mailto:twintalk39@gmail.com" class="text-decoration-none">
                                twintalk39@gmail.com
                            </a>
                        </div>
                    </div>
                </div>

                <div class="d-flex mb-3">
                    <div class="me-3 mt-1">
                        <span class="btn btn-sm btn-outline-secondary rounded-circle">
                            <i class="fa-solid fa-clock"></i>
                        </span>
                    </div>
                    <div>
                        <div class="fw-semibold small">Horario de atención</div>
                        <div class="text-muted small">
                            Lunes a viernes · 8:00 a.m. – 6:00 p.m.<br>
                            Sábados · 9:00 a.m. – 1:00 p.m.
                        </div>
                    </div>
                </div>

                <hr>

                <p class="small text-muted mb-0">
                    También puedes crear tu cuenta directamente en la plataforma y nos pondremos en contacto contigo
                    para completar el proceso de matrícula.
                </p>
            </div>
        </div>

        <!-- Columna: Formulario de contacto -->
        <div class="col-lg-7">
            <div class="card card-soft h-100 p-3 p-md-4">
                <h3 class="h6 fw-bold mb-3">
                    <i class="fa-solid fa-paper-plane me-1"></i>
                    Envíanos un mensaje
                </h3>

                <form action="#contacto" method="post">
                    <input type="hidden" name="form_contacto" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nombre completo</label>
                            <input type="text" name="nombre" class="form-control"
                                   placeholder="Tu nombre" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Correo electrónico</label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="tucorreo@ejemplo.com" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Teléfono / WhatsApp</label>
                            <input type="text" name="telefono" class="form-control"
                                   placeholder="Tu número de contacto">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Programa de interés</label>
                            <select name="programa" class="form-select">
                                <option value="">Selecciona una opción</option>
                                <option>Inglés para principiantes</option>
                                <option>Inglés conversacional</option>
                                <option>Inglés para negocios</option>
                                <option>Preparación para exámenes</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold">Mensaje</label>
                            <textarea name="mensaje" rows="4" class="form-control"
                                      placeholder="Cuéntanos qué necesitas o en qué nivel de inglés te encuentras"
                                      required></textarea>
                        </div>

                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-tt-primary px-4 rounded-pill">
                                <i class="fa-solid fa-paper-plane me-1"></i>
                                Enviar mensaje
                            </button>
                        </div>
                    </div>
                </form>

                <p class="small text-muted mt-3 mb-0">
                    Tu mensaje será revisado por la administración de TwinTalk English desde el panel del sistema. 💙
                </p>
            </div>
        </div>

    </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
