<?php
// api/get_notifications.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión expirada']);
    exit();
}

$user_id = $_SESSION['user_id'];
$db = new Database();
$conn = $db->getPortalConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

// 1. Obtener Notificaciones (Personales + Globales)
// Limitamos a las últimas 20 para no saturar
$sql = "SELECT id, titulo, mensaje, leido, fecha_creacion, tipo 
        FROM notificaciones 
        WHERE (user_id = ? OR user_id IS NULL)
        ORDER BY fecha_creacion DESC 
        LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notificaciones = [];
$sin_leer = 0;

while ($row = $result->fetch_assoc()) {
    // Convertimos el 0/1 de la BD a booleano real para JS
    $row['leido'] = (bool)$row['leido'];
    
    // Contamos las no leídas
    if (!$row['leido']) {
        $sin_leer++;
    }
    
    $notificaciones[] = $row;
}

// 2. Extra: Una pequeña acción para marcar como leídas (opcional aquí o en otro archivo)
// Por ahora solo las devolvemos.

echo json_encode([
    'success' => true, 
    'data' => $notificaciones,
    'unread_count' => $sin_leer
]);

$stmt->close();
$conn->close();
?>