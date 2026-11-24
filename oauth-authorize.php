<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/native-oauth.php';
require_once __DIR__ . '/lib/account-protection.php';

native_response_headers();
$error = '';
$requestId = '';
$request = null;
$user = null;
$completion = null;

/** The return is bound to this browser session and never supplied by a query parameter. */
function native_consent_completion(string $id): ?array
{
    app_start_session();
    if (!preg_match('/^[a-f0-9]{32}$/D', $id)) return null;
    $entry = $_SESSION['native_completed_authorizations'][$id] ?? null;
    return is_array($entry) && (int)($entry['expires_at'] ?? 0) > time() ? $entry : null;
}

try {
    native_require_https();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        throw new NativeRequestError('invalid_request', 'Méthode non autorisée.', 405);
    }
    $completionId = native_string($_GET, 'complete');
    if ($method === 'GET' && $completionId !== '') {
        $completion = native_consent_completion($completionId);
        $user = current_user();
        if ($completion === null || $user === null || (int)$completion['user_id'] !== $user['id']) {
            $completion = null;
            throw new NativeRequestError('invalid_request', 'Ce retour de connexion n’est plus disponible. Relancez la connexion depuis l’application.');
        }
        // The page contains a short-lived code in a loopback link. Never send a referrer.
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
    } else {
        $queryRequestId = native_string($_GET, 'request');
        $postedRequestId = native_string($_POST, 'request');
        if ($method === 'POST' && $queryRequestId !== '' && $postedRequestId !== '' && $queryRequestId !== $postedRequestId) {
            throw new NativeRequestError('invalid_request', 'Le formulaire ne correspond pas à cette demande. Relancez la connexion depuis l’application.');
        }
        $requestId = $method === 'POST' ? ($postedRequestId ?: $queryRequestId) : $queryRequestId;
        if ($requestId === '' && $method === 'GET') {
            $requestId = native_remember_authorization(native_validate_authorization($_GET));
        }
        $request = native_pending_authorization($requestId);
        if ($request === null) {
            $completedEntry = native_consent_completion($requestId);
            if ($method === 'POST' && $completedEntry !== null) {
                require_same_origin_post();
                $user = current_user();
                if ($user === null || (int)$completedEntry['user_id'] !== $user['id']
                    || !account_csrf_valid('native_authorize:' . $requestId)
                    || native_string($_POST, 'decision') !== $completedEntry['decision']) {
                    throw new NativeRequestError('access_denied', 'La confirmation est invalide. Recommencez depuis l’application.', 403);
                }
                // Repeated submissions reuse the same return; they never issue another authorization code.
                header('Location: oauth-authorize.php?complete=' . $requestId, true, 303);
                exit;
            }
            $reason = 'expired';
            $message = 'Cette demande a expiré. Recommencez depuis l’application.';
            if (!preg_match('/^[a-f0-9]{32}$/D', $requestId)) {
                $reason = 'missing_request';
                $message = 'Le formulaire de connexion est incomplet. Relancez la connexion depuis l’application.';
            } elseif ($completedEntry !== null || (is_int($_SESSION['native_completed_authorizations'][$requestId] ?? null)
                && $_SESSION['native_completed_authorizations'][$requestId] > time())) {
                $reason = 'already_processed';
                $message = 'Cette autorisation a déjà été traitée. Revenez dans l’application pour vérifier la connexion.';
            } elseif (!isset($_SESSION['native_authorizations'][$requestId])) {
                $reason = 'session_missing';
                $message = 'La demande n’a pas été retrouvée dans cette session du navigateur. Relancez la connexion depuis l’application.';
            }
            // No session IDs, CSRF tokens, OAuth parameters or account details in diagnostic logs.
            error_log('Scénarisation native consent rejected: reason=' . $reason . ' method=' . $method
                . ' session_cookie=' . (isset($_COOKIE[session_name()]) ? 'present' : 'absent')
                . ' posted_request=' . ($postedRequestId !== '' ? 'present' : 'absent'));
            throw new NativeRequestError('invalid_request', $message);
        }
        $user = current_user();
        if ($user === null) {
            header('Location: login.php?native_request=' . urlencode($requestId), true, 303);
            exit;
        }
        if ($method === 'POST') {
            require_same_origin_post();
            if (!account_csrf_valid('native_authorize:' . $requestId)
                || (int)($request['user_id'] ?? 0) !== $user['id']) {
                throw new NativeRequestError('access_denied', 'La confirmation est invalide. Recommencez depuis l’application.', 403);
            }
            $decision = native_string($_POST, 'decision');
            if (!in_array($decision, ['allow', 'deny'], true)) {
                throw new NativeRequestError('invalid_request', 'Choisissez une action.');
            }
            $parameters = ['state' => $request['state'], 'iss' => app_base_url()];
            if ($decision === 'allow') $parameters['code'] = native_issue_code(app_db(), $user['id'], $request);
            else $parameters['error'] = 'access_denied';
            unset($_SESSION['native_authorizations'][$requestId]);
            $completed = array_filter($_SESSION['native_completed_authorizations'] ?? [],
                static fn($entry) => (int)(is_array($entry) ? ($entry['expires_at'] ?? 0) : $entry) > time());
            while (count($completed) >= 8) array_shift($completed);
            $completed[$requestId] = [
                'expires_at' => time() + 300, 'user_id' => $user['id'], 'decision' => $decision,
                'url' => $request['redirect_uri'] . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986),
            ];
            $_SESSION['native_completed_authorizations'] = $completed;
            // Finish the form navigation on the same origin. A normal link then opens the Mac return,
            // outside the form-action redirect chain, with a visible fallback if automatic navigation fails.
            header('Location: oauth-authorize.php?complete=' . $requestId, true, 303);
            exit;
        }
        $_SESSION['native_authorizations'][$requestId]['user_id'] = $user['id'];
    }
} catch (NativeRequestError $exception) {
    http_response_code($exception->status); $error = $exception->getMessage();
} catch (Throwable $exception) {
    // Keep credentials and request contents out of logs, but retain a database error code for diagnosis.
    error_log('Scénarisation native consent failed: ' . get_class($exception)
        . ($exception instanceof PDOException ? ' SQLSTATE=' . (string)$exception->getCode() : ''));
    http_response_code(500); $error = 'La connexion est temporairement indisponible. Réessayez depuis l’application.';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title data-site-i18n-fr="Connecter le Mac | Scénarisation" data-site-i18n-en="Connect your Mac | Scénarisation">Connecter le Mac | Scénarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/interface.css?v=20261124-rem">
    <link rel="stylesheet" href="css/account-ui.css?v=20261124-rem">
    <link rel="stylesheet" href="css/account-pages.css?v=20261124-rem">
    <link rel="stylesheet" href="css/native-app.css?v=20261124-rem">
    <script src="js/native-consent.js?v=20261116-consent-return" defer></script>
<?php render_refined_styles(); ?>
</head>
<body class="native-consent-page">
<?php render_site_nav(); ?>
<main class="account-shell with-nav native-consent-shell">
    <section class="account-card native-consent" aria-labelledby="native-title">
        <header class="native-consent-header">
            <div class="native-app-brand">
                <span class="native-app-icon" aria-hidden="true"><i class="fa-solid fa-laptop"></i></span>
                <span data-site-i18n-fr="Scénarisation pour Mac" data-site-i18n-en="Scénarisation for Mac">Scénarisation pour Mac</span>
            </div>
            <?php if ($completion !== null && $error === ''): ?>
                <h1 id="native-title" data-site-i18n-fr="Revenir à l’application" data-site-i18n-en="Return to the app">Revenir à l’application</h1>
            <?php else: ?>
                <h1 id="native-title" data-site-i18n-fr="Connecter votre Mac" data-site-i18n-en="Connect your Mac">Connecter votre Mac</h1>
            <?php endif; ?>
        </header>
        <?php if ($error !== ''): ?>
            <p class="account-message error" role="alert"><?= h($error) ?></p>
            <a class="account-secondary-button" href="profile.php" data-site-i18n-fr="Mon compte" data-site-i18n-en="My account">Mon compte</a>
        <?php elseif ($completion !== null): ?>
            <?php if ($completion['decision'] === 'allow'): ?>
                <p class="native-consent-intro" data-site-i18n-fr="L’autorisation est enregistrée. Scénarisation va s’ouvrir pour terminer la connexion." data-site-i18n-en="Your authorization is saved. Scénarisation will open to finish signing in.">L’autorisation est enregistrée. Scénarisation va s’ouvrir pour terminer la connexion.</p>
            <?php else: ?>
                <p class="native-consent-intro" data-site-i18n-fr="La connexion a été annulée. Revenez à Scénarisation pour fermer la demande." data-site-i18n-en="Sign-in was cancelled. Return to Scénarisation to close the request.">La connexion a été annulée. Revenez à Scénarisation pour fermer la demande.</p>
            <?php endif; ?>
            <a id="native-return" class="native-consent-return" href="<?= h($completion['url']) ?>" rel="noreferrer" data-site-i18n-fr="Revenir à l’app" data-site-i18n-en="Return to the app">Revenir à l’app</a>
            <p class="account-copy native-consent-note" data-site-i18n-fr="Si le retour ne s’ouvre pas automatiquement, utilisez ce bouton. Gardez l’application Mac ouverte." data-site-i18n-en="If the return does not open automatically, use this button. Keep the Mac app open.">Si le retour ne s’ouvre pas automatiquement, utilisez ce bouton. Gardez l’application Mac ouverte.</p>
        <?php elseif ($user !== null && $request !== null): ?>
            <p class="native-consent-intro" data-site-i18n-fr="Retrouvez vos scénarios dans l’app et synchronisez vos modifications avec ce compte." data-site-i18n-en="Open your scenarios in the app and sync your changes with this account.">Retrouvez vos scénarios dans l’app et synchronisez vos modifications avec ce compte.</p>
            <div class="native-account">
                <i class="fa-regular fa-circle-user" aria-hidden="true"></i>
                <p><?= h($user['username']) ?> <span><?= h($user['email']) ?></span></p>
            </div>
            <ul class="native-permissions">
                <?php if (str_contains($request['scope'], 'designs:read')): ?>
                    <li><i class="fa-solid fa-folder-open" aria-hidden="true"></i><span data-site-i18n-fr="Lire et ouvrir vos scénarios sur le Mac" data-site-i18n-en="Read and open your scenarios on the Mac">Lire et ouvrir vos scénarios sur le Mac</span></li>
                <?php endif; ?>
                <?php if (str_contains($request['scope'], 'designs:write')): ?>
                    <li><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span data-site-i18n-fr="Créer et enregistrer vos modifications" data-site-i18n-en="Create scenarios and save your changes">Créer et enregistrer vos modifications</span></li>
                <?php endif; ?>
            </ul>
            <p class="account-copy native-consent-note" data-site-i18n-fr="La visibilité de vos scénarios est conservée. Vous pourrez retirer cet accès depuis votre profil." data-site-i18n-en="Your scenarios keep their visibility. You can revoke this access from your profile.">La visibilité de vos scénarios est conservée. Vous pourrez retirer cet accès depuis votre profil.</p>
            <form method="post" class="native-consent-actions" action="oauth-authorize.php?request=<?= h($requestId) ?>">
                <input type="hidden" name="request" value="<?= h($requestId) ?>">
                <input type="hidden" name="csrf_token" value="<?= h(account_csrf_token('native_authorize:' . $requestId)) ?>">
                <button type="submit" name="decision" value="deny" class="account-secondary-button" data-site-i18n-fr="Annuler" data-site-i18n-en="Cancel">Annuler</button>
                <button type="submit" name="decision" value="allow" data-site-i18n-fr="Autoriser" data-site-i18n-en="Allow">Autoriser</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<?php render_site_footer(); ?>
</body>
</html>
