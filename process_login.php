<?php
// process_login.php
session_start();
require_once __DIR__ . '/config/db.php';

// Definimos el charset explícitamente en el header
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

// Sanitización básica: eliminamos espacios en blanco al inicio y final
$identity = trim((string)($input['identity'] ?? ''));
$password = (string)($input['password'] ?? '');

// Early Return: Validación de campos vacíos
if (empty($identity) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Por favor complete todos los campos.']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

if (!$conn || $conn->connect_error) {
    // El error real se guarda en tu servidor a través de la clase Database
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos.']);
    exit();
}

// Buscamos el usuario Y SU ROL
$sql = "SELECT id, password_hash, rol FROM users WHERE identity_number = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error interno en el servidor.']);
    $conn->close();
    exit();
}

$stmt->bind_param("s", $identity);
$stmt->execute();
$result = $stmt->get_result();

// Extraemos el usuario (será null si no existe)
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Early Return: Verificación de usuario existente y contraseña correcta en un solo paso
if (!$user || !password_verify($password, $user['password_hash'])) {
    // Anti-enumeración: Mismo mensaje de error genérico unificado
    echo json_encode(['success' => false, 'error' => 'Credenciales incorrectas.']);
    exit();
}

// MEJORA DE SEGURIDAD
// Generamos un nuevo ID de sesión al autenticar para evitar ataques de fijación
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['rol'] = !empty($user['rol']) ? $user['rol'] : 'user'; 
$_SESSION['fresh_login'] = true; 

echo json_encode([
    'success' => true, 
    'redirect' => 'dashboard.html'
]);
exit();
?>