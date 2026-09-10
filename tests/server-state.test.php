<?php
declare(strict_types=1);

// No application data is used: config, sessions and SQLite live in a disposable fixture.
$fixture = sys_get_temp_dir() . '/ld-server-test-' . bin2hex(random_bytes(8));
mkdir($fixture . '/web/lib', 0700, true);
mkdir($fixture . '/sessions', 0700);
copy(__DIR__ . '/../lib/bootstrap.php', $fixture . '/web/lib/bootstrap.php');
copy(__DIR__ . '/../app-config.php', $fixture . '/web/app-config.php');
file_put_contents($fixture . '/web/config.local.php', "<?php return ['APP_BASE_URL'=>'https://local.example.test/learning', 'APP_MAIL_FROM'=>'local@example.test'];");
putenv('APP_DB_DSN=sqlite:' . $fixture . '/test.sqlite');
putenv('APP_BASE_URL');
putenv('APP_MAIL_FROM');
unset($_SERVER['APP_BASE_URL'], $_SERVER['APP_MAIL_FROM']);
ini_set('session.save_path', $fixture . '/sessions');
$_SERVER['SCRIPT_NAME'] = '/learning/designer.php';

$passed = [];
function check(bool $condition, string $name): void {
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $passed[] = $name;
}
function cleanup_fixture(string $path): void {
    if (!is_dir($path)) return;
    foreach (new FilesystemIterator($path) as $entry) {
        if ($entry->isDir()) cleanup_fixture($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($path);
}

try {
    require $fixture . '/web/lib/bootstrap.php';
    app_start_session();
    check(app_base_url() === 'https://local.example.test/learning', 'local base URL overrides distributed defaults');
    check(app_env('APP_MAIL_FROM') === 'local@example.test', 'local sender overrides distributed defaults');
    putenv('APP_BASE_URL=https://env.example.test/app');
    check(app_base_url() === 'https://env.example.test/app', 'environment remains highest priority');
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = 'designs.my-school.fr';
    $_SERVER['SCRIPT_NAME'] = '/atelier/profile.php';
    putenv('APP_BASE_URL=https://example.com/learning-designer-revised');
    check(app_base_url() === 'https://designs.my-school.fr/atelier', 'sample URL falls back to actual site and installation folder');
    putenv('APP_BASE_URL=https://WWW.EXAMPLE.COM/old-folder');
    $_SERVER['SCRIPT_NAME'] = '/profile.php';
    check(app_base_url() === 'https://designs.my-school.fr', 'sample subdomain also falls back for root installations');
    putenv('APP_BASE_URL=https://public.my-school.fr');
    $_SERVER['SCRIPT_NAME'] = '/atelier/profile.php';
    check(app_base_url() === 'https://public.my-school.fr', 'configured root does not inherit the request folder');
    putenv('APP_BASE_URL=https://public.my-school.fr/published');
    check(app_base_url() === 'https://public.my-school.fr/published', 'valid explicit public path remains authoritative');
    putenv('APP_BASE_URL=https://scenarisation.eu/');
    $_SERVER['HTTP_HOST'] = 'www.ralentirtravaux.com';
    $_SERVER['SCRIPT_NAME'] = '/flo/learning-designer/signup.php';
    check(app_base_url() === 'https://scenarisation.eu', 'alternate hosting URL preserves canonical root and removes trailing slash');
    check(app_base_url() . '/verify-email.php?token=test' === 'https://scenarisation.eu/verify-email.php?token=test', 'verification link uses canonical root from alternate hosting path');
    check(app_base_url() . '/reset-password.php?token=test' === 'https://scenarisation.eu/reset-password.php?token=test', 'password reset link uses canonical root from alternate hosting path');
    check(app_origin_url() === 'https://www.ralentirtravaux.com', 'request origin remains independent of canonical URL');
    $_SERVER['HTTP_HOST'] = 'scenarisation.eu';
    $_SERVER['SCRIPT_NAME'] = '/signup.php';
    check(app_base_url() === 'https://scenarisation.eu', 'both access URLs produce the same public base');
    putenv('APP_BASE_URL=https://www.ralentirtravaux.com/flo/learning-designer/');
    check(app_base_url() === 'https://www.ralentirtravaux.com/flo/learning-designer', 'explicit canonical subfolder remains supported');
    unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST']);
    $_SERVER['SCRIPT_NAME'] = '/learning/designer.php';
    putenv('APP_BASE_URL');

    // Simulate an existing v4 installation with a saved design and no revision.
    $legacy = new PDO('sqlite:' . $fixture . '/test.sqlite');
    $legacy->exec("CREATE TABLE app_schema_meta (id INTEGER PRIMARY KEY, schema_version INTEGER NOT NULL)");
    $legacy->exec("INSERT INTO app_schema_meta VALUES (1,4)");
    ensure_app_tables($legacy);
    ensure_app_migrations($legacy);
    $legacy->exec("ALTER TABLE learning_designs DROP COLUMN revision");
    $legacy->exec("INSERT INTO users (id,username,email,password_hash) VALUES (10,'Pending','pending@example.test','test')");
    $legacy->exec("INSERT INTO learning_designs (owner_user_id,title,document_json) VALUES (1,'Legacy design','{\"sessions\":[]}')");
    $legacy = null;
    $db = app_db();
    check((int)$db->query('SELECT schema_version FROM app_schema_meta')->fetchColumn() === APP_SCHEMA_VERSION, 'v4 schema upgrades to current version');
    check((int)$db->query('SELECT revision FROM learning_designs WHERE id=1')->fetchColumn() === 1, 'existing design starts at revision one');
    check($db->query('SELECT email_verified_at FROM users WHERE id=10')->fetchColumn() === null, 'upgrade does not replay old email-verification backfills');
    check($db->query('SELECT title FROM learning_designs WHERE id=1')->fetchColumn() === 'Legacy design', 'migration preserves existing content');
    check($db->query('SELECT terms_accepted_at FROM users WHERE id=10')->fetchColumn() === null, 'legacy account receives no fabricated acceptance date');
    check($db->query('SELECT terms_version FROM users WHERE id=10')->fetchColumn() === null, 'legacy account receives no fabricated terms version');
    ensure_app_schema($db);
    check((int)$db->query('SELECT revision FROM learning_designs WHERE id=1')->fetchColumn() === 1, 'migration is idempotent');

    $db->exec("INSERT INTO users (id,username,email,password_hash,role,status) VALUES (1,'Admin','admin@example.test','test','admin','active')");
    $_SESSION['user'] = ['id'=>1,'username'=>'Old name','email'=>'old@example.test','role'=>'admin'];
    check(current_user()['username'] === 'Admin', 'session identity is refreshed from database');
    $db->exec("UPDATE users SET role='designer' WHERE id=1");
    check(current_user()['role'] === 'designer', 'role removal takes effect in existing session');
    $db->exec("UPDATE users SET status='disabled' WHERE id=1");
    check(current_user() === null && !isset($_SESSION['user']), 'disabled user loses existing session');
    $_SESSION['user'] = ['id'=>999,'role'=>'admin'];
    check(current_user() === null && !isset($_SESSION['user']), 'deleted user loses existing session');
    $db->exec("UPDATE users SET status='active' WHERE id=1");

    // Freeze the SQL timestamp: revision checks must still distinguish writes.
    @$db->sqliteCreateFunction('current_timestamp', static fn() => '2026-09-05 12:00:00', 0);
    $a = app_save_design_document($db, 1, 1, 1, 'A', '{"sessions":[],"meta":{"name":"A"}}');
    $b = app_save_design_document($db, 1, 1, 2, 'B', '{"sessions":[],"meta":{"name":"B"}}');
    check($a['design']['updatedAt'] === $b['design']['updatedAt'], 'fixture uses identical timestamps for different writes');
    check($a['design']['revision'] === 2 && $b['design']['revision'] === 3, 'every write increments revision despite identical timestamp');
    $stale = app_save_design_document($db, 1, 1, 2, 'Stale', '{}');
    check($stale['status'] === 409 && $stale['design']['revision'] === 3, 'stale write is rejected');
    check($db->query('SELECT title FROM learning_designs WHERE id=1')->fetchColumn() === 'B', 'rejected write preserves server content');
    check(app_save_design_document($db, 1, 1, null, 'Old browser', '{}')['status'] === 409, 'missing revision fails safely');
    check(app_save_design_document($db, 2, 1, 3, 'Wrong owner', '{}')['status'] === 404, 'ownership is enforced');
    check(app_save_design_document($db, 1, 999, 1, 'Deleted design', '{}')['status'] === 404, 'deleted design is not silently recreated');
    check(!$db->inTransaction(), 'rejected writes close their transaction');
    $copy = app_save_design_document($db, 1, 0, null, 'Copy', '{}');
    check($copy['success'] && $copy['design']['revision'] === 1 && $copy['design']['id'] !== 1, 'copy creates an independent first revision');
    $pub = app_save_design_document($db, 1, 1, 3, 'Published', '{}', true);
    check($pub['design']['revision'] === 4 && strlen($pub['share_token']) === 48, 'CLI publication increments content revision and creates a share token');
    $pub2 = app_save_design_document($db, 1, 1, 4, 'Updated publication', '{}', true);
    check($pub['share_token'] === $pub2['share_token'], 'updating a publication preserves its share link');
    check(app_save_design_document($db, 1, 1, 3, 'Old web tab', '{}')['status'] === 409, 'web save detects a CLI update');

    // Two independent processes compete for the same revision. The timestamp
    // function holds the writer lock briefly so their transactions overlap.
    $worker = $fixture . '/worker.php';
    file_put_contents($worker, '<?php require __DIR__ . "/web/lib/bootstrap.php"; $db=app_db();'
        . '@$db->sqliteCreateFunction("current_timestamp", static function(){usleep(150000);return "2026-09-05 12:00:00";},0);'
        . '$r=app_save_design_document($db,1,1,5,$argv[1],"{}"); echo json_encode($r);');
    $workers = [];
    foreach (['Writer A','Writer B'] as $name) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, $worker, $name], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start concurrency test');
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $statuses = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException($error);
        $statuses[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR)['status'];
    }
    sort($statuses);
    check($statuses === [200,409], 'concurrent writers yield exactly one success and one conflict');
    check((int)$db->query('SELECT revision FROM learning_designs WHERE id=1')->fetchColumn() === 6, 'concurrent writes advance revision only once');
    session_write_close();
    echo count($passed) . " server checks passed\n";
    foreach ($passed as $name) echo "  OK: $name\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $db = null;
    cleanup_fixture($fixture);
}
