<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/native-oauth.php';
native_endpoint(static function (): array {
    $input = native_form_input();
    if (native_string($input, 'client_id') !== NATIVE_CLIENT_ID) {
        throw new NativeRequestError('invalid_client', 'Application inconnue.', 401);
    }
    native_revoke_token(app_db(), native_string($input, 'token'));
    return ['success' => true];
});
