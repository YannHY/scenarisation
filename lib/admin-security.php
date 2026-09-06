<?php
declare(strict_types=1);

function ensure_admin_security_table(PDO $db): void
{
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    // Deliberately independent of users: deletion must preserve the intervention record.
    $db->exec("CREATE TABLE IF NOT EXISTS admin_security_log (
        id $id,
        actor_id BIGINT NOT NULL,
        actor_name VARCHAR(255) NOT NULL,
        target_id BIGINT NOT NULL,
        target_name VARCHAR(255) NOT NULL,
        action VARCHAR(20) NOT NULL,
        reason TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )" . ($sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'));
}

function admin_moderate_user(PDO $db, int $actorId, int $targetId, string $action, string $reason, string $confirmation = ''): void
{
    if (!in_array($action, ['suspend', 'reactivate', 'delete'], true)) {
        throw new InvalidArgumentException('Action de sécurité invalide.');
    }
    $reason = trim($reason);
    if ($reason === '' || strlen($reason) > 2000 || preg_match('//u', $reason) !== 1 || preg_match_all('/./us', $reason) > 500) {
        throw new InvalidArgumentException('Indiquez un motif de 500 caractères maximum.');
    }
    $db->beginTransaction();
    try {
        // Serialize moderation, including concurrent interventions by two administrators.
        $db->exec("UPDATE users SET status = status WHERE role = 'admin'");
        $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $db->prepare('SELECT id, username, role, status FROM users WHERE id = ?' . $lock);
        $stmt->execute([$actorId]);
        $actor = $stmt->fetch();
        if (!$actor || $actor['role'] !== 'admin' || $actor['status'] !== 'active') {
            throw new InvalidArgumentException('Cette action est réservée aux administrateurs actifs.');
        }
        if ($actorId === $targetId) {
            throw new InvalidArgumentException('Vous ne pouvez pas intervenir sur votre propre compte ici.');
        }
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) throw new InvalidArgumentException('Ce compte n’existe plus.');
        if ($target['role'] === 'admin' && $target['status'] === 'active' && $action !== 'reactivate') {
            $count = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?");
            $count->execute([$targetId]);
            if ((int)$count->fetchColumn() === 0) {
                throw new InvalidArgumentException('Impossible de supprimer ou suspendre le dernier administrateur actif.');
            }
        }
        if ($action === 'delete') {
            if ($confirmation !== (string)$target['username']) {
                throw new InvalidArgumentException('Recopiez exactement le nom d’utilisateur pour confirmer la suppression.');
            }
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
        } else {
            $status = $action === 'suspend' ? 'disabled' : 'active';
            if ($target['status'] === $status) throw new InvalidArgumentException('Ce compte possède déjà ce statut.');
            $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $targetId]);
            if ($action === 'suspend') {
                $db->prepare('UPDATE learning_designs SET is_published = 0, is_listed = 0 WHERE owner_user_id = ?')->execute([$targetId]);
                $db->prepare('UPDATE learning_cli_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND revoked_at IS NULL')->execute([$targetId]);
                $db->prepare('UPDATE users SET password_reset_token_hash = NULL, password_reset_expires_at = NULL WHERE id = ?')->execute([$targetId]);
            }
        }
        $db->prepare('INSERT INTO admin_security_log (actor_id, actor_name, target_id, target_name, action, reason) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$actorId, $actor['username'], $targetId, $target['username'], $action, $reason]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
