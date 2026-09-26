<?php
declare(strict_types=1);

$fixture = sys_get_temp_dir() . '/scenarisation-manager-permissions-' . bin2hex(random_bytes(8));
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
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function cleanup_fixture(string $path): void
{
    if (!is_dir($path)) return;
    foreach (new FilesystemIterator($path) as $entry) {
        if ($entry->isDir()) cleanup_fixture($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($path);
}

try {
    require_once __DIR__ . '/../lib/bootstrap.php';
    $db = app_db();
    $db->exec("INSERT INTO users (
        id, username, email, password_hash, role, status, email_verified_at,
        manager_manage_accounts, manager_delete_feedback, manager_view_security_log
    ) VALUES
        (1, 'Authorized manager', 'manager@example.test', 'test', 'manager', 'active', CURRENT_TIMESTAMP, 1, 1, 1),
        (2, 'Platform admin', 'admin@example.test', 'test', 'admin', 'active', CURRENT_TIMESTAMP, 0, 0, 0),
        (3, 'Designer', 'designer@example.test', 'test', 'designer', 'active', CURRENT_TIMESTAMP, 0, 0, 0)");
    $db->prepare("INSERT INTO app_feedback (rating, comment, page_path, locale, visitor_hash, created_at_epoch) VALUES
        ('positive', 'Permission test', '/help.php', 'fr', '', ?)")->execute([time()]);

    app_start_session();
    $_SESSION['user'] = ['id' => 1, 'username' => 'Authorized manager', 'email' => 'manager@example.test', 'role' => 'manager'];
    ob_start();
    require __DIR__ . '/../admin.php';
    $html = (string)ob_get_clean();

    check(str_contains($html, 'id="admin-panel-accounts"'), 'authorized manager sees account tools');
    check(str_contains($html, 'id="admin-panel-security"'), 'authorized manager sees security tools');
    check(str_contains($html, '<form method="post" class="admin-feedback-delete-form"'), 'authorized manager can delete feedback');
    check(str_contains($html, 'name="admin_action" value="moderate_user"'), 'authorized manager receives moderation form');
    check(str_contains($html, 'Historique des interventions'), 'authorized manager can view security log');
    check(!str_contains($html, '<option value="delete"'), 'manager moderation excludes account deletion');
    check(!str_contains($html, 'name="admin_action" value="change_role"'), 'manager cannot change roles');
    check(!str_contains($html, 'id="admin-manager-permissions-title"'), 'manager cannot assign permissions');
    check(!str_contains($html, 'export_scenarios.php?scope=all'), 'manager cannot export every scenario');

    echo $checks . " configurable manager UI checks passed\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $db = null;
    cleanup_fixture($fixture);
}
