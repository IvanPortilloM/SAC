<?php
// api/get_user_data.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Validar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado.']);
    exit();
}

$now = time();
$force_refresh = false;

// 2. Control de Refresh (Rate Limiting Anti-Spam)
if (isset($_SESSION['fresh_login']) && $_SESSION['fresh_login'] === true) {
    $force_refresh = true;
    unset($_SESSION['fresh_login']); 
} elseif (isset($_GET['force_refresh']) && $_GET['force_refresh'] === 'true') {
    if (isset($_SESSION['last_refresh']) && ($now - $_SESSION['last_refresh']) < 15) {
        $force_refresh = false; 
    } else {
        $force_refresh = true;
        $_SESSION['last_refresh'] = $now;
    }
}

// 3. Sistema de Caché
if (!$force_refresh && isset($_SESSION['dashboard_cache'])) {
    echo json_encode($_SESSION['dashboard_cache']);
    exit();
}

$db = new Database();
$conn_portal = $db->getPortalConnection();

if (!$conn_portal || $conn_portal->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de sistema (Portal).']);
    exit();
}

// Obtener datos de usuario
$user_id = $_SESSION['user_id'];
$sql = "SELECT id, identity_number, email, nombres, apellidos, fecha_nacimiento, telefono, direccion, rol FROM users WHERE id = ?";
$stmt_user = $conn_portal->prepare($sql);

if (!$stmt_user) {
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor (Portal).']);
    $conn_portal->close();
    exit();
}

$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if (!$result_user) {
    echo json_encode(['success' => false, 'error' => 'Error interno al consultar datos del usuario.']);
    $stmt_user->close();
    $conn_portal->close();
    exit();
}

$user_data_portal = $result_user->fetch_assoc();
$stmt_user->close();
$conn_portal->close();

if (!$user_data_portal) {
    echo json_encode(['success' => false, 'error' => 'Datos de usuario no encontrados.']);
    exit();
}

// Inicia conexión financiera (el portal ya está cerrado, buen manejo de memoria)
$conn_estado = $db->getFinancialConnection();
if (!$conn_estado || $conn_estado->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de sistema (Financiero).']);
    exit();
}

$identidad = trim((string)$user_data_portal['identity_number']);

