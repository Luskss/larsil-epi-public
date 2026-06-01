<?php
require_once __DIR__ . '/bootstrap.php';
require_auth();

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
            s.STATUS_TRANSPORTE,
            CONVERT(varchar(19), s.DATA_SOLICITACAO, 126) AS DATA_SOLICITACAO,
            CONVERT(varchar(19), s.DATA_ENTREGA, 126) AS DATA_ENTREGA,
            s.EQUIPE,
            t.ID_TERMO,
            t.CAMINHO_PRE,
            t.CAMINHO_POS,
            CONVERT(varchar(19), t.DATA_ASSINATURA, 126) AS DATA_ASSINATURA,
            c.NOME AS COLABORADOR_NOME
        FROM dbo.EPI_SOLICITACAO s
        LEFT JOIN dbo.COLABORADORES c ON c.ID = s.COLABORADOR_ID
        OUTER APPLY (
            SELECT TOP 1
                tt.ID_TERMO,
                tt.CAMINHO_PRE,
                tt.CAMINHO_POS,
                tt.DATA_ASSINATURA
            FROM dbo.EPI_TERMOS_ENTREGA tt
            WHERE tt.SOLICITACAO_ID = s.ID_SOLICITACAO
            ORDER BY tt.ID_TERMO DESC
        ) t
        WHERE s.LIDER = ?
          AND s.EQUIPE = ?
        ORDER BY s.DATA_SOLICITACAO DESC
    ");
    $stmt->execute([$_SESSION['login'], $_SESSION['equipe'] ?? '']);
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
            'status_transporte' => isset($s['STATUS_TRANSPORTE']) ? intval($s['STATUS_TRANSPORTE']) : null,
            'data'             => $s['DATA_SOLICITACAO'],
            'data_entrega'     => $s['DATA_ENTREGA'],
            'equipe'           => $s['EQUIPE'] ?? ($_SESSION['equipe'] ?? null),
            'termo_id'         => isset($s['ID_TERMO']) ? intval($s['ID_TERMO']) : null,
            'assinatura_url'   => $s['CAMINHO_PRE'] ?? null,
            'assinatura_pos_url' => $s['CAMINHO_POS'] ?? null,
            'data_assinatura'  => $s['DATA_ASSINATURA'] ?? null,
            'termo_assinado'   => !empty($s['DATA_ASSINATURA']) || !empty($s['CAMINHO_POS']),
            'itens'            => $itensPorSol[$s['ID_SOLICITACAO']] ?? [],
        ];
    }, $sols);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    log_internal('get-historico', $e);
    json_error(500, 'Erro ao carregar histórico');
}
