<?php
require_once __DIR__ . '/bootstrap.php';
require_post();
require_auth();
require_csrf();

$body          = read_json_body();
$colaboradorId = (int)($body['colaborador_id'] ?? 0);
$prioridade    = trim((string)($body['prioridade'] ?? 'normal'));
$observacao    = trim((string)($body['observacao'] ?? '')) ?: null;
$itens         = $body['itens'] ?? [];

$prioridadesValidas = ['baixa','normal','alta','urgente'];
if (!in_array($prioridade, $prioridadesValidas, true)) {
    $prioridade = 'normal';
}

if (!$colaboradorId)               json_error(400, 'Colaborador inválido');
if (!is_array($itens) || !$itens)  json_error(400, 'Nenhum item informado');

try {
    require_once __DIR__ . '/config.php';
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, DB_OPTIONS);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO dbo.EPI_SOLICITACAO
            (LIDER, EQUIPE, COLABORADOR_ID, PRIORIDADE, OBSERVACAO, STATUS, DATA_SOLICITACAO)
        OUTPUT INSERTED.ID_SOLICITACAO
        VALUES (?, ?, ?, ?, ?, 0, GETDATE())
    ");
    $stmt->execute([
        $_SESSION['login'],
        $_SESSION['equipe'] ?? '',
        $colaboradorId,
        $prioridade,
        $observacao,
    ]);
    $solicitacaoId = (int)$stmt->fetchColumn();

    if (!$solicitacaoId) throw new RuntimeException('Falha ao obter ID da solicitação');

    $stmtItem = $pdo->prepare("
        INSERT INTO dbo.EPI_SOLICITACAO_ITENS (SOLICITACAO_ID, EPI_ID, QUANTIDADE)
        VALUES (?, ?, ?)
    ");
    foreach ($itens as $item) {
        if (!is_array($item)) continue;
        $epiId = (int)($item['epi_id'] ?? 0);
        $qtd   = max(1, (int)($item['quantidade'] ?? 1));
        if ($epiId > 0) {
            $stmtItem->execute([$solicitacaoId, $epiId, $qtd]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $solicitacaoId]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    log_internal('salvar-solicitacao', $e);
    json_error(500, 'Erro ao salvar solicitação');
}
