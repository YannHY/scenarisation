<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

require_same_origin_post(false);
$user = current_user();
if (!$user) {
    header('Location: login.php');
    exit;
}

$token = trim((string)($_POST['token'] ?? ''));
if ($token === '') {
    http_response_code(422);
    echo 'Lien invalide.';
    exit;
}

$db = app_db();
$stmt = $db->prepare("SELECT d.id, d.title, d.document_json, d.license_code, d.root_design_id, u.username
    FROM learning_designs d
    JOIN users u ON u.id = d.owner_user_id
    WHERE d.share_token = ? AND d.is_published = 1
    LIMIT 1");
$stmt->execute([$token]);
$source = $stmt->fetch();
if (!$source) {
    http_response_code(404);
    echo 'Cette production n’est pas disponible ou son lien de partage a été révoqué.';
    exit;
}

$sourceLicenseCode = strtolower(trim((string)($source['license_code'] ?? '')));

$document = json_decode((string)$source['document_json'], true);
if (!is_array($document) || !isset($document['sessions']) || !is_array($document['sessions'])) {
    http_response_code(422);
    echo 'Document invalide.';
    exit;
}

$title = trim((string)$source['title']);
if ($title === '') {
    $title = app_design_title_from_document($document);
}
$copyTitle = mb_substr('Copie de ' . $title, 0, 255, 'UTF-8');

$payload = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($payload)) {
    http_response_code(500);
    echo 'Impossible de copier le document.';
    exit;
}

$insert = $db->prepare("INSERT INTO learning_designs (
    owner_user_id, title, document_json, source_design_id, root_design_id,
    source_title, source_author_name, source_license_code, derived_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
$insert->execute([
    (int)$user['id'],
    $copyTitle,
    $payload,
    (int)$source['id'],
    (int)($source['root_design_id'] ?? 0) ?: (int)$source['id'],
    $title,
    (string)$source['username'],
    $sourceLicenseCode !== '' ? $sourceLicenseCode : null,
]);
$designId = (int)$db->lastInsertId();

header('Location: designer.php?remote_design_id=' . urlencode((string)$designId));
exit;
