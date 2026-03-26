<?php
// api/admin_create_notif.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// --- SEGURIDAD ACTIVADA ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '⛔ ACCESO DENEGADO: No eres Administrador.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$user_id = $input['user_id'] ?? null;
if($user_id === 'all' || $user_id === '') {
    $user_id = null;
}

$titulo = $input['titulo'] ?? 'Aviso';
$mensaje = $input['mensaje'] ?? '';

if(empty($mensaje)) {
    echo json_encode(['success' => false, 'error' => 'El mensaje no puede estar vacío.']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

if ($user_id === null) {
    // LÓGICA DE REPARTO INDIVIDUAL: Creamos una notificación por cada usuario
    $users = $conn->query("SELECT id FROM users");
    
    // Aquí están TODOS tus campos originales (fecha_creacion, tipo)
    $sql = "INSERT INTO notificaciones (user_id, titulo, mensaje, leido, fecha_creacion, tipo) 
            VALUES (?, ?, ?, 0, NOW(), 'info')";
    $stmt = $conn->prepare($sql);
    
    while($row = $users->fetch_assoc()) {
        $stmt->bind_param("iss", $row['id'], $titulo, $mensaje);
        $stmt->execute();
    }
    $stmt->close();

} else {
    // Lógica original para enviar a un solo usuario específico
    $sql = "INSERT INTO notificaciones (user_id, titulo, mensaje, leido, fecha_creacion, tipo) 
            VALUES (?, ?, ?, 0, NOW(), 'info')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $titulo, $mensaje);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Error BD: ' . $stmt->error]);
        exit();
    }
    $stmt->close();
}

echo json_encode(['success' => true]);
$conn->close();
?>