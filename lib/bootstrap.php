<?php
declare(strict_types=1);

const APP_SCHEMA_VERSION = 5;
const EMAIL_VERIFICATION_TTL_SECONDS = 86400;
const EMAIL_VERIFICATION_RESEND_DELAY_SECONDS = 60;
const PASSWORD_RESET_TTL_SECONDS = 3600;
const PASSWORD_RESET_RESEND_DELAY_SECONDS = 60;

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Une session ne peut plus démarrer une fois les en-têtes envoyés. Sans ce
    // garde-fou, PHP affichait trois avertissements au milieu de la page — avec
    // les chemins absolus du serveur — et la navigation présentait un visiteur
    // connecté comme déconnecté. La page doit appeler cette fonction avant
    // d'émettre du HTML ; on trace le manquement au lieu de défigurer la page.
    if (headers_sent($fichier, $ligne)) {
        error_log(sprintf(
            'Scenarisation : session demandée après envoi des en-têtes (sortie démarrée dans %s ligne %d).'
                . ' Appelez app_start_session() avant tout HTML.',
            (string)$fichier,
            (int)$ligne
        ));
        return;
    }

    ini_set('session.use_strict_mode', '1');
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function app_file_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $config = [];
    $projectRoot = dirname(__DIR__);
    $parent1 = dirname($projectRoot);
    $parent2 = dirname($parent1);
    $parent3 = dirname($parent2);
    $candidates = [
        $projectRoot . '/learning-design-secret.php',
        $projectRoot . '/config.local.php',
        $parent1 . '/learning-design-secret.php',
        $parent2 . '/learning-design-secret.php',
        $parent3 . '/learning-design-secret.php',
        $parent1 . '/config.local.php',
        $parent2 . '/config.local.php',
        $parent3 . '/config.local.php',
        // Local configuration takes precedence over the distributed defaults.
        $projectRoot . '/app-config.php',
    ];

    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        $loaded = require $path;
        if (is_array($loaded)) {
            foreach ($loaded as $key => $value) {
                $valueString = is_scalar($value) ? trim((string)$value) : '';
                $currentString = array_key_exists($key, $config) && is_scalar($config[$key])
                    ? trim((string)$config[$key])
                    : '';
                if (!array_key_exists($key, $config) || ($currentString === '' && $valueString !== '')) {
                    $config[$key] = $value;
                }
            }
        }
    }

    return $config;
}

function app_env(string $key): ?string
{
    $envValue = getenv($key);
    if ($envValue !== false && $envValue !== '') {
        return (string)$envValue;
    }

    if (isset($_SERVER[$key]) && (string)$_SERVER[$key] !== '') {
        return (string)$_SERVER[$key];
    }

    $fileConfig = app_file_config();
    if (array_key_exists($key, $fileConfig) && (string)$fileConfig[$key] !== '') {
        return (string)$fileConfig[$key];
    }

    return null;
}

function app_is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function app_base_url(): string
{
    $configured = trim((string)(app_env('APP_BASE_URL') ?? ''));
    // Ignore sample configuration left behind during installation.
    $configuredHost = strtolower(rtrim((string)(parse_url($configured, PHP_URL_HOST) ?? ''), '.'));
    if ($configuredHost === 'example.com' || str_ends_with($configuredHost, '.example.com')) {
        $configured = '';
    }
    if ($configured !== '') {
        $base = rtrim($configured, '/');
        $configuredPath = (string)(parse_url($base, PHP_URL_PATH) ?? '');
        if ($configuredPath !== '' && $configuredPath !== '/') {
            return $base;
        }

        $scriptDir = app_script_base_path();
        return $base . $scriptDir;
    }

    $scheme = app_is_https() ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    if ($host === '') {
        $host = 'localhost';
    }

    return $scheme . '://' . $host . app_script_base_path();
}

function app_script_base_path(): string
{
    $scriptDir = trim((string)dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $scriptDir = str_replace('\\', '/', $scriptDir);
    if ($scriptDir === '/' || $scriptDir === '.') {
        return '';
    }

    return '/' . trim($scriptDir, '/');
}

function app_origin_url(): string
{
    $scheme = app_is_https() ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    if ($host === '') {
        $host = 'localhost';
    }
    return $scheme . '://' . $host;
}

function app_default_sqlite_path(): string
{
    return dirname(__DIR__) . '/data/learning-designer.sqlite';
}

function app_db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }

    $dsn = trim((string)(app_env('APP_DB_DSN') ?? ''));
    $dbUser = (string)(app_env('APP_DB_USER') ?? '');
    $dbPass = (string)(app_env('APP_DB_PASS') ?? '');

    if ($dsn === '') {
        $dbHost = trim((string)(app_env('APP_DB_HOST') ?? ''));
        $dbName = trim((string)(app_env('APP_DB_NAME') ?? ''));
        if ($dbHost !== '' && $dbName !== '') {
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
        } else {
            $sqlitePath = trim((string)(app_env('APP_DB_SQLITE_PATH') ?? ''));
            if ($sqlitePath === '') {
                $sqlitePath = app_default_sqlite_path();
            }
            $sqliteDir = dirname($sqlitePath);
            if (!is_dir($sqliteDir) && !mkdir($sqliteDir, 0775, true) && !is_dir($sqliteDir)) {
                throw new RuntimeException("Impossible de creer le dossier de stockage local.");
            }
            $dsn = 'sqlite:' . $sqlitePath;
        }
    }

    if ($dsn === '') {
        throw new RuntimeException(
            "Configuration base de donnees manquante."
        );
    }

    $isSqlite = str_starts_with($dsn, 'sqlite:');
    if (!$isSqlite && $dbUser === '') {
        throw new RuntimeException(
            "Configuration base de donnees manquante (APP_DB_DSN ou APP_DB_HOST/APP_DB_NAME + APP_DB_USER + APP_DB_PASS)."
        );
    }

    $db = new PDO($dsn, $isSqlite ? null : $dbUser, $isSqlite ? null : $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($isSqlite) {
        $db->exec('PRAGMA foreign_keys = ON');
    }

    ensure_app_schema($db);
    return $db;
}

function ensure_app_schema(PDO $db): void
{
    $currentVersion = null;
    try {
        $currentVersion = $db->query("SELECT schema_version FROM app_schema_meta WHERE id = 1")
            ->fetchColumn();
    } catch (PDOException) {
        // Existing installations do not have the version table yet. The
        // idempotent bootstrap below upgrades them without a deployment step.
    }

    if ($currentVersion !== false && $currentVersion !== null && (int)$currentVersion >= APP_SCHEMA_VERSION) {
        return;
    }

    // A v4 upgrade only adds revisions; do not replay historical data backfills.
    if ($currentVersion === false || $currentVersion === null || (int)$currentVersion < 4) {
        ensure_app_tables($db);
        ensure_app_migrations($db);
    }
    ensure_design_revision_column($db);
    ensure_app_schema_meta_table($db);

    $stmt = $db->prepare("SELECT schema_version FROM app_schema_meta WHERE id = 1");
    $stmt->execute();
    if ($stmt->fetchColumn() === false) {
        try {
            $db->prepare("INSERT INTO app_schema_meta (id, schema_version) VALUES (1, ?)")
                ->execute([APP_SCHEMA_VERSION]);
        } catch (PDOException) {
            // Another first request may have completed the bootstrap in
            // parallel. Updating the singleton row is safe in that case.
            $db->prepare("UPDATE app_schema_meta SET schema_version = ? WHERE id = 1")
                ->execute([APP_SCHEMA_VERSION]);
        }
    } else {
        $db->prepare("UPDATE app_schema_meta SET schema_version = ? WHERE id = 1")
            ->execute([APP_SCHEMA_VERSION]);
    }
}

