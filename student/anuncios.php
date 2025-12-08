<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); 

$usuario_id = $_SESSION['usuario_id'];




$sql = "
    SELECT 
        a.titulo,
        a.contenido,
        a.fecha_publicacion,
        a.fecha_expiracion,
        a.importante,
        ta.tipo_anuncio,
        c.nombre_curso,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        u.nombre AS docente_nombre,
        u.apellido AS docente_apellido
    FROM anuncios a
    INNER JOIN tipos_anuncio ta ON a.tipo_anuncio_id = ta.id
    LEFT JOIN horarios h ON a.horario_id = h.id
    LEFT JOIN cursos c ON h.curso_id = c.id
    LEFT JOIN dias_semana d ON h.dia_semana_id = d.id
    LEFT JOIN docentes dc ON a.docente_id = dc.id
    LEFT JOIN usuarios u ON dc.id = u.id
    WHERE
        (
            a.horario_id IS NULL
            OR a.horario_id IN (
                SELECT m.horario_id
                FROM matriculas m
                INNER JOIN estados_matricula em ON m.estado_id = em.id
                WHERE m.estudiante_id = ? AND em.nombre_estado = 'Activa'
            )
        )
        AND (a.fecha_expiracion IS NULL OR a.fecha_expiracion >= CURDATE())
    ORDER BY a.importante DESC, a.fecha_publicacion DESC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$anuncios = $stmt->get_result();
$stmt->close();

include __DIR__ . "/../includes/header.php";
?>

<h1 class="h4 fw-bold mt-3">Anuncios de mis cursos</h1>
<p class="text-muted mb-3">
    Aquí verás anuncios generales de la academia y avisos específicos de tus clases.
</p>

<?php if ($anuncios->num_rows === 0): ?>
    <div class="alert alert-info">
        Aún no hay anuncios para tus cursos.
    </div>
<?php else: ?>
    <ul class="list-group list-group-flush">
        <?php while ($a = $anuncios->fetch_assoc()): ?>
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($a['importante']): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    Importante
                                </span>
                            <?php endif; ?>
                            <span class="badge bg-light text-muted border">
                                <?= htmlspecialchars($a['tipo_anuncio']) ?>
                            </span>
                        </div>
                        <h2 class="h6 fw-bold mt-2 mb-1">
                            <?= htmlspecialchars($a['titulo']) ?>
                        </h2>
                        <p class="small mb-1">
                            <?php if (!empty($a['nombre_curso'])): ?>
                                <strong>Curso:</strong> <?= htmlspecialchars($a['nombre_curso']) ?>
                                <?php if (!empty($a['nombre_dia'])): ?>
                                    · <?= htmlspecialchars($a['nombre_dia']) ?>
                                    · <?= substr($a['hora_inicio'], 0, 5) ?> - <?= substr($a['hora_fin'], 0, 5) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <strong>Curso:</strong> General para la academia
                            <?php endif; ?>
                        </p>
                        <p class="small mb-2 text-muted">
                            Docente:
                            <?= htmlspecialchars(trim($a['docente_nombre'] . " " . $a['docente_apellido'])) ?: 'Administración' ?>
                        </p>
                        <p class="mb-1">
                            <?= nl2br(htmlspecialchars($a['contenido'])) ?>
                        </p>
                    </div>
                    <small class="text-muted ms-3">
                        <?= date('d/m/Y H:i', strtotime($a['fecha_publicacion'])) ?>
                    </small>
                </div>
            </li>
        <?php endwhile; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . "/../includes/footer.php"; ?>
