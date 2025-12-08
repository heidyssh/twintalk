<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]); 

$docente_id = isset($_GET['docente_id']) ? (int)$_GET['docente_id'] : 0;

if ($docente_id <= 0) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="container my-4">
            <div class="alert alert-danger">Docente no válido.</div>
          </div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}


$sqlDocente = "
    SELECT
        u.id,
        u.nombre,
        u.apellido,
        u.email,
        u.telefono,
        u.foto_perfil,
        u.fecha_registro,
        u.ultimo_login,
        u.activo AS usuario_activo,
        d.especialidad,
        d.fecha_contratacion,
        d.archivo_titulo,
        d.activo AS docente_activo,
        ta.nombre_titulo,
        ta.nivel_titulo,
        ip.numero_documento,
        ip.fecha_nacimiento,
        ip.direccion,
        ip.ciudad,
        ip.pais,
        td.tipo_documento,
        ip.archivo_documento
    FROM usuarios u
    LEFT JOIN docentes d           ON d.id = u.id
    LEFT JOIN titulos_academicos ta ON ta.id = d.titulo_id
    LEFT JOIN informacion_personal ip ON ip.usuario_id = u.id
    LEFT JOIN tipos_documento td      ON td.id = ip.tipo_documento_id
    WHERE u.id = ? AND u.rol_id = 2
    LIMIT 1
";

$stmt = $mysqli->prepare($sqlDocente);
$stmt->bind_param("i", $docente_id);
$stmt->execute();
$resDocente = $stmt->get_result();
$docente = $resDocente->fetch_assoc();
$stmt->close();

if (!$docente) {
    include __DIR__ . "/../includes/header.php";
    echo '<div class="container my-4">
            <div class="alert alert-danger">
                No se encontró el docente especificado o no tiene rol de docente.
            </div>
            <a href="docentes.php" class="btn btn-link px-0 mt-2">‹ Volver a la lista de docentes</a>
          </div>';
    include __DIR__ . "/../includes/footer.php";
    exit;
}


$sqlHorarios = "
    SELECT
        h.id,
        h.aula,
        h.hora_inicio,
        h.hora_fin,
        h.fecha_inicio,
        h.fecha_fin,
        h.activo,
        c.nombre_curso,
        na.codigo_nivel,
        na.nombre_nivel,
        ds.nombre_dia,
        COUNT(DISTINCT m.id) AS total_matriculas
    FROM horarios h
    INNER JOIN cursos c            ON c.id = h.curso_id
    INNER JOIN niveles_academicos na ON na.id = c.nivel_id
    INNER JOIN dias_semana ds      ON ds.id = h.dia_semana_id
    LEFT JOIN matriculas m         ON m.horario_id = h.id
    WHERE h.docente_id = ?
    GROUP BY
        h.id, h.aula, h.hora_inicio, h.hora_fin, h.fecha_inicio, h.fecha_fin,
        h.activo, c.nombre_curso, na.codigo_nivel, na.nombre_nivel, ds.nombre_dia
    ORDER BY h.fecha_inicio DESC
";

$stmtHor = $mysqli->prepare($sqlHorarios);
$stmtHor->bind_param("i", $docente_id);
$stmtHor->execute();
$resHorarios = $stmtHor->get_result();
$horarios = [];
while ($row = $resHorarios->fetch_assoc()) {
    $horarios[] = $row;
}
$stmtHor->close();


$totalHorarios   = count($horarios);
$totalEstudiantes = 0;
foreach ($horarios as $h) {
    $totalEstudiantes += (int)$h['total_matriculas'];
}

