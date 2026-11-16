<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

require_same_origin_post(true);
$user = require_login_json();
$db = app_db();
$input = app_json_input();
$designId = isset($input['design_id']) ? (int)$input['design_id'] : 0;
if ($designId <= 0) {
    app_json_response(['success' => false, 'error' => 'Identifiant invalide.'], 422);
}

app_delete_design_document($db, (int)$user['id'], $designId);

app_json_response(['success' => true]);
