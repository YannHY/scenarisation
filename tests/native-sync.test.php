<?php
declare(strict_types=1);

$fixture = sys_get_temp_dir() . '/scenarisation-native-' . bin2hex(random_bytes(8));
mkdir($fixture . '/sessions', 0700, true);
putenv('APP_DB_DSN=sqlite:' . $fixture . '/test.sqlite');
putenv('APP_BASE_URL=https://scenarisation.eu');
ini_set('session.save_path', $fixture . '/sessions');
require_once __DIR__ . '/../lib/native-api.php';
$_SERVER['HTTPS'] = 'on';
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function expect_error(callable $operation, string $code, int $status = 400): void {
    try { $operation(); } catch (NativeRequestError $error) {
        check($error->oauthError === $code && $error->status === $status, 'Unexpected native error: ' . $error->oauthError);
        return;
    }
    throw new RuntimeException('Expected error: ' . $code);
}
function authorization(string $scope = 'designs:read designs:write'): array {
    return native_validate_authorization([
        'client_id' => NATIVE_CLIENT_ID, 'response_type' => 'code',
        'redirect_uri' => 'http://127.0.0.1:54123/auth/callback',
        'code_challenge_method' => 'S256', 'code_challenge' => native_pkce(str_repeat('v', 64)),
        'state' => str_repeat('s', 32), 'scope' => $scope,
    ]);
}
function grant(PDO $db, int $userId = 1, string $scope = 'designs:read designs:write'): array {
    $request = authorization($scope);
    return native_token_grant($db, ['client_id' => NATIVE_CLIENT_ID, 'grant_type' => 'authorization_code',
        'code' => native_issue_code($db, $userId, $request), 'code_verifier' => str_repeat('v', 64),
        'redirect_uri' => $request['redirect_uri']]);
}
function refresh_input(string $token): array {
    return ['client_id' => NATIVE_CLIENT_ID, 'grant_type' => 'refresh_token', 'refresh_token' => $token];
}
function design_input(string $uuid = 'd5a85cd6-2636-4f00-860d-71dcb61ce479'): array {
    return [
        'client_uuid' => $uuid,
        'document' => json_decode('{"meta":{"name":"Scénario natif","futureEmpty":{}},"sessions":[{"id":"moment-1","activities":[{"id":"activity-1","duration":10}]}],"future":{"keep":true}}'),
    ];
}

