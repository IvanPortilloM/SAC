<?php
// process_reset_password.php
session_start();
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$token = $input['token'] ?? '';
$new_password = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? ''; // NUEVO: Se recibe la confirmación

// 1. Validaciones estrictas en el servidor
if (empty($token) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos.']);
    exit();
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'error' => 'Las contraseñas no coinciden.']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres por seguridad.']);
    exit();
}

// 2. Hashear el token recibido para compararlo con el de la BD
$hashed_token = hash('sha256', $token);

$db = new Database();
$conn = $db->getPortalConnection();

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error interno de conexión.']);
    exit();
}

// 3. Verificar que el token exista y no haya expirado
$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
$stmt->bind_param("s", $hashed_token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Hashear la nueva contraseña
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // 4. Actualizar contraseña y DESTRUIR el token usado
    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
    $update_stmt->bind_param("si", $new_hash, $user['id']);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        error_log("Error reseteando contraseña: " . $update_stmt->error);
        echo json_encode(['success' => false, 'error' => 'Error al actualizar la contraseña en la base de datos.']);
    }
    $update_stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'El enlace de recuperación es inválido o ha expirado.']);
}

$stmt->close();
$conn->close();
?>