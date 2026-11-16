<?php
declare(strict_types=1);

$fixture = sys_get_temp_dir() . '/scenarisation-native-race-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
putenv('APP_DB_DSN=sqlite:' . $fixture . '/test.sqlite');
require_once __DIR__ . '/../lib/native-api.php';
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function race(array $payload): array {
    global $fixture;
    $go = $fixture . '/go';
    if (file_exists($go)) unlink($go);
    foreach (glob($fixture . '/ready-*') as $path) unlink($path);
    $workers = [];
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, $fixture . '/worker.php', dirname(__DIR__), $fixture],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start worker');
        fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR)); fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    for ($i = 0; $i < 200 && count(glob($fixture . '/ready-*')) < 2; $i++) usleep(10000);
    touch($go);
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process) === 0 && $error === '', 'Concurrent worker failed: ' . $error);
        $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    return $results;
}
file_put_contents($fixture . '/worker.php', <<<'WORKER'
<?php
require $argv[1] . '/lib/native-api.php';
$input = json_decode(stream_get_contents(STDIN), false, 512, JSON_THROW_ON_ERROR);
$db = app_db();
file_put_contents($argv[2] . '/ready-' . getmypid(), 'ready');
while (!file_exists($argv[2] . '/go')) usleep(1000);
try {
    $result = $input->mode === 'create'
        ? native_api_save($db, 1, (array)$input->request)
        : native_token_grant($db, (array)$input->request);
    echo json_encode(['status' => 200, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (NativeRequestError $error) {
    echo json_encode(['status' => $error->status, 'error' => $error->oauthError]);
}
WORKER);

try {
    $db = app_db();
    $db->exec("INSERT INTO users (id,username,email,password_hash,role,status,email_verified_at)
        VALUES (1,'Teacher','teacher@example.test','test','admin','active',CURRENT_TIMESTAMP)");
    $creation = ['client_uuid' => 'b8b7d4ac-e264-4ee9-8f26-2e348fe6af3a',
        'document' => (object)['meta' => (object)['name' => 'Concurrent creation'], 'sessions' => []]];
    $results = race(['mode' => 'create', 'request' => $creation]);
    check(array_column($results, 'status') === [200, 200], 'Concurrent creations both resolve successfully');
    check($results[0]['result']['design']['id'] === $results[1]['result']['design']['id'], 'Concurrent UUID requests resolve to one ID');
    check((int)$db->query('SELECT COUNT(*) FROM learning_designs')->fetchColumn() === 1, 'Only one scenario was inserted');
    $authorization = native_validate_authorization([
        'client_id' => NATIVE_CLIENT_ID, 'response_type' => 'code',
        'redirect_uri' => 'http://127.0.0.1:54123/auth/callback', 'state' => str_repeat('s', 32),
        'code_challenge' => native_pkce(str_repeat('v', 64)), 'code_challenge_method' => 'S256',
    ]);
    $input = ['client_id' => NATIVE_CLIENT_ID, 'grant_type' => 'authorization_code',
        'code' => native_issue_code($db, 1, $authorization), 'code_verifier' => str_repeat('v', 64),
        'redirect_uri' => $authorization['redirect_uri']];
    $results = race(['mode' => 'token', 'request' => $input]);
    $statuses = array_column($results, 'status'); sort($statuses);
    check($statuses === [200, 400], 'An authorization code can only be consumed once');
    $winner = array_values(array_filter($results, fn($item) => $item['status'] === 200))[0]['result'];
    try {
        native_access_user($db, $winner['access_token'], 'designs:read');
        throw new RuntimeException('Code reuse must revoke the resulting authorization');
    } catch (NativeRequestError $error) {
        check($error->oauthError === 'invalid_token', 'Concurrent code reuse revokes the issued session');
    }
    $input['code'] = native_issue_code($db, 1, $authorization);
    $tokens = native_token_grant($db, $input);
    $results = race(['mode' => 'token', 'request' => [
        'client_id' => NATIVE_CLIENT_ID, 'grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'],
    ]]);
    $statuses = array_column($results, 'status'); sort($statuses);
    check($statuses === [200, 400], 'A refresh token can only be rotated once');
    $winner = array_values(array_filter($results, fn($item) => $item['status'] === 200))[0]['result'];
    try {
        native_access_user($db, $winner['access_token'], 'designs:read');
        throw new RuntimeException('Refresh reuse must revoke the resulting authorization');
    } catch (NativeRequestError $error) {
        check($error->oauthError === 'invalid_token', 'Concurrent refresh reuse revokes the whole session');
    }
    check(!$db->inTransaction(), 'Concurrency leaves no open transaction');
    echo "$checks native concurrency checks passed\n";
} finally {
    $db = null;
    foreach (glob($fixture . '/*') as $path) unlink($path);
    rmdir($fixture);
}
