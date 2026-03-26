<?php
// api/check_user.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$db = new Database();
$conn = $db->getPortalConnection();

if (!$conn) {
    echo json_encode(['exists' => false, 'error' => 'Connection failed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$response = ['exists' => false];

if (isset($input['identity_number'])) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE identity_number = ?");
    $stmt->bind_param("s", $input['identity_number']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $response = ['exists' => true, 'field' => 'identity'];
    $stmt->close();
} elseif (isset($input['email'])) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $input['email']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $response = ['exists' => true, 'field' => 'email'];
    $stmt->close();
}

$conn->close();
echo json_encode($response);
?>