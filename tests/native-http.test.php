<?php
declare(strict_types=1);

// Real PHP HTTP routes, browser cookies and OAuth redirects, with a disposable database.
$fixture = sys_get_temp_dir() . '/scenarisation-native-http-' . bin2hex(random_bytes(8));
mkdir($fixture . '/sessions', 0700, true);
putenv('APP_DB_DSN=sqlite:' . $fixture . '/test.sqlite');
ini_set('session.save_path', $fixture . '/sessions');
$root = dirname(__DIR__);
require_once $root . '/lib/native-api.php';
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) throw new RuntimeException('Cannot allocate local test port');
$address = stream_socket_get_name($socket, false); fclose($socket);
$base = 'http://' . $address;
putenv('APP_BASE_URL=' . $base);
$checks = 0; $cookie = ''; $server = null; $secrets = [];

function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function request(string $route, string $method = 'GET', mixed $input = null, ?string $bearer = null, bool $json = false, bool $browser = true): array {
    global $base, $cookie;
    $headers = ['Origin: ' . $base];
    if ($browser && $cookie !== '') $headers[] = 'Cookie: ' . $cookie;
    if ($bearer !== null) $headers[] = 'Authorization: Bearer ' . $bearer;
    $body = $input === null ? '' : ($json ? json_encode($input, JSON_THROW_ON_ERROR) : http_build_query($input));
    if ($input !== null) $headers[] = 'Content-Type: ' . ($json ? 'application/json' : 'application/x-www-form-urlencoded');
    $options = ['http' => ['method' => $method, 'header' => implode("\r\n", $headers),
        'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]];
    $text = file_get_contents($base . '/' . ltrim($route, '/'), false, stream_context_create($options));
    if ($text === false) throw new RuntimeException('HTTP request failed');
    $lines = $http_response_header ?? [];
    preg_match('~HTTP/\S+ (\d+)~', $lines[0] ?? '', $match);
    $responseHeaders = [];
    foreach (array_slice($lines, 1) as $line) {
        if (!str_contains($line, ':')) continue;
        [$name, $value] = explode(':', $line, 2);
        $responseHeaders[strtolower($name)] = trim($value);
        if ($browser && strtolower($name) === 'set-cookie') $cookie = explode(';', trim($value))[0];
    }
    return ['status' => (int)($match[1] ?? 0), 'headers' => $responseHeaders, 'body' => $text];
}
function decoded(array $response): array {
    return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
}
function csrf_from(string $html): string {
    if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $match)) throw new RuntimeException('CSRF field missing');
    return $match[1];
}
function completion_callback(array $response): array {
    check($response['status'] === 303
        && preg_match('/^oauth-authorize\.php\?complete=[a-f0-9]{32}$/D', $response['headers']['location'] ?? '') === 1,
        'Consent finishes its form redirect on the same site');
    $page = request($response['headers']['location']);
    check($page['status'] === 200 && ($page['headers']['referrer-policy'] ?? '') === 'no-referrer',
        'Return page is available without disclosing its code through referrers');
    check(($page['headers']['cache-control'] ?? '') === 'no-store', 'Return page is not cached');
    check(str_contains($page['body'], 'js/native-consent.js') && str_contains($page['body'], 'Revenir à l’app'),
        'Return page includes automatic navigation and a visible fallback link');
    if (!preg_match('/id="native-return"[^>]*href="([^"]+)"/', $page['body'], $match)) {
        throw new RuntimeException('Return link missing');
    }
    $url = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
    check(str_starts_with($url, 'http://127.0.0.1:54123/auth/callback?'), 'Return link uses only the registered Mac loopback');
    parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
    return $parameters;
}
function auth_query(string $scope = 'designs:read designs:write'): array {
    return ['client_id' => NATIVE_CLIENT_ID, 'response_type' => 'code',
        'redirect_uri' => 'http://127.0.0.1:54123/auth/callback', 'code_challenge_method' => 'S256',
        'code_challenge' => native_pkce(str_repeat('v', 64)), 'state' => str_repeat('s', 32), 'scope' => $scope];
}
function exchange(string $code): array {
    global $secrets;
    $response = request('oauth-token.php', 'POST', ['client_id' => NATIVE_CLIENT_ID, 'grant_type' => 'authorization_code',
        'code' => $code, 'code_verifier' => str_repeat('v', 64), 'redirect_uri' => 'http://127.0.0.1:54123/auth/callback'], browser: false);
    check($response['status'] === 200, 'Code exchange returns HTTP 200');
    $tokens = decoded($response); $secrets[] = $tokens['access_token']; $secrets[] = $tokens['refresh_token'];
    check(($response['headers']['cache-control'] ?? '') === 'no-store', 'Token response is not cacheable');
    check(!isset($response['headers']['set-cookie']), 'Token endpoint uses no browser session');
    return $tokens;
}

