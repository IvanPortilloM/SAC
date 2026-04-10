<?php
// api/admin_create_faq.php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '⛔ Acceso denegado.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$etiquetas_permitidas = '<p><br><b><strong><i><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><img><div><span>';

$categoria_id = isset($input['categoria_id']) ? intval($input['categoria_id']) : 0;
$pregunta = isset($input['pregunta']) ? trim(strip_tags($input['pregunta'])) : '';
$respuesta = isset($input['respuesta']) ? trim(strip_tags($input['respuesta'], $etiquetas_permitidas)) : '';

if (empty($pregunta) || empty($respuesta)) {
    echo json_encode(['success' => false, 'error' => 'Pregunta y respuesta son obligatorias.']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

$sql = "INSERT INTO faqs (categoria_id, pregunta, respuesta) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $categoria_id, $pregunta, $respuesta);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al guardar en base de datos.']);
}

$stmt->close();
$conn->close();
?>