// =========================================================
// LÓGICA DE ACTUALIZACIÓN
// =========================================================
if ($force_refresh) {
    unset($_SESSION['dashboard_cache']);

    // Uso de Prepared Statements protegidos contra fallos
    $stmt_del1 = $conn_estado->prepare("DELETE FROM estado_cuenta WHERE Asociado = ?");
    if ($stmt_del1) {
        $stmt_del1->bind_param("s", $identidad);
        $stmt_del1->execute();
        $stmt_del1->close();
    }

    $stmt_del2 = $conn_estado->prepare("DELETE FROM peticiones_estado WHERE identidad = ?");
    if ($stmt_del2) {
        $stmt_del2->bind_param("s", $identidad);
        $stmt_del2->execute();
        $stmt_del2->close();
    }
    
    $stmt_insert = $conn_estado->prepare("INSERT INTO peticiones_estado (identidad, estado) VALUES (?, 'pendiente')");
    if ($stmt_insert) {
        $stmt_insert->bind_param("s", $identidad);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    
    $response = [
        'success' => true, 
        'status' => 'pending_data', 
        'message' => 'Actualizando información...', 
        'user_data' => $user_data_portal
    ];
    echo json_encode($response);
    $conn_estado->close();
    exit();
}

// =========================================================
// LÓGICA DE LECTURA NORMAL
// =========================================================
$status_code = 'pending_data';
$status_message = 'Procesando solicitud...';
$financial_data = [];

$peticion_query = $conn_estado->prepare("SELECT estado, fecha_procesado FROM peticiones_estado WHERE identidad = ? ORDER BY id DESC LIMIT 1");
if ($peticion_query) {
    $peticion_query->bind_param("s", $identidad);
    $peticion_query->execute();
    $peticion = $peticion_query->get_result()->fetch_assoc();
    $peticion_query->close();
} else {
    $peticion = null;
}

if (!$peticion) {
    $stmt_insert = $conn_estado->prepare("INSERT INTO peticiones_estado (identidad, estado) VALUES (?, 'pendiente')");
    if ($stmt_insert) {
        $stmt_insert->bind_param("s", $identidad);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    $status_message = 'Solicitud inicial enviada.';
} elseif (isset($peticion['estado']) && $peticion['estado'] === 'completado') {
    
    $sql_estado = "SELECT Grupo, Des_Grupo, Principal, saldo, Cuota, Operacion, Descripci, Pagos, N_Cuotas 
                   FROM estado_cuenta 
                   WHERE Asociado = ?";
    $stmt_estado = $conn_estado->prepare($sql_estado);
    
    if ($stmt_estado) {
        $stmt_estado->bind_param("s", $identidad);
        $stmt_estado->execute();
        $estadoCuenta_result = $stmt_estado->get_result();
        
        if ($estadoCuenta_result && $estadoCuenta_result->num_rows > 0) {
            $status_code = 'success';
            $status_message = 'Datos cargados con éxito.';
            
            $totalPrincipalPatrimoniales = 0;
            $totalAhorrosVoluntarios = 0;
            $totalSaldoCreditos = 0;
            $grupos = [];
            
            while ($fila = $estadoCuenta_result->fetch_assoc()) {
                $nombreRaw = !empty($fila['Des_Grupo']) ? $fila['Des_Grupo'] : ($fila['Grupo'] ?? 'SIN_GRUPO');
                $grupoKey = $fila['Grupo'] ?? 'OTRO';
                
                if (!isset($grupos[$grupoKey])) {
                    $grupos[$grupoKey] = ['nombre' => $nombreRaw, 'registros' => [], 'subPrincipal' => 0, 'subSaldo' => 0, 'subCuota' => 0];
                }
                $grupos[$grupoKey]['registros'][] = $fila;
                $grupos[$grupoKey]['subPrincipal'] += $fila['Principal'];
                $grupos[$grupoKey]['subSaldo'] += $fila['saldo'];
                $grupos[$grupoKey]['subCuota'] += $fila['Cuota'];
            }
            
            foreach ($grupos as $grupo) {
                $nombreGrupo = strtoupper(trim($grupo['nombre']));
                if ($nombreGrupo === "PATRIMONIALES") $totalPrincipalPatrimoniales = $grupo['subPrincipal'];
                elseif ($nombreGrupo === "AHORROS  VOLUNTARIOS") $totalAhorrosVoluntarios = $grupo['subPrincipal'];
                elseif ($nombreGrupo === "CREDITOS") $totalSaldoCreditos = $grupo['subSaldo'];
            }
            
            $patrimonioNeto = $totalPrincipalPatrimoniales - $totalSaldoCreditos;
            $creditoDisponible = $patrimonioNeto > 0 ? $patrimonioNeto : 0;

            $financial_data = [
                'total_ahorros' => $totalPrincipalPatrimoniales,
                'total_ahorros_voluntarios' => $totalAhorrosVoluntarios,
                'total_creditos' => $totalSaldoCreditos,
                'credito_disponible' => $creditoDisponible, 
                'detalle_grupos' => array_values($grupos),
                'fecha_procesado' => !empty($peticion['fecha_procesado']) ? date('d-m-Y H:i:s', strtotime($peticion['fecha_procesado'])) : null
            ];
        } else {
            $status_code = 'no_financial_records';
            $status_message = 'No se encontraron registros financieros.';
            $financial_data['fecha_procesado'] = !empty($peticion['fecha_procesado']) ? date('d-m-Y H:i:s', strtotime($peticion['fecha_procesado'])) : null;
        }
        $stmt_estado->close();
    } else {
        $status_code = 'error';
        $status_message = 'Error al leer el estado de cuenta.';
    }
} else {
    // Caso: el estado es 'pendiente' o similar, y se conserva el status_code 'pending_data' definido arriba.
    $financial_data['fecha_procesado'] = null;
}

$final_user_data = array_merge($user_data_portal, $financial_data);

$response = [
    'success' => true, 
    'status' => $status_code, 
    'message' => $status_message, 
    'user_data' => $final_user_data
];

if ($status_code === 'success') {
    $_SESSION['dashboard_cache'] = $response;
}

$conn_estado->close();
echo json_encode($response);
?>