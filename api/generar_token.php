<?php
// api/generar_token.php
session_start();
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/db.php';   // db.php ya carga el .env por nosotros
csrf_require(); // CSRF: bloquea POST sin token válido

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$identidad = $data['Identidad'] ?? '';

if (empty($identidad)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Identidad requerida']));
}

$token = (string)rand(100000, 999999);

$db = new Database();
$conn = $db->getPortalConnection();

if (!$conn) {
    http_response_code(500);
    exit(json_encode(['error' => 'No se pudo generar el token. Intenta más tarde.']));
}

$stmt = $conn->prepare("INSERT INTO TokensWhatsApp (Identidad, Token, Usado) VALUES (?, ?, 0)");
$stmt->bind_param("ss", $identidad, $token);

if ($stmt->execute()) {
    echo json_encode(['token' => $token]);
} else {
    // No filtramos el detalle del error al cliente; lo registramos en el log.
    error_log("generar_token DB error: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo generar el token. Intenta más tarde.']);
}

$stmt->close();
$conn->close();
