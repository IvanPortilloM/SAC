<?php
// api/get_rifa.php
session_start();
require_once __DIR__ . '/../config/db.php'; 

// 1. OBTENER IDENTIDAD (Compatible con el nuevo Login)
$identidad = $_SESSION['identity_number'] ?? null;

if (!$identidad && isset($_SESSION['user_id'])) {
    $db = new Database();
    $conn_portal = $db->getPortalConnection();
    
    if ($conn_portal) {
        $stmt = $conn_portal->prepare("SELECT identity_number FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($fetched_id);
        if ($stmt->fetch()) {
            $identidad = $fetched_id;
            $_SESSION['identity_number'] = $identidad;
        }
        $stmt->close();
        $conn_portal->close();
    }
}

if (!$identidad) {
    http_response_code(401);
    echo "<h2 class='text-red-500 font-medium'>Sesión no válida.</h2>";
    exit();
}

// 2. Conexión SAC
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME_SAC') ?: 'portal_sac';

$conn = new mysqli($host, $user, $pass, $name);

if ($conn->connect_error) {
    http_response_code(500);
    die("<h2 class='text-red-500 font-medium'>Error interno.</h2>");
}
$conn->set_charset("utf8");

$anio = $_POST['anio'] ?? '';

$stmt = $conn->prepare("SELECT `Nombre`, `Sucursal`, `Numeros` FROM `SAC_Rifa` WHERE Identidad = ? AND Anio = ?");
$stmt->bind_param("si", $identidad, $anio);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $registros = $result->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($registros)) {
        $nombre = htmlspecialchars($registros[0]['Nombre']);
        $sucursal = htmlspecialchars($registros[0]['Sucursal']);

        // DISEÑO APLICADO AQUÍ
        echo "<div class='bg-gray-50 border border-gray-200 rounded-2xl p-6 text-center max-w-2xl mx-auto shadow-sm'>";
        echo "<h3 class='text-2xl font-bold text-gray-800 mb-2'>Boletos Rifa $anio</h3>";
        echo "<div class='text-gray-500 text-sm mb-6 pb-4 border-b border-gray-300'><span class='font-semibold text-gray-700'>$nombre</span> | <span>$sucursal</span></div>";
        echo "<div class='flex flex-wrap gap-4 justify-center'>";
        
        foreach ($registros as $registro) {
            $numero = str_pad(htmlspecialchars($registro['Numeros']), 5, '0', STR_PAD_LEFT);
            echo "<div class='bg-white border-2 border-blue-500 text-blue-700 text-2xl font-bold py-2 px-6 rounded-lg shadow-md tracking-widest'>$numero</div>";
        }
        
        echo "</div></div>";
    } else {
        echo "<h2 class='text-gray-500 font-medium'>No hay números de rifa para este año.</h2>";
    }
    $stmt->close();
}
$conn->close();
?>