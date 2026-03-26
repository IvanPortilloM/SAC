<?php
// api/mark_read.php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { exit(); }

// Agregado ?: [] para evitar errores si Javascript envía petición vacía
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$notif_id = $input['id'] ?? null;
$user_id = $_SESSION['user_id'];

$db = new Database();
$conn = $db->getPortalConnection();

if ($notif_id) {
    // Marcar una notificación en específica (solo si me pertenece)
    $stmt = $conn->prepare("UPDATE notificaciones SET leido = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
} else {
    // Marcar TODAS mis notificaciones como leídas
    $stmt = $conn->prepare("UPDATE notificaciones SET leido = 1 WHERE user_id = ? AND leido = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

echo json_encode(['success' => true]);
?>