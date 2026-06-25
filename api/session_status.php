<?php
require_once __DIR__ . '/../config/session.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['logged_in' => false]);
    exit;
}

require_once __DIR__ . '/../config/db.php';
$db = new Database();
$conn = $db->getPortalConnection();

if (!$conn || $conn->connect_error) {
    echo json_encode(['logged_in' => false, 'error' => 'Error de conexión.']);
    exit;
}

$stmt = $conn->prepare("SELECT nombres, apellidos, identity_number, rol FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user) {
    echo json_encode(['logged_in' => false]);
    exit;
}

echo json_encode([
    'logged_in' => true,
    'user_name' => trim(($user['nombres'] ?? '') . ' ' . ($user['apellidos'] ?? '')),
    'rol' => $user['rol'] ?? 'user',
    'identity_number' => $user['identity_number'] ?? ''
]);
