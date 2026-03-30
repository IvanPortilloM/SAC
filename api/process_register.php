<?php
// api/process_register.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// 1. Recibir y sanitizar datos (A prueba de inyecciones)
$identity = preg_replace('/[^0-9]/', '', (string)($input['identity'] ?? '')); 
$nombres = trim(strip_tags((string)($input['nombres'] ?? '')));
$apellidos = trim(strip_tags((string)($input['apellidos'] ?? '')));
$password = (string)($input['password'] ?? '');
$confirm_password = (string)($input['confirm_password'] ?? '');
$email = trim((string)($input['email'] ?? ''));
$face_verified = isset($input['face_verified']) ? (int)$input['face_verified'] : 0;

// 2. Validaciones Críticas del Negocio
if (empty($identity) || empty($nombres) || empty($apellidos) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Por favor complete todos los campos obligatorios.']);
    exit();
}

if (strlen($identity) !== 13) {
    echo json_encode(['success' => false, 'error' => 'El número de identidad debe tener exactamente 13 dígitos.']);
    exit();
}

if (!empty($confirm_password) && $password !== $confirm_password) {
    echo json_encode(['success' => false, 'error' => 'Las contraseñas no coinciden.']);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres por seguridad.']);
    exit();
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'El formato del correo electrónico no es válido.']);
    exit();
}

// 3. Conexión a Base de Datos
$db = new Database();
$conn = $db->getPortalConnection();

// Corrección: Validar también si $conn es null (por nuestro refactor de db.php)
if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión interno.']);
    exit();
}

// 4. Verificar duplicados (Identidad o Correo)
$sql_check = "SELECT id FROM users WHERE identity_number = ?";
if (!empty($email)) {
    $sql_check .= " OR email = ?";
    $stmt_check = $conn->prepare($sql_check);
    if ($stmt_check) {
        $stmt_check->bind_param("ss", $identity, $email);
    }
} else {
    $stmt_check = $conn->prepare($sql_check);
    if ($stmt_check) {
        $stmt_check->bind_param("s", $identity);
    }
}

// Corrección: Evitar Fatal Error si la preparación falla
if (!$stmt_check) {
    echo json_encode(['success' => false, 'error' => 'Error interno al verificar usuario.']);
    $conn->close();
    exit();
}

$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check && $result_check->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Ya existe un usuario con esa identidad o correo electrónico.']);
    $stmt_check->close();
    $conn->close();
    exit();
}
$stmt_check->close();

// 5. Inserción Segura
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$rol = 'user'; // Forzar que todo registro nuevo sea un usuario normal

$sql_insert = "INSERT INTO users (identity_number, nombres, apellidos, password_hash, email, face_verified, rol) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt_insert = $conn->prepare($sql_insert);

if (!$stmt_insert) {
    echo json_encode(['success' => false, 'error' => 'Error interno al preparar el registro.']);
    $conn->close();
    exit();
}

$stmt_insert->bind_param("sssssis", $identity, $nombres, $apellidos, $hashed_password, $email, $face_verified, $rol);

if ($stmt_insert->execute()) {
    echo json_encode(['success' => true, 'message' => 'Usuario registrado exitosamente.']);
} else {
    error_log("Error registrando usuario: " . $stmt_insert->error);
    echo json_encode(['success' => false, 'error' => 'No se pudo crear el usuario. Inténtalo más tarde.']);
}

$stmt_insert->close();
$conn->close();
?>