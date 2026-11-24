<?php
declare(strict_types=1);
require_once __DIR__ . '/native-oauth.php';

/*
 * Collaboration bêta de l’app native : un propriétaire partage un scénario avec d’autres comptes
 * vérifiés. Un enseignant « editor » le lit et l’enregistre avec les mêmes révisions conditionnelles ;
 * un enseignant « viewer » peut seulement le consulter.
 * La présence indique qui a le scénario ouvert et où il travaille ; elle ne contient aucun contenu.
 */

const NATIVE_MAX_COLLABORATORS = 20;
/** Une fenêtre qui ne s’est pas signalée depuis ce délai n’apparaît plus parmi les participants. */
const NATIVE_PRESENCE_ACTIVE_SECONDS = 30;
/** Les signaux plus anciens sont supprimés lors du signal suivant sur le même scénario. */
const NATIVE_PRESENCE_RETENTION_SECONDS = 120;
const NATIVE_PRESENCE_FOCUS_BYTES = 300;
/** Droits d’un enseignant invité : modifier ou seulement consulter. */
const NATIVE_COLLABORATOR_ROLES = ['editor', 'viewer'];

/** Rôle enregistré d’un enseignant invité ; une valeur inconnue ne donne que la consultation. */
function native_collaborator_role(mixed $role): string
{
    return $role === 'editor' || $role === null ? 'editor' : 'viewer';
}

/** Rôle demandé par l’app, ou null s’il est absent. */
function native_requested_role(array $input): ?string
{
    $role = $input['role'] ?? null;
    if ($role === null) return null;
    if (!is_string($role) || !in_array($role, NATIVE_COLLABORATOR_ROLES, true)) {
        throw new NativeRequestError('invalid_request', 'Droits invalides : « editor » ou « viewer » sont attendus.', 422);
    }
    return $role;
}

/** Compte actif à l’adresse vérifiée, selon les mêmes règles que native_active_user(). */
function native_collaboration_verified(array $row): bool
{
    return ($row['status'] ?? '') === 'active' && trim((string)($row['email_verified_at'] ?? '')) !== '';
}

/**
 * Accès d’un compte à un scénario : propriétaire (owner), enseignant invité (editor ou viewer), ou aucun (null).
 * @return array{owner_user_id:int, role:string, owner_name:string, revision:int}|null
 */
