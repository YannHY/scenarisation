<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/native-api.php';
native_endpoint(static function (): array {
    $user = native_api_user('designs:read');
    return native_api_list(app_db(), $user['id']);
}, 'GET');
