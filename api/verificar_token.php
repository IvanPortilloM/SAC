<?php
// api/verificar_token.php
// Lo consume el bot de WhatsApp (C#) por HTTP GET: intercambia un token por la
// identidad asociada y marca el token como usado. NO lleva CSRF (no es navegador).
require_once __DIR__ . '/../config/env_loader.php';

header('Content-Type: application/json');

// Credenciales desde .env (ya NO van hardcodeadas en el archivo).
loadEnv(__DIR__ . '/../.env');

$token = $_GET['token'] ?? '';

if (empty($token)) {
    exit(json_encode(['valido' => false]));
}

try {
    $host   = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME_PORTAL') ?: '';
    $user   = getenv('DB_USER') ?: '';
    $pass   = getenv('DB_PASS') ?: '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buscamos si el token existe y no ha sido usado.
    $stmt = $pdo->prepare("SELECT Identidad FROM TokensWhatsApp WHERE Token = ? AND Usado = 0");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Si es válido, lo marcamos como usado para que no se repita.
        $update = $pdo->prepare("UPDATE TokensWhatsApp SET Usado = 1 WHERE Token = ?");
        $update->execute([$token]);

        echo json_encode(['valido' => true, 'identidad' => $row['Identidad']]);
    } else {
        echo json_encode(['valido' => false]);
    }
} catch (PDOException $e) {
    error_log("verificar_token DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['valido' => false]);
}