try {
    $db = app_db();
    $db->exec("INSERT INTO users (id, username, email, password_hash, role, status, email_verified_at) VALUES
        (1,'Designer','designer@example.test','test','admin','active',CURRENT_TIMESTAMP),
        (2,'Other','other@example.test','test','designer','active',CURRENT_TIMESTAMP),
        (3,'Unverified','unverified@example.test','test','designer','active',NULL)");
    check((int)$db->query('SELECT schema_version FROM app_schema_meta')->fetchColumn() === 10, 'Migration reaches schema 10');
    ensure_native_app_schema($db); ensure_app_schema($db);
    check((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 3, 'Idempotent migration preserves users');
    $legacy = app_save_design_document($db, 1, 0, null, 'Existing', '{"meta":{},"sessions":[]}');
    $legacyId = $legacy['design']['id'];
    check(native_api_get($db, 1, $legacyId)['design']['revision'] === 1, 'Existing scenarios need no conversion');

    $invalidRedirects = [
        'https://attacker.test/auth/callback', 'http://localhost:54123/auth/callback',
        'http://127.0.0.1.attacker.test:54123/auth/callback', 'http://127.0.0.1:54123/wrong',
        'http://127.0.0.1:54123/auth/callback?x=y', 'http://127.0.0.1:54123/auth/callback#x',
        'http://user@127.0.0.1:54123/auth/callback', 'http://127.0.0.1:65536/auth/callback',
        'http://127.0.0.1/auth/callback', "http://127.0.0.1:54123/auth/callback\r\nLocation: https://attacker.test",
    ];
    $valid = ['client_id' => NATIVE_CLIENT_ID, 'response_type' => 'code', 'code_challenge_method' => 'S256',
        'code_challenge' => native_pkce(str_repeat('v', 64)), 'state' => str_repeat('s', 32)];
    foreach ($invalidRedirects as $redirect) {
        expect_error(fn() => native_validate_authorization($valid + ['redirect_uri' => $redirect]), 'invalid_request');
    }
    foreach (['plain', '', 's256'] as $method) {
        expect_error(fn() => native_validate_authorization(array_merge($valid,
            ['redirect_uri' => 'http://127.0.0.1:54123/auth/callback', 'code_challenge_method' => $method])), 'invalid_request');
    }
    expect_error(fn() => authorization('designs:read publish'), 'invalid_scope');
    expect_error(fn() => native_validate_authorization($valid + [
        'redirect_uri' => 'http://127.0.0.1:54123/auth/callback', 'scope' => ['designs:read'],
    ]), 'invalid_scope');
    expect_error(fn() => native_issue_code($db, 3, authorization()), 'access_denied', 403);
    unset($_SERVER['HTTPS']);
    expect_error(fn() => native_require_https(), 'invalid_request');
    $_SERVER['HTTPS'] = 'on';
    native_require_https();

    $pendingId = native_remember_authorization(authorization());
    check(native_login_return($pendingId) === 'oauth-authorize.php?request=' . $pendingId, 'Login return is session-bound');
    check(native_login_return('https://attacker.test') === null, 'Login return cannot become an open redirect');
    $_SESSION['native_authorizations'][$pendingId]['expires_at'] = time() - 1;
    check(native_login_return($pendingId) === null, 'Expired login returns are rejected');
    for ($i = 0; $i < 12; $i++) native_remember_authorization(authorization());
    check(count($_SESSION['native_authorizations']) === 8, 'Pending authorizations are bounded');

    $request = authorization();
    $code = native_issue_code($db, 1, $request);
    $input = ['client_id' => NATIVE_CLIENT_ID, 'grant_type' => 'authorization_code', 'code' => $code,
        'code_verifier' => str_repeat('v', 64), 'redirect_uri' => $request['redirect_uri']];
    expect_error(fn() => native_token_grant($db, array_merge($input, ['code_verifier' => str_repeat('w', 64)])), 'invalid_grant');
    expect_error(fn() => native_token_grant($db, array_merge($input, ['redirect_uri' => 'http://127.0.0.1:54124/auth/callback'])), 'invalid_grant');
    expect_error(fn() => native_token_grant($db, array_merge($input, ['client_id' => 'other'])), 'invalid_client', 401);
    $tokens = native_token_grant($db, $input);
    check($tokens['expires_in'] === 900 && $tokens['refresh_expires_in'] <= 2592000, 'Tokens have bounded lifetimes');
    check(native_access_user($db, $tokens['access_token'], 'designs:read')['id'] === 1, 'Access token identifies its owner');
    $stored = $db->query('SELECT access_token_hash FROM native_app_sessions')->fetchColumn();
    check($stored !== $tokens['access_token'] && $stored === hash('sha256', $tokens['access_token']), 'Only hashes are stored');
    expect_error(fn() => native_token_grant($db, $input), 'invalid_grant');
    expect_error(fn() => native_access_user($db, $tokens['access_token'], 'designs:read'), 'invalid_token', 401);

    $tokens = grant($db);
    $rotated = native_token_grant($db, refresh_input($tokens['refresh_token']));
    check($tokens['access_token'] !== $rotated['access_token'] && $tokens['refresh_token'] !== $rotated['refresh_token'], 'Refresh rotates both tokens');
    expect_error(fn() => native_access_user($db, $tokens['access_token'], 'designs:read'), 'invalid_token', 401);
    check(native_access_user($db, $rotated['access_token'], 'designs:write')['id'] === 1, 'Rotated access token works');
    expect_error(fn() => native_token_grant($db, refresh_input($tokens['refresh_token'])), 'invalid_grant');
    expect_error(fn() => native_access_user($db, $rotated['access_token'], 'designs:read'), 'invalid_token', 401);
    expect_error(fn() => native_token_grant($db, refresh_input($rotated['refresh_token'])), 'invalid_grant');
    $readOnly = grant($db, 1, 'designs:read');
    expect_error(fn() => native_access_user($db, $readOnly['access_token'], 'designs:write'), 'insufficient_scope', 403);
    expect_error(fn() => native_token_grant($db, refresh_input($readOnly['refresh_token']) + ['scope' => 'designs:read designs:write']), 'invalid_scope');
    native_revoke_token($db, $readOnly['refresh_token']);
    expect_error(fn() => native_access_user($db, $readOnly['access_token'], 'designs:read'), 'invalid_token', 401);
    native_revoke_token($db, 'unknown');
    check(!$db->inTransaction(), 'Failed grants leave no open transaction');
    $expiredCode = native_issue_code($db, 1, authorization());
    $db->prepare('UPDATE native_oauth_codes SET expires_at = ? WHERE code_hash = ?')
        ->execute([time() - 1, hash('sha256', $expiredCode)]);
    expect_error(fn() => native_token_grant($db, array_merge($input, ['code' => $expiredCode])), 'invalid_grant');
    $expiring = grant($db);
    $db->prepare('UPDATE native_refresh_tokens SET expires_at = ? WHERE token_hash = ?')
        ->execute([time() - 1, hash('sha256', $expiring['refresh_token'])]);
    expect_error(fn() => native_token_grant($db, refresh_input($expiring['refresh_token'])), 'invalid_grant');
    $full = grant($db);
    $narrowed = native_token_grant($db, refresh_input($full['refresh_token']) + ['scope' => 'designs:read']);
    check(native_access_user($db, $narrowed['access_token'], 'designs:read')['id'] === 1, 'Refresh may narrow permissions');
    expect_error(fn() => native_access_user($db, $narrowed['access_token'], 'designs:write'), 'insufficient_scope', 403);
    native_revoke_user($db, 1);

    $tokens = grant($db);
    $sessionId = $db->query("SELECT id FROM native_app_sessions WHERE revoked_at IS NULL")->fetchColumn();
    native_revoke_session($db, (string)$sessionId, 2);
    check(native_access_user($db, $tokens['access_token'], 'designs:read')['id'] === 1, 'Other users cannot revoke this session');
    $db->exec("UPDATE users SET status='disabled' WHERE id=1");
    expect_error(fn() => native_access_user($db, $tokens['access_token'], 'designs:read'), 'invalid_token', 401);
    $db->exec("UPDATE users SET status='active' WHERE id=1");
    $db->prepare('UPDATE native_app_sessions SET access_expires_at = ? WHERE id = ?')->execute([time() - 1, $sessionId]);
    expect_error(fn() => native_access_user($db, $tokens['access_token'], 'designs:read'), 'invalid_token', 401);
    $renewed = native_token_grant($db, refresh_input($tokens['refresh_token']));
    native_revoke_user($db, 1);
    expect_error(fn() => native_token_grant($db, refresh_input($renewed['refresh_token'])), 'invalid_grant');

    $created = native_api_save($db, 1, design_input());
    $id = $created['design']['id'];
    check($created['design']['revision'] === 1, 'Native creation starts at revision 1');
    $retried = native_api_save($db, 1, design_input(strtoupper('d5a85cd6-2636-4f00-860d-71dcb61ce479')));
    check($retried['design']['id'] === $id, 'UUID retries do not create duplicates');
    $other = native_api_save($db, 2, design_input());
    check($other['design']['id'] !== $id, 'Creation UUIDs are isolated by account');
    check(native_api_list($db, 2)['items'][0]['id'] === $other['design']['id'], 'List contains only the owner’s scenarios');
    expect_error(fn() => native_api_get($db, 2, $id), 'not_found', 404);
    $downloaded = native_api_get($db, 1, $id);
    check($downloaded['design']['document']->future->keep === true, 'Unknown fields survive API round trips');
    check($downloaded['design']['document']->meta->futureEmpty instanceof stdClass, 'Empty JSON objects stay objects');
    $db->exec("UPDATE learning_designs SET is_published=1,is_listed=1,share_token='stable-public-link',source_title='Original source' WHERE id=$id");
    $updatedInput = design_input() + ['design_id' => $id, 'expected_revision' => 1];
    $updatedInput['document']->meta->name = 'Version Mac';
    $updatedInput['document']->_scenarisationMac = (object)['id' => 'local-only'];
    $updated = native_api_save($db, 1, $updatedInput);
    check($updated['design']['revision'] === 2, 'Native updates increment the shared revision');
    $row = $db->query("SELECT * FROM learning_designs WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
    check($row['share_token'] === 'stable-public-link' && (int)$row['is_published'] === 1 && $row['source_title'] === 'Original source', 'Updating preserves publication and provenance');
    check(!str_contains($row['document_json'], '_scenarisationMac'), 'Mac-only metadata stays out of stored documents');
    expect_error(fn() => native_api_save($db, 1, $updatedInput), 'conflict', 409);
    expect_error(fn() => native_api_save($db, 1, design_input() + ['design_id' => $id]), 'conflict', 409);
    expect_error(fn() => native_api_save($db, 2, design_input() + ['design_id' => $id, 'expected_revision' => 2]), 'not_found', 404);
    check(app_save_design_document($db, 1, $id, 1, 'Old web tab', '{}')['status'] === 409, 'Web tabs detect native updates');
    check((int)$db->query("SELECT is_published FROM learning_designs WHERE id={$other['design']['id']}")->fetchColumn() === 0, 'New native designs are private');
    app_delete_design_document($db, 2, $id);
    check(count(native_api_list($db, 2)['deleted']) === 0, 'Wrong-owner deletions create no tombstone');
    app_delete_design_document($db, 1, $id);
    expect_error(fn() => native_api_save($db, 1, design_input()), 'not_found', 404);
    check(native_api_list($db, 1)['deleted'][0]['id'] === $id, 'Deletion is recorded for its owner');
    expect_error(fn() => native_api_get($db, 1, $id), 'not_found', 404);
    $invalid = design_input('3b241101-e2bb-4255-8caf-4136c566a962');
    $invalid['document']->sessions[0]->activities[0]->id = 'moment-1';
    expect_error(fn() => native_api_save($db, 1, $invalid), 'invalid_document', 422);
    expect_error(fn() => native_api_save($db, 1, ['client_uuid' => 'bad']), 'invalid_request', 422);
    $db->exec('DELETE FROM users WHERE id = 2');
    check((int)$db->query('SELECT COUNT(*) FROM native_creations WHERE owner_user_id = 2')->fetchColumn() === 0, 'Account deletion removes native associations');
    echo "$checks native OAuth and synchronization checks passed\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $db = null;
    foreach (glob($fixture . '/sessions/*') as $path) unlink($path);
    rmdir($fixture . '/sessions');
    foreach (glob($fixture . '/*') as $path) unlink($path);
    rmdir($fixture);
}
