<?php
// api/admin_delete_news.php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '⛔ Acceso denegado.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de noticia inválido.']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

// Borrado lógico: Establece activo = 0
$sql = "UPDATE comunicados SET activo = 0 WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al eliminar en base de datos.']);
}

$stmt->close();
$conn->close();
?>