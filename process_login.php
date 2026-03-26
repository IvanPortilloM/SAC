<?php
// process_login.php
session_start();
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$identity = $input['identity'] ?? '';
$password = $input['password'] ?? '';

if (empty($identity) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Por favor complete todos los campos.']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

if ($conn->connect_error) {
    // El error real se guarda en tu servidor, al usuario se le da uno genérico
    error_log("Error BD Login: " . $conn->connect_error);
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos.']);
    exit();
}

// Buscamos el usuario Y SU ROL
$sql = "SELECT id, password_hash, rol FROM users WHERE identity_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $identity);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Verificar Contraseña
    if (password_verify($password, $user['password_hash'])) {
        
        // --- MEJORA CRÍTICA DE SEGURIDAD ---
        // Generamos un nuevo ID de sesión al autenticar para evitar ataques de fijación
        session_regenerate_id(true);
        // -----------------------------------
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['rol'] = !empty($user['rol']) ? $user['rol'] : 'user'; 
        $_SESSION['fresh_login'] = true; 
        
        echo json_encode([
            'success' => true, 
            'redirect' => 'dashboard.html'
        ]);
    } else {
        // Anti-enumeración: Mismo mensaje de error genérico
        echo json_encode(['success' => false, 'error' => 'Credenciales incorrectas.']);
    }
} else {
    // Anti-enumeración: Mismo mensaje de error genérico
    echo json_encode(['success' => false, 'error' => 'Credenciales incorrectas.']);
}

$stmt->close();
$conn->close();
?>