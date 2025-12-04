<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role([2]);

$docenteId = $_SESSION['usuario_id'] ?? null;

if (!$docenteId) {
    header("Location: /twintalk/login.php");
    exit;
}

$sql = "
    SELECT 
        h.id AS horario_id,
        c.nombre_curso,
        c.descripcion,
        d.nombre_dia,
        h.hora_inicio,
        h.hora_fin,
        h.aula,
        h.fecha_inicio,
        h.fecha_fin,
        h.cupos_disponibles
    FROM horarios h
    INNER JOIN cursos c ON h.curso_id = c.id
    INNER JOIN dias_semana d ON h.dia_semana_id = d.id
    WHERE h.docente_id = ?
    ORDER BY d.numero_dia, h.hora_inicio
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $docenteId);
$stmt->execute();
$horarios = $stmt->get_result();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <!-- Encabezado -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
            style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">
            <div>
                <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                    Mis cursos y horarios
                </h1>
                <small class="text-muted">
                    Aquí puedes ver los cursos y horarios de clase que tienes asignados.
                </small>
            </div>
            <div class="text-md-end">
                <span class="badge rounded-pill text-bg-light border">
                    Docente
                </span>
            </div>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                <div>
                    <h2 class="h6 fw-semibold mb-1">Listado de clases</h2>
                    <p class="small text-muted mb-0">
                        Revisa la información de cada grupo y accede a estudiantes, calificaciones, materiales y
                        anuncios.
                    </p>
                </div>
            </div>

            <div class="table-responsive mt-2">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Curso</th>
                            <th>Día</th>
                            <th>Hora</th>
                            <th>Aula</th>
                            <th>Fechas</th>
                            <th>Cupos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $horarios->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['nombre_curso']); ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($row['descripcion']); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($row['nombre_dia']); ?></td>
                                <td><?php echo htmlspecialchars($row['hora_inicio'] . " - " . $row['hora_fin']); ?></td>
                                <td><?php echo htmlspecialchars($row['aula'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($row['fecha_inicio']); ?>
                                    <br>
                                    <small class="text-muted">
                                        al <?php echo htmlspecialchars($row['fecha_fin']); ?>
                                    </small>
                                </td>
                                <td><?php echo (int) $row['cupos_disponibles']; ?></td>
                                <td>

                                    <style>
                                        .btn-tt {
                                            font-size: .78rem;
                                            padding: 6px 12px;
                                            border-radius: 8px;
                                            font-weight: 500;
                                            margin: 2px;
                                            display: inline-block;
                                            transition: .25s ease;
                                            text-decoration: none
                                        }

                                        .btn-main {
                                            background: #b14f72;
                                            color: #fff;
                                        }

                                        .btn-main:hover {
                                            background: linear-gradient(90deg, #b14f72, #e7b4c7);
                                            transform: scale(1.05)
                                        }

                                        .btn-sec {
                                            background: #d88aa6;
                                            color: #fff;
                                        }

                                        .btn-sec:hover {
                                            background: linear-gradient(90deg, #d88aa6, #efd3e0);
                                            transform: scale(1.05)
                                        }

                                        .btn-light {
                                            background: #eed0da;
                                            color: #5b2a3f;
                                        }

                                        .btn-light:hover {
                                            background: #b14f72;
                                            color: #fff;
                                            transform: scale(1.05)
                                        }

                                        .btn-soft {
                                            background: #f8dde6;
                                            color: #6d3b52;
                                        }

                                        .btn-soft:hover {
                                            background: #d88aa6;
                                            color: #fff;
                                            transform: scale(1.05)
                                        }
                                    </style>

                                    <a href="estudiantes.php?horario_id=<?php echo (int) $row['horario_id']; ?>"
                                        class="btn-tt btn-main">
                                        Estudiantes
                                    </a>

                                    <a href="calificaciones.php?horario_id=<?php echo (int) $row['horario_id']; ?>"
                                        class="btn-tt btn-sec">
                                        Calificaciones
                                    </a>

                                    <a href="materiales.php?horario_id=<?php echo (int) $row['horario_id']; ?>"
                                        class="btn-tt btn-light">
                                        Materiales
                                    </a>

                                    <a href="anuncios.php?horario_id=<?php echo (int) $row['horario_id']; ?>"
                                        class="btn-tt btn-soft">
                                        Anuncios
                                    </a>

                                </td>


                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>