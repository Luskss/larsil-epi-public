<?php
require_once __DIR__ . '/bootstrap.php';
require_post();

$body  = read_json_body();
$login = strtoupper(trim((string)($body['login'] ?? '')));
$senha = (string)($body['senha'] ?? '');

if ($login === '') {
    json_error(400, 'Login é obrigatório');
}

// ── Conexão com Azure SQL via pdo_sqlsrv ────────────────────────────────────
require_once __DIR__ . '/config.php';
try {
    $pdo  = new PDO(DB_DSN, DB_USER, DB_PASSWORD, DB_OPTIONS);
    $stmt = $pdo->prepare("SELECT TOP 1 LOGIN, SENHA, EQUIPE, LIDER, PROJETO
                           FROM dbo.ORGANOGRAMA WHERE LOGIN = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    log_internal('auth', $e);
    json_error(500, 'Não foi possível conectar ao banco de dados.');
}

if (!$user) {
    json_error(401, 'Login ou senha incorretos');
}

$projeto = trim((string)($user['PROJETO'] ?? ''));
$senhaObrigatoria = ($projeto !== '400');

if ($senhaObrigatoria) {
    $stored = (string)($user['SENHA'] ?? '');
    // Suporta hash (password_hash) e legado em texto plano (comparação em tempo constante).
    $isHash = (strlen($stored) >= 60 && (str_starts_with($stored, '$2y$')
              || str_starts_with($stored, '$argon2') || str_starts_with($stored, '$2a$')));
    $ok = $isHash
        ? password_verify($senha, $stored)
        : ($stored !== '' && hash_equals($stored, $senha));
    if (!$ok) {
        json_error(401, 'Login ou senha incorretos');
    }
}

// ── Sessão ────────────────────────────────────────────────────────────────────
session_regenerate_id(true);
$_SESSION['authenticated'] = true;
$_SESSION['login']         = $login;
$_SESSION['equipe']        = trim((string)($user['EQUIPE'] ?? ''));
$_SESSION['nome_lider']    = trim((string)($user['LIDER'] ?? ''));

echo json_encode([
    'success' => true,
    'login'   => $login,
    'csrf'    => csrf_token(),
]);
