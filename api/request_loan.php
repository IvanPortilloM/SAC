<?php
// api/request_loan.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Validaciones iniciales sin base de datos (rápidas y baratas)
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
$motivo = isset($input['motivo']) ? trim(strip_tags((string)$input['motivo'])) : 'OTRAS NECESIDADES';
$tipo   = isset($input['tipo_solicitud']) ? trim(strip_tags((string)$input['tipo_solicitud'])) : 'PRÉSTAMO';

if ($monto < 2000) {
    echo json_encode(['success' => false, 'error' => 'El monto mínimo es de L 2,000.00']);
    exit();
}

// Recibir IDs de los préstamos a refinanciar
$raw_ids = isset($input['refinanciar_ids']) && is_array($input['refinanciar_ids']) ? $input['refinanciar_ids'] : [];
$ids = [];
foreach ($raw_ids as $id) {
    $ids[] = htmlspecialchars_decode(trim(strip_tags((string)$id)), ENT_QUOTES); 
}

$prestamos_refinanciar_str = implode(',', $ids);
$es_refinanciamiento = !empty($ids) ? 1 : 0;

// 2. Conexiones a Bases de Datos
$db = new Database();
$conn_portal = $db->getPortalConnection();     
$conn_financiera = $db->getFinancialConnection(); 

if (!$conn_portal || !$conn_financiera) {
    if ($conn_portal) $conn_portal->close();
    if ($conn_financiera) $conn_financiera->close();
    echo json_encode(['success' => false, 'error' => 'Error de conexión a bases de datos.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// 3. Verificación de solicitud pendiente (Movido hacia arriba por eficiencia)
// Si ya tiene una solicitud pendiente, no perdemos tiempo consultando todo el estado de cuenta
$stmt_check = $conn_portal->prepare("SELECT id FROM solicitudes_prestamo WHERE user_id = ? AND estado = 'pendiente'");
$stmt_check->bind_param("i", $user_id);
$stmt_check->execute();
$stmt_check->store_result();
$has_pending = $stmt_check->num_rows > 0;
$stmt_check->close();

if ($has_pending) {
    echo json_encode(['success' => false, 'error' => 'Ya tienes una solicitud en proceso.']);
    $conn_portal->close();
    $conn_financiera->close();
    exit();
}

// 4. Obtener identidad del usuario
$stmt_user = $conn_portal->prepare("SELECT identity_number FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user_data) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
    $conn_portal->close();
    $conn_financiera->close();
    exit();
}

$identidad = trim($user_data['identity_number']);

// =========================================================================
// CÁLCULO DE CAPACIDAD (Lógica de negocio intacta)
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
    $nombreRaw = !empty($row['Des_Grupo']) ? $row['Des_Grupo'] : ($row['Grupo'] ?? 'OTRO');
    $nombreGrupo = strtoupper(trim($nombreRaw));
    
    if ($nombreGrupo === 'PATRIMONIALES') {
        $totalPrincipalPatrimoniales += floatval($row['Principal']);
    } elseif (strpos($nombreGrupo, 'CREDITOS') !== false || $nombreGrupo === 'CRÉDITOS') {
        $totalSaldoCreditos += floatval($row['saldo']);
        
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

// 5. Validaciones de negocio con cierre de recursos
if ($monto > ($capacidad_total + 1)) {
    echo json_encode(['success' => false, 'error' => 'El monto solicitado (L '.number_format($monto, 2).') supera tu capacidad máxima en el sistema.']);
    $conn_portal->close();
    $conn_financiera->close();
    exit();
}

$monto_liquido = $monto - $saldo_refinanciado;

if ($es_refinanciamiento && $monto_liquido < 2000) {
    echo json_encode([
        'success' => false, 
        'error' => "El desembolso líquido (L " . number_format($monto_liquido, 2) . ") es menor a L 2,000.00. Aumente el monto solicitado o revise sus cancelaciones."
    ]);
    $conn_portal->close();
    $conn_financiera->close();
    exit();
}

// 6. Inserción final
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
        // En caso de fallar el query, es buena práctica ocultar el error SQL crudo al usuario
        error_log("Error insertando préstamo: " . $stmt->error);
        echo json_encode(['success' => false, 'error' => 'Error al guardar solicitud. Comuníquese con soporte.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Error en la consulta interna.']);
}

$conn_portal->close();
$conn_financiera->close();
?>