function native_design_access(PDO $db, int $userId, int $designId): ?array
{
    if ($userId <= 0 || $designId <= 0) return null;
    $query = $db->prepare('SELECT d.owner_user_id, d.revision, u.username AS owner_name
        FROM learning_designs d JOIN users u ON u.id = d.owner_user_id WHERE d.id = ?');
    $query->execute([$designId]); $design = $query->fetch(); $query->closeCursor();
    if (!$design) return null;
    $access = [
        'owner_user_id' => (int)$design['owner_user_id'], 'role' => 'owner',
        'owner_name' => (string)$design['owner_name'], 'revision' => (int)$design['revision'],
    ];
    if ($access['owner_user_id'] === $userId) return $access;
    $member = $db->prepare('SELECT c.role, u.status, u.email_verified_at FROM design_collaborators c
        JOIN users u ON u.id = c.user_id WHERE c.design_id = ? AND c.user_id = ?');
    $member->execute([$designId, $userId]); $row = $member->fetch(); $member->closeCursor();
    if (!$row || !native_collaboration_verified($row)) return null;
    return ['role' => native_collaborator_role($row['role'])] + $access;
}

/** Enseignants invités dont le compte est actif et vérifié, triés par nom. */
function native_design_collaborators(PDO $db, int $designId): array
{
    $query = $db->prepare('SELECT u.id, u.username, u.email, u.status, u.email_verified_at, c.role
        FROM design_collaborators c JOIN users u ON u.id = c.user_id
        WHERE c.design_id = ? ORDER BY u.username, u.id');
    $query->execute([$designId]);
    return array_values(array_filter($query->fetchAll(), 'native_collaboration_verified'));
}

/** Scénarios partagés parmi ceux qu’un compte possède ou auxquels il a été invité. */
function native_shared_design_ids(PDO $db, int $userId): array
{
    $query = $db->prepare('SELECT c.design_id, u.status, u.email_verified_at
        FROM design_collaborators c JOIN users u ON u.id = c.user_id
        JOIN learning_designs d ON d.id = c.design_id
        WHERE d.owner_user_id = ? OR d.id IN (SELECT design_id FROM design_collaborators WHERE user_id = ?)');
    $query->execute([$userId, $userId]);
    $shared = [];
    foreach ($query->fetchAll() as $row) {
        if (native_collaboration_verified($row)) $shared[(int)$row['design_id']] = true;
    }
    return $shared;
}

function native_collaboration_design_id(array $input): int
{
    $designId = $input['design_id'] ?? null;
    if (!is_int($designId) || $designId <= 0) {
        throw new NativeRequestError('invalid_request', 'Identifiant de scénario invalide.', 422);
    }
    return $designId;
}

function native_collaboration_not_found(): NativeRequestError
{
    return new NativeRequestError('not_found', 'Production introuvable.', 404);
}

/** Participants affichés dans l’app ; l’adresse n’est visible que du propriétaire. */
function native_sharing_payload(PDO $db, int $designId, array $access): array
{
    $canManage = $access['role'] === 'owner';
    $owner = $db->prepare('SELECT id, username, email FROM users WHERE id = ?');
    $owner->execute([$access['owner_user_id']]); $ownerRow = $owner->fetch(); $owner->closeCursor();
    $member = static function (array $row, string $role) use ($canManage): array {
        $entry = ['id' => (int)$row['id'], 'username' => (string)$row['username'], 'role' => $role];
        if ($canManage) $entry['email'] = (string)$row['email'];
        return $entry;
    };
    $members = $ownerRow ? [$member($ownerRow, 'owner')] : [];
    foreach (native_design_collaborators($db, $designId) as $row) {
        $members[] = $member($row, native_collaborator_role($row['role']));
    }
    return ['designId' => $designId, 'canManage' => $canManage, 'members' => $members];
}

function native_api_collaborators(PDO $db, int $userId, int $designId): array
{
    $access = native_design_access($db, $userId, $designId);
    if (!$access) throw native_collaboration_not_found();
    return ['success' => true, 'sharing' => native_sharing_payload($db, $designId, $access)];
}

/**
 * Invite un compte vérifié, désigné par son adresse, avec les droits demandés (modifier par défaut).
 * Une invitation répétée change seulement ces droits, s’ils sont précisés.
 */
function native_api_share(PDO $db, int $userId, array $input): array
{
    $designId = native_collaboration_design_id($input);
    $access = native_design_access($db, $userId, $designId);
    if (!$access) throw native_collaboration_not_found();
    if ($access['role'] !== 'owner') {
        throw new NativeRequestError('forbidden', 'Seul le propriétaire peut inviter des enseignants.', 403);
    }
    $role = native_requested_role($input);
    $email = mb_strtolower(trim(native_string($input, 'email')), 'UTF-8');
    if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new NativeRequestError('invalid_request', 'Adresse e-mail invalide.', 422);
    }
    $lookup = $db->prepare('SELECT id, status, email_verified_at FROM users WHERE LOWER(email) = ? ORDER BY id');
    $lookup->execute([$email]);
    $target = null;
    foreach ($lookup->fetchAll() as $row) {
        if (native_collaboration_verified($row)) { $target = (int)$row['id']; break; }
    }
    if ($target === null) {
        throw new NativeRequestError('unknown_user', 'Aucun compte vérifié de Scénarisation n’utilise cette adresse.', 422);
    }
    if ($target === $access['owner_user_id']) {
        throw new NativeRequestError('invalid_request', 'Vous êtes déjà propriétaire de ce scénario.', 422);
    }
    $members = array_map(static fn($row) => (int)$row['id'], native_design_collaborators($db, $designId));
    if (!in_array($target, $members, true)) {
        if (count($members) >= NATIVE_MAX_COLLABORATORS) {
            throw new NativeRequestError('invalid_request',
                'Un scénario peut être partagé avec ' . NATIVE_MAX_COLLABORATORS . ' enseignants au plus.', 422);
        }
        $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $db->prepare(($sqlite ? 'INSERT OR IGNORE' : 'INSERT IGNORE') . " INTO design_collaborators
            (design_id, user_id, role, invited_by, created_at) VALUES (?, ?, ?, ?, ?)")
            ->execute([$designId, $target, $role ?? 'editor', $userId, time()]);
    } elseif ($role !== null) {
        native_update_collaborator_role($db, $designId, $target, $role);
    }
    return ['success' => true, 'sharing' => native_sharing_payload($db, $designId, $access)];
}

function native_update_collaborator_role(PDO $db, int $designId, int $userId, string $role): void
{
    $db->prepare('UPDATE design_collaborators SET role = ? WHERE design_id = ? AND user_id = ?')
        ->execute([$role, $designId, $userId]);
}

/** Le propriétaire change les droits d’un enseignant déjà invité. */
function native_api_collaborator_role(PDO $db, int $userId, array $input): array
{
    $designId = native_collaboration_design_id($input);
    $target = $input['user_id'] ?? null;
    if (!is_int($target) || $target <= 0) {
        throw new NativeRequestError('invalid_request', 'Identifiant de compte invalide.', 422);
    }
    $role = native_requested_role($input);
    if ($role === null) throw new NativeRequestError('invalid_request', 'Les droits à donner sont requis.', 422);
    $access = native_design_access($db, $userId, $designId);
    if (!$access) throw native_collaboration_not_found();
    if ($access['role'] !== 'owner') {
        throw new NativeRequestError('forbidden', 'Seul le propriétaire peut changer les droits des enseignants.', 403);
    }
    $members = array_map(static fn($row) => (int)$row['id'], native_design_collaborators($db, $designId));
    if (!in_array($target, $members, true)) {
        throw new NativeRequestError('invalid_request', 'Cet enseignant n’est pas invité sur ce scénario.', 422);
    }
    native_update_collaborator_role($db, $designId, $target, $role);
    return ['success' => true, 'sharing' => native_sharing_payload($db, $designId, $access)];
}

/** Le propriétaire retire un enseignant ; un enseignant invité peut seulement quitter le partage. */
function native_api_unshare(PDO $db, int $userId, array $input): array
{
    $designId = native_collaboration_design_id($input);
    $target = $input['user_id'] ?? null;
    if (!is_int($target) || $target <= 0) {
        throw new NativeRequestError('invalid_request', 'Identifiant de compte invalide.', 422);
    }
    $access = native_design_access($db, $userId, $designId);
    if (!$access) throw native_collaboration_not_found();
    if ($access['role'] === 'owner' && $target === $access['owner_user_id']) {
        throw new NativeRequestError('invalid_request', 'Le propriétaire ne peut pas quitter son propre scénario.', 422);
    }
    if ($access['role'] !== 'owner' && $target !== $userId) {
        throw new NativeRequestError('forbidden', 'Seul le propriétaire peut retirer un enseignant.', 403);
    }
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM design_collaborators WHERE design_id = ? AND user_id = ?')->execute([$designId, $target]);
        $db->prepare('DELETE FROM design_presence WHERE design_id = ? AND user_id = ?')->execute([$designId, $target]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
    if ($target === $userId) return ['success' => true, 'sharing' => null];
    return ['success' => true, 'sharing' => native_sharing_payload($db, $designId, $access)];
}

/**
 * Signal de présence d’une fenêtre : il renvoie la révision courante, pour que l’app récupère
 * aussitôt les modifications des autres, et les participants actifs, sans la fenêtre appelante.
 */
function native_api_presence(PDO $db, int $userId, array $input): array
{
    $designId = native_collaboration_design_id($input);
    $clientId = strtolower(native_string($input, 'client_id'));
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $clientId)) {
        throw new NativeRequestError('invalid_request', 'Identifiant de fenêtre invalide.', 422);
    }
    $focus = $input['focus'] ?? null;
    if ($focus !== null && !is_string($focus)) {
        throw new NativeRequestError('invalid_request', 'Emplacement de travail invalide.', 422);
    }
    if (is_string($focus)) {
        $focus = preg_replace('/\p{Cc}+/u', '', $focus);
        if ($focus === null) throw new NativeRequestError('invalid_request', 'Emplacement de travail invalide.', 422);
        // Coupé à la limite de la colonne sans scinder un caractère UTF-8.
        $focus = mb_strcut($focus, 0, NATIVE_PRESENCE_FOCUS_BYTES, 'UTF-8');
        if (trim($focus) === '') $focus = null;
    }
    $access = native_design_access($db, $userId, $designId);
    if (!$access) throw native_collaboration_not_found();
    $now = time();
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    if (($input['leave'] ?? null) === true) {
        $db->prepare('DELETE FROM design_presence WHERE design_id = ? AND user_id = ? AND client_id = ?')
            ->execute([$designId, $userId, $clientId]);
    } else {
        $db->prepare('INSERT INTO design_presence (design_id, user_id, client_id, focus, updated_at) VALUES (?, ?, ?, ?, ?)'
            . ($sqlite
                ? ' ON CONFLICT(design_id, user_id, client_id) DO UPDATE SET focus = excluded.focus, updated_at = excluded.updated_at'
                : ' ON DUPLICATE KEY UPDATE focus = VALUES(focus), updated_at = VALUES(updated_at)'))
            ->execute([$designId, $userId, $clientId, $focus, $now]);
    }
    $db->prepare('DELETE FROM design_presence WHERE design_id = ? AND updated_at < ?')
        ->execute([$designId, $now - NATIVE_PRESENCE_RETENTION_SECONDS]);

    $collaborators = native_design_collaborators($db, $designId);
    $members = [$access['owner_user_id'] => true];
    foreach ($collaborators as $row) $members[(int)$row['id']] = true;
    $query = $db->prepare('SELECT p.user_id, p.client_id, p.focus, p.updated_at, u.username
        FROM design_presence p JOIN users u ON u.id = p.user_id
        WHERE p.design_id = ? AND p.updated_at >= ? ORDER BY u.username, p.client_id');
    $query->execute([$designId, $now - NATIVE_PRESENCE_ACTIVE_SECONDS]);
    $participants = [];
    foreach ($query->fetchAll() as $row) {
        $participant = (int)$row['user_id'];
        if (!isset($members[$participant])) continue;
        if ($participant === $userId && $row['client_id'] === $clientId) continue;
        $participants[] = [
            'userId' => $participant, 'username' => (string)$row['username'], 'clientId' => (string)$row['client_id'],
            'focus' => $row['focus'] === null ? null : (string)$row['focus'], 'updatedAt' => (int)$row['updated_at'],
        ];
    }
    return ['success' => true, 'presence' => [
        'revision' => $access['revision'], 'shared' => $collaborators !== [], 'participants' => $participants,
    ]];
}
