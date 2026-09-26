<?php
declare(strict_types=1);

$fixture = sys_get_temp_dir() . '/scenarisation-permissions-dialog-' . bin2hex(random_bytes(8));
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
        (1, 'Admin', 'admin@example.test', 'test', 'admin', 'active', CURRENT_TIMESTAMP, 0, 0, 0),
        (2, 'Manager', 'manager@example.test', 'test', 'manager', 'active', CURRENT_TIMESTAMP, 1, 0, 1),
        (3, 'Designer', 'designer@example.test', 'test', 'designer', 'active', CURRENT_TIMESTAMP, 0, 0, 0)");

    app_start_session();
    $_SESSION['user'] = ['id' => 1, 'username' => 'Admin', 'email' => 'admin@example.test', 'role' => 'admin'];
    ob_start();
    require __DIR__ . '/../admin.php';
    $html = (string)ob_get_clean();

    check(!str_contains($html, '<section class="panel admin-manager-permissions"'), 'permissions are no longer displayed in a separate section');
    check(substr_count($html, 'class="admin-permission-open"') === 1, 'only manager rows receive a permissions button');
    check(str_contains($html, 'data-manager-id="2"'), 'manager button targets the correct account');
    check(str_contains($html, 'data-manage-accounts="1"'), 'button carries account permission state');
    check(str_contains($html, 'data-delete-feedback="0"'), 'button carries feedback permission state');
    check(str_contains($html, 'data-view-security-log="1"'), 'button carries security permission state');
    check(str_contains($html, 'id="admin-manager-permissions-dialog"'), 'shared permissions dialog is rendered for administrator');
    check(str_contains($html, 'name="admin_action" value="update_manager_permissions"'), 'dialog submits the permissions update action');

    echo $checks . " permissions dialog UI checks passed\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $db = null;
    cleanup_fixture($fixture);
}
