<?php
declare(strict_types=1);

// Real page rendering with disposable SQLite data, including a failed connection.
if (($argv[1] ?? '') === 'render') {
    ini_set('session.save_path', sys_get_temp_dir());
    require_once __DIR__ . '/../lib/bootstrap.php';
    app_start_session();
    if (($argv[2] ?? '') === 'signed-in') $_SESSION['user'] = ['id' => 1];
    if (($argv[3] ?? '') === 'embed') $_GET['embed'] = '1';
    ob_start();
    require __DIR__ . '/../share.php';
    $html = ob_get_clean();
    echo json_encode(['status' => http_response_code() ?: 200, 'html' => $html]);
    exit;
}

function gallery_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function gallery_render(string $dsn, bool $signedIn = false, bool $embed = false): array {
    putenv('APP_DB_DSN=' . $dsn);
    $process = proc_open([PHP_BINARY, __FILE__, 'render', $signedIn ? 'signed-in' : 'guest', $embed ? 'embed' : 'page'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    gallery_check(proc_close($process) === 0, 'Page failed: ' . $errors);
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

$fixture = sys_get_temp_dir() . '/shared-gallery-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
try {
    foreach ([false, true] as $signedIn) {
        $result = gallery_render('sqlite:' . $fixture . '/missing/database.sqlite', $signedIn);
        gallery_check($result['status'] === 503, 'Unavailable database returns 503');
        gallery_check(str_contains($result['html'], 'temporairement indisponibles'), 'Unavailable message rendered');
        gallery_check(str_contains($result['html'], 'site-nav-actions'), 'Navigation survives database failure');
        gallery_check(!str_contains($result['html'], 'Aucun scénario n’est encore visible'), 'Failure is not an empty catalogue');
        gallery_check(!str_contains($result['html'], 'PDOException') && !str_contains($result['html'], $fixture), 'No internal details exposed');
    }
    $dsn = 'sqlite:' . $fixture . '/gallery.sqlite';
    $result = gallery_render($dsn);
    gallery_check($result['status'] === 200 && str_contains($result['html'], 'Aucun scénario n’est encore visible'), 'Healthy empty gallery renders normally');
    $db = new PDO($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec("INSERT INTO users (username, email, password_hash, role) VALUES ('Teacher', 'teacher@example.test', 'test-only', 'designer')");
    $insert = $db->prepare('INSERT INTO learning_designs (owner_user_id, title, document_json, is_published, is_listed, share_token) VALUES (1, ?, ?, ?, ?, ?)');
    $insert->execute(['Public example', '{"meta":{"name":"Public example","schoolSystem":"france","schoolLevel":"quatrieme"},"sessions":[{"activities":[{"tools":["competency:acquerir:1"]}]}]}', 1, 1, 'public-example']);
    $insert->execute(['Private example', '{"sessions":[]}', 0, 0, null]);
    $result = gallery_render($dsn);
    gallery_check($result['status'] === 200 && str_contains($result['html'], 'Public example'), 'Published catalogue still loads');
    gallery_check(!str_contains($result['html'], 'Private example'), 'Private scenarios stay excluded');
    $embedded = gallery_render($dsn, false, true);
    gallery_check(str_contains($embedded['html'], 'data-school-stage="college"'), 'French lower-secondary stage is exposed for filtering');
    gallery_check(str_contains($embedded['html'], 'data-school-level="quatrieme"'), 'Exact school level is exposed for filtering');
    gallery_check(str_contains($embedded['html'], 'competency:acquerir:1'), 'Activity competencies are exposed for filtering');
    echo "Shared gallery checks passed\n";
} finally {
    $db = null;
    foreach (glob($fixture . '/*') as $file) unlink($file);
    rmdir($fixture);
}
