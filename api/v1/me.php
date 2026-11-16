<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/native-api.php';
native_endpoint(static fn() => ['success' => true, 'user' => native_api_user('designs:read')], 'GET');
