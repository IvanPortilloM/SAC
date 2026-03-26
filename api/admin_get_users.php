<?php
// api/admin_get_users.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// --- SEGURIDAD ACTIVADA ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Prohibido']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

// Seleccionamos ID y Nombre para llenar el select
// IMPORTANTE: Verifica si tu columna se llama 'nombres' o 'names' en la BD
$sql = "SELECT id, nombres, apellidos, identity_number FROM users ORDER BY nombres ASC";

$result = $conn->query($sql);
$users = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

echo json_encode(['success' => true, 'users' => $users]);
$conn->close();
?>