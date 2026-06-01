<?php
// Carrega variáveis do .env (parse_ini_file entende KEY="valor")
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['error' => 'Configuração ausente']));
}

$env = parse_ini_file($envFile);

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
}

define('DB_SERVER',   $env['DB_SERVER']   ?? '');
define('DB_DATABASE', $env['DB_DATABASE'] ?? '');
define('DB_USER',     $env['DB_USER']     ?? '');
define('DB_PASSWORD', $env['DB_PASSWORD'] ?? '');

define('DB_DSN',
    "sqlsrv:Server=" . DB_SERVER . ",1433;"
    . "Database=" . DB_DATABASE . ";"
    . "Encrypt=1;TrustServerCertificate=0;LoginTimeout=30"
);

define('DB_OPTIONS', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
