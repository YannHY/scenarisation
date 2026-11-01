<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$user = require_login_json();
$db = app_db();
$designId = isset($_GET['design_id']) ? (int)$_GET['design_id'] : 0;
if ($designId <= 0) {
    app_json_response(['success' => false, 'error' => 'Identifiant invalide.'], 422);
}

$stmt = $db->prepare("SELECT id, title, document_json, updated_at, revision,
    source_design_id, root_design_id, source_title, source_author_name, source_license_code, derived_at
    FROM learning_designs WHERE id = ? AND owner_user_id = ? LIMIT 1");
$stmt->execute([$designId, (int)$user['id']]);
$row = $stmt->fetch();
if (!$row) {
    app_json_response(['success' => false, 'error' => 'Production introuvable.'], 404);
}

$document = json_decode((string)$row['document_json'], true);
if (!is_array($document)) {
    $document = ['sessions' => [], 'meta' => []];
}
$provenance = app_design_provenance_payload($row);

app_json_response([
    'success' => true,
    'design' => [
        'id' => (int)$row['id'],
        'title' => (string)$row['title'],
        'updatedAt' => (string)$row['updated_at'],
        'revision' => (int)$row['revision'],
        'provenance' => $provenance,
        'document' => $document,
    ],
]);
