<?php
// api/get_faqs.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Validar sesión (opcional según donde lo uses)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

// Traemos todas las activas ordenadas por el campo 'orden' que definimos en la BD
$sql = "SELECT id, pregunta, respuesta, categoria 
        FROM faqs 
        WHERE activo = 1 
        ORDER BY orden ASC, id ASC";

$result = $conn->query($sql);

$faqs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $faqs[] = $row;
    }
}

echo json_encode(['success' => true, 'data' => $faqs]);
$conn->close();
?>