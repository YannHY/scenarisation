<?php
declare(strict_types=1);
require_once __DIR__ . '/native-schema.php';

const APP_VERSION = '1.0.0';
const APP_SCHEMA_VERSION = 11;
const TERMS_VERSION = '2026-09-26';
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
            'Scénarisation : session demandée après envoi des en-têtes (sortie démarrée dans %s ligne %d).'
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
        // The configured URL is canonical, including an explicitly chosen root.
        // An alternate access path must not be appended to another public host.
        return rtrim($configured, '/');
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

    // Incremental upgrades add targeted columns below; do not replay historical data backfills.
    if ($currentVersion === false || $currentVersion === null || (int)$currentVersion < 4) {
        ensure_app_tables($db);
        ensure_app_migrations($db);
    }
    ensure_design_revision_column($db);
    ensure_design_provenance_columns($db);
    ensure_terms_acceptance_columns($db);
    ensure_manager_role($db);
    ensure_manager_permission_columns($db);
    ensure_native_app_schema($db);
    ensure_native_collaboration_schema($db);
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

/** Existing accounts retain access; NULL is not evidence of explicit acceptance. */
function ensure_terms_acceptance_columns(PDO $db): void
{
    $isSqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    foreach (['terms_accepted_at' => 'DATETIME', 'terms_version' => 'VARCHAR(32)'] as $column => $type) {
        $exists = static function () use ($db, $isSqlite, $column): bool {
            if ($isSqlite) {
                return in_array($column, array_column($db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name'), true);
            }
            $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
            $stmt->execute([$column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        if ($exists()) continue;
        try {
            $db->exec("ALTER TABLE users ADD COLUMN $column " . ($isSqlite ? 'TEXT' : $type) . ' NULL');
        } catch (PDOException $error) {
            if (!$exists()) throw $error;
        }
    }
}

/** Allow limited administration accounts on both new and existing installs. */
function ensure_manager_role(PDO $db): void
{
    $isSqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    if (!$isSqlite) {
        $stmt = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'");
        $columnType = strtolower((string)$stmt->fetchColumn());
        if (!str_contains($columnType, "'manager'")) {
            $db->exec("ALTER TABLE users MODIFY role ENUM('admin','manager','designer') NOT NULL DEFAULT 'designer'");
        }
        return;
    }

    $tableSql = (string)$db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'")
        ->fetchColumn();
    if ($tableSql === '' || preg_match("/['\"]manager['\"]/i", $tableSql) === 1) {
        return;
    }

    $upgradedSql = preg_replace(
        "/CHECK\s*\(\s*role\s+IN\s*\(\s*'admin'\s*,\s*'designer'\s*\)\s*\)/i",
        "CHECK (role IN ('admin','manager','designer'))",
        $tableSql,
        1,
        $replacementCount
    );
    if (!is_string($upgradedSql) || $replacementCount !== 1) {
        throw new RuntimeException('Impossible de préparer la migration des rôles utilisateur.');
    }
    $upgradedSql = preg_replace(
        '/^CREATE TABLE\s+(?:IF NOT EXISTS\s+)?(?:"users"|`users`|\[users\]|users)/i',
        'CREATE TABLE users_role_upgrade',
        $upgradedSql,
        1,
        $tableReplacementCount
    );
    if (!is_string($upgradedSql) || $tableReplacementCount !== 1) {
        throw new RuntimeException('Impossible de préparer la table des rôles utilisateur.');
    }

    $dependentObjects = $db->query("SELECT sql FROM sqlite_master
        WHERE tbl_name = 'users' AND type IN ('index', 'trigger') AND sql IS NOT NULL")
        ->fetchAll(PDO::FETCH_COLUMN);
    $foreignKeysEnabled = (int)$db->query('PRAGMA foreign_keys')->fetchColumn() === 1;
    if ($foreignKeysEnabled) {
        $db->exec('PRAGMA foreign_keys = OFF');
    }
    try {
        $db->beginTransaction();
        $db->exec($upgradedSql);
        $db->exec('INSERT INTO users_role_upgrade SELECT * FROM users');
        $db->exec('DROP TABLE users');
        $db->exec('ALTER TABLE users_role_upgrade RENAME TO users');
        foreach ($dependentObjects as $objectSql) {
            $db->exec((string)$objectSql);
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    } finally {
        if ($foreignKeysEnabled) {
            $db->exec('PRAGMA foreign_keys = ON');
        }
    }

    if ($db->query('PRAGMA foreign_key_check')->fetch() !== false) {
        throw new RuntimeException('La migration des rôles a détecté une relation invalide.');
    }
}

function ensure_manager_permission_columns(PDO $db): void
{
    $isSqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $columns = [
        'manager_manage_accounts',
        'manager_delete_feedback',
        'manager_view_security_log',
    ];
    foreach ($columns as $column) {
        $exists = static function () use ($db, $isSqlite, $column): bool {
            if ($isSqlite) {
                return in_array($column, array_column($db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name'), true);
            }
            $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
            $stmt->execute([$column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        if ($exists()) {
            continue;
        }
        try {
            $db->exec("ALTER TABLE users ADD COLUMN $column "
                . ($isSqlite ? 'INTEGER' : 'TINYINT(1)') . ' NOT NULL DEFAULT 0');
        } catch (PDOException $error) {
            if (!$exists()) {
                throw $error;
            }
        }
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

/** Add immutable source snapshots used to attribute reused scenarios. */
function ensure_design_provenance_columns(PDO $db): void
{
    $isSqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $columns = [
        'source_design_id' => $isSqlite ? 'INTEGER NULL' : 'BIGINT UNSIGNED NULL',
        'root_design_id' => $isSqlite ? 'INTEGER NULL' : 'BIGINT UNSIGNED NULL',
        'source_title' => $isSqlite ? 'TEXT NULL' : 'VARCHAR(255) NULL',
        'source_author_name' => $isSqlite ? 'TEXT NULL' : 'VARCHAR(80) NULL',
        'source_license_code' => $isSqlite ? 'TEXT NULL' : 'VARCHAR(24) NULL',
        'derived_at' => $isSqlite ? 'TEXT NULL' : 'DATETIME NULL',
    ];
    foreach ($columns as $column => $definition) {
        $exists = static function () use ($db, $isSqlite, $column): bool {
            if ($isSqlite) {
                return in_array($column, array_column($db->query('PRAGMA table_info(learning_designs)')->fetchAll(PDO::FETCH_ASSOC), 'name'), true);
            }
            $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_designs' AND COLUMN_NAME = ?");
            $stmt->execute([$column]);
            return (int)$stmt->fetchColumn() > 0;
        };
        if ($exists()) continue;
        try {
            $db->exec("ALTER TABLE learning_designs ADD COLUMN $column $definition");
        } catch (PDOException $error) {
            if (!$exists()) throw $error;
        }
    }
    if ($isSqlite) {
        $db->exec('CREATE INDEX IF NOT EXISTS idx_learning_designs_source ON learning_designs(source_design_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_learning_designs_root ON learning_designs(root_design_id)');
        return;
    }
    foreach ([
        'idx_learning_designs_source' => 'CREATE INDEX idx_learning_designs_source ON learning_designs(source_design_id)',
        'idx_learning_designs_root' => 'CREATE INDEX idx_learning_designs_root ON learning_designs(root_design_id)',
    ] as $indexName => $sql) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'learning_designs' AND INDEX_NAME = ?");
        $stmt->execute([$indexName]);
        if ((int)$stmt->fetchColumn() === 0) $db->exec($sql);
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
            role TEXT NOT NULL DEFAULT 'designer' CHECK (role IN ('admin','manager','designer')),
            status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','disabled')),
            email_verified_at TEXT NULL,
            email_verification_token_hash TEXT NULL,
            email_verification_expires_at INTEGER NULL,
            email_verification_sent_at INTEGER NULL,
            password_reset_token_hash TEXT NULL,
            password_reset_expires_at INTEGER NULL,
            password_reset_sent_at INTEGER NULL,
            manager_manage_accounts INTEGER NOT NULL DEFAULT 0,
            manager_delete_feedback INTEGER NOT NULL DEFAULT 0,
            manager_view_security_log INTEGER NOT NULL DEFAULT 0,
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
            source_design_id INTEGER NULL,
            root_design_id INTEGER NULL,
            source_title TEXT NULL,
            source_author_name TEXT NULL,
            source_license_code TEXT NULL,
            derived_at TEXT NULL,
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
        role ENUM('admin','manager','designer') NOT NULL DEFAULT 'designer',
        status ENUM('active','disabled') NOT NULL DEFAULT 'active',
        email_verified_at DATETIME NULL,
        email_verification_token_hash CHAR(64) NULL,
        email_verification_expires_at BIGINT UNSIGNED NULL,
        email_verification_sent_at BIGINT UNSIGNED NULL,
        password_reset_token_hash CHAR(64) NULL,
        password_reset_expires_at BIGINT UNSIGNED NULL,
        password_reset_sent_at BIGINT UNSIGNED NULL,
        manager_manage_accounts TINYINT(1) NOT NULL DEFAULT 0,
        manager_delete_feedback TINYINT(1) NOT NULL DEFAULT 0,
        manager_view_security_log TINYINT(1) NOT NULL DEFAULT 0,
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
        source_design_id BIGINT UNSIGNED NULL,
        root_design_id BIGINT UNSIGNED NULL,
        source_title VARCHAR(255) NULL,
        source_author_name VARCHAR(80) NULL,
        source_license_code VARCHAR(24) NULL,
        derived_at DATETIME NULL,
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
 * Creative Commons 4.0 licenses available when publishing a scenario.
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

function creative_commons_allows_derivatives(string $code): bool
{
    return !in_array(strtolower(trim($code)), ['cc-by-nd', 'cc-by-nc-nd'], true);
}

/** @return array<string, mixed>|null */
function app_design_provenance_payload(array $row): ?array
{
    $sourceDesignId = (int)($row['source_design_id'] ?? 0);
    $sourceTitle = trim((string)($row['source_title'] ?? ''));
    $sourceAuthor = trim((string)($row['source_author_name'] ?? ''));
    if ($sourceDesignId <= 0 && $sourceTitle === '' && $sourceAuthor === '') return null;

    $licenseCode = strtolower(trim((string)($row['source_license_code'] ?? '')));
    $license = creative_commons_license($licenseCode);
    return [
        'verified' => true,
        'sourceDesignId' => $sourceDesignId > 0 ? $sourceDesignId : null,
        'rootDesignId' => (int)($row['root_design_id'] ?? 0) ?: null,
        'sourceTitle' => $sourceTitle,
        'sourceAuthor' => $sourceAuthor,
        'sourceLicenseCode' => $license ? $licenseCode : null,
        'sourceLicenseLabel' => $license['label'] ?? null,
        'sourceLicenseUrl' => $license['url'] ?? null,
        'derivedAt' => trim((string)($row['derived_at'] ?? '')) ?: null,
    ];
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
    $stmt = app_db()->prepare("SELECT id, username, email, role, status,
        manager_manage_accounts, manager_delete_feedback, manager_view_security_log
        FROM users WHERE id = ? LIMIT 1");
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
        'manager_manage_accounts' => (int)$user['manager_manage_accounts'],
        'manager_delete_feedback' => (int)$user['manager_delete_feedback'],
        'manager_view_security_log' => (int)$user['manager_view_security_log'],
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

function user_is_full_admin(?array $user): bool
{
    return (string)($user['role'] ?? '') === 'admin';
}

function user_can_access_admin(?array $user): bool
{
    return in_array((string)($user['role'] ?? ''), ['admin', 'manager'], true);
}

function user_has_admin_permission(?array $user, string $permission): bool
{
    if (user_is_full_admin($user)) {
        return true;
    }
    if ((string)($user['role'] ?? '') !== 'manager') {
        return false;
    }
    $columns = [
        'manage_accounts' => 'manager_manage_accounts',
        'delete_feedback' => 'manager_delete_feedback',
        'view_security_log' => 'manager_view_security_log',
    ];
    $column = $columns[$permission] ?? null;
    return $column !== null && (int)($user[$column] ?? 0) === 1;
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
    if (!user_is_full_admin($user)) {
        http_response_code(403);
        echo 'Acces refuse.';
        exit;
    }
    return $user;
}

function require_admin_dashboard_page(): array
{
    $user = require_login_page();
    if (!user_can_access_admin($user)) {
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
    $fromName = trim((string)(app_env('APP_MAIL_FROM_NAME') ?? 'Scénarisation'));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from . $fromName)) {
        return false;
    }

    $verificationUrl = app_base_url() . '/verify-email.php?token=' . rawurlencode($token);
    $safeUsername = trim(str_replace(["\r", "\n"], ' ', $username));
    $body = "Bonjour {$safeUsername},\n\n"
        . "Confirmez votre adresse email pour activer votre compte Scénarisation :\n"
        . $verificationUrl . "\n\n"
        . "Ce lien est valable pendant 24 heures et ne peut être utilisé qu'une fois.\n\n"
        . "Si vous n'avez pas demandé la création de ce compte, vous pouvez ignorer ce message.\n";

    $subject = 'Confirmez votre adresse email — Scénarisation';
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
    $fromName = trim((string)(app_env('APP_MAIL_FROM_NAME') ?? 'Scénarisation'));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from . $fromName)) {
        return false;
    }

    $resetUrl = app_base_url() . '/reset-password.php?token=' . rawurlencode($token);
    $safeUsername = trim(str_replace(["\r", "\n"], ' ', $username));
    $body = "Bonjour {$safeUsername},\n\n"
        . "Vous avez demandé la réinitialisation de votre mot de passe Scénarisation :\n"
        . $resetUrl . "\n\n"
        . "Ce lien est valable pendant une heure et ne peut être utilisé qu'une fois.\n\n"
        . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe ne sera pas modifié.\n";

    $subject = 'Réinitialisez votre mot de passe — Scénarisation';
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
 * n'ait pas à lire les 356 Ko de interface.js à chaque affichage d'un scénario
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
        error_log('Scénarisation : catalogue de compétences introuvable (' . $path . ').');
        return $source = '';
    }

    $js = (string)file_get_contents($path);
    if (!preg_match('/const\s+COMPETENCY_CATALOG_SOURCE\s*=\s*String\.raw`(.*?)`;/s', $js, $matches)) {
        error_log('Scénarisation : COMPETENCY_CATALOG_SOURCE illisible dans ' . $path
            . ' (constante renommée ou littéral modifié ?).');
        return $source = '';
    }

    $sourceFr = (string)$matches[1];
    if (!preg_match('/const\s+COMPETENCY_CATALOG_EN_SOURCE\s*=\s*String\.raw`(.*?)`;/s', $js, $translationMatches)) {
        error_log('Scénarisation : traductions anglaises du catalogue de compétences illisibles dans ' . $path . '.');
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
        error_log('Scénarisation : catalogue de cadres de compétences introuvable (' . $path . ').');
        return $source = '';
    }

    $js = (string)file_get_contents($path);
    if (!preg_match('/const\s+COMPETENCY_FRAMEWORK_CATALOG_SOURCE\s*=\s*String\.raw`(.*?)`;/s', $js, $matches)) {
        error_log('Scénarisation : COMPETENCY_FRAMEWORK_CATALOG_SOURCE illisible dans ' . $path . '.');
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
        error_log('Scénarisation : repères DigComp introuvables (' . $path . ').');
        return $source = '';
    }

    $js = (string)file_get_contents($path);
    if (!preg_match('/const\s+COMPETENCY_DIGCOMP_DETAIL_SOURCE\s*=\s*String\.raw`(.*?)`;/s', $js, $matches)) {
        error_log('Scénarisation : COMPETENCY_DIGCOMP_DETAIL_SOURCE illisible dans ' . $path . '.');
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
        error_log('Scénarisation : repères GreenComp introuvables (' . $path . ').');
        return $source = '';
    }

    $js = (string)file_get_contents($path);
    if (!preg_match('/const\s+COMPETENCY_GREENCOMP_DETAIL_SOURCE\s*=\s*String\.raw`(.*?)`;/s', $js, $matches)) {
        error_log('Scénarisation : COMPETENCY_GREENCOMP_DETAIL_SOURCE illisible dans ' . $path . '.');
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
/**
 * Dernière version publiée de l’app Mac, lue dans le flux Sparkle du site (mac/appcast.xml).
 * Sans flux lisible, la page reste utilisable avec la dernière version connue.
 * @return array{version:string, url:string, minimumSystem:string}
 */
function app_mac_latest_release(): array
{
    $release = [
        'version' => '0.4.6',
        'url' => 'https://scenarisation.eu/mac/Scenarisation-0.4.6-AppleSilicon.dmg',
        'minimumSystem' => '15.0',
    ];
    $feed = dirname(__DIR__) . '/mac/appcast.xml';
    if (!is_file($feed)) return $release;
    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_file($feed);
    libxml_use_internal_errors($previous);
    if ($xml === false || !isset($xml->channel->item)) return $release;
    $sparkle = 'http://www.andymatuschak.org/xml-namespaces/sparkle';
    $best = null;
    foreach ($xml->channel->item as $item) {
        $meta = $item->children($sparkle);
        $version = trim((string)($meta->shortVersionString ?? ''));
        $url = trim((string)($item->enclosure['url'] ?? ''));
        if ($version === '' || !str_starts_with($url, 'https://')) continue;
        if ($best === null || version_compare($version, $best['version'], '>')) {
            $minimum = trim((string)($meta->minimumSystemVersion ?? ''));
            $best = ['version' => $version, 'url' => $url, 'minimumSystem' => $minimum !== '' ? $minimum : $release['minimumSystem']];
        }
    }
    return $best ?? $release;
}

/** Finitions visuelles communes (css/refined.css), chargées après les feuilles propres à chaque page. */
function render_refined_styles(): void
{
    $path = dirname(__DIR__) . '/css/refined.css';
    $version = is_file($path) ? substr((string)hash_file('sha256', $path), 0, 16) : APP_VERSION;
    echo '<link rel="stylesheet" href="css/refined.css?v=' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '" />' . "\n";
}

function render_theme_boot_script(): void
{
    $root = dirname(__DIR__);
    $script = $root . '/js/display-preferences.js';
    $style = $root . '/css/display-preferences.css';
    $scriptVersion = is_file($script) ? substr((string)hash_file('sha256', $script), 0, 16) : APP_VERSION;
    $styleVersion = is_file($style) ? substr((string)hash_file('sha256', $style), 0, 16) : APP_VERSION;
    // Chargé sans defer : le mode, la taille et l’espacement s’appliquent avant le premier rendu.
    echo '<script src="js/display-preferences.js?v=' . h($scriptVersion) . '"></script>' . "\n";
    echo '<link rel="stylesheet" href="css/display-preferences.css?v=' . h($styleVersion) . '" />' . "\n";
}

function render_site_nav(string $active = '', bool $accountAvailable = true): void
{
    $user = $accountAvailable ? current_user() : null;
    $canAccessAdmin = user_can_access_admin($user);
    $username = trim((string)($user['username'] ?? $user['email'] ?? ''));
    $savesClass = $active === 'saves' ? ' nav-account-btn-active' : '';
    $profileClass = $active === 'profile' ? ' nav-account-btn-active' : '';
    $adminClass = $active === 'admin' ? ' nav-account-btn-active' : '';
    $breadcrumbItems = site_breadcrumb_items($active);
    ?>
    <header class="site-nav site-nav-page" role="navigation" aria-label="Navigation principale" data-site-i18n-attr="aria-label" data-site-i18n-en="Main navigation" data-site-i18n-fr="Navigation principale">
        <div class="site-nav-brand">
            <a class="site-nav-brand-link" href="index.php" aria-label="Accueil Scénarisation" data-site-i18n-attr="aria-label" data-site-i18n-en="Scénarisation home" data-site-i18n-fr="Accueil Scénarisation">
                <span class="site-nav-brand-mark" aria-hidden="true"></span>
                <div class="site-nav-brand-copy">
                    <p class="site-nav-title">Scénarisation</p>
                </div>
            </a>
        </div>
        <div id="site-nav-actions" class="site-nav-actions">
            <button id="site-search-open" class="nav-icon-btn site-search-open" type="button" aria-label="Rechercher sur le site" title="Rechercher sur le site" aria-keyshortcuts="Meta+K Control+K">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </button>
            <a class="nav-icon-btn nav-help-link" href="help.php" aria-label="Aide" title="Aide" data-site-i18n-attr="title,aria-label" data-site-i18n-en="Help" data-site-i18n-fr="Aide">
                <i class="fa-solid fa-book-open" aria-hidden="true"></i>
            </a>
            <div class="account-toolbar-cluster">
                <a class="nav-icon-btn" href="new-design.php" title="Nouveau scénario" aria-label="Nouveau scénario" data-site-i18n-attr="title,aria-label" data-site-i18n-en="New scenario" data-site-i18n-fr="Nouveau scénario">
                    <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
                </a>
                <a class="nav-account-btn nav-account-icon-btn<?= $savesClass ?>" href="my-designs.php" title="Scénarios" aria-label="Scénarios" data-site-i18n-attr="title,aria-label" data-site-i18n-en="Scenarios" data-site-i18n-fr="Scénarios">
                    <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                </a>
                <div class="account-menu-wrap">
                    <button id="account-menu-btn" class="nav-account-btn nav-account-icon-btn<?= $profileClass !== '' || $adminClass !== '' ? ' nav-account-btn-active' : '' ?>" type="button" aria-expanded="false" aria-controls="account-menu" title="Compte" aria-label="Compte" data-site-i18n-attr="title,aria-label" data-site-i18n-en="Account" data-site-i18n-fr="Compte">
                        <i class="<?= $user ? 'fa-solid fa-user-check' : 'fa-regular fa-user' ?>" aria-hidden="true"></i>
                    </button>
                    <div id="account-menu" class="account-menu account-preferences-menu hidden" aria-hidden="true">
                        <?php if ($user): ?>
                            <a class="account-menu-link<?= $profileClass ?>" href="profile.php">
                                <i class="account-menu-icon fa-regular fa-user" aria-hidden="true"></i>
                                <span data-site-i18n-en="Profile" data-site-i18n-fr="Profil">Profil</span>
                            </a>
                            <?php if ($canAccessAdmin): ?>
                                <a class="account-menu-link<?= $adminClass ?>" href="admin.php">
                                    <svg class="account-menu-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.09a2 2 0 0 1 1 1.74v.5a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <span data-site-i18n-en="Administration" data-site-i18n-fr="Administration">Administration</span>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a class="account-menu-link" href="login.php">
                                <i class="account-menu-icon fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                                <span data-site-i18n-en="Sign in" data-site-i18n-fr="Se connecter">Se connecter</span>
                            </a>
                        <?php endif; ?>
                        <section class="account-preferences" aria-labelledby="account-preferences-title">
                            <h2 id="account-preferences-title">
                                <i class="account-menu-icon fa-solid fa-sliders" aria-hidden="true"></i>
                                <span data-site-i18n-en="Preferences" data-site-i18n-fr="Préférences">Préférences</span>
                            </h2>
                            <div class="display-row">
                                <label id="account-language-label" class="display-row-label" for="lang-select" data-site-i18n-en="Language" data-site-i18n-fr="Langue">Langue</label>
                                <select id="lang-select" hidden aria-hidden="true" tabindex="-1">
                                    <option value="fr" lang="fr">Français</option>
                                    <option value="en" lang="en">English</option>
                                </select>
                                <div class="account-preference-options" role="group" aria-labelledby="account-language-label">
                                    <button type="button" class="account-preference-option" data-account-language="fr" aria-pressed="true" lang="fr" aria-label="Français" title="Français">FR</button>
                                    <button type="button" class="account-preference-option" data-account-language="en" aria-pressed="false" lang="en" aria-label="English" title="English">EN</button>
                                </div>
                            </div>
                        </section>
                        <section class="account-preferences display-preferences" aria-labelledby="display-preferences-title">
                            <h2 id="display-preferences-title">
                                <i class="account-menu-icon fa-solid fa-eye" aria-hidden="true"></i>
                                <span data-site-i18n-en="Display" data-site-i18n-fr="Affichage">Affichage</span>
                            </h2>
                            <div class="display-row mode-toggle-wrap">
                                <span id="display-mode-label" class="display-row-label" data-site-i18n-en="Mode" data-site-i18n-fr="Mode">Mode</span>
                                <div class="mode-toggle" role="radiogroup" aria-labelledby="display-mode-label">
                                    <span class="mode-toggle-knob" aria-hidden="true">
                                        <span class="mode-toggle-face">
                                            <svg class="mode-glyph mode-glyph-sun" viewBox="0 0 48 48" focusable="false">
                                                <defs>
                                                    <radialGradient id="mode-sun-core" cx="40%" cy="35%" r="70%">
                                                        <stop offset="0" stop-color="#FFE9B8"/>
                                                        <stop offset="1" stop-color="#E2A06E"/>
                                                    </radialGradient>
                                                </defs>
                                                <g class="mode-sun-rays">
                                                    <g transform="rotate(0 24 24)"><line class="mode-ray mode-n0" x1="24" y1="4" x2="24" y2="9.5"/></g>
                                                    <g transform="rotate(45 24 24)"><line class="mode-ray mode-n1" x1="24" y1="4" x2="24" y2="9.5"/></g>
                                                    <g transform="rotate(90 24 24)"><line class="mode-ray mode-n2" x1="24" y1="4" x2="24" y2="9.5"/></g>
                                                    <g transform="rotate(135 24 24)"><line class="mode-ray mode-n3" x1="24" y1="4" x2="24" y2="9.5"/></g>
                                                    <g transform="rotate(180 24 24)"><line class="mode-ray mode-n4" x1="24" y1="4" x2="24" y2="9.5"/></g>
                                                    <g transform="rotate(225 24 24)"><line class="mode-ray mode-n5" x1="24" y1="4" x2="24" y2="9.5"/></g>
                                                    <g transform="rotate(270 24 24)"><line class="mode-ray mode-n6" x1="24" y1="4" x2="24" y2="9.5"/></g>
                                                    <g transform="rotate(315 24 24)"><line class="mode-ray mode-n7" x1="24" y1="4" x2="24" y2="9.5"/></g>
                                                </g>
                                                <circle class="mode-sun-core" cx="24" cy="24" r="9.5" fill="url(#mode-sun-core)"/>
                                            </svg>
                                            <svg class="mode-glyph mode-glyph-sunset" viewBox="0 0 48 48" focusable="false">
                                                <defs>
                                                    <radialGradient id="mode-sunset-glow" cx="50%" cy="50%" r="50%">
                                                        <stop offset="0" stop-color="#F3C98A" stop-opacity="0.95"/>
                                                        <stop offset="0.45" stop-color="#E66A63" stop-opacity="0.5"/>
                                                        <stop offset="1" stop-color="#B4607E" stop-opacity="0"/>
                                                    </radialGradient>
                                                    <linearGradient id="mode-sunset-sun" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="0" stop-color="#F6D898"/>
                                                        <stop offset="1" stop-color="#E66A63"/>
                                                    </linearGradient>
                                                    <clipPath id="mode-sunset-sky"><rect x="0" y="0" width="48" height="30"/></clipPath>
                                                </defs>
                                                <circle class="mode-sunset-glow" cx="24" cy="30" r="21" fill="url(#mode-sunset-glow)"/>
                                                <g clip-path="url(#mode-sunset-sky)"><circle class="mode-sunset-sun" cx="24" cy="30" r="10" fill="url(#mode-sunset-sun)"/></g>
                                                <line class="mode-sunset-line" x1="7" y1="30" x2="41" y2="30"/>
                                                <line class="mode-sunset-ripple mode-n0" x1="14" y1="35" x2="34" y2="35"/>
                                                <line class="mode-sunset-ripple mode-n1" x1="19" y1="39.5" x2="29" y2="39.5"/>
                                            </svg>
                                            <svg class="mode-glyph mode-glyph-moon" viewBox="0 0 48 48" focusable="false">
                                                <defs>
                                                    <linearGradient id="mode-moon-fill" x1="0" y1="0" x2="1" y2="1">
                                                        <stop offset="0" stop-color="#FAF9F6"/>
                                                        <stop offset="1" stop-color="#C9C3B8"/>
                                                    </linearGradient>
                                                </defs>
                                                <g transform="translate(4 7) scale(1.5)"><path class="mode-moon-body" d="M19.5 14.6A8 8 0 0 1 9.4 4.5a8 8 0 1 0 10.1 10.1Z" fill="url(#mode-moon-fill)"/></g>
                                                <path class="mode-star mode-n0" d="M37 6.5Q37 10 40.5 10Q37 10 37 13.5Q37 10 33.5 10Q37 10 37 6.5Z"/>
                                                <path class="mode-star mode-n1" d="M42 19Q42 21 44 21Q42 21 42 23Q42 21 40 21Q42 21 42 19Z"/>
                                                <path class="mode-star mode-n2" d="M31 17.5Q31 19 32.5 19Q31 19 31 20.5Q31 19 29.5 19Q31 19 31 17.5Z"/>
                                                <path class="mode-star mode-n3" d="M41 31Q41 32.5 42.5 32.5Q41 32.5 41 34Q41 32.5 39.5 32.5Q41 32.5 41 31Z"/>
                                            </svg>
                                        </span>
                                    </span>
                                    <button type="button" class="mode-toggle-stop" role="radio" aria-checked="true" tabindex="0" data-display-mode="light" aria-label="Clair" title="Clair" data-site-i18n-attr="aria-label,title" data-site-i18n-en="Light" data-site-i18n-fr="Clair">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2.5M12 19v2.5M2.5 12H5M19 12h2.5M5.3 5.3l1.8 1.8M16.9 16.9l1.8 1.8M5.3 18.7l1.8-1.8M16.9 7.1l1.8-1.8"/></svg>
                                    </button>
                                    <button type="button" class="mode-toggle-stop" role="radio" aria-checked="false" tabindex="-1" data-display-mode="warm" aria-label="Chaud" title="Chaud" data-site-i18n-attr="aria-label,title" data-site-i18n-en="Warm" data-site-i18n-fr="Chaud">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 17a5 5 0 0 1 10 0"/><path d="M2.5 17h19M5.5 20.5h13M12 5.5V8M5.3 9.8l1.6 1.6M18.7 9.8l-1.6 1.6"/></svg>
                                    </button>
                                    <button type="button" class="mode-toggle-stop" role="radio" aria-checked="false" tabindex="-1" data-display-mode="dark" aria-label="Sombre" title="Sombre" data-site-i18n-attr="aria-label,title" data-site-i18n-en="Dark" data-site-i18n-fr="Sombre">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19.5 14.6A8 8 0 0 1 9.4 4.5a8 8 0 1 0 10.1 10.1Z"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="display-row">
                                <span id="display-text-size-label" class="display-row-label" data-site-i18n-en="Text size" data-site-i18n-fr="Taille du texte">Taille du texte</span>
                                <div class="display-stepper" role="group" aria-labelledby="display-text-size-label">
                                    <button type="button" class="display-step" data-display-text-step="-1" aria-label="Réduire le texte" title="Réduire le texte" data-site-i18n-attr="aria-label,title" data-site-i18n-en="Smaller text" data-site-i18n-fr="Réduire le texte">
                                        <span class="display-step-glyph display-step-glyph-small" aria-hidden="true">A</span>
                                    </button>
                                    <output class="display-stepper-value" aria-live="polite" data-display-text-size-value>100&nbsp;%</output>
                                    <button type="button" class="display-step" data-display-text-step="1" aria-label="Agrandir le texte" title="Agrandir le texte" data-site-i18n-attr="aria-label,title" data-site-i18n-en="Larger text" data-site-i18n-fr="Agrandir le texte">
                                        <span class="display-step-glyph" aria-hidden="true">A</span>
                                    </button>
                                </div>
                            </div>
                            <div class="display-row">
                                <span id="display-spacing-label" class="display-row-label" data-site-i18n-en="Spacing" data-site-i18n-fr="Espacement">Espacement</span>
                                <div class="account-preference-options" role="group" aria-labelledby="display-spacing-label">
                                    <button type="button" class="account-preference-option display-icon-option" data-display-spacing="default" aria-pressed="true" aria-label="Standard" title="Standard" data-site-i18n-attr="aria-label,title" data-site-i18n-en="Standard" data-site-i18n-fr="Standard">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 8h14M5 12h14M5 16h14"/></svg>
                                    </button>
                                    <button type="button" class="account-preference-option display-icon-option" data-display-spacing="relaxed" aria-pressed="false" aria-label="Aéré" title="Aéré" data-site-i18n-attr="aria-label,title" data-site-i18n-en="Relaxed" data-site-i18n-fr="Aéré">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 6.5h14M5 12h14M5 17.5h14"/></svg>
                                    </button>
                                    <button type="button" class="account-preference-option display-icon-option" data-display-spacing="wide" aria-pressed="false" aria-label="Large" title="Large" data-site-i18n-attr="aria-label,title" data-site-i18n-en="Wide" data-site-i18n-fr="Large">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 5h14M5 12h14M5 19h14"/></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="display-toggle-row" role="switch" aria-checked="false" data-display-motion>
                                <span class="display-toggle-copy">
                                    <span class="display-row-label" data-site-i18n-en="Reduce motion" data-site-i18n-fr="Réduire les animations">Réduire les animations</span>
                                </span>
                                <span class="display-switch" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="display-reset" data-display-reset hidden>
                                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                <span data-site-i18n-en="Reset" data-site-i18n-fr="Réinitialiser">Réinitialiser</span>
                            </button>
                            <svg class="display-filters" aria-hidden="true" focusable="false">
                                <filter id="display-warm-filter" color-interpolation-filters="sRGB">
                                    <feColorMatrix type="matrix" values="1 0 0 0 0  0 0.949 0 0 0  0 0 0.871 0 0  0 0 0 1 0"/>
                                </filter>
                            </svg>
                        </section>
                        <?php if ($user): ?>
                            <a class="account-menu-link" href="logout.php">
                                <i class="account-menu-icon fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                                <span data-site-i18n-en="Sign out" data-site-i18n-fr="Déconnexion">Déconnexion</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
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
        var languageOptions = document.querySelectorAll('[data-account-language]');
        function syncLanguageOptions() {
            languageOptions.forEach(function (option) {
                option.setAttribute('aria-pressed', option.dataset.accountLanguage === html.lang ? 'true' : 'false');
            });
        }
        new MutationObserver(syncLanguageOptions).observe(html, { attributes: true, attributeFilter: ['lang'] });
        languageOptions.forEach(function (option) {
            option.addEventListener('click', function () {
                if (!langSelect || langSelect.value === option.dataset.accountLanguage) return;
                langSelect.value = option.dataset.accountLanguage;
                langSelect.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
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
            html.setAttribute('lang', savedLang);
            applySiteNavLanguage(savedLang);
            syncLanguageOptions();
            langSelect.addEventListener('change', function () {
                html.setAttribute('lang', langSelect.value);
                applySiteNavLanguage(langSelect.value);
                try {
                    localStorage.setItem('learningDesignerLang', langSelect.value);
                } catch (error) {
                }
            });

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

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !menu.classList.contains('hidden')) {
                closeMenu();
                button.focus();
            }
        });
        document.addEventListener('focusin', function (event) {
            if (!menu.contains(event.target) && !button.contains(event.target)) closeMenu();
        });
        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target) && !button.contains(event.target)) {
                closeMenu();
            }
        });
    });
    </script>
    <link rel="stylesheet" href="css/site-tooltip.css?v=<?= hash_file('sha256', __DIR__ . '/../css/site-tooltip.css') ?>" />
    <script defer src="js/site-tooltip.js?v=<?= hash_file('sha256', __DIR__ . '/../js/site-tooltip.js') ?>"></script>
    <link rel="stylesheet" href="css/account-preferences.css?v=<?= hash_file('sha256', __DIR__ . '/../css/account-preferences.css') ?>" />
    <link rel="stylesheet" href="css/site-search.css?v=20261124-rem" />
    <script src="js/site-search.js?v=20261101-updates-page"></script>
    <?php
}

function site_breadcrumb_items(string $active = ''): array
{
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $key = $active !== '' ? $active : pathinfo($page, PATHINFO_FILENAME);
    $map = [
        'home' => [],
        'designer' => [],
        'new-design' => [
            ['fr' => 'Nouveau scénario', 'en' => 'New scenario'],
        ],
        'about' => [
            ['fr' => 'À propos', 'en' => 'About'],
        ],
        'updates' => [
            ['fr' => 'Nouveautés', 'en' => 'What’s new'],
        ],
        'mac-app' => [
            ['fr' => 'App', 'en' => 'App'],
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
        'oauth-authorize' => [
            ['fr' => 'Connecter le Mac', 'en' => 'Connect your Mac'],
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
            ['fr' => 'Scénarios partagés', 'en' => 'Shared scenarios'],
        ],
        'saves' => [
            ['fr' => 'Mes scénarios', 'en' => 'My scenarios'],
        ],
        'setup_admin' => [
            ['fr' => 'Administration', 'en' => 'Administration'],
            ['fr' => 'Configuration', 'en' => 'Setup'],
        ],
        'signup' => [
            ['fr' => 'Créer un compte', 'en' => 'Create account'],
        ],
        'terms' => [
            ['fr' => 'Conditions et licences', 'en' => 'Terms and licenses'],
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
function app_save_design_document(PDO $db, int $ownerId, int $designId, ?int $expectedRevision, string $title, string $payload, bool $publish = false, ?string $clientUUID = null): array
{
    $db->beginTransaction();
    try {
        if ($designId <= 0 && $clientUUID !== null) {
            // The first write claims the UUID atomically, including concurrent creation requests.
            try {
                $db->prepare('INSERT INTO native_creations (owner_user_id, client_uuid, created_at) VALUES (?, ?, ?)')
                    ->execute([$ownerId, $clientUUID, time()]);
            } catch (PDOException $error) {
                $db->rollBack();
                $lookup = $db->prepare('SELECT c.design_id, d.id, d.title, d.revision, d.updated_at
                    FROM native_creations c LEFT JOIN learning_designs d
                    ON d.id = c.design_id AND d.owner_user_id = c.owner_user_id
                    WHERE c.owner_user_id = ? AND c.client_uuid = ?');
                $lookup->execute([$ownerId, $clientUUID]);
                $existing = $lookup->fetch();
                if (!$existing) throw $error;
                if ($existing['id'] === null) {
                    return ['status' => 404, 'success' => false, 'error' => 'Production supprimée.'];
                }
                return ['status' => 200, 'success' => true, 'design' => [
                    'id' => (int)$existing['id'], 'title' => (string)$existing['title'],
                    'revision' => (int)$existing['revision'], 'updatedAt' => (string)$existing['updated_at'],
                ]];
            }
        }
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
            if ($clientUUID !== null) {
                $db->prepare('UPDATE native_creations SET design_id = ? WHERE owner_user_id = ? AND client_uuid = ?')
                    ->execute([$designId, $ownerId, $clientUUID]);
            }
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

/** A tombstone and its deletion commit together; UUID creation records remain intact. */
function app_delete_design_document(PDO $db, int $ownerId, int $designId): bool
{
    $db->beginTransaction();
    try {
        $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $sql = $sqlite
            ? 'INSERT OR IGNORE INTO native_design_deletions (owner_user_id, design_id, deleted_at)
                SELECT owner_user_id, id, ? FROM learning_designs WHERE id = ? AND owner_user_id = ?'
            : 'INSERT IGNORE INTO native_design_deletions (owner_user_id, design_id, deleted_at)
                SELECT owner_user_id, id, ? FROM learning_designs WHERE id = ? AND owner_user_id = ?';
        $db->prepare($sql)->execute([time(), $designId, $ownerId]);
        $delete = $db->prepare('DELETE FROM learning_designs WHERE id = ? AND owner_user_id = ?');
        $delete->execute([$designId, $ownerId]);
        $deleted = $delete->rowCount() > 0;
        if ($deleted) app_forget_design_sharing($db, $designId);
        $db->commit();
        return $deleted;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

/**
 * Retire les partages et la présence d’un scénario supprimé, dans la transaction de sa suppression.
 * Le nettoyage reste explicite : un identifiant réattribué par MySQL ne doit jamais hériter d’un partage.
 */
function app_forget_design_sharing(PDO $db, int $designId): void
{
    $db->prepare('DELETE FROM design_collaborators WHERE design_id = ?')->execute([$designId]);
    $db->prepare('DELETE FROM design_presence WHERE design_id = ?')->execute([$designId]);
}
