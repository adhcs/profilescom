<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
auth_require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    $client = app_client();
    $res = $client->testToken();

    echo json_encode([
        'ok' => true,
        'result' => $res,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
