<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'login'         => $_SESSION['login'],
    'nome_lider'    => $_SESSION['nome_lider'] ?? null,
    'equipe'        => $_SESSION['equipe'] ?? null,
    'csrf'          => csrf_token(),
]);
