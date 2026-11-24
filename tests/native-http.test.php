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
    $createAgain['client_uuid'] = '0e6f3a52-7c1d-4f8b-a9e4-2d5b8c7a1f30';
    $third = decoded(request('api/v1/save_design.php', 'POST', $createAgain, $tokens['access_token'], true, false))['design']['id'];
    check(request('api/v1/delete_design.php', 'POST', ['design_id' => $third, 'expected_revision' => 2], $tokens['access_token'], true, false)['status'] === 409, 'HTTP stale deletions conflict');
    check(request('api/v1/delete_design.php', 'POST', ['design_id' => $third, 'expected_revision' => 1], $tokens['access_token'], browser: false)['status'] === 415, 'HTTP deletions require JSON');
    check(request('api/v1/delete_design.php', bearer: $tokens['access_token'], browser: false)['status'] === 405, 'HTTP deletion requires POST');
    $nativeDeleted = request('api/v1/delete_design.php', 'POST', ['design_id' => $third, 'expected_revision' => 1], $tokens['access_token'], true, false);
    check($nativeDeleted['status'] === 200 && decoded($nativeDeleted)['deleted']['id'] === $third, 'HTTP native deletion succeeds');
    check(request('api/v1/get_design.php?design_id=' . $third, bearer: $tokens['access_token'], browser: false)['status'] === 404, 'HTTP native deletion removes the scenario');
    check(request('api/v1/delete_design.php', 'POST', ['design_id' => $third, 'expected_revision' => 1], $tokens['access_token'], true, false)['status'] === 200, 'HTTP native deletion is idempotent');

    // Collaboration bêta : partage, présence et départ par les vraies routes.
    $db->exec("INSERT INTO users (id,username,email,password_hash,role,status,email_verified_at)
        VALUES (2,'Colleague','colleague@example.test','test','designer','active',CURRENT_TIMESTAMP)");
    $colleague = exchange(native_issue_code($db, 2, native_validate_authorization(auth_query())))['access_token'];
    $createAgain['client_uuid'] = '5c0f3b8e-2a4d-4e6f-8b1c-7d9e0a2b4c6d';
    $sharedId = decoded(request('api/v1/save_design.php', 'POST', $createAgain, $tokens['access_token'], true, false))['design']['id'];
    check(request('api/v1/collaborators.php?design_id=' . $sharedId, 'POST', [], $tokens['access_token'], true, false)['status'] === 405,
        'HTTP member list requires GET');
    check(request('api/v1/share_design.php', bearer: $tokens['access_token'], browser: false)['status'] === 405, 'HTTP sharing requires POST');
    check(request('api/v1/share_design.php', 'POST', ['design_id' => $sharedId, 'email' => 'colleague@example.test'], $tokens['access_token'], browser: false)['status'] === 415,
        'HTTP sharing requires JSON');
    $unknown = request('api/v1/share_design.php', 'POST', ['design_id' => $sharedId, 'email' => 'nobody@example.test'], $tokens['access_token'], true, false);
    check($unknown['status'] === 422 && decoded($unknown)['error'] === 'unknown_user', 'HTTP sharing reports unknown addresses');
    $invited = request('api/v1/share_design.php', 'POST', ['design_id' => $sharedId, 'email' => 'Colleague@Example.test'], $tokens['access_token'], true, false);
    check($invited['status'] === 200 && array_column(decoded($invited)['sharing']['members'], 'role') === ['owner', 'editor'], 'HTTP sharing invites a colleague');
    $members = decoded(request('api/v1/collaborators.php?design_id=' . $sharedId, bearer: $colleague, browser: false))['sharing'];
    check($members['canManage'] === false && !isset($members['members'][1]['email']), 'HTTP members hide addresses from collaborators');
    $listed = decoded(request('api/v1/list_designs.php', bearer: $colleague, browser: false))['items'];
    check($listed[0]['id'] === $sharedId && $listed[0]['role'] === 'editor' && $listed[0]['owner'] === 'Teacher', 'HTTP list includes shared scenarios');
    $sharedInput = ['client_uuid' => '5c0f3b8e-2a4d-4e6f-8b1c-7d9e0a2b4c6d', 'design_id' => $sharedId, 'expected_revision' => 1, 'document' => $document];
    check(decoded(request('api/v1/save_design.php', 'POST', $sharedInput, $colleague, true, false))['design']['revision'] === 2, 'HTTP collaborators save the shared scenario');
    $signal = ['design_id' => $sharedId, 'client_id' => '1f0e4f6a-3c2b-4d5e-8f70-112233445566', 'focus' => 'activity:a-1:instructions'];
    check(request('api/v1/presence.php', 'POST', $signal, $colleague, true, false)['status'] === 200, 'HTTP presence accepts a signal');
    $signal['client_id'] = '2a1b3c4d-5e6f-4a7b-9c8d-0e1f2a3b4c5d'; $signal['focus'] = null;
    $seen = decoded(request('api/v1/presence.php', 'POST', $signal, $tokens['access_token'], true, false))['presence'];
    check($seen['revision'] === 2 && $seen['shared'] === true && $seen['participants'][0]['username'] === 'Colleague'
        && $seen['participants'][0]['focus'] === 'activity:a-1:instructions', 'HTTP presence returns the revision and participants');
    check(request('api/v1/delete_design.php', 'POST', ['design_id' => $sharedId, 'expected_revision' => 2], $colleague, true, false)['status'] === 403,
        'HTTP collaborators cannot delete the scenario');
    check(request('api/v1/collaborator_role.php', bearer: $tokens['access_token'], browser: false)['status'] === 405, 'HTTP role changes require POST');
    $viewer = request('api/v1/collaborator_role.php', 'POST', ['design_id' => $sharedId, 'user_id' => 2, 'role' => 'viewer'], $tokens['access_token'], true, false);
    check($viewer['status'] === 200 && array_column(decoded($viewer)['sharing']['members'], 'role') === ['owner', 'viewer'], 'HTTP owner restricts a colleague to viewing');
    check(request('api/v1/collaborator_role.php', 'POST', ['design_id' => $sharedId, 'user_id' => 2, 'role' => 'editor'], $colleague, true, false)['status'] === 403,
        'HTTP collaborators cannot change their rights');
    $sharedInput['expected_revision'] = 2;
    check(request('api/v1/save_design.php', 'POST', $sharedInput, $colleague, true, false)['status'] === 403, 'HTTP viewers cannot save');
    check(decoded(request('api/v1/get_design.php?design_id=' . $sharedId, bearer: $colleague, browser: false))['design']['role'] === 'viewer',
        'HTTP viewers still read the scenario');
    $left = request('api/v1/unshare_design.php', 'POST', ['design_id' => $sharedId, 'user_id' => 2], $colleague, true, false);
    check($left['status'] === 200 && decoded($left)['sharing'] === null, 'HTTP collaborators can leave the sharing');
    check(request('api/v1/get_design.php?design_id=' . $sharedId, bearer: $colleague, browser: false)['status'] === 404, 'HTTP access ends after leaving');

    // Collaboration depuis le site : mêmes règles avec la session web du propriétaire.
    $webShare = request('design_sharing.php', 'POST', ['action' => 'share', 'design_id' => $sharedId, 'email' => 'colleague@example.test', 'role' => 'editor'], json: true);
    check($webShare['status'] === 200 && array_column(decoded($webShare)['sharing']['members'], 'role') === ['owner', 'editor'], 'Web sharing invites a colleague');
    check(decoded(request('design_sharing.php', 'POST', ['action' => 'members', 'design_id' => $sharedId], json: true))['sharing']['canManage'] === true,
        'Web member list lets the owner manage');
    $webPresence = decoded(request('design_sharing.php', 'POST', ['action' => 'presence', 'design_id' => $sharedId,
        'client_id' => '3b2c1d4e-5f6a-4b7c-8d9e-0f1a2b3c4d5e', 'focus' => 'moment:m1:title'], json: true))['presence'];
    check($webPresence['shared'] === true && $webPresence['revision'] === 2, 'Web presence returns the shared revision');
    check(request('design_sharing.php', 'POST', ['action' => 'unknown', 'design_id' => $sharedId], json: true)['status'] === 422, 'Web sharing rejects unknown actions');
    check(request('design_sharing.php', 'POST', ['action' => 'members', 'design_id' => $sharedId], json: true, browser: false)['status'] === 401,
        'Web sharing requires a signed-in session');
    $webDesign = decoded(request('get_design.php?design_id=' . $sharedId))['design'];
    check($webDesign['role'] === 'owner' && $webDesign['shared'] === true, 'Web editor learns that the scenario is shared');
    $colleagueSave = request('api/v1/save_design.php', 'POST', $sharedInput, $colleague, true, false);
    check($colleagueSave['status'] === 200, 'Invited colleague saves again after a new invitation');
    $staleWeb = request('save_design.php', 'POST', ['design_id' => $sharedId, 'expected_revision' => 2, 'document' => $document], json: true);
    check($staleWeb['status'] === 409, 'Web save of a stale revision reports a conflict to merge');
    check(request('design_sharing.php', 'POST', ['action' => 'unshare', 'design_id' => $sharedId, 'user_id' => 2], json: true)['status'] === 200,
        'Web owner removes a colleague');
    check(request('api/v1/presence.php', 'POST', $signal, $colleague, true, false)['status'] === 404, 'HTTP presence requires membership');

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
