<?php
// logout.php
session_start();

// 1. Vaciar todas las variables de sesión en memoria
$_SESSION = array();

// 2. Matar la "Cookie Zombi" en el navegador del usuario
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destruir la sesión en el servidor
session_destroy();

// 4. Redirección Relativa (A prueba de cambios de servidor)
$redirect_url = 'login.html';

// Mantener el mensaje de inactividad si fue expulsado por tiempo
if (isset($_GET['status'])) {
    $status = urlencode($_GET['status']);
    $redirect_url .= '?status=' . $status;
}

header("Location: " . $redirect_url);
exit();
?>