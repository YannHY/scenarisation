<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/native-api.php';
native_endpoint(static function (): array {
    $user = native_api_user('designs:read');
    $id = filter_var($_GET['design_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new NativeRequestError('invalid_request', 'Identifiant invalide.', 422);
    return native_api_get(app_db(), $user['id'], $id);
}, 'GET');
