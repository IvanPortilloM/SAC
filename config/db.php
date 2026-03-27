<?php
// config/db.php
require_once __DIR__ . '/env_loader.php';

// Cargamos el .env asumiendo que está en la raíz del proyecto (un nivel arriba de config)
loadEnv(__DIR__ . '/../.env');

// 1. SOLUCIÓN PHP: Forzar la Zona Horaria de Honduras (UTC-6)
// Esto arregla cualquier cálculo de hora que haga PHP internamente.
date_default_timezone_set('America/Tegucigalpa');

class Database {
    // Implementación de tipado estricto (PHP 7.4+)
    private string $host;
    private string $user;
    private string $pass;
    private string $namePortal;
    private string $nameEstado;

    public function __construct() {
        // Uso de coalescencia nula para evitar warnings si la variable no está definida
        $this->host = getenv('DB_HOST') ?: '';
        $this->user = getenv('DB_USER') ?: '';
        $this->pass = getenv('DB_PASS') ?: '';
        $this->namePortal = getenv('DB_NAME_PORTAL') ?: '';
        $this->nameEstado = getenv('DB_NAME_ESTADO') ?: '';
    }

    // Conexión a la BD de Usuarios (Portal)
    // El tipo de retorno ?mysqli indica que devuelve un objeto mysqli o null
    public function getPortalConnection(): ?mysqli {
        // Suprimimos el warning de mysqli con @ o manejamos la excepción silenciosamente
        // para mantener tu lógica de error_log intacta.
        mysqli_report(MYSQLI_REPORT_OFF); 
        
        $conn = new mysqli($this->host, $this->user, $this->pass, $this->namePortal);
        
        if ($conn->connect_error) {
            error_log("Error conexión Portal: " . $conn->connect_error);
            return null;
        }
        $conn->set_charset("utf8");
        
        // 2. SOLUCIÓN MYSQL: Sincronizar el reloj de la base de datos con Honduras (-06:00)
        // Esto asegura que cuando uses NOW() en tus consultas SQL, guarde la hora correcta.
        $conn->query("SET time_zone = '-06:00'");
        
        return $conn;
    }

    // Conexión a la BD Financiera (Estado de Cuenta)
    public function getFinancialConnection(): ?mysqli {
        mysqli_report(MYSQLI_REPORT_OFF);
        
        $conn = new mysqli($this->host, $this->user, $this->pass, $this->nameEstado);
        
        if ($conn->connect_error) {
            error_log("Error conexión Financiera: " . $conn->connect_error);
            return null;
        }
        $conn->set_charset("utf8");
        
        // También lo aplicamos aquí por precaución
        $conn->query("SET time_zone = '-06:00'");
        
        return $conn;
    }
}
?>