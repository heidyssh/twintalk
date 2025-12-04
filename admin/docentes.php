<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]); // solo admin

$mensaje = "";
$error   = "";

// Traer todos los usuarios que son docentes (rol_id = 2)
$sql = "
    SELECT
        u.id,
        u.nombre,
        u.apellido,
        u.email,
        u.telefono,
        u.foto_perfil,
        u.fecha_registro,
        u.activo,
        d.especialidad,
        d.fecha_contratacion,
        d.activo AS docente_activo,
        COUNT(DISTINCT h.id)         AS total_horarios,
        COUNT(DISTINCT m.id)         AS total_matriculas
    FROM usuarios u
    LEFT JOIN docentes d   ON d.id = u.id
    LEFT JOIN horarios h   ON h.docente_id = d.id
    LEFT JOIN matriculas m ON m.horario_id = h.id
    WHERE u.rol_id = 2
    GROUP BY
        u.id, u.nombre, u.apellido, u.email, u.telefono,
        u.foto_perfil, u.fecha_registro, u.activo,
        d.especialidad, d.fecha_contratacion, d.activo
    ORDER BY u.nombre, u.apellido
";

$result = $mysqli->query($sql);
if (!$result) {
    $error = "Error al cargar la lista de docentes: " . $mysqli->error;
}

include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">
    <!-- HEADER con gradiente al estilo TwinTalk -->
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
             style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">
            <div>
                <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                    <i class="fa-solid fa-chalkboard-user me-2"></i>
                    Docentes registrados
                </h1>
                <small class="text-muted">
                    Consulta la información general de los docentes activos en el sistema.
                </small>
            </div>
        </div>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card card-soft shadow-sm border-0">
        <div class="card-body">
            <p class="text-muted">
                Aquí puedes ver la lista de docentes que existen en el sistema.
                Para ver la información completa de un docente, haz clic en
                <strong>Ver perfil</strong>.
            </p>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Foto</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Especialidad</th>
                            <th>Fecha contratación</th>
                            <th>Horarios asignados</th>
                            <th>Estudiantes matriculados</th>
                            <th>Estado</th>
                            <th>Perfil</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                                $docenteId = (int)$row['id'];
                                $foto      = $row['foto_perfil'] ?: "/twintalk/assets/img/avatars/avatar1.jpg";
                            ?>
                            <tr>
                                <td>
                                    <img src="<?= htmlspecialchars($foto) ?>"
                                         alt="Foto"
                                         class="rounded-circle"
                                         style="width:45px;height:45px;object-fit:cover;">
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['nombre'] . " " . $row['apellido']) ?><br>
                                    <span class="badge bg-light text-muted small">
                                        ID: <?= $docenteId ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['telefono'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['especialidad'] ?? '—') ?></td>
                                <td>
                                    <?php if (!empty($row['fecha_contratacion'])): ?>
                                        <?= htmlspecialchars($row['fecha_contratacion']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin definir</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$row['total_horarios'] ?></td>
                                <td><?= (int)$row['total_matriculas'] ?></td>
                                <td>
                                    <?php if ((int)$row['activo'] === 1 && (int)$row['docente_activo'] === 1): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="docente_perfil.php?docente_id=<?= $docenteId ?>"
                                       class="btn btn-sm"
                                       style="
                                           border:1px solid #ff4b7b;
                                           color:#ff4b7b;
                                           background-color:transparent;
                                           border-radius:6px;
                                           padding:4px 10px;
                                           font-size:0.8rem;
                                           font-weight:500;
                                       "
                                       onmouseover="this.style.backgroundColor='#ff4b7b'; this.style.color='#fff';"
                                       onmouseout="this.style.backgroundColor='transparent'; this.style.color='#ff4b7b';"
                                    >
                                        <i class="fa-solid fa-eye"></i> Ver perfil
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                No hay docentes registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="small text-muted mb-0">
                Los docentes se crean automáticamente cuando asignas el rol
                <strong>docente</strong> a un usuario en la sección <code>Usuarios</code>.
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
