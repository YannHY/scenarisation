<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/native-oauth.php';
require_once __DIR__ . '/lib/account-protection.php';

native_response_headers();
$error = '';
$requestId = '';
$request = null;
$user = null;
try {
    native_require_https();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        throw new NativeRequestError('invalid_request', 'Méthode non autorisée.', 405);
    }
    $requestId = native_string($method === 'POST' ? $_POST : $_GET, 'request');
    if ($requestId === '' && $method === 'GET') {
        $requestId = native_remember_authorization(native_validate_authorization($_GET));
    }
    $request = native_pending_authorization($requestId);
    if ($request === null) throw new NativeRequestError('invalid_request', 'Cette demande a expiré. Recommencez depuis l’application.');
    // Chrome applies form-action to a POST's redirect as well as its initial destination.
    // This callback was validated before it entered the session; it is never an arbitrary URL.
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self' " . $request['redirect_uri']);
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
        header('Location: ' . $request['redirect_uri'] . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986), true, 303);
        exit;
    }
    $_SESSION['native_authorizations'][$requestId]['user_id'] = $user['id'];
} catch (NativeRequestError $exception) {
    http_response_code($exception->status); $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Scénarisation native consent failed: ' . get_class($exception));
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
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260926-login-colour-rhythm">
    <link rel="stylesheet" href="css/native-app.css?v=20261116-native-sync">
</head>
<body>
<?php render_site_nav(); ?>
<main class="account-shell with-nav native-consent-shell">
    <section class="account-card native-consent" aria-labelledby="native-title">
        <div class="native-app-icon" aria-hidden="true"><i class="fa-solid fa-laptop"></i></div>
        <h1 id="native-title" data-site-i18n-fr="Connecter Scénarisation pour Mac" data-site-i18n-en="Connect Scénarisation for Mac">Connecter Scénarisation pour Mac</h1>
        <?php if ($error !== ''): ?>
            <p class="account-message error" role="alert"><?= h($error) ?></p>
            <a class="account-secondary-button" href="profile.php" data-site-i18n-fr="Mon compte" data-site-i18n-en="My account">Mon compte</a>
        <?php elseif ($user !== null && $request !== null): ?>
            <p class="native-account"><?= h($user['username']) ?> <span><?= h($user['email']) ?></span></p>
            <p data-site-i18n-fr="Autorisez l’application à synchroniser vos scénarios avec ce compte." data-site-i18n-en="Allow the app to synchronize scenarios with this account.">Autorisez l’application à synchroniser vos scénarios avec ce compte.</p>
            <ul class="native-permissions">
                <?php if (str_contains($request['scope'], 'designs:read')): ?>
                    <li><i class="fa-solid fa-folder-open" aria-hidden="true"></i><span data-site-i18n-fr="Lire et ouvrir vos scénarios sur le Mac" data-site-i18n-en="Read and open your scenarios on the Mac">Lire et ouvrir vos scénarios sur le Mac</span></li>
                <?php endif; ?>
                <?php if (str_contains($request['scope'], 'designs:write')): ?>
                    <li><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span data-site-i18n-fr="Créer et enregistrer vos modifications" data-site-i18n-en="Create scenarios and save your changes">Créer et enregistrer vos modifications</span></li>
                <?php endif; ?>
            </ul>
            <p class="account-copy" data-site-i18n-fr="La synchronisation conserve la visibilité de vos scénarios. Vous pourrez retirer cet accès depuis votre profil." data-site-i18n-en="Synchronization preserves the visibility of your scenarios. You can revoke this access from your profile.">La synchronisation conserve la visibilité de vos scénarios. Vous pourrez retirer cet accès depuis votre profil.</p>
            <form method="post" class="native-consent-actions">
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