include __DIR__ . "/../includes/header.php";
?>


    <div class="row g-4">
        <!-- Columna izquierda: info principal -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <?php
                        $foto = $docente['foto_perfil'] ?: "/twintalk/assets/img/avatars/avatar1.jpg";
                    ?>
                    <img src="<?= htmlspecialchars($foto) ?>"
                         alt="Foto de perfil"
                         class="rounded-circle mb-3"
                         style="width:110px;height:110px;object-fit:cover;">

                    <h4 class="mb-0">
                        <?= htmlspecialchars($docente['nombre'] . " " . $docente['apellido']) ?>
                    </h4>
                    <p class="text-muted mb-1">
                        Docente · ID <?= (int)$docente['id'] ?>
                    </p>

                    <?php if ((int)$docente['usuario_activo'] === 1 && (int)$docente['docente_activo'] === 1): ?>
                        <span class="badge bg-success mb-2">Activo</span>
                    <?php else: ?>
                        <span class="badge bg-secondary mb-2">Inactivo</span>
                    <?php endif; ?>

                    <?php if (!empty($docente['especialidad'])): ?>
                        <p class="mb-1">
                            <i class="fa-solid fa-star me-1"></i>
                            <strong>Especialidad:</strong>
                            <?= htmlspecialchars($docente['especialidad']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($docente['fecha_contratacion'])): ?>
                        <p class="mb-1">
                            <i class="fa-solid fa-briefcase me-1"></i>
                            <strong>Contratado el:</strong>
                            <?= htmlspecialchars($docente['fecha_contratacion']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($docente['nombre_titulo'])): ?>
                        <p class="mb-1">
                            <i class="fa-solid fa-graduation-cap me-1"></i>
                            <strong>Título:</strong>
                            <?= htmlspecialchars($docente['nombre_titulo']) ?>
                            <?php if (!empty($docente['nivel_titulo'])): ?>
                                (<?= htmlspecialchars($docente['nivel_titulo']) ?>)
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($docente['archivo_titulo'])): ?>
                        <p class="mb-1">
                            <a href="<?= htmlspecialchars($docente['archivo_titulo']) ?>" target="_blank">
                                <i class="fa-solid fa-file-pdf me-1"></i>
                                Ver archivo de título
                            </a>
                        </p>
                    <?php endif; ?>

                    <hr>

                    <p class="mb-1">
                        <i class="fa-solid fa-envelope me-1"></i>
                        <?= htmlspecialchars($docente['email']) ?>
                    </p>
                    <?php if (!empty($docente['telefono'])): ?>
                        <p class="mb-1">
                            <i class="fa-solid fa-phone me-1"></i>
                            <?= htmlspecialchars($docente['telefono']) ?>
                        </p>
                    <?php endif; ?>

                    <p class="small text-muted mb-0 mt-2">
                        Registrado en el sistema el
                        <?= htmlspecialchars($docente['fecha_registro']) ?>
                        <?php if (!empty($docente['ultimo_login'])): ?>
                            <br>Último acceso:
                            <?= htmlspecialchars($docente['ultimo_login']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Información personal -->
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <strong>
                        <i class="fa-solid fa-id-card me-1"></i>
                        Información personal
                    </strong>
                </div>
                <div class="card-body">
                    <?php if (!empty($docente['tipo_documento']) || !empty($docente['numero_documento'])): ?>
                        <p class="mb-1">
                            <strong>Documento:</strong>
                            <?= htmlspecialchars($docente['tipo_documento'] ?? '') ?>
                            <?= htmlspecialchars($docente['numero_documento'] ?? '') ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($docente['fecha_nacimiento'])): ?>
                        <p class="mb-1">
                            <strong>Fecha de nacimiento:</strong>
                            <?= htmlspecialchars($docente['fecha_nacimiento']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($docente['direccion'])): ?>
                        <p class="mb-1">
                            <strong>Dirección:</strong>
                            <?= nl2br(htmlspecialchars($docente['direccion'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($docente['ciudad']) || !empty($docente['pais'])): ?>
                        <p class="mb-1">
                            <strong>Ciudad / País:</strong>
                            <?= htmlspecialchars($docente['ciudad'] ?? '') ?>
                            <?= !empty($docente['ciudad']) && !empty($docente['pais']) ? ' - ' : '' ?>
                            <?= htmlspecialchars($docente['pais'] ?? '') ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($docente['archivo_documento'])): ?>
                        <p class="mb-1">
                            <a href="<?= htmlspecialchars($docente['archivo_documento']) ?>" target="_blank">
                                <i class="fa-solid fa-file me-1"></i>
                                Ver documento de identidad
                            </a>
                        </p>
                    <?php endif; ?>

                    <?php if (
                        empty($docente['tipo_documento']) &&
                        empty($docente['numero_documento']) &&
                        empty($docente['fecha_nacimiento']) &&
                        empty($docente['direccion']) &&
                        empty($docente['ciudad']) &&
                        empty($docente['pais'])
                    ): ?>
                        <p class="text-muted mb-0">
                            No hay información personal registrada para este docente.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Columna derecha: horarios y resumen -->
        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fa-solid fa-chart-line me-1"></i>
                        Resumen académico
                    </h5>
                    <div class="row text-center">
                        <div class="col-6 col-md-4 mb-2">
                            <div class="border rounded p-2">
                                <div class="fw-bold"><?= $totalHorarios ?></div>
                                <div class="text-muted small">Horarios asignados</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 mb-2">
                            <div class="border rounded p-2">
                                <div class="fw-bold"><?= $totalEstudiantes ?></div>
                                <div class="text-muted small">Estudiantes matriculados</div>
                            </div>
                        </div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        Estos datos se calculan a partir de las tablas <code>horarios</code> y
                        <code>matriculas</code>.
                    </p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header">
                    <strong>
                        <i class="fa-solid fa-calendar-days me-1"></i>
                        Horarios y cursos impartidos
                    </strong>
                </div>
                <div class="card-body">
                    <?php if ($totalHorarios > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Curso</th>
                                        <th>Nivel</th>
                                        <th>Día</th>
                                        <th>Hora</th>
                                        <th>Aula</th>
                                        <th>Fechas</th>
                                        <th>Estudiantes</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($horarios as $h): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($h['nombre_curso']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($h['codigo_nivel']) ?>
                                            - <?= htmlspecialchars($h['nombre_nivel']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($h['nombre_dia']) ?></td>
                                        <td>
                                            <?= substr(htmlspecialchars($h['hora_inicio']), 0, 5) ?>
                                            -
                                            <?= substr(htmlspecialchars($h['hora_fin']), 0, 5) ?>
                                        </td>
                                        <td><?= htmlspecialchars($h['aula'] ?? '') ?></td>
                                        <td>
                                            <?= htmlspecialchars($h['fecha_inicio']) ?><br>
                                            <span class="text-muted small">al</span><br>
                                            <?= htmlspecialchars($h['fecha_fin']) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= (int)$h['total_matriculas'] ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$h['activo'] === 1): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            Este docente aún no tiene horarios asignados.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>
