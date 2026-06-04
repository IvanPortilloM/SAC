<?php
// config/csrf.php
// Protección CSRF mediante "synchronizer token": el token vive en la sesión
// y el frontend lo reenvía en la cabecera X-CSRF-Token en cada POST.
// IMPORTANTE: la sesión ya debe estar iniciada (session_start) antes de usar esto.

if (!function_exists('csrf_token')) {
    /** Devuelve el token de la sesión (lo crea la primera vez). */
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_require')) {
    /**
     * Bloquea la petición si es POST/PUT/PATCH/DELETE y no trae un token válido.
     * Las peticiones de solo lectura (GET/HEAD) se dejan pasar.
     */
    function csrf_require(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $sent   = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $stored = $_SESSION['csrf_token'] ?? '';

        if ($stored === '' || !is_string($sent) || $sent === '' || !hash_equals($stored, $sent)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'Sesión de seguridad expirada. Recarga la página e inténtalo de nuevo.'
            ]);
            exit();
        }
    }
}
