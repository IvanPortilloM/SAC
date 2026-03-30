<?php
// send_reset_link.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

if (file_exists(__DIR__ . '/config/env_loader.php')) {
    require_once __DIR__ . '/config/env_loader.php';
} else {
    error_log("Error Crítico: No se encuentra config/env_loader.php");
    die("Error interno del servidor.");
}

// Cargar variables de entorno (asume que .env está en la raíz)
loadEnv(__DIR__ . '/.env');

$dsn = "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME_PORTAL') . ";charset=utf8";
try {
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Error de BD en Recuperación: " . $e->getMessage());
    header("Location: forgot_password.html?status=error&message=" . urlencode("Error de conexión. Intente más tarde."));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Solución a las advertencias: Uso de coalescencia nula y forzado de tipo string
    $email = trim((string)($_POST['email'] ?? ''));
    $identity = trim((string)($_POST['identity_number'] ?? ''));

    // Validación para evitar procesar consultas si faltan datos
    if (empty($email) || empty($identity)) {
        header("Location: forgot_password.html?status=error&message=" . urlencode("Por favor, complete todos los campos obligatorios."));
        exit();
    }

    $stmt = $pdo->prepare("SELECT id, nombres FROM users WHERE email = ? AND identity_number = ?");
    $stmt->execute([$email, $identity]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $hashed_token = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
        $updateStmt->execute([$hashed_token, $expires, $user['id']]);

        // --- MEJORA: CREACIÓN DINÁMICA DE ENLACE ---
        // Construye la URL base automáticamente sin importar en qué dominio o carpeta esté el sistema
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        $baseDir = rtrim(dirname($_SERVER['REQUEST_URI']), '/');
        $reset_link = $protocol . $domainName . $baseDir . "/reset_password.html?token=" . $token;
        // ------------------------------------------

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = getenv('SMTP_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('SMTP_USER');
            $mail->Password   = getenv('SMTP_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = getenv('SMTP_PORT') ?: 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(getenv('SMTP_USER'), 'ADI - Grupo Granjas Marinas');
            $mail->addAddress($email, $user['nombres']);

            $mail->isHTML(true);
            $mail->Subject = 'Restablecer tu contraseña - ADI GGM';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333333;'>
                    <h2>Hola, {$user['nombres']}</h2>
                    <p>Has solicitado restablecer tu contraseña.</p>
                    <p>Haz clic en el siguiente enlace para crear una nueva clave:</p>
                    <p style='margin: 20px 0;'>
                        <a href='{$reset_link}' style='background-color: #007bff; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                            Restablecer Contraseña
                        </a>
                    </p>
                    <p>Este enlace expira en 30 minutos.</p>
                    <p><small style='color: #666;'>Si no fuiste tú, ignora este mensaje.</small></p>
                </div>
            ";

            $mail->send();
            header("Location: forgot_password.html?status=success&message=" . urlencode("Si los datos coinciden, recibirás un correo con las instrucciones."));
            exit();

        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
            header("Location: forgot_password.html?status=error&message=" . urlencode("Error al enviar el correo. Por favor, intenta de nuevo más tarde."));
            exit();
        }
    } else {
        header("Location: forgot_password.html?status=success&message=" . urlencode("Si los datos coinciden, recibirás un correo con las instrucciones."));
        exit();
    }
} else {
    header("Location: forgot_password.html");
    exit();
}
?>