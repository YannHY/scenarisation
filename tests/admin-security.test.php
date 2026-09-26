<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/admin-security.php';

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys = ON');
ensure_app_tables($db);
ensure_app_migrations($db);
ensure_admin_security_table($db);
ensure_admin_security_table($db);
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function rejected(callable $action, string $message): void {
    try { $action(); } catch (InvalidArgumentException $e) { check(true, $message); return; }
    throw new RuntimeException('Not rejected: ' . $message);
}
$db->exec("INSERT INTO users (id,username,email,password_hash,role) VALUES
    (1,'Admin','admin@example.test','test','admin'),
    (2,'Designer','designer@example.test','test','designer'),
    (3,'Second admin','second@example.test','test','admin'),
    (4,'Manager','manager@example.test','test','manager'),
    (5,'Second designer','designer2@example.test','test','designer')");
$db->exec("INSERT INTO learning_designs (owner_user_id,title,document_json,is_published,is_listed,share_token) VALUES (2,'Example','{}',1,1,'shared')");
$db->exec("INSERT INTO learning_cli_tokens (user_id,name,token_hash,token_prefix) VALUES (2,'Test','hash','prefix')");
rejected(fn() => admin_moderate_user($db, 2, 1, 'delete', 'Abus', 'Admin'), 'non-admin rejected');
rejected(fn() => admin_moderate_user($db, 4, 2, 'suspend', 'Abus'), 'limited manager rejected');
rejected(fn() => admin_change_user_role($db, 4, 2, 'admin'), 'limited manager cannot change roles');
rejected(fn() => admin_change_user_role($db, 1, 1, 'designer'), 'administrator cannot change own role');
rejected(fn() => admin_change_user_role($db, 1, 2, 'unknown'), 'invalid role rejected');
admin_change_user_role($db, 1, 2, 'manager');
check($db->query('SELECT role FROM users WHERE id=2')->fetchColumn() === 'manager', 'designer promoted to limited manager');
rejected(fn() => admin_change_user_role($db, 1, 2, 'manager'), 'duplicate role change rejected');
admin_change_user_role($db, 1, 2, 'designer');
check($db->query('SELECT role FROM users WHERE id=2')->fetchColumn() === 'designer', 'limited manager returned to designer');
check((int)$db->query("SELECT COUNT(*) FROM admin_security_log WHERE action LIKE 'role_%'")->fetchColumn() === 2, 'role changes are audited');
rejected(fn() => admin_update_manager_permissions($db, 4, 4, true, true, true), 'manager cannot grant own permissions');
rejected(fn() => admin_update_manager_permissions($db, 1, 2, true, false, false), 'detailed permissions rejected for designer');
admin_update_manager_permissions($db, 1, 4, true, true, true);
check((int)$db->query('SELECT manager_manage_accounts + manager_delete_feedback + manager_view_security_log FROM users WHERE id=4')->fetchColumn() === 3, 'administrator grants independent manager permissions');
check((int)$db->query("SELECT COUNT(*) FROM admin_security_log WHERE action='permissions'")->fetchColumn() === 1, 'manager permission change is audited');
admin_moderate_user($db, 4, 5, 'suspend', 'Test gestionnaire');
check($db->query('SELECT status FROM users WHERE id=5')->fetchColumn() === 'disabled', 'authorized manager suspends designer');
admin_moderate_user($db, 4, 5, 'reactivate', 'Test gestionnaire');
check($db->query('SELECT status FROM users WHERE id=5')->fetchColumn() === 'active', 'authorized manager reactivates designer');
rejected(fn() => admin_moderate_user($db, 4, 5, 'delete', 'Test', 'Second designer'), 'manager cannot delete account');
rejected(fn() => admin_moderate_user($db, 4, 1, 'suspend', 'Test'), 'manager cannot moderate administrator');
admin_change_user_role($db, 1, 4, 'designer');
check((int)$db->query('SELECT manager_manage_accounts + manager_delete_feedback + manager_view_security_log FROM users WHERE id=4')->fetchColumn() === 0, 'role change clears manager permissions');
rejected(fn() => admin_moderate_user($db, 1, 1, 'suspend', 'Abus'), 'self suspension rejected');
rejected(fn() => admin_moderate_user($db, 1, 1, 'delete', 'Abus', 'Admin'), 'self deletion rejected');
rejected(fn() => admin_moderate_user($db, 1, 2, 'suspend', ' '), 'reason required');
rejected(fn() => admin_moderate_user($db, 1, 999, 'delete', 'Abus', 'Missing'), 'missing target rejected');
rejected(fn() => admin_moderate_user($db, 1, 2, 'unknown', 'Abus'), 'unknown action rejected');
rejected(fn() => admin_moderate_user($db, 1, 2, 'delete', 'Abus', 'wrong'), 'typed confirmation enforced on server');
check((int)$db->query("SELECT COUNT(*) FROM admin_security_log WHERE target_id=2 AND action IN ('suspend','reactivate','delete')")->fetchColumn() === 0, 'rejected moderation actions do not create success logs');
admin_moderate_user($db, 1, 2, 'suspend', 'Publications abusives');
check($db->query('SELECT status FROM users WHERE id=2')->fetchColumn() === 'disabled', 'account suspended');
check((int)$db->query('SELECT COUNT(*) FROM learning_designs WHERE is_published=1 OR is_listed=1')->fetchColumn() === 0, 'publications withdrawn');
check((int)$db->query('SELECT COUNT(*) FROM learning_cli_tokens WHERE revoked_at IS NULL')->fetchColumn() === 0, 'CLI access revoked');
rejected(fn() => admin_moderate_user($db, 1, 2, 'suspend', 'Repeated'), 'duplicate suspension rejected');
admin_moderate_user($db, 1, 2, 'reactivate', 'Situation résolue');
check($db->query('SELECT status FROM users WHERE id=2')->fetchColumn() === 'active', 'account reactivated');
check((int)$db->query('SELECT is_published FROM learning_designs')->fetchColumn() === 0, 'reactivation does not republish');
$db->exec("CREATE TRIGGER reject_audit BEFORE INSERT ON admin_security_log BEGIN SELECT RAISE(ABORT, 'test log failure'); END");
try {
    admin_moderate_user($db, 1, 2, 'delete', 'Abus', 'Designer');
    throw new RuntimeException('Expected log failure');
} catch (PDOException $e) {
    check((int)$db->query('SELECT COUNT(*) FROM users WHERE id=2')->fetchColumn() === 1, 'log failure rolls back deletion');
    check((int)$db->query('SELECT COUNT(*) FROM learning_designs')->fetchColumn() === 1, 'rollback restores dependent designs');
}
$db->exec('DROP TRIGGER reject_audit');
admin_moderate_user($db, 1, 2, 'delete', 'Récidive', 'Designer');
check((int)$db->query('SELECT COUNT(*) FROM users WHERE id=2')->fetchColumn() === 0, 'account deleted');
check((int)$db->query('SELECT COUNT(*) FROM learning_designs')->fetchColumn() === 0, 'designs cascade deleted');
check((int)$db->query('SELECT COUNT(*) FROM learning_cli_tokens')->fetchColumn() === 0, 'tokens cascade deleted');
check((int)$db->query("SELECT COUNT(*) FROM admin_security_log WHERE target_name='Designer' AND action IN ('suspend','reactivate','delete')")->fetchColumn() === 3, 'history survives account deletion');
admin_moderate_user($db, 1, 3, 'suspend', 'Test');
rejected(fn() => admin_moderate_user($db, 3, 1, 'delete', 'Test', 'Admin'), 'suspended admin cannot delete remaining admin');
check((int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn() === 1, 'last active administrator preserved');
