<?php
// api/get_excedentes.php
session_start();
require_once __DIR__ . '/../config/db.php';

// 1. OBTENER IDENTIDAD (Compatible con el nuevo Login)
$identidad = $_SESSION['identity_number'] ?? null;

if (!$identidad && isset($_SESSION['user_id'])) {
    // Si no está la identidad en sesión, la buscamos usando el ID de usuario
    $db = new Database();
    $conn_portal = $db->getPortalConnection();
    
    if ($conn_portal) {
        $stmt = $conn_portal->prepare("SELECT identity_number FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($fetched_id);
        if ($stmt->fetch()) {
            $identidad = $fetched_id;
            $_SESSION['identity_number'] = $identidad; // Guardar para la próxima
        }
        $stmt->close();
        $conn_portal->close();
    }
}

// 2. Validaciones
if (!$identidad) {
    http_response_code(401);
    echo "<h2 class='text-red-500 font-medium'>Error: Sesión no válida (Identidad no encontrada).</h2>";
    exit();
}

if (!isset($_POST['anio'])) {
    http_response_code(400);
    echo "<h2 class='text-red-500 font-medium'>Error: Año no especificado.</h2>";
    exit();
}

// 3. Conexión a DB SAC
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME_SAC') ?: 'portal_sac'; 

$conn = new mysqli($host, $user, $pass, $name);

if ($conn->connect_error) {
    http_response_code(500);
    die("<h2 class='text-red-500 font-medium'>Error de conexión a SAC.</h2>");
}
$conn->set_charset("utf8");

$anio = $_POST['anio'];

$stmt = $conn->prepare("SELECT Nombre, Total, Porc_Capitalizar, Anio FROM SAC_Excedentes WHERE Identidad = ? AND Anio = ?");
$stmt->bind_param("si", $identidad, $anio);

if ($stmt->execute()) {
    $stmt->bind_result($nombre, $total, $porc_cap, $anioSQL);
    if ($stmt->fetch()) {
        $cant_cap = ($porc_cap / 100) * $total;
        $cant_entregar = $total - $cant_cap;
        
        // DISEÑO APLICADO AQUÍ
        echo "<table class='styled-table'>";
        echo "<tr><td class='text-gray-600'><strong>Nombre:</strong></td><td class='text-gray-800'>$nombre</td></tr>";
        echo "<tr><td class='text-gray-600'><strong>Total excedentes:</strong></td><td class='text-green-600 text-lg'><b>L. " . number_format($total, 2) . "</b></td></tr>";
        echo "<tr><td class='text-gray-600'><strong>A capitalizar ($porc_cap%):</strong></td><td class='text-gray-800'>L. " . number_format($cant_cap, 2) . "</td></tr>";
        echo "<tr><td class='text-gray-600'><strong>A entregar:</strong></td><td class='text-gray-800'>L. " . number_format($cant_entregar, 2) . "</td></tr>";
        echo "<tr><td class='text-gray-600'><strong>ISR (10%):</strong></td><td class='text-red-500 font-bold'>- L. " . number_format($cant_entregar * 0.1, 2) . "</td></tr>";
        echo "<tr><td class='text-gray-600'><strong>Neto a recibir:</strong></td><td class='text-blue-700 text-xl'><b>L. " . number_format($cant_entregar * 0.9, 2) . "</b></td></tr>";
        echo "</table>";
    } else {
        echo "<h2 class='text-gray-500 font-medium'>No se encontraron datos para el año $anio.</h2>";
    }
    $stmt->close();
} else {
    echo "<h2 class='text-red-500 font-medium'>Error en la consulta.</h2>";
}
$conn->close();
?>