<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$body          = json_decode(file_get_contents('php://input'), true);
$colaboradorId = intval($body['colaborador_id'] ?? 0);
$prioridade    = trim($body['prioridade'] ?? 'normal');
$observacao    = trim($body['observacao'] ?? '') ?: null;
$itens         = $body['itens'] ?? [];

if (!$colaboradorId) {
    http_response_code(400);
    echo json_encode(['error' => 'Colaborador inválido']);
    exit;
}
if (empty($itens)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum item informado']);
    exit;
}

try {
    require_once __DIR__ . '/config.php';
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, DB_OPTIONS);

    $pdo->beginTransaction();

    // Insere a solicitação e retorna o ID gerado
    $stmt = $pdo->prepare("
        INSERT INTO dbo.EPI_SOLICITACAO
            (LIDER, EQUIPE, COLABORADOR_ID, PRIORIDADE, OBSERVACAO, STATUS, DATA_SOLICITACAO)
        OUTPUT INSERTED.ID_SOLICITACAO
        VALUES (?, ?, ?, ?, ?, 0, GETDATE())
    ");
    $stmt->execute([$_SESSION['login'], $_SESSION['equipe'] ?? '', $colaboradorId, $prioridade, $observacao]);
    $solicitacaoId = intval($stmt->fetchColumn());

    if (!$solicitacaoId) throw new Exception('Falha ao obter ID da solicitação');

    // Insere os itens
    $stmtItem = $pdo->prepare("
        INSERT INTO dbo.EPI_SOLICITACAO_ITENS (SOLICITACAO_ID, EPI_ID, QUANTIDADE)
        VALUES (?, ?, ?)
    ");
    foreach ($itens as $item) {
        $epiId = intval($item['epi_id'] ?? 0);
        $qtd   = max(1, intval($item['quantidade'] ?? 1));
        if ($epiId > 0) {
            $stmtItem->execute([$solicitacaoId, $epiId, $qtd]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $solicitacaoId]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
