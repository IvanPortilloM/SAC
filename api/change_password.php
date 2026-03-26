<?php
// api/change_password.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// 1. Verificar Sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado. Por favor inicie sesión.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';

// 2. Validaciones en servidor
if (empty($current_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'error' => 'Por favor, complete todos los campos.']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'error' => 'La nueva contraseña debe tener al menos 6 caracteres por seguridad.']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error interno de conexión.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// 3. Buscar la contraseña actual del usuario
$stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// 4. Verificar que la contraseña actual ingresada sea correcta
if ($user && password_verify($current_password, $user['password_hash'])) {
    
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // 5. Actualizar la clave e INVALIDAR cualquier intento de recuperación previo
    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
    $update_stmt->bind_param("si", $new_hash, $user_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        error_log("Error cambiando contraseña interna: " . $update_stmt->error);
        echo json_encode(['success' => false, 'error' => 'No se pudo actualizar la contraseña.']);
    }
    $update_stmt->close();
} else {
    // Mensaje claro si falló la validación
    echo json_encode(['success' => false, 'error' => 'La contraseña actual ingresada es incorrecta.']);
}

$stmt->close();
$conn->close();
?>