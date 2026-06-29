<?php
// api/verificar_token.php
// Lo consume el bot de WhatsApp (C#) por HTTP GET: intercambia un token por la
// identidad asociada y lo marca como usado. NO lleva CSRF (no es un navegador).
require_once __DIR__ . '/../config/db.php';   // db.php ya carga el .env por nosotros

header('Content-Type: application/json');

$token = $_GET['token'] ?? '';

if (empty($token)) {
    exit(json_encode(['valido' => false]));
}

$db = new Database();
$conn = $db->getPortalConnection();

if (!$conn) {
    http_response_code(500);
    exit(json_encode(['valido' => false]));
}

// Buscamos si el token existe y no ha sido usado.
$stmt = $conn->prepare("SELECT Identidad FROM TokensWhatsApp WHERE Token = ? AND Usado = 0");
$stmt->bind_param("s", $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    // Si es válido, lo marcamos como usado para que no se repita.
    $upd = $conn->prepare("UPDATE TokensWhatsApp SET Usado = 1 WHERE Token = ?");
    $upd->bind_param("s", $token);
    $upd->execute();
    $upd->close();

    echo json_encode(['valido' => true, 'identidad' => $row['Identidad']]);
} else {
    echo json_encode(['valido' => false]);
}

$conn->close();
