<?php
require_once __DIR__ . '/bootstrap.php';
require_auth();

try {
    require_once __DIR__ . '/config.php';
    $pdo  = new PDO(DB_DSN, DB_USER, DB_PASSWORD, DB_OPTIONS);

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
} catch (Throwable $e) {
    log_internal('get-colaboradores', $e);
    json_error(500, 'Erro ao carregar colaboradores');
}
