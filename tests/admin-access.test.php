<?php
declare(strict_types=1);

$fixture = sys_get_temp_dir() . '/scenarisation-admin-access-' . bin2hex(random_bytes(8));
mkdir($fixture . '/sessions', 0700, true);
putenv('APP_DB_DSN=sqlite:' . $fixture . '/test.sqlite');
putenv('APP_BASE_URL=http://localhost');
ini_set('session.save_path', $fixture . '/sessions');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/admin.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function cleanup_fixture(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (new FilesystemIterator($path) as $entry) {
        if ($entry->isDir()) {
            cleanup_fixture($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

try {
    require_once __DIR__ . '/../lib/bootstrap.php';
    $db = app_db();
    $db->exec("INSERT INTO users (id, username, email, password_hash, role, status, email_verified_at) VALUES
        (1, 'Limited manager', 'manager@example.test', 'test', 'manager', 'active', CURRENT_TIMESTAMP),
        (2, 'Active designer', 'designer-private@example.test', 'test', 'designer', 'active', CURRENT_TIMESTAMP)");
    $db->exec("INSERT INTO learning_designs (owner_user_id, title, document_json) VALUES
        (2, 'Example scenario', '{}')");
    $db->prepare("INSERT INTO app_feedback (rating, comment, page_path, locale, visitor_hash, created_at_epoch) VALUES
        ('positive', 'Helpful platform', '/help.php', 'fr', '', ?)")->execute([time()]);

    app_start_session();
    $_SESSION['user'] = [
        'id' => 1,
        'username' => 'Limited manager',
        'email' => 'manager@example.test',
        'role' => 'manager',
    ];

    ob_start();
    require __DIR__ . '/../admin.php';
    $html = (string)ob_get_clean();

    check(str_contains($html, 'admin-panel-statistics'), 'manager sees platform statistics');
    check(str_contains($html, 'admin-panel-feedback'), 'manager sees user feedback');
    check(str_contains($html, 'Helpful platform'), 'manager can read recent feedback');
    check(!str_contains($html, 'id="admin-panel-accounts"'), 'manager does not receive account controls');
    check(!str_contains($html, 'id="admin-panel-security"'), 'manager does not receive security controls');
    check(!str_contains($html, '<form method="post" class="admin-feedback-delete-form"'), 'manager cannot delete feedback from the interface');
    check(!str_contains($html, 'designer-private@example.test'), 'manager statistics do not expose creator emails');
    check(str_contains($html, 'Accès gestionnaire'), 'manager is told that access is limited');

    echo $checks . " limited administration checks passed\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $db = null;
    cleanup_fixture($fixture);
}
