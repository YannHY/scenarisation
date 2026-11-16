<?php
declare(strict_types=1);
require_once __DIR__ . '/native-oauth.php';

function native_api_user(string $scope): array
{
    return native_access_user(app_db(), native_bearer(), $scope);
}

function native_api_list(PDO $db, int $userId): array
{
    $query = $db->prepare('SELECT id, title, revision, updated_at FROM learning_designs
        WHERE owner_user_id = ? ORDER BY updated_at DESC, id DESC');
    $query->execute([$userId]);
    $items = array_map(static fn($row) => [
        'id' => (int)$row['id'], 'title' => (string)$row['title'],
        'revision' => (int)$row['revision'], 'updatedAt' => (string)$row['updated_at'],
    ], $query->fetchAll());
    $deleted = $db->prepare('SELECT design_id, deleted_at FROM native_design_deletions
        WHERE owner_user_id = ? ORDER BY deleted_at, design_id');
    $deleted->execute([$userId]);
    return ['success' => true, 'items' => $items, 'deleted' => array_map(static fn($row) => [
        'id' => (int)$row['design_id'], 'deletedAt' => (int)$row['deleted_at'],
    ], $deleted->fetchAll())];
}

function native_api_get(PDO $db, int $userId, int $designId): array
{
    if ($designId <= 0) throw new NativeRequestError('invalid_request', 'Identifiant invalide.', 422);
    $query = $db->prepare('SELECT * FROM learning_designs WHERE id = ? AND owner_user_id = ?');
    $query->execute([$designId, $userId]); $row = $query->fetch();
    if (!$row) throw new NativeRequestError('not_found', 'Production introuvable.', 404);
    $document = json_decode((string)$row['document_json'], false, 512, JSON_THROW_ON_ERROR);
    if (!($document instanceof stdClass) || !(($document->meta ?? null) instanceof stdClass) || !is_array($document->sessions ?? null)) {
        throw new NativeRequestError('invalid_document', 'Ce scénario doit être vérifié dans le site avant sa synchronisation.', 422);
    }
    return ['success' => true, 'design' => [
        'id' => (int)$row['id'], 'title' => (string)$row['title'], 'revision' => (int)$row['revision'],
        'updatedAt' => (string)$row['updated_at'], 'provenance' => app_design_provenance_payload($row),
        'document' => $document,
    ]];
}

function native_api_input(): array
{
    if (strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0])) !== 'application/json') {
        throw new NativeRequestError('invalid_request', 'Un document JSON est requis.', 415);
    }
    $max = 20000000;
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $max) {
        throw new NativeRequestError('invalid_request', 'Le document dépasse 20 Mo.', 413);
    }
    $stream = fopen('php://input', 'rb');
    if ($stream === false) throw new NativeRequestError('invalid_request', 'Document illisible.', 400);
    try { $raw = stream_get_contents($stream, $max + 1); } finally { fclose($stream); }
    if ($raw === false || strlen($raw) > $max) throw new NativeRequestError('invalid_request', 'Document trop volumineux.', 413);
    try { $input = json_decode($raw, false, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new NativeRequestError('invalid_request', 'Document JSON invalide.', 400); }
    if (!$input instanceof stdClass) throw new NativeRequestError('invalid_request', 'Un objet JSON est requis.', 400);
    return (array)$input;
}

function native_validate_document(mixed $document): void
{
    if (!($document instanceof stdClass) || !(($document->meta ?? null) instanceof stdClass)
        || !is_array($document->sessions ?? null)) {
        throw new NativeRequestError('invalid_document', 'Le scénario doit contenir meta et sessions.', 422);
    }
    $seen = [];
    foreach ($document->sessions as $moment) {
        if (!$moment instanceof stdClass || !is_array($moment->activities ?? null)) {
            throw new NativeRequestError('invalid_document', 'Moment invalide.', 422);
        }
        $entries = array_merge([$moment], $moment->activities);
        foreach ($entries as $entry) {
            $id = $entry instanceof stdClass ? ($entry->id ?? null) : null;
            if (!is_string($id) || $id === '' || strlen($id) > 200 || isset($seen[$id])) {
                throw new NativeRequestError('invalid_document', 'Chaque moment et activité doit avoir un identifiant unique.', 422);
            }
            $seen[$id] = true;
        }
        foreach ($moment->activities as $activity) {
            $duration = $activity->duration ?? null;
            if ((!is_int($duration) && !is_float($duration)) || !is_finite((float)$duration) || $duration <= 0 || $duration > 100000) {
                throw new NativeRequestError('invalid_document', 'Durée d’activité invalide.', 422);
            }
        }
    }
}

function native_api_save(PDO $db, int $userId, array $input): array
{
    $uuid = strtolower(native_string($input, 'client_uuid'));
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $uuid)) {
        throw new NativeRequestError('invalid_request', 'Identifiant de création invalide.', 422);
    }
    $designId = $input['design_id'] ?? null;
    if ($designId !== null && (!is_int($designId) || $designId <= 0)) {
        throw new NativeRequestError('invalid_request', 'Identifiant de scénario invalide.', 422);
    }
    $revision = $input['expected_revision'] ?? null;
    if ($designId !== null && (!is_int($revision) || $revision <= 0)) {
        throw new NativeRequestError('conflict', 'Une révision valide est requise pour enregistrer.', 409);
    }
    if ($designId === null && $revision !== null) {
        throw new NativeRequestError('invalid_request', 'Une création ne doit pas fournir de révision.', 422);
    }
    $document = $input['document'] ?? null;
    native_validate_document($document);
    unset($document->_scenarisationMac);
    $title = trim(native_string($input, 'title'));
    if ($title === '') $title = is_string($document->meta->name ?? null) ? trim($document->meta->name) : '';
    $title = mb_substr($title === '' ? 'Production sans titre' : $title, 0, 255, 'UTF-8');
    $payload = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $result = app_save_design_document($db, $userId, $designId ?? 0, $revision, $title, $payload, false,
        $designId === null ? $uuid : null);
    if (!$result['success']) {
        throw new NativeRequestError($result['status'] === 409 ? 'conflict' : 'not_found', $result['error'], $result['status']);
    }
    unset($result['status'], $result['share_token']);
    return $result;
}
