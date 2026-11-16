<?php
declare(strict_types=1);

/** Additive migration: native identities never replace website accounts or scenario IDs. */
function ensure_native_app_schema(PDO $db): void
{
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $userId = $sqlite ? 'INTEGER' : 'INT UNSIGNED';
    $designId = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
    $suffix = $sqlite ? '' : ' ENGINE=InnoDB';
    $db->exec("CREATE TABLE IF NOT EXISTS native_app_sessions (
        id CHAR(32) NOT NULL PRIMARY KEY,
        user_id $userId NOT NULL,
        client_id VARCHAR(64) NOT NULL,
        name VARCHAR(120) NOT NULL,
        scope VARCHAR(128) NOT NULL,
        access_token_hash CHAR(64) NOT NULL UNIQUE,
        access_expires_at BIGINT NOT NULL,
        expires_at BIGINT NOT NULL,
        created_at BIGINT NOT NULL,
        last_used_at BIGINT NULL,
        revoked_at BIGINT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )$suffix");
    $db->exec("CREATE TABLE IF NOT EXISTS native_oauth_codes (
        code_hash CHAR(64) NOT NULL PRIMARY KEY,
        user_id $userId NOT NULL,
        client_id VARCHAR(64) NOT NULL,
        redirect_uri VARCHAR(512) NOT NULL,
        challenge CHAR(43) NOT NULL,
        scope VARCHAR(128) NOT NULL,
        expires_at BIGINT NOT NULL,
        consumed_at BIGINT NULL,
        session_id CHAR(32) NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )$suffix");
    $db->exec("CREATE TABLE IF NOT EXISTS native_refresh_tokens (
        token_hash CHAR(64) NOT NULL PRIMARY KEY,
        session_id CHAR(32) NOT NULL,
        expires_at BIGINT NOT NULL,
        consumed_at BIGINT NULL,
        FOREIGN KEY (session_id) REFERENCES native_app_sessions(id) ON DELETE CASCADE
    )$suffix");
    // Keep this association after a scenario is deleted: a delayed retry must not recreate it.
    $db->exec("CREATE TABLE IF NOT EXISTS native_creations (
        owner_user_id $userId NOT NULL,
        client_uuid CHAR(36) NOT NULL,
        design_id $designId NULL,
        created_at BIGINT NOT NULL,
        PRIMARY KEY (owner_user_id, client_uuid),
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
    )$suffix");
    $db->exec("CREATE TABLE IF NOT EXISTS native_design_deletions (
        owner_user_id $userId NOT NULL,
        design_id $designId NOT NULL,
        deleted_at BIGINT NOT NULL,
        PRIMARY KEY (owner_user_id, design_id),
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
    )$suffix");
    $indexes = [
        ['native_app_sessions', 'idx_native_sessions_user', 'user_id, revoked_at'],
        ['native_oauth_codes', 'idx_native_codes_expiry', 'expires_at'],
        ['native_refresh_tokens', 'idx_native_refresh_session', 'session_id'],
    ];
    foreach ($indexes as [$table, $name, $columns]) {
        if ($sqlite) {
            $db->exec("CREATE INDEX IF NOT EXISTS $name ON $table ($columns)");
        } else {
            $query = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
            $query->execute([$table, $name]);
            if ((int)$query->fetchColumn() > 0) continue;
            try {
                $db->exec("CREATE INDEX $name ON $table ($columns)");
            } catch (PDOException $error) {
                $query->execute([$table, $name]);
                if ((int)$query->fetchColumn() === 0) throw $error;
            }
        }
    }
}
