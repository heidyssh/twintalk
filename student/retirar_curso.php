<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/auth.php";
require_role([3]); // Solo estudiantes

$usuario_id = $_SESSION['usuario_id'] ?? 0;
$matricula_id = isset($_GET['matricula_id']) ? (int)$_GET['matricula_id'] : 0;

if ($usuario_id <= 0 || $matricula_id <= 0) {
    header("Location: mis_matriculas.php?err=param");
    exit;
}

// Validar que la matrícula pertenezca al estudiante
$stmt = $mysqli->prepare("
    SELECT id FROM matriculas 
    WHERE id = ? AND estudiante_id = ? LIMIT 1
");
$stmt->bind_param("ii", $matricula_id, $usuario_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header("Location: mis_matriculas.php?err=noauth");
    exit;
}

// Cambiar estado a RETIRADO
$stmt = $mysqli->prepare("UPDATE matriculas SET estado_id = 99 WHERE id = ?");
$stmt->bind_param("i", $matricula_id);
$stmt->execute();
$stmt->close();

header("Location: mis_matriculas.php?ok=retirado");
exit;
