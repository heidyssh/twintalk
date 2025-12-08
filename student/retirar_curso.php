<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); 

$usuario_id   = $_SESSION['usuario_id'] ?? 0;
$matricula_id = isset($_GET['matricula_id']) ? (int) $_GET['matricula_id'] : 0;

if ($usuario_id <= 0 || $matricula_id <= 0) {
    header("Location: mis_matriculas.php?err=param");
    exit;
}


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
    
    header("Location: mis_matriculas.php?err=noauth");
    exit;
}


if ($mat['nombre_estado'] !== 'Activa') {
    header("Location: mis_matriculas.php?err=noactiva");
    exit;
}


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
    
    header("Location: mis_matriculas.php?err=nestado");
    exit;
}

$estado_cancelada_id = (int)$res['id'];


$stmt = $mysqli->prepare("
    UPDATE matriculas 
    SET estado_id = ? 
    WHERE id = ?
");
$stmt->bind_param("ii", $estado_cancelada_id, $matricula_id);
$stmt->execute();
$stmt->close();


$stmt = $mysqli->prepare("
    UPDATE horarios 
    SET cupos_disponibles = cupos_disponibles + 1 
    WHERE id = ?
");
$stmt->bind_param("i", $mat['horario_id']);
$stmt->execute();
$stmt->close();


header("Location: mis_matriculas.php?ok=retirado");
exit;
