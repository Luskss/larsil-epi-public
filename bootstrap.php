<?php
// Bootstrap comum: headers de segurança, sessão segura, CSRF e helpers.
// Inclua este arquivo no TOPO de todo endpoint PHP, antes de qualquer output.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

// Sessão hardenada — precisa rodar antes de session_start()
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Gera token CSRF na primeira chamada autenticada
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function json_error(int $status, string $msg): void {
    http_response_code($status);
    echo json_encode(['error' => $msg]);
    exit;
}

function require_auth(): void {
    if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        json_error(401, 'Não autenticado');
    }
}

function require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error(405, 'Método não permitido');
    }
}

function require_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        json_error(403, 'CSRF inválido');
    }
}

function read_json_body(): array {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error(400, 'Payload inválido');
    }
    return $data;
}

function log_internal(string $tag, Throwable $e): void {
    error_log('[' . $tag . '] ' . $e->getMessage());
}