try {
    $db = app_db();
    $db->prepare("INSERT INTO users (id,username,email,password_hash,role,status,email_verified_at)
        VALUES (1,'Teacher','teacher@example.test',?,'admin','active',CURRENT_TIMESTAMP)")
        ->execute([password_hash('native-test-password', PASSWORD_DEFAULT)]);
    $pipes = [];
    $server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $fixture . '/sessions',
        '-d', 'display_errors=0', '-S', $address, '-t', $root],
        [0 => ['pipe', 'r'], 1 => ['file', $fixture . '/server.log', 'a'], 2 => ['file', $fixture . '/server.log', 'a']], $pipes, $root);
    if (!is_resource($server)) throw new RuntimeException('Cannot start isolated HTTP server');
    fclose($pipes[0]);
    $ready = false;
    for ($i = 0; $i < 60; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, .1);
        if ($connection) { fclose($connection); $ready = true; break; }
        usleep(20000);
    }
    check($ready, 'Local HTTP test server starts');

    $invalid = request('oauth-authorize.php?' . http_build_query(array_merge(auth_query(), ['redirect_uri' => 'https://attacker.test/'])));
    check($invalid['status'] === 400 && !isset($invalid['headers']['location']), 'Invalid redirects stay on the site');
    $start = request('oauth-authorize.php?' . http_build_query(auth_query()));
    check($start['status'] === 303 && str_starts_with($start['headers']['location'], 'login.php?native_request='), 'Authorization continues through browser login');
    parse_str(parse_url($start['headers']['location'], PHP_URL_QUERY), $loginParameters);
    $login = request($start['headers']['location']);
    check(str_contains($login['body'], 'name="native_request"'), 'Login retains the pending authorization');
    $signedIn = request('login.php', 'POST', ['email' => 'teacher@example.test', 'password' => 'native-test-password',
        'native_request' => $loginParameters['native_request']]);
    check($signedIn['status'] === 302 && str_starts_with($signedIn['headers']['location'], 'oauth-authorize.php?request='), 'Successful login returns to consent');
    $consent = request($signedIn['headers']['location']);
    check($consent['status'] === 200 && str_contains($consent['body'], 'name="decision" value="allow"'), 'Consent displays explicit authorization');
    check(!str_contains($consent['body'], 'native-test-password'), 'Consent never includes the password');
    check(($consent['headers']['x-frame-options'] ?? '') === 'DENY', 'Consent cannot be framed');
    check(($consent['headers']['referrer-policy'] ?? '') === 'same-origin', 'Same-origin form verification retains its origin without leaking URLs to third parties');
    check(str_contains($consent['headers']['content-security-policy'] ?? '', "form-action 'self'")
        && !str_contains($consent['headers']['content-security-policy'] ?? '', 'http://127.0.0.1:'),
        'Form navigation remains restricted to the site');
    $csrf = csrf_from($consent['body']);
    parse_str(parse_url($signedIn['headers']['location'], PHP_URL_QUERY), $authorization);
    check(str_contains($consent['body'], 'action="oauth-authorize.php?request=' . $authorization['request'] . '"'),
        'Consent has an explicit same-origin action bound to the displayed request');
    $missingForm = request('oauth-authorize.php', 'POST', ['decision' => 'allow', 'csrf_token' => $csrf]);
    check($missingForm['status'] === 400 && str_contains($missingForm['body'], 'formulaire de connexion est incomplet'),
        'Missing form identity is not reported as an expiration');
    $lostSession = request('oauth-authorize.php?request=' . $authorization['request'], browser: false);
    check($lostSession['status'] === 400 && str_contains($lostSession['body'], 'session du navigateur'),
        'A request from another browser session is refused with an accurate message');
    $mismatch = request('oauth-authorize.php?request=' . str_repeat('f', 32), 'POST',
        ['request' => $authorization['request'], 'decision' => 'allow', 'csrf_token' => $csrf]);
    check($mismatch['status'] === 400 && !isset($mismatch['headers']['location']), 'Mismatched form and action cannot authorize');
    $badConsent = request('oauth-authorize.php', 'POST', ['request' => $authorization['request'], 'decision' => 'allow', 'csrf_token' => 'invalid']);
    check($badConsent['status'] === 403 && !isset($badConsent['headers']['location']), 'Invalid consent CSRF cannot issue a code');
    $allowed = request('oauth-authorize.php', 'POST', ['request' => $authorization['request'], 'decision' => 'allow', 'csrf_token' => $csrf]);
    $callback = completion_callback($allowed);
    check($callback['state'] === str_repeat('s', 32) && $callback['iss'] === $base, 'Callback binds state and issuer');
    $secrets[] = $callback['code'];
    $codes = (int)$db->query('SELECT COUNT(*) FROM native_oauth_codes')->fetchColumn();
    $repeated = request('oauth-authorize.php', 'POST', ['request' => $authorization['request'], 'decision' => 'allow', 'csrf_token' => $csrf]);
    check($repeated['headers']['location'] === $allowed['headers']['location'] && completion_callback($repeated) === $callback,
        'Repeated consent returns to the existing confirmation instead of displaying an error');
    check((int)$db->query('SELECT COUNT(*) FROM native_oauth_codes')->fetchColumn() === $codes,
        'Repeated consent never issues another code');
    $badRepeat = request('oauth-authorize.php', 'POST', ['request' => $authorization['request'], 'decision' => 'allow', 'csrf_token' => 'invalid']);
    check($badRepeat['status'] === 403 && !isset($badRepeat['headers']['location']), 'Repeated consent still requires its CSRF token');
    $changedDecision = request('oauth-authorize.php', 'POST', ['request' => $authorization['request'], 'decision' => 'deny', 'csrf_token' => $csrf]);
    check($changedDecision['status'] === 403, 'Repeated consent cannot change the recorded decision');
    $privateReturn = request($allowed['headers']['location'], browser: false);
    check($privateReturn['status'] === 400 && !str_contains($privateReturn['body'], $callback['code']),
        'Another browser session cannot read the return code');
    $tokens = exchange($callback['code']);
    $me = request('api/v1/me.php', bearer: $tokens['access_token'], browser: false);
    check($me['status'] === 200 && decoded($me)['user']['id'] === 1, 'Bearer identifies the account through HTTP');
    check(!isset($me['headers']['set-cookie']), 'API requests do not create browser sessions');
    check(request('api/v1/me.php')['status'] === 401, 'Browser cookies alone cannot access the native API');
    check(request('api/v1/me.php', bearer: 'ld_cli-token', browser: false)['status'] === 401, 'CLI publication tokens cannot authorize native requests');
    check(request('cli_publish.php', 'POST', [], $tokens['access_token'], true, false)['status'] === 401, 'Native access cannot publish through the CLI route');
    check(request('publish_design.php', 'POST', [], $tokens['access_token'], true, false)['status'] === 401, 'Native access cannot publish through the browser route');
    check(request('api/v1/me.php', 'POST', [], $tokens['access_token'], browser: false)['status'] === 405, 'API methods are enforced');
    check(request('oauth-token.php', 'POST', [], json: true, browser: false)['status'] === 400, 'Token endpoint rejects JSON in place of OAuth form data');
    $document = json_decode('{"meta":{"name":"HTTP scenario","future":{}},"sessions":[{"id":"m-1","activities":[{"id":"a-1","duration":12}]}]}');
    $input = ['client_uuid' => '9bdbd672-884b-44d4-962c-bc88e9a72202', 'document' => $document];
    $created = request('api/v1/save_design.php', 'POST', $input, $tokens['access_token'], true, false);
    check($created['status'] === 200, 'HTTP creation succeeds');
    $id = decoded($created)['design']['id'];
    $retry = request('api/v1/save_design.php', 'POST', $input, $tokens['access_token'], true, false);
    check(decoded($retry)['design']['id'] === $id, 'HTTP creation retries use the same scenario');
    $list = request('api/v1/list_designs.php', bearer: $tokens['access_token'], browser: false);
    check(decoded($list)['items'][0]['revision'] === 1, 'HTTP list includes revisions');
    $get = request('api/v1/get_design.php?design_id=' . $id, bearer: $tokens['access_token'], browser: false);
    $bodyObject = json_decode($get['body']);
    check($bodyObject->design->document->meta->future instanceof stdClass, 'HTTP transport preserves empty objects');
    check(request('api/v1/save_design.php', 'POST', $input, $tokens['access_token'], browser: false)['status'] === 415, 'Scenario writes require JSON');
    $input['design_id'] = $id; $input['expected_revision'] = 1;
    $input['document']->meta->name = 'Updated HTTP scenario';
    $updated = request('api/v1/save_design.php', 'POST', $input, $tokens['access_token'], true, false);
    check(decoded($updated)['design']['revision'] === 2, 'HTTP updates increment revisions');
    check(request('api/v1/save_design.php', 'POST', $input, $tokens['access_token'], true, false)['status'] === 409, 'HTTP stale writes conflict');
    check(request('api/v1/get_design.php?design_id=99999', bearer: $tokens['access_token'], browser: false)['status'] === 404, 'HTTP missing designs return 404');
    $deletedFromList = request('my-designs.php', 'POST', ['action' => 'delete', 'design_id' => $id]);
    check($deletedFromList['status'] === 200 && str_contains($deletedFromList['body'], 'Production supprimée.'), 'Website list deletion keeps its success message');
    check(request('api/v1/get_design.php?design_id=' . $id, bearer: $tokens['access_token'], browser: false)['status'] === 404, 'Website list deletion reaches the native API');
    $missingFromList = request('my-designs.php', 'POST', ['action' => 'delete', 'design_id' => $id]);
    check($missingFromList['status'] === 200 && str_contains($missingFromList['body'], 'Production introuvable.'), 'Repeated website deletion keeps its not-found message');
    $createAgain = ['client_uuid' => '9bdbd672-884b-44d4-962c-bc88e9a72202', 'document' => $document];
    check(request('api/v1/save_design.php', 'POST', $createAgain, $tokens['access_token'], true, false)['status'] === 404, 'Website deletion prevents a delayed native creation from reappearing');
    $createAgain['client_uuid'] = 'f2219b9f-c282-49ab-85dc-6ea8339bd306';
    $second = decoded(request('api/v1/save_design.php', 'POST', $createAgain, $tokens['access_token'], true, false))['design']['id'];
    check(request('delete_design.php', 'POST', ['design_id' => $second], json: true)['status'] === 200, 'Editor deletion still succeeds');
    $deletedList = decoded(request('api/v1/list_designs.php', bearer: $tokens['access_token'], browser: false));
    check(count($deletedList['deleted']) === 2 && count($deletedList['items']) === 0, 'Both website deletion routes record their tombstones');

    $profile = request('profile.php');
    check(str_contains($profile['body'], 'native-session-row') && str_contains($profile['body'], 'Scénarisation pour Mac'), 'Profile displays connected applications');
    preg_match('/name="native_session_id" value="([a-f0-9]{32})"/', $profile['body'], $sessionMatch);
    $badRevoke = request('profile.php', 'POST', ['action' => 'revoke_native_session', 'native_session_id' => $sessionMatch[1], 'csrf_token' => 'bad']);
    check(str_contains($badRevoke['body'], 'La confirmation a expiré'), 'Profile revocation checks CSRF');
    check(request('api/v1/me.php', bearer: $tokens['access_token'], browser: false)['status'] === 200, 'Invalid revocation leaves access intact');
    request('profile.php', 'POST', ['action' => 'revoke_native_session', 'native_session_id' => $sessionMatch[1], 'csrf_token' => csrf_from($profile['body'])]);
    check(request('api/v1/me.php', bearer: $tokens['access_token'], browser: false)['status'] === 401, 'Profile revocation immediately invalidates access');
    check(!str_contains(request('profile.php')['body'], 'class="native-session-row"'), 'Empty connected-app state is displayed after revocation');

    $again = request('oauth-authorize.php?' . http_build_query(auth_query()));
    preg_match('/name="request" value="([a-f0-9]{32})"/', $again['body'], $pending);
    $denied = request('oauth-authorize.php?request=' . $pending[1], 'POST', ['decision' => 'deny', 'csrf_token' => csrf_from($again['body'])]);
    $deniedCallback = completion_callback($denied);
    check($deniedCallback['error'] === 'access_denied' && !isset($deniedCallback['code']), 'Consent denial issues no code');
    $stillSignedIn = request('login.php?native_request=https://attacker.test/');
    check($stillSignedIn['headers']['location'] === 'new-design.php', 'Login ignores arbitrary return destinations');
    $wrongMethod = request('oauth-token.php');
    check($wrongMethod['status'] === 405 && $wrongMethod['headers']['allow'] === 'POST', 'OAuth token route requires POST');
    $revoked = request('oauth-revoke.php', 'POST', ['client_id' => NATIVE_CLIENT_ID, 'token' => 'unknown'], browser: false);
    check($revoked['status'] === 200, 'Unknown-token revocation is idempotent');

    $log = (string)file_get_contents($fixture . '/server.log');
    check(!str_contains($log, 'PHP Fatal error') && !str_contains($log, 'PHP Warning'), 'HTTP routes emit no PHP warnings');
    foreach ($secrets as $secret) check(!str_contains($log, $secret), 'Tokens never appear in request logs');
    echo "$checks native HTTP integration checks passed\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $db = null;
    foreach (glob($fixture . '/sessions/*') as $path) unlink($path);
    rmdir($fixture . '/sessions');
    foreach (glob($fixture . '/*') as $path) unlink($path);
    rmdir($fixture);
}