function ensure_design_revision_column(PDO $db): void
{
    $isSqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $exists = static function () use ($db, $isSqlite): bool {
        if ($isSqlite) {
            return in_array('revision', array_column($db->query("PRAGMA table_info(learning_designs)")->fetchAll(), 'name'), true);
        }
        return (int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_designs' AND COLUMN_NAME = 'revision'")->fetchColumn() > 0;
    };
    if ($exists()) return;
    try {
        $db->exec('ALTER TABLE learning_designs ADD COLUMN revision '
            . ($isSqlite ? 'INTEGER' : 'BIGINT UNSIGNED') . ' NOT NULL DEFAULT 1');
    } catch (PDOException $error) {
        // Another request may have completed this migration concurrently.
        if (!$exists()) throw $error;
    }
}

function ensure_app_schema_meta_table(PDO $db): void
{
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS app_schema_meta (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            schema_version INTEGER NOT NULL
        )");
        return;
    }

    $db->exec("CREATE TABLE IF NOT EXISTS app_schema_meta (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        schema_version INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensure_app_tables(PDO $db): void
{
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'designer' CHECK (role IN ('admin','designer')),
            status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','disabled')),
            email_verified_at TEXT NULL,
            email_verification_token_hash TEXT NULL,
            email_verification_expires_at INTEGER NULL,
            email_verification_sent_at INTEGER NULL,
            password_reset_token_hash TEXT NULL,
            password_reset_expires_at INTEGER NULL,
            password_reset_sent_at INTEGER NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login_at TEXT NULL
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS learning_designs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            owner_user_id INTEGER NOT NULL,
            title TEXT NOT NULL DEFAULT '',
            document_json TEXT NOT NULL,
            revision INTEGER NOT NULL DEFAULT 1,
            share_token TEXT NULL,
            license_code TEXT NULL,
            is_published INTEGER NOT NULL DEFAULT 0,
            is_listed INTEGER NOT NULL DEFAULT 0,
            listed_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $db->exec("CREATE INDEX IF NOT EXISTS idx_learning_designs_owner ON learning_designs(owner_user_id)");

        $db->exec("CREATE TABLE IF NOT EXISTS learning_cli_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL DEFAULT 'CLI',
            token_hash TEXT NOT NULL UNIQUE,
            token_prefix TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at TEXT NULL,
            revoked_at TEXT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $db->exec("CREATE INDEX IF NOT EXISTS idx_learning_cli_tokens_user ON learning_cli_tokens(user_id)");

        $db->exec("CREATE TABLE IF NOT EXISTS app_feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rating TEXT NOT NULL CHECK (rating IN ('positive','neutral','negative')),
            comment TEXT NULL,
            page_path TEXT NOT NULL DEFAULT '',
            locale TEXT NOT NULL DEFAULT 'fr',
            visitor_hash TEXT NOT NULL,
            created_at_epoch INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_app_feedback_created ON app_feedback(created_at_epoch DESC)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_app_feedback_visitor ON app_feedback(visitor_hash, created_at_epoch DESC)");
        return;
    }

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','designer') NOT NULL DEFAULT 'designer',
        status ENUM('active','disabled') NOT NULL DEFAULT 'active',
        email_verified_at DATETIME NULL,
        email_verification_token_hash CHAR(64) NULL,
        email_verification_expires_at BIGINT UNSIGNED NULL,
        email_verification_sent_at BIGINT UNSIGNED NULL,
        password_reset_token_hash CHAR(64) NULL,
        password_reset_expires_at BIGINT UNSIGNED NULL,
        password_reset_sent_at BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login_at DATETIME NULL,
        UNIQUE INDEX idx_users_email_verification_token (email_verification_token_hash),
        UNIQUE INDEX idx_users_password_reset_token (password_reset_token_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS learning_designs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        owner_user_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT '',
        document_json LONGTEXT NOT NULL,
        revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
        share_token VARCHAR(64) NULL UNIQUE,
        license_code VARCHAR(24) NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        is_listed TINYINT(1) NOT NULL DEFAULT 0,
        listed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_learning_designs_owner (owner_user_id),
        INDEX idx_learning_designs_owner_updated (owner_user_id, updated_at),
        INDEX idx_learning_designs_owner_published_updated (owner_user_id, is_published, updated_at),
        INDEX idx_learning_designs_listed (is_published, is_listed, updated_at),
        INDEX idx_learning_designs_catalogue (is_published, is_listed, listed_at, id),
        CONSTRAINT fk_learning_designs_owner
            FOREIGN KEY (owner_user_id) REFERENCES users(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS learning_cli_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL DEFAULT 'CLI',
        token_hash CHAR(64) NOT NULL UNIQUE,
        token_prefix VARCHAR(16) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NULL,
        revoked_at DATETIME NULL,
        INDEX idx_learning_cli_tokens_user (user_id),
        CONSTRAINT fk_learning_cli_tokens_user
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS app_feedback (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        rating VARCHAR(16) NOT NULL,
        comment TEXT NULL,
        page_path VARCHAR(500) NOT NULL DEFAULT '',
        locale VARCHAR(8) NOT NULL DEFAULT 'fr',
        visitor_hash CHAR(64) NOT NULL,
        created_at_epoch BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_app_feedback_created (created_at_epoch),
        INDEX idx_app_feedback_visitor (visitor_hash, created_at_epoch)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensure_app_migrations(PDO $db): void
{
    $isSqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

    if ($isSqlite) {
        $db->exec("CREATE TABLE IF NOT EXISTS learning_cli_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL DEFAULT 'CLI',
            token_hash TEXT NOT NULL UNIQUE,
            token_prefix TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at TEXT NULL,
            revoked_at TEXT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_learning_cli_tokens_user ON learning_cli_tokens(user_id)");

        $userCols = $db->query("PRAGMA table_info(users)")->fetchAll();
        $userColNames = array_column($userCols, 'name');
        if (!in_array('email_verified_at', $userColNames, true)) {
            $db->exec("ALTER TABLE users ADD COLUMN email_verified_at TEXT NULL");
        }
        if (!in_array('email_verification_token_hash', $userColNames, true)) {
            $db->exec("ALTER TABLE users ADD COLUMN email_verification_token_hash TEXT NULL");
        }
        if (!in_array('email_verification_expires_at', $userColNames, true)) {
            $db->exec("ALTER TABLE users ADD COLUMN email_verification_expires_at INTEGER NULL");
        }
        if (!in_array('email_verification_sent_at', $userColNames, true)) {
            $db->exec("ALTER TABLE users ADD COLUMN email_verification_sent_at INTEGER NULL");
        }
        if (!in_array('password_reset_token_hash', $userColNames, true)) {
            $db->exec("ALTER TABLE users ADD COLUMN password_reset_token_hash TEXT NULL");
        }
        if (!in_array('password_reset_expires_at', $userColNames, true)) {
            $db->exec("ALTER TABLE users ADD COLUMN password_reset_expires_at INTEGER NULL");
        }
        if (!in_array('password_reset_sent_at', $userColNames, true)) {
            $db->exec("ALTER TABLE users ADD COLUMN password_reset_sent_at INTEGER NULL");
        }
        // Accounts created before email verification existed remain usable.
        $db->exec("UPDATE users
            SET email_verified_at = CURRENT_TIMESTAMP
            WHERE email_verified_at IS NULL
              AND email_verification_token_hash IS NULL");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_verification_token ON users(email_verification_token_hash) WHERE email_verification_token_hash IS NOT NULL");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_password_reset_token ON users(password_reset_token_hash) WHERE password_reset_token_hash IS NOT NULL");

        $cols = $db->query("PRAGMA table_info(learning_designs)")->fetchAll();
        $colNames = array_column($cols, 'name');
        if (!in_array('share_token', $colNames, true)) {
            $db->exec("ALTER TABLE learning_designs ADD COLUMN share_token TEXT NULL");
        }
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_learning_designs_share_token ON learning_designs(share_token) WHERE share_token IS NOT NULL");
        if (!in_array('is_published', $colNames, true)) {
            $db->exec("ALTER TABLE learning_designs ADD COLUMN is_published INTEGER NOT NULL DEFAULT 0");
        }
        if (!in_array('is_listed', $colNames, true)) {
            $db->exec("ALTER TABLE learning_designs ADD COLUMN is_listed INTEGER NOT NULL DEFAULT 0");
        }
        if (!in_array('listed_at', $colNames, true)) {
            $db->exec("ALTER TABLE learning_designs ADD COLUMN listed_at TEXT NULL");
        }
        if (!in_array('license_code', $colNames, true)) {
            $db->exec("ALTER TABLE learning_designs ADD COLUMN license_code TEXT NULL");
        }
        // Designs already present in the public catalogue predate the license
        // selector. Keep their existing publication terms explicit.
        $db->exec("UPDATE learning_designs
            SET license_code = 'cc-by-sa'
            WHERE is_published = 1
              AND is_listed = 1
              AND (license_code IS NULL OR TRIM(license_code) = '')");
        $db->exec("UPDATE learning_designs
            SET listed_at = updated_at
            WHERE is_published = 1
              AND is_listed = 1
              AND listed_at IS NULL");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_learning_designs_listed ON learning_designs(is_published, is_listed, updated_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_learning_designs_owner_updated ON learning_designs(owner_user_id, updated_at DESC)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_learning_designs_owner_published_updated ON learning_designs(owner_user_id, is_published, updated_at DESC)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_learning_designs_catalogue ON learning_designs(is_published, is_listed, listed_at DESC, id DESC)");
        return;
    }

    $db->exec("CREATE TABLE IF NOT EXISTS learning_cli_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL DEFAULT 'CLI',
        token_hash CHAR(64) NOT NULL UNIQUE,
        token_prefix VARCHAR(16) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NULL,
        revoked_at DATETIME NULL,
        INDEX idx_learning_cli_tokens_user (user_id),
        CONSTRAINT fk_learning_cli_tokens_user_migration
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $userColumns = [
        'email_verified_at' => "ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL",
        'email_verification_token_hash' => "ALTER TABLE users ADD COLUMN email_verification_token_hash CHAR(64) NULL",
        'email_verification_expires_at' => "ALTER TABLE users ADD COLUMN email_verification_expires_at BIGINT UNSIGNED NULL",
        'email_verification_sent_at' => "ALTER TABLE users ADD COLUMN email_verification_sent_at BIGINT UNSIGNED NULL",
        'password_reset_token_hash' => "ALTER TABLE users ADD COLUMN password_reset_token_hash CHAR(64) NULL",
        'password_reset_expires_at' => "ALTER TABLE users ADD COLUMN password_reset_expires_at BIGINT UNSIGNED NULL",
        'password_reset_sent_at' => "ALTER TABLE users ADD COLUMN password_reset_sent_at BIGINT UNSIGNED NULL",
    ];
    $userColumnCheck = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
    foreach ($userColumns as $columnName => $sql) {
        $userColumnCheck->execute([$columnName]);
        if ((int)$userColumnCheck->fetchColumn() === 0) {
            $db->exec($sql);
        }
    }
    // Accounts created before email verification existed remain usable.
    $db->exec("UPDATE users
        SET email_verified_at = CURRENT_TIMESTAMP
        WHERE email_verified_at IS NULL
          AND email_verification_token_hash IS NULL");
    $verificationIndexCheck = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_email_verification_token'");
    $verificationIndexCheck->execute();
    if ((int)$verificationIndexCheck->fetchColumn() === 0) {
        $db->exec("CREATE UNIQUE INDEX idx_users_email_verification_token ON users(email_verification_token_hash)");
    }
    $resetIndexCheck = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_password_reset_token'");
    $resetIndexCheck->execute();
    if ((int)$resetIndexCheck->fetchColumn() === 0) {
        $db->exec("CREATE UNIQUE INDEX idx_users_password_reset_token ON users(password_reset_token_hash)");
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_designs' AND COLUMN_NAME = 'share_token'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE learning_designs ADD COLUMN share_token VARCHAR(64) NULL UNIQUE");
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_designs' AND COLUMN_NAME = 'is_published'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE learning_designs ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0");
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_designs' AND COLUMN_NAME = 'is_listed'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE learning_designs ADD COLUMN is_listed TINYINT(1) NOT NULL DEFAULT 0");
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_designs' AND COLUMN_NAME = 'listed_at'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE learning_designs ADD COLUMN listed_at DATETIME NULL");
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_designs' AND COLUMN_NAME = 'license_code'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE learning_designs ADD COLUMN license_code VARCHAR(24) NULL");
    }
    // Backfill the legacy catalogue entries. New catalogue publications must
    // provide their own valid Creative Commons license in publish_design.php.
    $db->exec("UPDATE learning_designs
        SET license_code = 'cc-by-sa'
        WHERE is_published = 1
          AND is_listed = 1
          AND (license_code IS NULL OR TRIM(license_code) = '')");
    $db->exec("UPDATE learning_designs
        SET listed_at = updated_at
        WHERE is_published = 1
          AND is_listed = 1
          AND listed_at IS NULL");

    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_designs' AND INDEX_NAME = 'idx_learning_designs_listed'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $db->exec("CREATE INDEX idx_learning_designs_listed ON learning_designs(is_published, is_listed, updated_at)");
    }

    $mysqlIndexes = [
        'idx_learning_designs_owner_updated' => 'CREATE INDEX idx_learning_designs_owner_updated ON learning_designs(owner_user_id, updated_at)',
        'idx_learning_designs_owner_published_updated' => 'CREATE INDEX idx_learning_designs_owner_published_updated ON learning_designs(owner_user_id, is_published, updated_at)',
        'idx_learning_designs_catalogue' => 'CREATE INDEX idx_learning_designs_catalogue ON learning_designs(is_published, is_listed, listed_at, id)',
    ];
    $indexCheck = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_designs' AND INDEX_NAME = ?");
    foreach ($mysqlIndexes as $indexName => $sql) {
        $indexCheck->execute([$indexName]);
        if ((int)$indexCheck->fetchColumn() === 0) {
            $db->exec($sql);
        }
    }
}

/**
 * Creative Commons 4.0 licenses available when publishing a design.
 *
 * @return array<string, array{label: string, url: string}>
 */
function creative_commons_licenses(): array
{
    return [
        'cc-by' => [
            'label' => 'CC BY 4.0',
            'url' => 'https://creativecommons.org/licenses/by/4.0/',
        ],
        'cc-by-sa' => [
            'label' => 'CC BY-SA 4.0',
            'url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
        ],
        'cc-by-nd' => [
            'label' => 'CC BY-ND 4.0',
            'url' => 'https://creativecommons.org/licenses/by-nd/4.0/',
        ],
        'cc-by-nc' => [
            'label' => 'CC BY-NC 4.0',
            'url' => 'https://creativecommons.org/licenses/by-nc/4.0/',
        ],
        'cc-by-nc-sa' => [
            'label' => 'CC BY-NC-SA 4.0',
            'url' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
        ],
        'cc-by-nc-nd' => [
            'label' => 'CC BY-NC-ND 4.0',
            'url' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
        ],
    ];
}

function creative_commons_license(string $code): ?array
{
    return creative_commons_licenses()[strtolower(trim($code))] ?? null;
}

function current_user(): ?array
{
    app_start_session();
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return null;
    }
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if ($userId <= 0) {
        unset($_SESSION['user']);
        return null;
    }
    $stmt = app_db()->prepare("SELECT id, username, email, role, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user']);
        return null;
    }
    // Session data is a cache of identity, never the authority for permissions.
    return $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'email' => (string)$user['email'],
        'role' => (string)$user['role'],
    ];
}

function require_login_json(): array
{
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => 'Authentification requise']);
        exit;
    }
    return $user;
}

function require_login_page(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function require_cli_token_json(): array
{
    $header = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    $token = trim((string)($_SERVER['HTTP_X_LEARNING_CLI_TOKEN'] ?? ''));
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strcasecmp((string)$key, 'Authorization') === 0) {
                $header = trim((string)$value);
            }
            if ($token === '' && strcasecmp((string)$key, 'X-Learning-CLI-Token') === 0) {
                $token = trim((string)$value);
            }
        }
    }
    if ($token === '' && preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        $token = trim((string)$matches[1]);
    }
    if ($token === '') {
        app_json_response(['success' => false, 'error' => 'Jeton CLI requis.'], 401);
    }

    $db = app_db();
    $hash = hash('sha256', $token);
    $stmt = $db->prepare("SELECT t.id AS token_id, u.id, u.username, u.email, u.role, u.status
        FROM learning_cli_tokens t
        JOIN users u ON u.id = t.user_id
        WHERE t.token_hash = ? AND t.revoked_at IS NULL
        LIMIT 1");
    $stmt->execute([$hash]);
    $user = $stmt->fetch();
    if (!$user || (string)($user['status'] ?? '') !== 'active') {
        app_json_response(['success' => false, 'error' => 'Jeton CLI invalide.'], 401);
    }

    $db->prepare("UPDATE learning_cli_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([(int)$user['token_id']]);

    return [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'email' => (string)$user['email'],
        'role' => (string)$user['role'],
    ];
}

function require_admin_page(): array
{
    $user = require_login_page();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Acces refuse.';
        exit;
    }
    return $user;
}

function is_admin_seed_needed(PDO $db): bool
{
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    return ((int)$stmt->fetchColumn()) === 0;
}

function sanitize_username(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', '_', $value) ?? '';
    $value = preg_replace('/[^\p{L}\p{N}_.-]/u', '', $value) ?? '';
    return mb_substr($value, 0, 80, 'UTF-8');
}

function is_florimont_email(string $email): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $atPosition = strrpos($email, '@');
    if ($atPosition === false) {
        return false;
    }

    return strtolower(substr($email, $atPosition + 1)) === 'florimont.ch';
}

/**
 * Create and store a one-time email verification token.
 *
 * The raw token is returned to the caller and only its SHA-256 hash is stored.
 */
function create_email_verification_token(PDO $db, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("UPDATE users
        SET email_verification_token_hash = ?,
            email_verification_expires_at = ?,
            email_verification_sent_at = NULL
        WHERE id = ? AND email_verified_at IS NULL");
    $stmt->execute([
        hash('sha256', $token),
        time() + EMAIL_VERIFICATION_TTL_SECONDS,
        $userId,
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Impossible de créer le lien de vérification.');
    }

    return $token;
}

function send_email_verification_message(string $email, string $username, string $token): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
        return false;
    }

    $from = trim((string)(app_env('APP_MAIL_FROM') ?? 'no-reply@ralentirtravaux.com'));
    $fromName = trim((string)(app_env('APP_MAIL_FROM_NAME') ?? 'Scenarisation'));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from . $fromName)) {
        return false;
    }

    $verificationUrl = app_base_url() . '/verify-email.php?token=' . rawurlencode($token);
    $safeUsername = trim(str_replace(["\r", "\n"], ' ', $username));
    $body = "Bonjour {$safeUsername},\n\n"
        . "Confirmez votre adresse email pour activer votre compte Scenarisation :\n"
        . $verificationUrl . "\n\n"
        . "Ce lien est valable pendant 24 heures et ne peut être utilisé qu'une fois.\n\n"
        . "Si vous n'avez pas demandé la création de ce compte, vous pouvez ignorer ce message.\n";

    $subject = 'Confirmez votre adresse email — Scenarisation';
    if (function_exists('mb_encode_mimeheader')) {
        $subject = mb_encode_mimeheader($subject, 'UTF-8');
        $encodedFromName = mb_encode_mimeheader($fromName, 'UTF-8');
    } else {
        $encodedFromName = $fromName;
    }
    $headers = [
        'From: ' . $encodedFromName . ' <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    return mail($email, $subject, wordwrap($body, 78), implode("\r\n", $headers));
}

function mark_email_verification_sent(PDO $db, int $userId): void
{
    $stmt = $db->prepare("UPDATE users SET email_verification_sent_at = ? WHERE id = ? AND email_verified_at IS NULL");
    $stmt->execute([time(), $userId]);
}

/**
 * Create a one-time password reset token without storing the raw value.
 */
function create_password_reset_token(PDO $db, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("UPDATE users
        SET password_reset_token_hash = ?,
            password_reset_expires_at = ?,
            password_reset_sent_at = ?
        WHERE id = ? AND status = 'active'");
    $stmt->execute([
        hash('sha256', $token),
        time() + PASSWORD_RESET_TTL_SECONDS,
        time(),
        $userId,
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Impossible de créer le lien de réinitialisation.');
    }

    return $token;
}

function send_password_reset_message(string $email, string $username, string $token): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
        return false;
    }

    $from = trim((string)(app_env('APP_MAIL_FROM') ?? 'no-reply@ralentirtravaux.com'));
    $fromName = trim((string)(app_env('APP_MAIL_FROM_NAME') ?? 'Scenarisation'));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from . $fromName)) {
        return false;
    }

    $resetUrl = app_base_url() . '/reset-password.php?token=' . rawurlencode($token);
    $safeUsername = trim(str_replace(["\r", "\n"], ' ', $username));
    $body = "Bonjour {$safeUsername},\n\n"
        . "Vous avez demandé la réinitialisation de votre mot de passe Scenarisation :\n"
        . $resetUrl . "\n\n"
        . "Ce lien est valable pendant une heure et ne peut être utilisé qu'une fois.\n\n"
        . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe ne sera pas modifié.\n";

    $subject = 'Réinitialisez votre mot de passe — Scenarisation';
    if (function_exists('mb_encode_mimeheader')) {
        $subject = mb_encode_mimeheader($subject, 'UTF-8');
        $encodedFromName = mb_encode_mimeheader($fromName, 'UTF-8');
    } else {
        $encodedFromName = $fromName;
    }
    $headers = [
        'From: ' . $encodedFromName . ' <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    return mail($email, $subject, wordwrap($body, 78), implode("\r\n", $headers));
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Renvoie le TSV du catalogue des compétences numériques.
 *
 * La source de vérité est js/competency-catalog.js, un fichier dédié à cette
 * seule constante pour que le JS y accède sans requête réseau et que le PHP
 * n'ait pas à lire les 356 Ko de interface.js à chaque affichage d'un design
 * public. view.php et competencies.php passent tous les deux par ici : c'est
 * le seul endroit qui connaît l'emplacement du catalogue.
 *
 * Un échec d'extraction se traduisait par des libellés de compétences vides,
 * sans aucun signal ; il est désormais tracé dans le journal du serveur.
 */
function app_competency_catalog_source(): string
{
    static $source = null;
    if (is_string($source)) {
        return $source;
    }

    $path = __DIR__ . '/../js/competency-catalog.js';
    if (!is_file($path)) {
        error_log('Scenarisation : catalogue de compétences introuvable (' . $path . ').');
        return $source = '';
    }

    $js = (string)file_get_contents($path);
    if (!preg_match('/const\s+COMPETENCY_CATALOG_SOURCE\s*=\s*String\.raw`(.*?)`;/s', $js, $matches)) {
        error_log('Scenarisation : COMPETENCY_CATALOG_SOURCE illisible dans ' . $path
            . ' (constante renommée ou littéral modifié ?).');
        return $source = '';
    }

    $sourceFr = (string)$matches[1];
    if (!preg_match('/const\s+COMPETENCY_CATALOG_EN_SOURCE\s*=\s*String\.raw`(.*?)`;/s', $js, $translationMatches)) {
        error_log('Scenarisation : traductions anglaises du catalogue de compétences illisibles dans ' . $path . '.');
        return $source = $sourceFr;
    }

    $translations = [];
    $levelId = '';
    foreach (preg_split('/\R/u', (string)$translationMatches[1]) ?: [] as $line) {
        $line = str_replace("\r", '', (string)$line);
        if (trim($line) === '') continue;
        if (str_starts_with($line, '# ')) {
            $levelId = trim(substr($line, 2));
            continue;
        }
        [$number, $labelEn, $descEn] = array_pad(explode("\t", $line, 3), 3, '');
        if ($levelId !== '' && trim($number) !== '') {
            $translations[$levelId . ':' . trim($number)] = [$labelEn, $descEn];
        }
    }

    $merged = [];
    $sourceLevelId = '';
    foreach (preg_split('/\R/u', $sourceFr) ?: [] as $line) {
        $line = str_replace("\r", '', (string)$line);
        if (str_starts_with($line, '# ')) {
            $sourceLevelId = trim((string)(explode("\t", substr($line, 2), 2)[0] ?? ''));
            $merged[] = $line;
            continue;
        }
        if (trim($line) === '') {
            $merged[] = $line;
            continue;
        }
        $number = trim((string)(explode("\t", $line, 4)[2] ?? ''));
        [$labelEn, $descEn] = $translations[$sourceLevelId . ':' . $number] ?? ['', ''];
        $merged[] = $line . "\t" . $labelEn . "\t" . $descEn;
    }

    return $source = implode("\n", $merged);
}

/**
 * Renvoie le TSV des référentiels complémentaires depuis la même source de
 * vérité que l'éditeur JavaScript.
 */
function app_competency_framework_catalog_source(): string
{
    static $source = null;
    if (is_string($source)) {
        return $source;
    }

    $path = __DIR__ . '/../js/competency-catalog.js';
    if (!is_file($path)) {
        error_log('Scenarisation : catalogue de cadres de compétences introuvable (' . $path . ').');
        return $source = '';
    }

    $js = (string)file_get_contents($path);
    if (!preg_match('/const\s+COMPETENCY_FRAMEWORK_CATALOG_SOURCE\s*=\s*String\.raw`(.*?)`;/s', $js, $matches)) {
        error_log('Scenarisation : COMPETENCY_FRAMEWORK_CATALOG_SOURCE illisible dans ' . $path . '.');
        return $source = '';
    }

    return $source = (string)$matches[1];
}

/**
 * Renvoie les 362 énoncés de compétence bilingues de DigComp 3.0,
 * répartis selon quatre niveaux de maîtrise.
 */
function app_competency_digcomp_detail_source(): string
{
    static $source = null;
    if (is_string($source)) {
        return $source;
    }

    $path = __DIR__ . '/../js/competency-digcomp-details.js';
    if (!is_file($path)) {
        error_log('Scenarisation : repères DigComp introuvables (' . $path . ').');
        return $source = '';
    }

    $js = (string)file_get_contents($path);
    if (!preg_match('/const\s+COMPETENCY_DIGCOMP_DETAIL_SOURCE\s*=\s*String\.raw`(.*?)`;/s', $js, $matches)) {
        error_log('Scenarisation : COMPETENCY_DIGCOMP_DETAIL_SOURCE illisible dans ' . $path . '.');
        return $source = '';
    }

    return $source = trim((string)$matches[1]);
}

/**
 * Renvoie les descriptions et les 169 repères GreenComp
 * (connaissances, aptitudes et attitudes).
 */
function app_competency_greencomp_detail_source(): string
{
    static $source = null;
    if (is_string($source)) {
        return $source;
    }

    $path = __DIR__ . '/../js/competency-greencomp-details.js';
    if (!is_file($path)) {
        error_log('Scenarisation : repères GreenComp introuvables (' . $path . ').');
        return $source = '';
    }

    $js = (string)file_get_contents($path);
    if (!preg_match('/const\s+COMPETENCY_GREENCOMP_DETAIL_SOURCE\s*=\s*String\.raw`(.*?)`;/s', $js, $matches)) {
        error_log('Scenarisation : COMPETENCY_GREENCOMP_DETAIL_SOURCE illisible dans ' . $path . '.');
        return $source = '';
    }

    return $source = trim((string)$matches[1]);
}

/**
 * Applique le thème sombre avant le premier rendu.
 *
 * Le script de navigation attend DOMContentLoaded pour lire le thème, ce qui
 * laissait apparaître un flash clair sur toutes les pages. À appeler dans le
 * <head>, avant les feuilles de style. Le pendant statique de cette fonction
 * est également utilisé par designer.php.
 */
function render_theme_boot_script(): void
{
    ?>
    <script>
        try {
            if (localStorage.getItem('learningDesignerTheme') === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        } catch (error) {
        }
    </script>
    <?php
}

function render_site_nav(string $active = ''): void
{
    $user = current_user();
    $isDesigner = $active === 'designer';
    $isAdmin = (string)($user['role'] ?? '') === 'admin';
    $username = trim((string)($user['username'] ?? $user['email'] ?? ''));
    $savesClass = $active === 'saves' ? ' nav-account-btn-active' : '';
    $profileClass = $active === 'profile' ? ' nav-account-btn-active' : '';
    $adminClass = $active === 'admin' ? ' nav-account-btn-active' : '';
    $breadcrumbItems = site_breadcrumb_items($active);
    ?>
    <header class="site-nav site-nav-page" role="navigation" aria-label="Navigation principale" data-site-i18n-attr="aria-label" data-site-i18n-en="Main navigation" data-site-i18n-fr="Navigation principale">
        <div class="site-nav-brand">
            <a class="site-nav-brand-link" href="index.php" aria-label="Accueil Scenarisation" data-site-i18n-attr="aria-label" data-site-i18n-en="Scenarisation home" data-site-i18n-fr="Accueil Scenarisation">
                <span class="site-nav-brand-mark" aria-hidden="true"></span>
                <div class="site-nav-brand-copy">
                    <p class="site-nav-title">Scenarisation</p>
                </div>
            </a>
        </div>
        <div id="site-nav-actions" class="site-nav-actions">
            <label for="lang-select" class="sr-only" data-site-i18n-en="Interface language" data-site-i18n-fr="Langue de l'interface">Langue de l'interface</label>
            <div class="nav-language-switch" aria-label="Langue de l'interface" data-site-i18n-attr="aria-label" data-site-i18n-en="Interface language" data-site-i18n-fr="Langue de l'interface">
                <button class="nav-language-toggle" type="button" aria-label="Passer en anglais" title="Passer en anglais">
                    <span class="nav-language-label">FR</span>
                </button>
            </div>
            <select id="lang-select" hidden tabindex="-1" aria-hidden="true">
                <option value="fr">FR</option>
                <option value="en">EN</option>
            </select>
            <button id="theme-toggle-btn" class="theme-toggle-btn" type="button" aria-label="Basculer le thème sombre/clair" title="Thème sombre / clair" data-site-i18n-attr="aria-label,title" data-site-i18n-en="Toggle dark/light theme" data-site-i18n-fr="Basculer le thème sombre/clair">
                <svg class="theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true" width="18" height="18">
                    <path fill="currentColor" d="M12 7a5 5 0 1 0 0 10A5 5 0 0 0 12 7zm0-5a1 1 0 0 1 1 1v1a1 1 0 0 1-2 0V3a1 1 0 0 1 1-1zm0 18a1 1 0 0 1 1 1v1a1 1 0 0 1-2 0v-1a1 1 0 0 1 1-1zM5 12a1 1 0 0 1-1 1H3a1 1 0 0 1 0-2h1a1 1 0 0 1 1 1zm16 0a1 1 0 0 1-1 1h-1a1 1 0 0 1 0-2h1a1 1 0 0 1 1 1zM6.34 7.76a1 1 0 0 1 0-1.42l.7-.7a1 1 0 1 1 1.42 1.42l-.71.71a1 1 0 0 1-1.41-.01zm9.9 9.9a1 1 0 0 1 0-1.42l.7-.7a1 1 0 0 1 1.42 1.42l-.71.71a1 1 0 0 1-1.41-.01zM6.34 17.66a1 1 0 0 1-1.41.01l-.71-.71a1 1 0 0 1 1.42-1.42l.7.7a1 1 0 0 1 0 1.42zM17.66 6.34a1 1 0 0 1-1.41.01l-.71-.71a1 1 0 0 1 1.42-1.42l.7.7a1 1 0 0 1 0 1.42z"/>
                </svg>
                <svg class="theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true" width="18" height="18">
                    <path fill="currentColor" d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/>
                </svg>
            </button>
            <button id="site-search-open" class="nav-icon-btn site-search-open" type="button" aria-label="Rechercher sur le site" title="Rechercher sur le site" aria-keyshortcuts="Meta+K Control+K">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </button>
            <div class="account-toolbar-cluster">
                <?php if ($isDesigner): ?>
                    <button id="nav-new-design-btn" class="nav-icon-btn" type="button" title="Nouveau scénario" aria-label="Nouveau scénario" data-site-i18n-attr="title,aria-label" data-site-i18n-en="New design" data-site-i18n-fr="Nouveau scénario">
                        <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
                    </button>
                <?php else: ?>
                    <a class="nav-icon-btn" href="designer.php" title="Nouveau scénario" aria-label="Nouveau scénario" data-site-i18n-attr="title,aria-label" data-site-i18n-en="New design" data-site-i18n-fr="Nouveau scénario">
                        <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
                <a class="nav-account-btn nav-account-icon-btn<?= $savesClass ?>" href="my-designs.php" title="Scénarios" aria-label="Scénarios" data-site-i18n-attr="title,aria-label" data-site-i18n-en="Designs" data-site-i18n-fr="Scénarios">
                    <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                </a>
                <?php if ($user): ?>
                    <div class="account-menu-wrap">
                        <button id="account-menu-btn" class="nav-account-btn nav-account-icon-btn<?= $profileClass !== '' || $adminClass !== '' ? ' nav-account-btn-active' : '' ?>" type="button" aria-expanded="false" aria-controls="account-menu" title="Compte" aria-label="Compte" data-site-i18n-attr="title,aria-label" data-site-i18n-en="Account" data-site-i18n-fr="Compte">
                            <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                        </button>
                        <div id="account-menu" class="account-menu hidden" role="menu" aria-hidden="true">
                            <a class="account-menu-link<?= $profileClass ?>" role="menuitem" href="profile.php" data-site-i18n-en="Profile" data-site-i18n-fr="Profil">Profil</a>
                            <?php if ($isAdmin): ?>
                                <a class="account-menu-link<?= $adminClass ?>" role="menuitem" href="admin.php" data-site-i18n-en="Administration" data-site-i18n-fr="Administration">Administration</a>
                            <?php endif; ?>
                            <a class="account-menu-link" role="menuitem" href="logout.php" data-site-i18n-en="Sign out" data-site-i18n-fr="Déconnexion">Déconnexion</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="nav-account-btn nav-account-icon-btn<?= $active === 'login' ? ' nav-account-btn-active' : '' ?>" href="login.php" title="Connexion" aria-label="Connexion" data-site-i18n-attr="title,aria-label" data-site-i18n-en="Sign in" data-site-i18n-fr="Connexion">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <button id="nav-hamburger" class="nav-hamburger" type="button" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="site-nav-actions" data-site-i18n-attr="aria-label" data-site-i18n-en="Open menu" data-site-i18n-fr="Ouvrir le menu">
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
        </button>
    </header>
    <?php render_site_breadcrumb($breadcrumbItems); ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var html = document.documentElement;
        var savedTheme = '';
        try {
            savedTheme = localStorage.getItem('learningDesignerTheme') || '';
        } catch (error) {
            savedTheme = '';
        }
        if (savedTheme === 'dark') {
            html.setAttribute('data-theme', 'dark');
        }

        var themeButton = document.getElementById('theme-toggle-btn');
        if (themeButton) {
            themeButton.addEventListener('click', function () {
                var isDark = html.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    html.removeAttribute('data-theme');
                    try {
                        localStorage.setItem('learningDesignerTheme', 'light');
                    } catch (error) {
                    }
                } else {
                    html.setAttribute('data-theme', 'dark');
                    try {
                        localStorage.setItem('learningDesignerTheme', 'dark');
                    } catch (error) {
                    }
                }
            });
        }

        function applySiteNavLanguage(lang) {
            document.querySelectorAll('[data-site-i18n-en]').forEach(function (el) {
                var value = lang === 'en' ? el.dataset.siteI18nEn : el.dataset.siteI18nFr;
                if (!value) return;
                var attrs = (el.dataset.siteI18nAttr || '').split(',').map(function (attr) {
                    return attr.trim();
                }).filter(Boolean);
                if (attrs.length) {
                    attrs.forEach(function (attr) {
                        el.setAttribute(attr, value);
                    });
                } else {
                    el.textContent = value;
                }
            });
        }

        var langSelect = document.getElementById('lang-select');
        var languageButton = document.querySelector('.nav-language-toggle');
        var languageToggleManagedByPage = <?= $isDesigner ? 'true' : 'false' ?>;
        function syncLanguageSwitch(lang) {
            if (!languageButton) return;
            var isEnglish = lang === 'en';
            var label = languageButton.querySelector('.nav-language-label');
            if (label) label.textContent = isEnglish ? 'EN' : 'FR';
            var actionLabel = isEnglish ? 'Switch to French' : 'Passer en anglais';
            languageButton.setAttribute('aria-label', actionLabel);
            languageButton.setAttribute('title', actionLabel);
        }
        function changeLanguage(nextLang) {
            if (!langSelect || langSelect.value === nextLang) return;
            var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (!languageButton || reduceMotion) {
                langSelect.value = nextLang;
                langSelect.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
            languageButton.classList.add('is-leaving');
            window.setTimeout(function () {
                langSelect.value = nextLang;
                langSelect.dispatchEvent(new Event('change', { bubbles: true }));
                languageButton.classList.remove('is-leaving');
                languageButton.classList.add('is-entering');
                window.setTimeout(function () {
                    languageButton.classList.remove('is-entering');
                }, 180);
            }, 90);
        }
        if (langSelect) {
            var savedLang = 'fr';
            try {
                savedLang = localStorage.getItem('learningDesignerLang') || 'fr';
            } catch (error) {
                savedLang = 'fr';
            }
            if (savedLang !== 'fr' && savedLang !== 'en') {
                savedLang = 'fr';
            }
            langSelect.value = savedLang;
            syncLanguageSwitch(savedLang);
            html.setAttribute('lang', savedLang);
            applySiteNavLanguage(savedLang);
            langSelect.addEventListener('change', function () {
                syncLanguageSwitch(langSelect.value);
                html.setAttribute('lang', langSelect.value);
                applySiteNavLanguage(langSelect.value);
                try {
                    localStorage.setItem('learningDesignerLang', langSelect.value);
                } catch (error) {
                }
            });
            if (languageButton && !languageToggleManagedByPage) {
                languageButton.addEventListener('click', function () {
                    changeLanguage(langSelect.value === 'en' ? 'fr' : 'en');
                });
            }
        }

        var hamburger = document.getElementById('nav-hamburger');
        var navActions = document.getElementById('site-nav-actions');
        if (hamburger && navActions) {
            hamburger.addEventListener('click', function () {
                var open = navActions.classList.toggle('nav-open');
                hamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            document.addEventListener('click', function (event) {
                if (!hamburger.contains(event.target) && !navActions.contains(event.target)) {
                    navActions.classList.remove('nav-open');
                    hamburger.setAttribute('aria-expanded', 'false');
                }
            });
        }

        var button = document.getElementById('account-menu-btn');
        var menu = document.getElementById('account-menu');
        if (!button || !menu) {
            return;
        }

        function closeMenu() {
            menu.classList.add('hidden');
            menu.setAttribute('aria-hidden', 'true');
            button.setAttribute('aria-expanded', 'false');
        }

        button.addEventListener('click', function () {
            var opening = menu.classList.contains('hidden');
            if (opening) {
                menu.classList.remove('hidden');
                menu.setAttribute('aria-hidden', 'false');
                button.setAttribute('aria-expanded', 'true');
            } else {
                closeMenu();
            }
        });

        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target) && !button.contains(event.target)) {
                closeMenu();
            }
        });
    });
    </script>
    <script src="js/site-search.js?v=20260906-scenarisation"></script>
    <?php
}

function site_breadcrumb_items(string $active = ''): array
{
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $key = $active !== '' ? $active : pathinfo($page, PATHINFO_FILENAME);
    $map = [
        'home' => [],
        'designer' => [],
        'about' => [
            ['fr' => 'À propos', 'en' => 'About'],
        ],
        'admin' => [
            ['fr' => 'Compte', 'en' => 'Account'],
            ['fr' => 'Administration', 'en' => 'Administration'],
        ],
        'bloom' => [
            ['fr' => 'Aide', 'en' => 'Help', 'href' => 'help.php'],
            ['fr' => 'Taxonomie de Bloom', 'en' => "Bloom's Taxonomy"],
        ],
        'competencies' => [
            ['fr' => 'Aide', 'en' => 'Help', 'href' => 'help.php'],
            ['fr' => 'Référentiels de compétences', 'en' => 'Competency frameworks'],
        ],
        'help' => [
            ['fr' => 'Aide', 'en' => 'Help'],
        ],
        'learning-design' => [
            ['fr' => 'Aide', 'en' => 'Help', 'href' => 'help.php'],
            ['fr' => 'Learning design', 'en' => 'Learning design'],
        ],
        'framework' => [
            ['fr' => 'Aide', 'en' => 'Help', 'href' => 'help.php'],
            ['fr' => 'Learning design', 'en' => 'Learning design', 'href' => 'learning-design.php'],
            ['fr' => 'Cadre conversationnel', 'en' => 'Conversational Framework'],
        ],
        'login' => [
            ['fr' => 'Connexion', 'en' => 'Sign in'],
        ],
        'license' => [
            ['fr' => 'Licence et réutilisation', 'en' => 'License and reuse'],
        ],
        'legal' => [
            ['fr' => 'Mentions légales', 'en' => 'Legal notice'],
        ],
        'forgot_password' => [
            ['fr' => 'Connexion', 'en' => 'Sign in', 'href' => 'login.php'],
            ['fr' => 'Mot de passe oublié', 'en' => 'Forgot password'],
        ],
        'models' => [
            ['fr' => 'Aide', 'en' => 'Help', 'href' => 'help.php'],
            ['fr' => 'Modèles de scénarios', 'en' => 'Scenario templates'],
        ],
        'profile' => [
            ['fr' => 'Compte', 'en' => 'Account'],
            ['fr' => 'Profil', 'en' => 'Profile'],
        ],
        'privacy' => [
            ['fr' => 'Politique de confidentialité', 'en' => 'Privacy policy'],
        ],
        'prompts' => [
            ['fr' => 'Aide', 'en' => 'Help', 'href' => 'help.php'],
            ['fr' => 'Prompts pédagogiques', 'en' => 'Teaching prompts'],
        ],
        'share' => [
            ['fr' => 'Scénarios partagés', 'en' => 'Shared designs'],
        ],
        'saves' => [
            ['fr' => 'Mes designs', 'en' => 'My designs'],
        ],
        'setup_admin' => [
            ['fr' => 'Administration', 'en' => 'Administration'],
            ['fr' => 'Configuration', 'en' => 'Setup'],
        ],
        'signup' => [
            ['fr' => 'Créer un compte', 'en' => 'Create account'],
        ],
        'reset_password' => [
            ['fr' => 'Connexion', 'en' => 'Sign in', 'href' => 'login.php'],
            ['fr' => 'Nouveau mot de passe', 'en' => 'New password'],
        ],
        'verify_email' => [
            ['fr' => 'Vérifier l’email', 'en' => 'Verify email'],
        ],
    ];

    return $map[$key] ?? [
        ['fr' => 'Page', 'en' => 'Page'],
    ];
}

function render_site_breadcrumb(array $items): void
{
    if ($items === []) {
        return;
    }
    ?>
    <nav class="site-breadcrumb" aria-label="Fil d'Ariane" data-site-i18n-attr="aria-label" data-site-i18n-en="Breadcrumb" data-site-i18n-fr="Fil d'Ariane">
        <ol>
            <li>
                <a class="site-breadcrumb-home" href="index.php" aria-label="Accueil" title="Accueil" data-site-i18n-attr="aria-label,title" data-site-i18n-en="Home" data-site-i18n-fr="Accueil">
                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                </a>
            </li>
            <?php foreach ($items as $index => $item): ?>
                <li class="site-breadcrumb-separator" aria-hidden="true">/</li>
                <li>
                    <?php if ($index === count($items) - 1): ?>
                        <span aria-current="page" data-site-i18n-en="<?= h((string)($item['en'] ?? 'Page')) ?>" data-site-i18n-fr="<?= h((string)($item['fr'] ?? 'Page')) ?>"><?= h((string)($item['fr'] ?? 'Page')) ?></span>
                    <?php elseif (!empty($item['href'])): ?>
                        <a href="<?= h((string)$item['href']) ?>" data-site-i18n-en="<?= h((string)($item['en'] ?? 'Page')) ?>" data-site-i18n-fr="<?= h((string)($item['fr'] ?? 'Page')) ?>"><?= h((string)($item['fr'] ?? 'Page')) ?></a>
                    <?php else: ?>
                        <span data-site-i18n-en="<?= h((string)($item['en'] ?? 'Page')) ?>" data-site-i18n-fr="<?= h((string)($item['fr'] ?? 'Page')) ?>"><?= h((string)($item['fr'] ?? 'Page')) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php
}

function require_same_origin_post(bool $allowJson = false): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit;
    }

    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    $baseUrl = app_base_url();
    $originUrl = app_origin_url();
    $basePath = (string)(parse_url($baseUrl, PHP_URL_PATH) ?? '');
    if ($basePath === '/') {
        $basePath = '';
    }

    $matches = false;
    if ($origin !== '') {
        $originScheme = (string)(parse_url($origin, PHP_URL_SCHEME) ?? '');
        $originHost = (string)(parse_url($origin, PHP_URL_HOST) ?? '');
        $appScheme = (string)(parse_url($originUrl, PHP_URL_SCHEME) ?? '');
        $appHost = (string)(parse_url($originUrl, PHP_URL_HOST) ?? '');
        $originPort = (int)(parse_url($origin, PHP_URL_PORT) ?? 0);
        $appPort = (int)(parse_url($originUrl, PHP_URL_PORT) ?? 0);
        if (
            $originScheme !== '' &&
            $originHost !== '' &&
            $originScheme === $appScheme &&
            $originHost === $appHost &&
            $originPort === $appPort
        ) {
            $matches = true;
        }
    }
    if (!$matches && $referer !== '') {
        $refererOrigin = (string)(parse_url($referer, PHP_URL_SCHEME) ?? '') . '://' . (string)(parse_url($referer, PHP_URL_HOST) ?? '');
        $refererPath = (string)(parse_url($referer, PHP_URL_PATH) ?? '');
        $refererPort = (int)(parse_url($referer, PHP_URL_PORT) ?? 0);
        $appPort = (int)(parse_url($originUrl, PHP_URL_PORT) ?? 0);
        if (
            $refererOrigin === $originUrl &&
            $refererPort === $appPort &&
            ($basePath === '' || str_starts_with($refererPath, $basePath))
        ) {
            $matches = true;
        }
    }

    if (!$matches && !$allowJson) {
        http_response_code(403);
        exit('Requete refusee.');
    }
    if (!$matches && $allowJson) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Requete refusee']);
        exit;
    }
}

