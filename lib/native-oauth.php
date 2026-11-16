<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const NATIVE_CLIENT_ID = 'scenarisation-mac';
const NATIVE_ACCESS_TTL = 900;
const NATIVE_SESSION_TTL = 2592000;
const NATIVE_SCOPES = ['designs:read', 'designs:write'];

final class NativeRequestError extends RuntimeException
{
    public function __construct(public string $oauthError, string $message, public int $status = 400)
    {
        parent::__construct($message);
    }
}

function native_random_token(string $prefix = ''): string
{
    return $prefix . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function native_string(array $input, string $key): string
{
    return is_string($input[$key] ?? null) ? $input[$key] : '';
}

function native_require_https(): void
{
    $localDevelopment = PHP_SAPI === 'cli-server'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (!app_is_https() && !$localDevelopment) {
        throw new NativeRequestError('invalid_request', 'Une connexion HTTPS est requise.', 400);
    }
}

function native_response_headers(): void
{
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    // Preserve same-origin form verification; never send this page's URL to a third-party origin.
    header('Referrer-Policy: same-origin');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

function native_scope(string $value): string
{
    $scopes = preg_split('/ +/', trim($value)) ?: [];
    if (!$scopes || array_diff($scopes, NATIVE_SCOPES)) {
        throw new NativeRequestError('invalid_scope', 'Permissions demandées invalides.');
    }
    return implode(' ', array_values(array_intersect(NATIVE_SCOPES, $scopes)));
}

/** One pre-registered public client, exact loopback path, variable TCP port, S256 only. */
function native_validate_authorization(array $input): array
{
    if (isset($input['scope']) && !is_string($input['scope'])) {
        throw new NativeRequestError('invalid_scope', 'Permissions demandées invalides.');
    }
    if (native_string($input, 'client_id') !== NATIVE_CLIENT_ID) {
        throw new NativeRequestError('unauthorized_client', 'Application inconnue.');
    }
    $redirect = native_string($input, 'redirect_uri');
    if (!preg_match('~^http://127\.0\.0\.1:([1-9][0-9]{0,4})/auth/callback$~D', $redirect, $match)
        || (int)$match[1] > 65535) {
        throw new NativeRequestError('invalid_request', 'Adresse de retour invalide.');
    }
    if (native_string($input, 'response_type') !== 'code'
        || native_string($input, 'code_challenge_method') !== 'S256'
        || !preg_match('/^[A-Za-z0-9_-]{43}$/D', native_string($input, 'code_challenge'))
        || !preg_match('/^[A-Za-z0-9._~-]{22,512}$/D', native_string($input, 'state'))) {
        throw new NativeRequestError('invalid_request', 'Demande de connexion invalide.');
    }
    return [
        'client_id' => NATIVE_CLIENT_ID, 'redirect_uri' => $redirect,
        'challenge' => $input['code_challenge'], 'state' => $input['state'],
        'scope' => native_scope(native_string($input, 'scope') ?: 'designs:read designs:write'),
    ];
}

function native_pending_authorization(string $id): ?array
{
    app_start_session();
    $entry = $_SESSION['native_authorizations'][$id] ?? null;
    if (!preg_match('/^[a-f0-9]{32}$/D', $id) || !is_array($entry)
        || (int)($entry['expires_at'] ?? 0) <= time()) return null;
    return $entry;
}

function native_remember_authorization(array $request): string
{
    app_start_session();
    $entries = $_SESSION['native_authorizations'] ?? [];
    $entries = array_filter(is_array($entries) ? $entries : [], static fn($entry) =>
        is_array($entry) && (int)($entry['expires_at'] ?? 0) > time());
    while (count($entries) >= 8) array_shift($entries);
    $id = bin2hex(random_bytes(16));
    $entries[$id] = $request + ['expires_at' => time() + 600];
    $_SESSION['native_authorizations'] = $entries;
    return $id;
}

/** Fixed relative destination; never redirect to a user-provided return URL. */
function native_login_return(string $requestId): ?string
{
    return native_pending_authorization($requestId) === null ? null
        : 'oauth-authorize.php?request=' . $requestId;
}

function native_active_user(PDO $db, int $id): ?array
{
    // MySQL rejects comparing a DATETIME with ''. SQLite can contain legacy empty strings.
    $query = $db->prepare("SELECT id, username, email, email_verified_at FROM users
        WHERE id = ? AND status = 'active' AND email_verified_at IS NOT NULL");
    $query->execute([$id]);
    $user = $query->fetch();
    if (!$user || trim((string)$user['email_verified_at']) === '') return null;
    return ['id' => (int)$user['id'], 'username' => (string)$user['username'], 'email' => (string)$user['email']];
}

function native_issue_code(PDO $db, int $userId, array $request): string
{
    if (!native_active_user($db, $userId)) {
        throw new NativeRequestError('access_denied', 'Le compte doit être actif et son email vérifié.', 403);
    }
    $code = native_random_token();
    $db->prepare('INSERT INTO native_oauth_codes
        (code_hash, user_id, client_id, redirect_uri, challenge, scope, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([hash('sha256', $code), $userId, NATIVE_CLIENT_ID, $request['redirect_uri'],
            $request['challenge'], $request['scope'], time() + 300]);
    return $code;
}

function native_pkce(string $verifier): string
{
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
}

function native_revoke_session(PDO $db, string $sessionId, ?int $userId = null): void
{
    $sql = 'UPDATE native_app_sessions SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL';
    $parameters = [time(), $sessionId];
    if ($userId !== null) { $sql .= ' AND user_id = ?'; $parameters[] = $userId; }
    $db->prepare($sql)->execute($parameters);
}

function native_revoke_user(PDO $db, int $userId): void
{
    $db->prepare('UPDATE native_app_sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL')
        ->execute([time(), $userId]);
    $db->prepare('UPDATE native_oauth_codes SET expires_at = 0 WHERE user_id = ? AND consumed_at IS NULL')
        ->execute([$userId]);
}

function native_token_result(string $access, string $refresh, string $scope, int $expiresAt): array
{
    return ['access_token' => $access, 'token_type' => 'Bearer', 'expires_in' => NATIVE_ACCESS_TTL,
        'refresh_token' => $refresh, 'refresh_expires_in' => max(0, $expiresAt - time()), 'scope' => $scope];
}

function native_exchange_code(PDO $db, array $input): array
{
    $code = native_string($input, 'code');
    $verifier = native_string($input, 'code_verifier');
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $code)
        || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $verifier)) {
        throw new NativeRequestError('invalid_grant', 'Code de connexion invalide.');
    }
    $query = $db->prepare('SELECT * FROM native_oauth_codes WHERE code_hash = ?');
    $query->execute([hash('sha256', $code)]);
    $row = $query->fetch();
    $query->closeCursor();
    if (!$row || $row['client_id'] !== NATIVE_CLIENT_ID
        || !hash_equals((string)$row['redirect_uri'], native_string($input, 'redirect_uri'))
        || !hash_equals((string)$row['challenge'], native_pkce($verifier))
        || (int)$row['expires_at'] <= time() || !native_active_user($db, (int)$row['user_id'])) {
        throw new NativeRequestError('invalid_grant', 'Code de connexion invalide ou expiré.');
    }
    $db->beginTransaction();
    try {
        $consume = $db->prepare('UPDATE native_oauth_codes SET consumed_at = ?
            WHERE code_hash = ? AND consumed_at IS NULL AND expires_at > ?');
        $consume->execute([time(), $row['code_hash'], time()]);
        if ($consume->rowCount() !== 1) {
            $db->rollBack();
            $query->execute([$row['code_hash']]); $used = $query->fetch(); $query->closeCursor();
            if (!empty($used['session_id'])) native_revoke_session($db, (string)$used['session_id']);
            throw new NativeRequestError('invalid_grant', 'Ce code de connexion a déjà été utilisé.');
        }
        $sessionId = bin2hex(random_bytes(16));
        $access = native_random_token('sca_'); $refresh = native_random_token('scr_');
        $now = time(); $expires = $now + NATIVE_SESSION_TTL;
        $db->prepare('INSERT INTO native_app_sessions
            (id, user_id, client_id, name, scope, access_token_hash, access_expires_at, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$sessionId, $row['user_id'], NATIVE_CLIENT_ID, 'Scénarisation pour Mac',
                $row['scope'], hash('sha256', $access), $now + NATIVE_ACCESS_TTL, $expires, $now]);
        $db->prepare('INSERT INTO native_refresh_tokens (token_hash, session_id, expires_at) VALUES (?, ?, ?)')
            ->execute([hash('sha256', $refresh), $sessionId, $expires]);
        $db->prepare('UPDATE native_oauth_codes SET session_id = ? WHERE code_hash = ?')->execute([$sessionId, $row['code_hash']]);
        $db->commit();
        return native_token_result($access, $refresh, (string)$row['scope'], $expires);
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

function native_refresh(PDO $db, array $input): array
{
    $token = native_string($input, 'refresh_token');
    if (!preg_match('/^scr_[A-Za-z0-9_-]{43}$/D', $token)) {
        throw new NativeRequestError('invalid_grant', 'Session invalide.');
    }
    $query = $db->prepare('SELECT t.token_hash, t.consumed_at, t.expires_at AS token_expires_at, s.*
        FROM native_refresh_tokens t JOIN native_app_sessions s ON s.id = t.session_id WHERE t.token_hash = ?');
    $query->execute([hash('sha256', $token)]); $row = $query->fetch(); $query->closeCursor();
    if (!$row || $row['client_id'] !== NATIVE_CLIENT_ID || $row['revoked_at'] !== null
        || (int)$row['expires_at'] <= time() || (int)$row['token_expires_at'] <= time()
        || !native_active_user($db, (int)$row['user_id'])) {
        throw new NativeRequestError('invalid_grant', 'Session invalide ou expirée.');
    }
    $scope = native_string($input, 'scope') === '' ? (string)$row['scope'] : native_scope($input['scope']);
    if (array_diff(explode(' ', $scope), explode(' ', (string)$row['scope']))) {
        throw new NativeRequestError('invalid_scope', 'Les permissions ne peuvent pas être élargies.');
    }
    $db->beginTransaction();
    try {
        $consume = $db->prepare('UPDATE native_refresh_tokens SET consumed_at = ?
            WHERE token_hash = ? AND consumed_at IS NULL AND expires_at > ?');
        $consume->execute([time(), $row['token_hash'], time()]);
        if ($consume->rowCount() !== 1) {
            $db->rollBack();
            // Refresh-token rotation detects reuse and invalidates the whole authorization family.
            native_revoke_session($db, (string)$row['id']);
            throw new NativeRequestError('invalid_grant', 'Cette session doit être reconnectée.');
        }
        $access = native_random_token('sca_'); $refresh = native_random_token('scr_');
        $update = $db->prepare('UPDATE native_app_sessions SET access_token_hash = ?, access_expires_at = ?, scope = ?
            WHERE id = ? AND revoked_at IS NULL AND expires_at > ?');
        $update->execute([hash('sha256', $access), time() + NATIVE_ACCESS_TTL, $scope, $row['id'], time()]);
        if ($update->rowCount() !== 1) throw new NativeRequestError('invalid_grant', 'Session révoquée.');
        $db->prepare('INSERT INTO native_refresh_tokens (token_hash, session_id, expires_at) VALUES (?, ?, ?)')
            ->execute([hash('sha256', $refresh), $row['id'], $row['expires_at']]);
        $db->commit();
        return native_token_result($access, $refresh, $scope, (int)$row['expires_at']);
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

function native_token_grant(PDO $db, array $input): array
{
    if (native_string($input, 'client_id') !== NATIVE_CLIENT_ID || isset($input['client_secret'])) {
        throw new NativeRequestError('invalid_client', 'Application inconnue.', 401);
    }
    return match (native_string($input, 'grant_type')) {
        'authorization_code' => native_exchange_code($db, $input),
        'refresh_token' => native_refresh($db, $input),
        default => throw new NativeRequestError('unsupported_grant_type', 'Type de connexion non pris en charge.'),
    };
}

function native_access_user(PDO $db, string $token, string $scope): array
{
    if (!preg_match('/^sca_[A-Za-z0-9_-]{43}$/D', $token)) {
        throw new NativeRequestError('invalid_token', 'Authentification requise.', 401);
    }
    $query = $db->prepare('SELECT id, user_id, scope FROM native_app_sessions
        WHERE access_token_hash = ? AND revoked_at IS NULL AND access_expires_at > ? AND expires_at > ?');
    $query->execute([hash('sha256', $token), time(), time()]); $session = $query->fetch(); $query->closeCursor();
    $user = $session ? native_active_user($db, (int)$session['user_id']) : null;
    if (!$user) throw new NativeRequestError('invalid_token', 'Session invalide ou expirée.', 401);
    if (!in_array($scope, explode(' ', (string)$session['scope']), true)) {
        throw new NativeRequestError('insufficient_scope', 'Cette action n’est pas autorisée.', 403);
    }
    $db->prepare('UPDATE native_app_sessions SET last_used_at = ? WHERE id = ?')->execute([time(), $session['id']]);
    return $user;
}

function native_bearer(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) { $header = $value; break; }
        }
    }
    if (is_string($header) && preg_match('/^Bearer (sca_[A-Za-z0-9_-]{43})$/iD', $header, $match)) return $match[1];
    throw new NativeRequestError('invalid_token', 'Authentification requise.', 401);
}

/** Revocation is idempotent and never reveals whether a token existed. */
function native_revoke_token(PDO $db, string $token): void
{
    if (strlen($token) > 256) return;
    $hash = hash('sha256', $token);
    $query = $db->prepare('SELECT id FROM native_app_sessions WHERE access_token_hash = ?
        UNION SELECT session_id AS id FROM native_refresh_tokens WHERE token_hash = ?');
    $query->execute([$hash, $hash]);
    foreach ($query->fetchAll() as $row) native_revoke_session($db, (string)$row['id']);
}

/** Opaque secrets are hashed at rest. Only this JSON response returns their plain values. */
function native_endpoint(callable $handler, string $method = 'POST'): void
{
    native_response_headers();
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            header('Allow: ' . $method);
            throw new NativeRequestError('invalid_request', 'Méthode non autorisée.', 405);
        }
        native_require_https();
        $result = $handler();
        app_json_response($result);
    } catch (NativeRequestError $error) {
        if ($error->status === 401) header('WWW-Authenticate: Bearer realm="Scenarisation"');
        app_json_response(['success' => false, 'error' => $error->oauthError, 'error_description' => $error->getMessage()], $error->status);
    } catch (Throwable $error) {
        error_log('Scénarisation native endpoint failed: ' . get_class($error));
        app_json_response(['success' => false, 'error' => 'server_error', 'error_description' => 'Le service est temporairement indisponible.'], 500);
    }
}

function native_form_input(): array
{
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384
        || strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0])) !== 'application/x-www-form-urlencoded') {
        throw new NativeRequestError('invalid_request', 'Le formulaire est invalide.');
    }
    return $_POST;
}
