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
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, DB_OPTIONS);

    // 1. Solicita\u00e7\u00f5es do l\u00edder logado com nome do colaborador
    $stmt = $pdo->prepare("
        SELECT
            s.ID_SOLICITACAO,
            s.PRIORIDADE,
            s.OBSERVACAO,
            s.STATUS,
            CONVERT(varchar(19), s.DATA_SOLICITACAO, 126) AS DATA_SOLICITACAO,
            c.NOME AS COLABORADOR_NOME
        FROM dbo.EPI_SOLICITACAO s
        LEFT JOIN dbo.COLABORADORES c ON c.ID = s.COLABORADOR_ID
        WHERE s.LIDER = ?
        ORDER BY s.DATA_SOLICITACAO DESC
    ");
    $stmt->execute([$_SESSION['login']]);
    $sols = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($sols)) {
        echo json_encode([]);
        exit;
    }

    // 2. Itens com descri\u00e7\u00e3o do EPI
    $ids          = array_column($sols, 'ID_SOLICITACAO');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmtItens = $pdo->prepare("
        SELECT
            i.SOLICITACAO_ID,
            i.QUANTIDADE,
            e.DESCRICAO AS EPI_DESCRICAO,
            e.CA
        FROM dbo.EPI_SOLICITACAO_ITENS i
        LEFT JOIN dbo.EPI e ON e.ID = i.EPI_ID
        WHERE i.SOLICITACAO_ID IN ($placeholders)
        ORDER BY i.ID
    ");
    $stmtItens->execute($ids);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    // 3. Agrupa itens por solicita\u00e7\u00e3o
    $itensPorSol = [];
    foreach ($itens as $item) {
        $sid = $item['SOLICITACAO_ID'];
        $itensPorSol[$sid][] = [
            'epi_descricao' => $item['EPI_DESCRICAO'],
            'ca'            => $item['CA'],
            'quantidade'    => intval($item['QUANTIDADE']),
        ];
    }

    // 4. Monta resultado final
    $result = array_map(function ($s) use ($itensPorSol) {
        return [
            'id'               => $s['ID_SOLICITACAO'],
            'colaborador_nome' => $s['COLABORADOR_NOME'],
            'prioridade'       => strtolower($s['PRIORIDADE']),
            'observacao'       => $s['OBSERVACAO'],
            'status'           => intval($s['STATUS']),
            'data'             => $s['DATA_SOLICITACAO'],
            'itens'            => $itensPorSol[$s['ID_SOLICITACAO']] ?? [],
        ];
    }, $sols);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
