<?php
// config/db.php
require_once __DIR__ . '/env_loader.php';

// Cargamos el .env asumiendo que está en la raíz del proyecto (un nivel arriba de config)
loadEnv(__DIR__ . '/../.env');

// 1. SOLUCIÓN PHP: Forzar la Zona Horaria de Honduras (UTC-6)
// Esto arregla cualquier cálculo de hora que haga PHP internamente.
date_default_timezone_set('America/Tegucigalpa');

class Database {
    private $host;
    private $user;
    private $pass;
    private $namePortal;
    private $nameEstado;

    public function __construct() {
        $this->host = getenv('DB_HOST');
        $this->user = getenv('DB_USER');
        $this->pass = getenv('DB_PASS');
        $this->namePortal = getenv('DB_NAME_PORTAL');
        $this->nameEstado = getenv('DB_NAME_ESTADO');
    }

    // Conexión a la BD de Usuarios (Portal)
    public function getPortalConnection() {
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
    public function getFinancialConnection() {
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