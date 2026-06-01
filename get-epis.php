<?php
require_once __DIR__ . '/bootstrap.php';
require_auth();

try {
    require_once __DIR__ . '/config.php';
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, DB_OPTIONS);

    $stmt = $pdo->query("
        SELECT e.ID, e.DESCRICAO, e.CA, g.NOME_GRUPO
        FROM dbo.EPI e
        LEFT JOIN dbo.EPI_GRUPOS g ON e.GRUPO_ID = g.ID
        WHERE e.ATIVO = 1
        ORDER BY g.NOME_GRUPO, e.DESCRICAO
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grupos = [];
    foreach ($rows as $row) {
        $grupo = $row['NOME_GRUPO'] ?? 'Sem grupo';
        if (!isset($grupos[$grupo])) {
            $grupos[$grupo] = ['NOME_GRUPO' => $grupo, 'epis' => []];
        }
        $grupos[$grupo]['epis'][] = [
            'ID'        => $row['ID'],
            'DESCRICAO' => $row['DESCRICAO'],
            'CA'        => $row['CA'],
        ];
    }

    echo json_encode(array_values($grupos), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    log_internal('get-epis', $e);
    json_error(500, 'Erro ao carregar EPIs');
}
