<?php
// api/get_news.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Si quieres que las noticias se vean SIN iniciar sesión (en el login), comenta estas líneas de validación.
if (!isset($_SESSION['user_id'])) {
    // Por seguridad, si es para el Dashboard interno, mejor valida sesión.
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$db = new Database();
$conn = $db->getPortalConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

// Lógica: Traer noticias activas, que ya se publicaron y que NO han expirado.
// Orden: Primero las importantes (1), luego las más recientes.
$sql = "SELECT id, titulo, contenido, imagen_url, fecha_publicacion, es_importante 
        FROM comunicados 
        WHERE activo = 1 
        AND fecha_publicacion <= NOW() 
        AND (fecha_expiracion IS NULL OR fecha_expiracion >= NOW())
        ORDER BY es_importante DESC, fecha_publicacion DESC";

$result = $conn->query($sql);

$noticias = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $noticias[] = $row;
    }
}

echo json_encode(['success' => true, 'data' => $noticias]);
$conn->close();
?>