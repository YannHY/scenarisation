<?php
declare(strict_types=1);
require_once __DIR__ . '/native-oauth.php';
require_once __DIR__ . '/native-collaboration.php';

function native_api_user(string $scope): array
{
    return native_access_user(app_db(), native_bearer(), $scope);
}

/** Scénarios du compte, suivis de ceux qui lui sont partagés ; le rôle distingue les deux. */
function native_api_list(PDO $db, int $userId): array
{
    $query = $db->prepare('SELECT d.id, d.title, d.revision, d.updated_at, d.owner_user_id, u.username AS owner_name,
            mine.role AS member_role
        FROM learning_designs d JOIN users u ON u.id = d.owner_user_id
        LEFT JOIN design_collaborators mine ON mine.design_id = d.id AND mine.user_id = ?
        WHERE d.owner_user_id = ? OR mine.user_id IS NOT NULL
        ORDER BY d.updated_at DESC, d.id DESC');
    $query->execute([$userId, $userId]);
    $shared = native_shared_design_ids($db, $userId);
    $items = [];
    foreach ($query->fetchAll() as $row) {
        $owned = (int)$row['owner_user_id'] === $userId;
        // Une invitation n’est visible que tant que le compte invité reste actif et vérifié.
        if (!$owned && !isset($shared[(int)$row['id']])) continue;
        $items[] = [
            'id' => (int)$row['id'], 'title' => (string)$row['title'],
            'revision' => (int)$row['revision'], 'updatedAt' => (string)$row['updated_at'],
            'role' => $owned ? 'owner' : native_collaborator_role($row['member_role']), 'owner' => (string)$row['owner_name'],
            'shared' => isset($shared[(int)$row['id']]),
        ];
    }
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
    $access = native_design_access($db, $userId, $designId);
    if (!$access) throw new NativeRequestError('not_found', 'Production introuvable.', 404);
    $query = $db->prepare('SELECT * FROM learning_designs WHERE id = ? AND owner_user_id = ?');
    $query->execute([$designId, $access['owner_user_id']]); $row = $query->fetch();
    if (!$row) throw new NativeRequestError('not_found', 'Production introuvable.', 404);
    $document = json_decode((string)$row['document_json'], false, 512, JSON_THROW_ON_ERROR);
    if (!($document instanceof stdClass) || !(($document->meta ?? null) instanceof stdClass) || !is_array($document->sessions ?? null)) {
        throw new NativeRequestError('invalid_document', 'Ce scénario doit être vérifié dans le site avant sa synchronisation.', 422);
    }
    return ['success' => true, 'design' => [
        'id' => (int)$row['id'], 'title' => (string)$row['title'], 'revision' => (int)$row['revision'],
        'updatedAt' => (string)$row['updated_at'], 'provenance' => app_design_provenance_payload($row),
        'role' => $access['role'], 'owner' => $access['owner_name'],
        'shared' => native_design_collaborators($db, $designId) !== [],
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
    // Un enseignant invité enregistre le scénario de son propriétaire ; une création reste à l’auteur.
    // Sans accès, l’écriture vise le compte appelant et répond 404 comme auparavant.
    $ownerId = $userId;
    if ($designId !== null) {
        $access = native_design_access($db, $userId, $designId);
        if (($access['role'] ?? null) === 'viewer') {
            throw new NativeRequestError('forbidden', 'Ce scénario vous est partagé en consultation : vous ne pouvez pas le modifier.', 403);
        }
        $ownerId = $access['owner_user_id'] ?? $userId;
    }
    $result = app_save_design_document($db, $ownerId, $designId ?? 0, $revision, $title, $payload, false,
        $designId === null ? $uuid : null);
    if (!$result['success']) {
        throw new NativeRequestError($result['status'] === 409 ? 'conflict' : 'not_found', $result['error'], $result['status']);
    }
    unset($result['status'], $result['share_token']);
    return $result;
}

/** Supprime un scénario seulement s’il n’a pas changé sur le site depuis la révision connue de l’app. */
function native_api_delete(PDO $db, int $userId, array $input): array
{
    $designId = $input['design_id'] ?? null;
    if (!is_int($designId) || $designId <= 0) {
        throw new NativeRequestError('invalid_request', 'Identifiant de scénario invalide.', 422);
    }
    $revision = $input['expected_revision'] ?? null;
    if (!is_int($revision) || $revision <= 0) {
        throw new NativeRequestError('conflict', 'Une révision valide est requise pour supprimer.', 409);
    }
    if (in_array(native_design_access($db, $userId, $designId)['role'] ?? null, NATIVE_COLLABORATOR_ROLES, true)) {
        throw new NativeRequestError('forbidden', 'Seul le propriétaire peut supprimer ce scénario. Vous pouvez quitter le partage.', 403);
    }
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $tombstone = ($sqlite ? 'INSERT OR IGNORE' : 'INSERT IGNORE') . ' INTO native_design_deletions (owner_user_id, design_id, deleted_at)
        SELECT owner_user_id, id, ? FROM learning_designs WHERE id = ? AND owner_user_id = ? AND revision = ?';
    $db->beginTransaction();
    try {
        $db->prepare($tombstone)->execute([time(), $designId, $userId, $revision]);
        $delete = $db->prepare('DELETE FROM learning_designs WHERE id = ? AND owner_user_id = ? AND revision = ?');
        $delete->execute([$designId, $userId, $revision]);
        $deleted = $delete->rowCount() > 0;
        if ($deleted) app_forget_design_sharing($db, $designId);
        if (!$deleted) {
            $current = $db->prepare('SELECT 1 FROM learning_designs WHERE id = ? AND owner_user_id = ?');
            $current->execute([$designId, $userId]);
            $changed = $current->fetchColumn() !== false;
            $known = $db->prepare('SELECT 1 FROM native_design_deletions WHERE owner_user_id = ? AND design_id = ?');
            $known->execute([$userId, $designId]);
            $alreadyDeleted = $known->fetchColumn() !== false;
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
    if (!$deleted && $changed) {
        throw new NativeRequestError('conflict', 'Le scénario a été modifié sur le site depuis la dernière synchronisation.', 409);
    }
    if (!$deleted && !$alreadyDeleted) throw new NativeRequestError('not_found', 'Production introuvable.', 404);
    return ['success' => true, 'deleted' => ['id' => $designId]];
}
