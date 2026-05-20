<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$out = [
    'session_login'     => $_SESSION['login'] ?? null,
    'authenticated'     => $_SESSION['authenticated'] ?? false,
    'solicitacoes'      => [],
    'error'             => null,
    'lider_na_tabela'   => [],
];

try {
    require_once __DIR__ . '/config.php';
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, DB_OPTIONS);

    // Mostra todos os LIDERs distintos na tabela
    $s1 = $pdo->query("SELECT DISTINCT LIDER FROM dbo.EPI_SOLICITACAO");
    $out['lider_na_tabela'] = array_column($s1->fetchAll(PDO::FETCH_ASSOC), 'LIDER');

    // Tenta buscar as do usuário atual
    $s2 = $pdo->prepare("SELECT ID_SOLICITACAO, LIDER, STATUS, DATA_SOLICITACAO FROM dbo.EPI_SOLICITACAO WHERE LIDER = ? ORDER BY DATA_SOLICITACAO DESC");
    $s2->execute([$_SESSION['login'] ?? '']);
    $out['solicitacoes'] = $s2->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
