<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/native-collaboration.php';

/*
 * Collaboration depuis le site : mêmes règles que l’API native (lib/native-collaboration.php),
 * avec la session web et la vérification d’origine au lieu du jeton de l’app.
 * Corps JSON : { "action": "members" | "share" | "role" | "unshare" | "presence", "design_id": 7, … }
 */

require_same_origin_post(true);
$user = require_login_json();
header('Cache-Control: no-store');
$input = app_json_input();
$db = app_db();
$userId = (int)$user['id'];

try {
    $result = match ((string)($input['action'] ?? '')) {
        'members' => native_api_collaborators($db, $userId, native_collaboration_design_id($input)),
        'share' => native_api_share($db, $userId, $input),
        'role' => native_api_collaborator_role($db, $userId, $input),
        'unshare' => native_api_unshare($db, $userId, $input),
        'presence' => native_api_presence($db, $userId, $input),
        default => throw new NativeRequestError('invalid_request', 'Action inconnue.', 422),
    };
    app_json_response($result);
} catch (NativeRequestError $error) {
    app_json_response(['success' => false, 'error' => $error->getMessage(), 'code' => $error->oauthError], $error->status);
}
