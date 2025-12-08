<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); // Solo estudiantes

$usuario_id   = $_SESSION['usuario_id'] ?? 0;
$matricula_id = isset($_GET['matricula_id']) ? (int) $_GET['matricula_id'] : 0;

if ($usuario_id <= 0 || $matricula_id <= 0) {
    header("Location: mis_matriculas.php?err=param");
    exit;
}

// 1) Verificar que la matrícula pertenece al estudiante y obtener horario + estado
$stmt = $mysqli->prepare("
    SELECT 
        m.id,
        m.horario_id,
        em.nombre_estado
    FROM matriculas m
    INNER JOIN estados_matricula em ON em.id = m.estado_id
    WHERE m.id = ? AND m.estudiante_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $matricula_id, $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
$mat = $res->fetch_assoc();
$stmt->close();

if (!$mat) {
    // La matrícula no es tuya o no existe
    header("Location: mis_matriculas.php?err=noauth");
    exit;
}

// Solo permitir retiro si la matrícula está Activa
if ($mat['nombre_estado'] !== 'Activa') {
    header("Location: mis_matriculas.php?err=noactiva");
    exit;
}

// 2) Buscar id del estado 'Cancelada'
$stmt = $mysqli->prepare("
    SELECT id 
    FROM estados_matricula 
    WHERE nombre_estado = 'Cancelada'
    LIMIT 1
");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    // No se encontró el estado Cancelada
    header("Location: mis_matriculas.php?err=nestado");
    exit;
}

$estado_cancelada_id = (int)$res['id'];

// 3) Cambiar estado de la matrícula a Cancelada
$stmt = $mysqli->prepare("
    UPDATE matriculas 
    SET estado_id = ? 
    WHERE id = ?
");
$stmt->bind_param("ii", $estado_cancelada_id, $matricula_id);
$stmt->execute();
$stmt->close();

// 4) Liberar un cupo en el horario
$stmt = $mysqli->prepare("
    UPDATE horarios 
    SET cupos_disponibles = cupos_disponibles + 1 
    WHERE id = ?
");
$stmt->bind_param("i", $mat['horario_id']);
$stmt->execute();
$stmt->close();

// 5) Regresar a Mis matrículas con mensaje OK
header("Location: mis_matriculas.php?ok=retirado");
exit;
