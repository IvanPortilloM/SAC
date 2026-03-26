<?php
// api/request_loan.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida o expirada.']);
    exit();
}

$now = time();
if (isset($_SESSION['last_loan_request']) && ($now - $_SESSION['last_loan_request']) < 60) {
    echo json_encode(['success' => false, 'error' => 'Por favor, espera un minuto antes de enviar otra solicitud.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Datos no recibidos.']);
    exit();
}

$monto  = floatval($input['monto'] ?? 0);
$plazo  = intval($input['plazo'] ?? 0);
$cuota  = floatval($input['cuota'] ?? 0);
$tasa   = floatval($input['tasa'] ?? 0);
$motivo = isset($input['motivo']) ? trim(strip_tags($input['motivo'])) : 'OTRAS NECESIDADES';
$tipo   = isset($input['tipo_solicitud']) ? trim(strip_tags($input['tipo_solicitud'])) : 'PRÉSTAMO';

// Recibir IDs de los préstamos a refinanciar y revertir cualquier escape de HTML
$raw_ids = isset($input['refinanciar_ids']) && is_array($input['refinanciar_ids']) ? $input['refinanciar_ids'] : [];
$ids = [];
foreach ($raw_ids as $id) {
    $ids[] = htmlspecialchars_decode(trim(strip_tags($id)), ENT_QUOTES); 
}

$prestamos_refinanciar_str = implode(',', $ids);
$es_refinanciamiento = !empty($ids) ? 1 : 0;

if ($monto < 2000) {
    echo json_encode(['success' => false, 'error' => 'El monto mínimo es de L 2,000.00']);
    exit();
}

$db = new Database();
$conn_portal = $db->getPortalConnection();     
$conn_financiera = $db->getFinancialConnection(); 

if (!$conn_portal || !$conn_financiera) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a bases de datos.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt_user = $conn_portal->prepare("SELECT identity_number FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user_data) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
    exit();
}

$identidad = trim($user_data['identity_number']);

// =========================================================================
// CORRECCIÓN: LÓGICA DE CAPACIDAD EXACTAMENTE IGUAL AL DASHBOARD
// =========================================================================
$sql_ahorros = "SELECT Grupo, Des_Grupo, Principal, saldo, Operacion, Descripci FROM estado_cuenta WHERE TRIM(Asociado) = ?";
$stmt_ahorros = $conn_financiera->prepare($sql_ahorros);
$stmt_ahorros->bind_param("s", $identidad);
$stmt_ahorros->execute();
$res_ahorros = $stmt_ahorros->get_result();

$totalPrincipalPatrimoniales = 0;
$totalSaldoCreditos = 0;
$saldo_refinanciado = 0;

while ($row = $res_ahorros->fetch_assoc()) {
    // Usar la descripción del grupo para identificar correctamente (Igual que get_user_data.php)
    $nombreRaw = !empty($row['Des_Grupo']) ? $row['Des_Grupo'] : ($row['Grupo'] ?? 'OTRO');
    $nombreGrupo = strtoupper(trim($nombreRaw));
    
    if ($nombreGrupo === 'PATRIMONIALES') {
        $totalPrincipalPatrimoniales += floatval($row['Principal']);
    } elseif (strpos($nombreGrupo, 'CREDITOS') !== false || $nombreGrupo === 'CRÉDITOS') {
        $totalSaldoCreditos += floatval($row['saldo']);
        
        // Sumar si la Operación O la Descripción coinciden con las seleccionadas para refinanciar
        if ($es_refinanciamiento) {
            $op_db = trim((string)$row['Operacion']);
            $desc_db = trim((string)$row['Descripci']);
            if (in_array($op_db, $ids) || in_array($desc_db, $ids)) {
                $saldo_refinanciado += floatval($row['saldo']);
            }
        }
    }
}
$stmt_ahorros->close();

$patrimonioNeto = $totalPrincipalPatrimoniales - $totalSaldoCreditos;
$credito_disponible_base = $patrimonioNeto > 0 ? $patrimonioNeto : 0;
$capacidad_total = $credito_disponible_base + $saldo_refinanciado;

if ($monto > ($capacidad_total + 1)) {
    echo json_encode(['success' => false, 'error' => 'El monto solicitado (L '.number_format($monto, 2).') supera tu capacidad máxima en el sistema.']);
    exit();
}

$monto_liquido = $monto - $saldo_refinanciado;

if ($es_refinanciamiento && $monto_liquido < 2000) {
    echo json_encode([
        'success' => false, 
        'error' => "El desembolso líquido (L " . number_format($monto_liquido, 2) . ") es menor a L 2,000.00. Aumente el monto solicitado o revise sus cancelaciones."
    ]);
    exit();
}

$stmt_check = $conn_portal->prepare("SELECT id FROM solicitudes_prestamo WHERE user_id = ? AND estado = 'pendiente'");
$stmt_check->bind_param("i", $user_id);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Ya tienes una solicitud en proceso.']);
    $stmt_check->close();
    $conn_portal->close();
    $conn_financiera->close();
    exit();
}
$stmt_check->close();

$_SESSION['last_loan_request'] = $now;

$sql = "INSERT INTO solicitudes_prestamo 
        (user_id, monto, tasa, plazo, cuota_estimada, motivo, tipo_solicitud, prestamos_refinanciar, es_refinanciamiento, saldo_refinanciado, monto_liquido) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn_portal->prepare($sql);

if ($stmt) {
    $stmt->bind_param("iddidsssidd", 
        $user_id, $monto, $tasa, $plazo, $cuota, $motivo, $tipo, 
        $prestamos_refinanciar_str, $es_refinanciamiento, $saldo_refinanciado, $monto_liquido
    );
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Solicitud enviada correctamente.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar solicitud.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Error en la consulta interna.']);
}

$conn_portal->close();
$conn_financiera->close();
?>