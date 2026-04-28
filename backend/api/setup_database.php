<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../create_database.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendJsonResponse(405, [
        'success' => false,
        'message' => 'methode non autorisee',
    ]);
}

try {
    $result = createDatabaseAndTables();

    sendJsonResponse(200, [
        'success' => true,
        'database_created' => (bool) ($result['database_created'] ?? false),
        'message' => (string) ($result['message'] ?? 'operation terminee'),
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'erreur creation base/tables',
        'error' => $e->getMessage(),
    ]);
}
