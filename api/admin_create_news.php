<?php
// api/admin_create_news.php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// 1. Verificar sesión y rol estricto
if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '⛔ Acceso denegado. Se requiere nivel de administrador.']);
    exit();
}

// 2. Recibir y decodificar JSON
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// 3. Sanitización (Elimina TODO el HTML para guardar solo texto plano)
$titulo = isset($input['titulo']) ? trim(strip_tags($input['titulo'])) : '';
$contenido = isset($input['contenido']) ? trim(strip_tags($input['contenido'])) : '';
$imagen_url = isset($input['imagen_url']) ? trim(strip_tags($input['imagen_url'])) : '';
$es_importante = !empty($input['es_importante']) ? 1 : 0;

// 4. Validaciones
if (empty($titulo) || empty($contenido)) {
    echo json_encode(['success' => false, 'error' => 'El título y el contenido son obligatorios.']);
    exit();
}

if (strlen($titulo) > 255) {
    echo json_encode(['success' => false, 'error' => 'El título es demasiado largo (máximo 255 caracteres).']);
    exit();
}

if (strlen($contenido) > 5000) {
    echo json_encode(['success' => false, 'error' => 'El contenido supera el límite permitido.']);
    exit();
}

// Validar URL de la imagen si existe
if (!empty($imagen_url) && !filter_var($imagen_url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'error' => 'La URL de la imagen no tiene un formato válido.']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
    exit();
}

// 5. Inserción Segura
$sql = "INSERT INTO comunicados (titulo, contenido, imagen_url, es_importante, fecha_publicacion) VALUES (?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $titulo, $contenido, $imagen_url, $es_importante);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Noticia publicada correctamente.']);
} else {
    error_log("Error creando noticia: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos.']);
}

$stmt->close();
$conn->close();
?>