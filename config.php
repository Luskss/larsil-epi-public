<?php
// Carrega variáveis do ambiente (Railway/produção) ou do .env (local).
$envFile = __DIR__ . '/.env';
$env = file_exists($envFile) ? (parse_ini_file($envFile) ?: []) : [];

// Variáveis de ambiente do servidor têm prioridade sobre o .env.
$cfg = static function (string $key) use ($env) {
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_SERVER[$key] ?? $_ENV[$key] ?? ($env[$key] ?? '');
    }
    return $v;
};

// Falha cedo se as credenciais essenciais não estiverem configuradas.
foreach (['DB_SERVER', 'DB_DATABASE', 'DB_USER', 'DB_PASSWORD'] as $required) {
    if ($cfg($required) === '') {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['error' => 'Configuração ausente']));
    }
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', filter_var($cfg('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN));
}

define('DB_SERVER',   $cfg('DB_SERVER'));
define('DB_DATABASE', $cfg('DB_DATABASE'));
define('DB_USER',     $cfg('DB_USER'));
define('DB_PASSWORD', $cfg('DB_PASSWORD'));

define('DB_DSN',
    "sqlsrv:Server=" . DB_SERVER . ",1433;"
    . "Database=" . DB_DATABASE . ";"
    . "Encrypt=1;TrustServerCertificate=0;LoginTimeout=30"
);

define('DB_OPTIONS', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
