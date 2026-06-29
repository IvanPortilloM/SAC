<?php
// api/csrf_token.php — entrega al frontend el token CSRF de la sesión actual.
session_start();
require_once __DIR__ . '/../config/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(['token' => csrf_token()]);
