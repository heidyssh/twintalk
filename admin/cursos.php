<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([1]); 

$mensaje = "";
$error = "";


if (isset($_GET['eliminar']) && ctype_digit($_GET['eliminar'])) {
    $id_eliminar = (int) $_GET['eliminar'];
    $stmt = $mysqli->prepare("UPDATE cursos SET activo = 0 WHERE id = ?");
    $stmt->bind_param("i", $id_eliminar);
    if ($stmt->execute()) {
        $mensaje = "Curso desactivado correctamente.";
    } else {
        $error = "No se pudo desactivar el curso.";
    }
    $stmt->close();
}


$niveles = [];
$resNiv = $mysqli->query("SELECT id, codigo_nivel, nombre_nivel FROM niveles_academicos ORDER BY id ASC");
if ($resNiv) {
    while ($row = $resNiv->fetch_assoc()) {
        $niveles[] = $row;
    }
}


$curso_editar = null;
if (isset($_GET['editar']) && ctype_digit($_GET['editar'])) {
    $id_editar = (int) $_GET['editar'];
    $stmt = $mysqli->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmt->bind_param("i", $id_editar);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $curso_editar = $res->fetch_assoc();

        
        $stmtPrecio = $mysqli->prepare("
            SELECT precio
            FROM precios_cursos
            WHERE curso_id = ?
              AND activo = 1
              AND fecha_inicio_vigencia <= CURDATE()
              AND (fecha_fin_vigencia IS NULL OR fecha_fin_vigencia >= CURDATE())
            ORDER BY fecha_inicio_vigencia DESC
            LIMIT 1
        ");
        if ($stmtPrecio) {
            $stmtPrecio->bind_param("i", $id_editar);
            $stmtPrecio->execute();
            $resPrecio = $stmtPrecio->get_result();
            if ($resPrecio && ($rowPrecio = $resPrecio->fetch_assoc())) {
                $curso_editar['precio_actual'] = (float) $rowPrecio['precio'];
            }
            $stmtPrecio->close();
        }
    }
    $stmt->close();
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_curso = trim($_POST['nombre_curso'] ?? '');
    $nivel_id = (int) ($_POST['nivel_id'] ?? 0);
    $duracion_horas = (int) ($_POST['duracion_horas'] ?? 0);
    $capacidad_maxima = (int) ($_POST['capacidad_maxima'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio_raw = trim($_POST['precio'] ?? '');
    $precio = ($precio_raw !== '' ? (float) $precio_raw : null);
    $curso_id = isset($_POST['curso_id']) && ctype_digit($_POST['curso_id'])
        ? (int) $_POST['curso_id']
        : 0;

    if ($nombre_curso === '' || $nivel_id <= 0 || $duracion_horas <= 0 || $capacidad_maxima <= 0) {
        $error = "Completa todos los campos obligatorios.";
    } else {
        
        $stmt = $mysqli->prepare("
            SELECT id FROM cursos
            WHERE nombre_curso = ? AND nivel_id = ? AND id <> ?
            LIMIT 1
        ");
        $stmt->bind_param("sii", $nombre_curso, $nivel_id, $curso_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Ya existe un curso con ese nombre y nivel.";
        } else {
            if ($curso_id > 0) {
                
                $stmt2 = $mysqli->prepare("
                    UPDATE cursos
                    SET nombre_curso = ?, descripcion = ?, nivel_id = ?,
                        duracion_horas = ?, capacidad_maxima = ?
                    WHERE id = ?
                ");
                $stmt2->bind_param(
                    "ssiiii",
                    $nombre_curso,
                    $descripcion,
                    $nivel_id,
                    $duracion_horas,
                    $capacidad_maxima,
                    $curso_id
                );
                if ($stmt2->execute()) {
                    $mensaje = "Curso actualizado correctamente.";
                    $curso_editar = null;

                    
                    if ($precio !== null && $precio > 0) {
                        
                        $stmtPrecio = $mysqli->prepare("
            UPDATE precios_cursos
            SET activo = 0
            WHERE curso_id = ? AND activo = 1
        ");
                        if ($stmtPrecio) {
                            $stmtPrecio->bind_param("i", $curso_id);
                            $stmtPrecio->execute();
                            $stmtPrecio->close();
                        }

                        
                        $stmtPrecioIns = $mysqli->prepare("
            INSERT INTO precios_cursos (curso_id, precio, fecha_inicio_vigencia, activo)
            VALUES (?, ?, CURDATE(), 1)
        ");
                        if ($stmtPrecioIns) {
                            $stmtPrecioIns->bind_param("id", $curso_id, $precio);
                            $stmtPrecioIns->execute();
                            $stmtPrecioIns->close();
                        }
                    }
                } else {
                    $error = "Error al actualizar el curso.";
                }
                $stmt2->close();

            } else {
                
                $stmt2 = $mysqli->prepare("
                    INSERT INTO cursos (nombre_curso, descripcion, nivel_id, duracion_horas, capacidad_maxima, activo, fecha_creacion)
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt2->bind_param(
                    "ssiii",
                    $nombre_curso,
                    $descripcion,
                    $nivel_id,
                    $duracion_horas,
                    $capacidad_maxima
                );
                if ($stmt2->execute()) {
                    $mensaje = "Curso creado correctamente.";

                    
                    if ($precio !== null && $precio > 0) {
                        $curso_nuevo_id = $stmt2->insert_id;

                        $stmtPrecioIns = $mysqli->prepare("
            INSERT INTO precios_cursos (curso_id, precio, fecha_inicio_vigencia, activo)
            VALUES (?, ?, CURDATE(), 1)
        ");
                        if ($stmtPrecioIns) {
                            $stmtPrecioIns->bind_param("id", $curso_nuevo_id, $precio);
                            $stmtPrecioIns->execute();
                            $stmtPrecioIns->close();
                        }
                    }
                } else {
                    $error = "Error al crear el curso.";
                }
                $stmt2->close();

            }
        }
        $stmt->close();
    }
}


$cursos = $mysqli->query("
    SELECT 
        c.*,
        n.codigo_nivel,
        n.nombre_nivel,
        pc.precio AS precio_actual
    FROM cursos c
    JOIN niveles_academicos n ON c.nivel_id = n.id
    LEFT JOIN (
        SELECT curso_id, precio
        FROM precios_cursos
        WHERE activo = 1
          AND fecha_inicio_vigencia <= CURDATE()
          AND (fecha_fin_vigencia IS NULL OR fecha_fin_vigencia >= CURDATE())
    ) pc ON pc.curso_id = c.id
    ORDER BY c.activo DESC, c.nombre_curso ASC
");


include __DIR__ . "/../includes/header.php";
?>

<div class="container my-4">
    <div class="card card-soft border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2"
            style="background: linear-gradient(90deg, #fbe9f0, #ffffff);">
            <div>
                <h1 class="h5 fw-bold mb-1" style="color:#b14f72;">
                    Gestión de cursos
                </h1>
                <small class="text-muted">
                    Administra los cursos, niveles, duración, capacidad y precio vigente.
                </small>
            </div>
        </div>
    </div>
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card card-soft shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3" style="color:#4b2e83;">
                        <?= $curso_editar ? "Editar curso" : "Nuevo curso" ?>
                    </h5>
                    <form method="post">
                        <?php if ($curso_editar): ?>
                            <input type="hidden" name="curso_id" value="<?= (int) $curso_editar['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-2">
                            <label class="form-label">Nombre del curso *</label>
                            <input type="text" name="nombre_curso" class="form-control"
                                value="<?= htmlspecialchars($curso_editar['nombre_curso'] ?? '') ?>" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Nivel *</label>
                            <select name="nivel_id" class="form-select" required>
                                <option value="">Selecciona nivel</option>
                                <?php foreach ($niveles as $niv): ?>
                                    <option value="<?= (int) $niv['id'] ?>" <?= isset($curso_editar['nivel_id']) && $curso_editar['nivel_id'] == $niv['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($niv['codigo_nivel'] . " - " . $niv['nombre_nivel']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Duración (horas) *</label>
                                <input type="number" name="duracion_horas" min="1" class="form-control"
                                    value="<?= htmlspecialchars($curso_editar['duracion_horas'] ?? '1') ?>" required>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Capacidad máxima *</label>
                                <input type="number" name="capacidad_maxima" min="1" class="form-control"
                                    value="<?= htmlspecialchars($curso_editar['capacidad_maxima'] ?? '10') ?>" required>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Precio del curso (L.)</label>
                                <input type="number" name="precio" min="0" step="0.01" class="form-control"
                                    value="<?= isset($curso_editar['precio_actual']) ? htmlspecialchars($curso_editar['precio_actual']) : '' ?>"
                                    placeholder="Ej: 1200.00">
                                <div class="form-text small">
                                    Este precio se usará al matricular estudiantes.
                                </div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control"
                                rows="3"><?= htmlspecialchars($curso_editar['descripcion'] ?? '') ?></textarea>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="submit" class="btn btn-sm" style="
                            background-color:#ff4b7b;
                            border:1px solid #ff4b7b;
                            color:white;
                            font-weight:500;
                            border-radius:6px;
                            padding:6px 14px;
                        " onmouseover="this.style.backgroundColor='#e84372'"
                                onmouseout="this.style.backgroundColor='#ff4b7b'">
                                <?= $curso_editar ? "Guardar cambios" : "Crear curso" ?>
                            </button>
                            <?php if ($curso_editar): ?>
                                <a href="cursos.php" class="btn btn-sm" style="
                            border:1px solid #ff4b7b;
                            color:#ff4b7b;
                            background-color:transparent;
                            border-radius:6px;
                            padding:6px 14px;
                            font-weight:500;
                    " onmouseover="this.style.backgroundColor='#ff4b7b'; this.style.color='#fff';"
                                    onmouseout="this.style.backgroundColor='transparent'; this.style.color='#ff4b7b';">
                                    Cancelar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tabla de cursos -->
        <div class="col-lg-8">
            <div class="card card-soft shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3" style="color:#4b2e83;">Listado de cursos</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Nivel</th>
                                    <th>Duración</th>
                                    <th>Capacidad</th>
                                    <th>Precio actual</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($cursos && $cursos->num_rows > 0): ?>
                                    <?php while ($c = $cursos->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($c['nombre_curso']) ?></td>
                                            <td><?= htmlspecialchars($c['codigo_nivel']) ?></td>
                                            <td><?= (int) $c['duracion_horas'] ?> h</td>
                                            <td><?= (int) $c['capacidad_maxima'] ?></td>
                                            <td>
                                                <?php if (!is_null($c['precio_actual'])): ?>
                                                    L <?= number_format($c['precio_actual'], 2) ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">Sin precio</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($c['activo']): ?>
                                                    <span class="badge bg-success">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="cursos.php?editar=<?= (int) $c['id'] ?>" class="btn btn-sm" style="
                                            border:1px solid #ff4b7b;
                                            color:#ff4b7b;
                                            background-color:transparent;
                                            border-radius:6px;
                                            padding:4px 10px;
                                            font-size:0.8rem;
                                            font-weight:500;
                                    " onmouseover="this.style.backgroundColor='#ff4b7b'; this.style.color='#fff';"
                                                    onmouseout="this.style.backgroundColor='transparent'; this.style.color='#ff4b7b';">
                                                    Editar
                                                </a>

                                                <?php if ($c['activo']): ?>
                                                    <a href="cursos.php?eliminar=<?= (int) $c['id'] ?>"
                                                        class="btn btn-outline-danger btn-sm"
                                                        onclick="return confirm('¿Desactivar este curso?');">
                                                        Desactivar
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-muted">No hay cursos registrados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mb-0">* Los cursos desactivados no aparecerán para matrícula ni horarios.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>