<?php
// api/admin_create_faq.php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// 1. Verificar sesión y rol
if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '⛔ Acceso denegado.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// 2. Sanitización (Eliminación de HTML para evitar XSS de raíz)
$pregunta = isset($input['pregunta']) ? trim(strip_tags($input['pregunta'])) : '';
$respuesta = isset($input['respuesta']) ? trim(strip_tags($input['respuesta'])) : '';
// Por si en el futuro usas categorías, lo dejamos sanitizado
$categoria = isset($input['categoria']) ? trim(strip_tags($input['categoria'])) : 'General'; 

// 3. Validaciones de longitud e integridad
if (empty($pregunta) || empty($respuesta)) {
    echo json_encode(['success' => false, 'error' => 'La pregunta y la respuesta son obligatorias.']);
    exit();
}

if (strlen($pregunta) > 255) {
    echo json_encode(['success' => false, 'error' => 'La pregunta es muy larga (máx. 255 caracteres).']);
    exit();
}

if (strlen($respuesta) > 3000) {
    echo json_encode(['success' => false, 'error' => 'La respuesta es muy extensa (máx. 3000 caracteres).']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error interno de conexión.']);
    exit();
}

// 4. Inserción Segura
$sql = "INSERT INTO faqs (pregunta, respuesta, categoria) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $pregunta, $respuesta, $categoria);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Pregunta frecuente creada correctamente.']);
} else {
    error_log("Error creando FAQ: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Error al guardar la FAQ.']);
}

$stmt->close();
$conn->close();
?>