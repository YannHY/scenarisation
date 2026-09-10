<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/public-search.php';

header('Cache-Control: no-store');
$query = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
$offset = max(0, (int)($_GET['offset'] ?? 0));
try {
    app_json_response(search_public_designs(app_db(), $query, $offset));
} catch (Throwable $error) {
    error_log('Public search failed: ' . $error->getMessage());
    app_json_response(['error' => 'Search unavailable'], 503);
}
