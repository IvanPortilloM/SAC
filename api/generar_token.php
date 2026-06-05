<?php
// api/generar_token.php
session_start();
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/env_loader.php';
csrf_require(); // CSRF: bloquea POST sin token válido

header('Content-Type: application/json');

// Credenciales desde .env (ya NO van hardcodeadas en el archivo).
loadEnv(__DIR__ . '/../.env');

$data = json_decode(file_get_contents('php://input'), true);
$identidad = $data['Identidad'] ?? '';

if (empty($identidad)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Identidad requerida']));
}

$token = (string)rand(100000, 999999);

try {
    $host   = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME_PORTAL') ?: '';
    $user   = getenv('DB_USER') ?: '';
    $pass   = getenv('DB_PASS') ?: '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("INSERT INTO TokensWhatsApp (Identidad, Token, Usado) VALUES (?, ?, 0)");
    $stmt->execute([$identidad, $token]);

    echo json_encode(['token' => $token]);
} catch (PDOException $e) {
    // No filtramos el detalle del error al cliente; lo registramos en el log.
    error_log("generar_token DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo generar el token. Intenta más tarde.']);
}
