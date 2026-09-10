<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function account_post_string(string $name): string
{
    return is_string($_POST[$name] ?? null) ? $_POST[$name] : '';
}

function account_csrf_token(string $purpose): string
{
    app_start_session();
    $entry = $_SESSION['account_csrf'][$purpose] ?? null;
    if (!is_array($entry) || (int)($entry['expires'] ?? 0) <= time()) {
        $entry = ['token' => bin2hex(random_bytes(32)), 'expires' => time() + 3600];
        $_SESSION['account_csrf'][$purpose] = $entry;
    }
    return $entry['token'];
}

function account_csrf_valid(string $purpose): bool
{
    app_start_session();
    $entry = $_SESSION['account_csrf'][$purpose] ?? null;
    return is_array($entry) && (int)($entry['expires'] ?? 0) > time()
        && is_string($entry['token'] ?? null)
        && hash_equals($entry['token'], account_post_string('csrf_token'));
}

function account_turnstile_config(): array
{
    $hosts = strtolower(trim((string)(app_env('APP_TURNSTILE_HOSTNAMES') ?? '')));
    return [
        'sitekey' => trim((string)(app_env('APP_TURNSTILE_SITE_KEY') ?? '')),
        'secret' => trim((string)(app_env('APP_TURNSTILE_SECRET_KEY') ?? '')),
        'hosts' => array_values(array_filter(array_map('trim', explode(',', $hosts)))),
    ];
}

function account_turnstile_ready(): bool
{
    $config = account_turnstile_config();
    return $config['sitekey'] !== '' && $config['secret'] !== '' && $config['hosts'] !== [];
}

/** HTTPS validation never sends the password, email, or session token to Cloudflare. */
function account_turnstile_request(array $payload): ?array
{
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $body = http_build_query($payload);
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($response === false || $status !== 200) return null;
    } else {
        $context = stream_context_create([
            'http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body, 'timeout' => 10, 'follow_location' => 0],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function account_turnstile_valid(string $token, string $purpose, ?callable $transport = null): bool
{
    if (!account_turnstile_ready() || $token === '' || strlen($token) > 2048) return false;
    $config = account_turnstile_config();
    try {
        $result = ($transport ?? 'account_turnstile_request')([
            'secret' => $config['secret'], 'response' => $token,
        ]);
        return is_array($result) && ($result['success'] ?? null) === true
            && ($result['action'] ?? null) === $purpose
            && is_string($result['hostname'] ?? null)
            && in_array(strtolower($result['hostname']), $config['hosts'], true);
    } catch (Throwable) {
        return false;
    }
}

/** Atomic fixed-window counters, shared by registration and verification resends. */
function account_rate_allowed(PDO $db, string $email, ?int $now = null): bool
{
    $now ??= time();
    $secret = account_turnstile_config()['secret'];
    if ($secret === '') return false;
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $db->exec('CREATE TABLE IF NOT EXISTS account_rate_limits (
        bucket_key VARCHAR(64) NOT NULL PRIMARY KEY,
        attempts INTEGER NOT NULL,
        expires_at BIGINT NOT NULL
    )' . ($sqlite ? '' : ' ENGINE=InnoDB'));
    if ($sqlite) $db->exec('PRAGMA busy_timeout = 5000');
    // Expired counters are removed on the next protected request.
    $db->prepare('DELETE FROM account_rate_limits WHERE expires_at <= ?')->execute([$now]);
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'); // Do not trust client-supplied proxy headers.
    $buckets = [[$ip, 'ip', 900, 60], [strtolower(trim($email)), 'email', 3600, 5]];
    $db->beginTransaction();
    try {
        $allowed = true;
        foreach ($buckets as [$identity, $kind, $duration, $limit]) {
            $window = intdiv($now, $duration);
            $key = hash_hmac('sha256', "account-rate:$kind:$window:$identity", $secret);
            $sql = $sqlite
                ? 'INSERT INTO account_rate_limits VALUES (?, 1, ?) ON CONFLICT(bucket_key) DO UPDATE SET attempts = attempts + 1'
                : 'INSERT INTO account_rate_limits VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = attempts + 1';
            $db->prepare($sql)->execute([$key, ($window + 1) * $duration]);
            $stmt = $db->prepare('SELECT attempts FROM account_rate_limits WHERE bucket_key = ?');
            $stmt->execute([$key]);
            if ((int)$stmt->fetchColumn() > $limit) {
                $allowed = false;
                break; // Avoid creating unbounded email buckets for a blocked IP.
            }
        }
        $db->commit();
        return $allowed;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

function account_protection_error(PDO $db, string $purpose, string $email): string
{
    if (!account_csrf_valid($purpose)) {
        return 'Le formulaire a expiré ou n’est pas valide. Rechargez la page puis réessayez.';
    }
    if (!account_turnstile_ready()) {
        return 'La vérification de sécurité est temporairement indisponible. Réessayez plus tard.';
    }
    try {
        if (!account_rate_allowed($db, $email)) {
            http_response_code(429);
            return 'Trop de tentatives. Patientez avant de réessayer (jusqu’à une heure pour une même adresse email).';
        }
    } catch (Throwable) {
        return 'La vérification de sécurité est temporairement indisponible. Réessayez plus tard.';
    }
    if (!account_turnstile_valid(account_post_string('cf-turnstile-response'), $purpose)) {
        return 'La vérification anti-robot a échoué ou a expiré. Effectuez une nouvelle vérification puis réessayez.';
    }
    return '';
}

function render_account_protection(string $purpose, bool $antiRobot = true): void
{
    echo '<input type="hidden" name="csrf_token" value="' . h(account_csrf_token($purpose)) . '">';
    if (!$antiRobot) return;
    if (!account_turnstile_ready()) {
        echo '<p class="account-message error">La vérification de sécurité est temporairement indisponible. Réessayez plus tard.</p>';
        return;
    }
    echo '<div class="cf-turnstile" data-sitekey="' . h(account_turnstile_config()['sitekey'])
        . '" data-action="' . h($purpose) . '" data-theme="auto" data-size="flexible" data-language="fr"></div>';
    echo '<noscript><p>Activez JavaScript pour effectuer la vérification anti-robot.</p></noscript>';
    echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}
