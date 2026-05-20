<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

try {
    require_once __DIR__ . '/config.php';
    $pdo  = new PDO(DB_DSN, DB_USER, DB_PASSWORD, DB_OPTIONS);

    // Busca colaboradores da mesma EQUIPE do líder logado
    $stmt = $pdo->prepare("
        SELECT c.ID, c.NOME, c.FUNCAO
        FROM dbo.COLABORADORES c
        INNER JOIN dbo.ORGANOGRAMA o ON c.EQUIPE = o.EQUIPE
        WHERE o.LOGIN = ?
        ORDER BY c.NOME
    ");
    $stmt->execute([$_SESSION['login']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