function app_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

function app_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_design_title_from_document(array $document): string
{
    $title = trim((string)($document['meta']['name'] ?? ''));
    if ($title !== '') {
        return mb_substr($title, 0, 255, 'UTF-8');
    }

    return 'Production sans titre';
}

function render_site_footer(): void
{
    require __DIR__ . '/../partials/site-footer.php';
}

/**
 * Compare and write in one SQL statement. The transaction keeps the returned
 * revision tied to this exact write, even if another writer is waiting.
 */
function app_save_design_document(PDO $db, int $ownerId, int $designId, ?int $expectedRevision, string $title, string $payload, bool $publish = false): array
{
    $db->beginTransaction();
    try {
        if ($designId > 0) {
            $changed = false;
            if ($expectedRevision !== null && $expectedRevision > 0) {
                $update = $db->prepare("UPDATE learning_designs
                    SET title = ?, document_json = ?, revision = revision + 1, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND owner_user_id = ? AND revision = ?");
                $update->execute([$title, $payload, $designId, $ownerId, $expectedRevision]);
                $changed = $update->rowCount() === 1;
            }
            if (!$changed) {
                $read = $db->prepare("SELECT id, updated_at, revision FROM learning_designs WHERE id = ? AND owner_user_id = ?");
                $read->execute([$designId, $ownerId]);
                $current = $read->fetch();
                $db->rollBack();
                if (!$current) {
                    return ['status' => 404, 'success' => false, 'error' => 'Production introuvable.'];
                }
                return [
                    'status' => 409, 'success' => false, 'conflict' => true,
                    'error' => 'La version distante doit être vérifiée avant de sauvegarder. Votre brouillon est conservé.',
                    'design' => ['id' => $designId, 'updatedAt' => (string)$current['updated_at'], 'revision' => (int)$current['revision']],
                ];
            }
        } else {
            $db->prepare("INSERT INTO learning_designs (owner_user_id, title, document_json) VALUES (?, ?, ?)")
                ->execute([$ownerId, $title, $payload]);
            $designId = (int)$db->lastInsertId();
        }
        if ($publish) {
            $db->prepare("UPDATE learning_designs SET share_token = COALESCE(NULLIF(share_token, ''), ?), is_published = 1
                WHERE id = ? AND owner_user_id = ?")
                ->execute([bin2hex(random_bytes(24)), $designId, $ownerId]);
        }
        $read = $db->prepare("SELECT id, title, updated_at, revision, share_token FROM learning_designs WHERE id = ? AND owner_user_id = ?");
        $read->execute([$designId, $ownerId]);
        $saved = $read->fetch();
        $db->commit();
        return [
            'status' => 200, 'success' => true,
            'design' => [
                'id' => (int)$saved['id'], 'title' => (string)$saved['title'],
                'updatedAt' => (string)$saved['updated_at'], 'revision' => (int)$saved['revision'],
            ],
            'share_token' => $saved['share_token'],
        ];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
