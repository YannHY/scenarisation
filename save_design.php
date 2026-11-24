<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/native-collaboration.php';

require_same_origin_post(true);
$user = require_login_json();
$db = app_db();
$input = app_json_input();
$document = $input['document'] ?? null;

if (!is_array($document) || !isset($document['sessions']) || !is_array($document['sessions'])) {
    app_json_response(['success' => false, 'error' => 'Document invalide.'], 422);
}

$designId = isset($input['design_id']) ? (int)$input['design_id'] : 0;
// Missing revisions (including older browser tabs) fail safely as conflicts.
$expectedRevision = filter_var($input['expected_revision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$title = trim((string)($input['title'] ?? app_design_title_from_document($document)));
if ($title === '') {
    $title = app_design_title_from_document($document);
}
$title = mb_substr($title, 0, 255, 'UTF-8');

$payload = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($payload)) {
    app_json_response(['success' => false, 'error' => 'Impossible de serialiser le document.'], 500);
}

// Un enseignant invité en modification enregistre le scénario de son propriétaire ;
// une création appartient toujours à son auteur. Sans accès, l’écriture vise le compte et répond 404.
$ownerId = (int)$user['id'];
if ($designId > 0) {
    $access = native_design_access($db, (int)$user['id'], $designId);
    if (($access['role'] ?? null) === 'viewer') {
        app_json_response(['success' => false, 'code' => 'forbidden',
            'error' => 'Ce scénario vous est partagé en consultation : vous ne pouvez pas le modifier.'], 403);
    }
    $ownerId = $access['owner_user_id'] ?? $ownerId;
}

$result = app_save_design_document($db, $ownerId, $designId,
    $expectedRevision === false ? null : $expectedRevision, $title, $payload);
$status = $result['status'];
unset($result['status'], $result['share_token']);
app_json_response($result, $status);
