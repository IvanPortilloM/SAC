<?php
// api/update_profile.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Datos no recibidos']);
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Sanitización compatible con PHP 8.1+
$email = trim($input['email'] ?? '');
$telefono = trim(strip_tags($input['telefono'] ?? ''));
$direccion = trim(strip_tags($input['direccion'] ?? ''));

// 2. Validación estricta del formato de correo
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'El formato del correo electrónico no es válido.']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

// 3. Validación Anti-Duplicados: Verificar que el correo no le pertenezca a OTRO usuario
if (!empty($email)) {
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt_check->bind_param("si", $email, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Este correo electrónico ya está registrado por otro asociado.']);
        $stmt_check->close();
        $conn->close();
        exit();
    }
    $stmt_check->close();
}

// 4. Actualización Segura
$sql = "UPDATE users SET email = ?, telefono = ?, direccion = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $email, $telefono, $direccion, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    error_log("Error actualizando perfil: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Error al actualizar los datos en la base de datos.']);
}

$stmt->close();
$conn->close();
?>