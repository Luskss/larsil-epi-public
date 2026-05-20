<?php
header('Content-Type: application/json; charset=utf-8');

// Permite apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

session_start();

$body  = json_decode(file_get_contents('php://input'), true);
$login = strtoupper(trim($body['login'] ?? ''));
$senha = trim($body['senha'] ?? '');

if ($login === '' || $senha === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Login e senha são obrigatórios']);
    exit;
}

// ── Conexão com Azure SQL via pdo_sqlsrv ────────────────────────────────────
require_once __DIR__ . '/config.php';
try {
    $pdo  = new PDO(DB_DSN, DB_USER, DB_PASSWORD, DB_OPTIONS);
    $stmt = $pdo->prepare("SELECT TOP 1 LOGIN, SENHA, EQUIPE FROM dbo.ORGANOGRAMA WHERE LOGIN = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível conectar ao banco de dados.']);
    exit;
}

// ── Validação ─────────────────────────────────────────────────────────────────
if (!$user || $user['SENHA'] !== $senha) {
    http_response_code(401);
    echo json_encode(['error' => 'Login ou senha incorretos']);
    exit;
}

// ── Sessão ────────────────────────────────────────────────────────────────────
session_regenerate_id(true); // previne session fixation
$_SESSION['authenticated'] = true;
$_SESSION['login']         = $login;                         // valor digitado (já trim + strtoupper)
$_SESSION['equipe']        = trim($user['EQUIPE'] ?? '');

echo json_encode(['success' => true, 'login' => $login]);
