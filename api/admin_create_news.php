<?php
// api/admin_create_news.php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '⛔ Acceso denegado. Se requiere nivel de administrador.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// LISTA BLANCA DE HTML: Permite formato pero bloquea scripts maliciosos
$etiquetas_permitidas = '<p><br><b><strong><i><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><img><div><span>';

// Al título le quitamos el HTML, al contenido se lo permitimos (filtrado)
$titulo = isset($input['titulo']) ? trim(strip_tags($input['titulo'])) : '';
$contenido = isset($input['contenido']) ? trim(strip_tags($input['contenido'], $etiquetas_permitidas)) : '';
$imagen_url = isset($input['imagen_url']) ? trim(strip_tags($input['imagen_url'])) : '';
$es_importante = !empty($input['es_importante']) ? 1 : 0;

if (empty($titulo) || empty($contenido)) {
    echo json_encode(['success' => false, 'error' => 'El título y el contenido son obligatorios.']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
    exit();
}

$sql = "INSERT INTO comunicados (titulo, contenido, imagen_url, es_importante, fecha_publicacion) VALUES (?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $titulo, $contenido, $imagen_url, $es_importante);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al guardar en base de datos.']);
}

$stmt->close();
$conn->close();
?>