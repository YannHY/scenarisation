<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$error = '';
$verificationRequired = false;
try {
    $db = app_db();
    if (is_admin_seed_needed($db)) {
        header('Location: setup_admin.php');
        exit;
    }

    if (current_user()) {
        header('Location: designer.php');
        exit;
    }
} catch (Throwable $e) {
    $db = null;
    $error = 'Le stockage utilisateur n’a pas pu etre initialise. Verifiez la configuration ou les droits d’ecriture du dossier data/.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_same_origin_post();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($db === null) {
        $error = 'Le stockage utilisateur n’est pas disponible pour le moment.';
    } elseif ($email === '' || $password === '') {
        $error = 'Merci de renseigner l’email et le mot de passe.';
    } else {
        $stmt = $db->prepare("SELECT id, username, email, password_hash, role, status, email_verified_at FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active' || !password_verify($password, (string)$user['password_hash'])) {
            $error = 'Identifiants invalides.';
        } elseif ($user['email_verified_at'] === null || trim((string)$user['email_verified_at']) === '') {
            app_start_session();
            $_SESSION['pending_verification_email'] = (string)$user['email'];
            $verificationRequired = true;
            $error = 'Votre adresse email doit être vérifiée avant la première connexion.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => (string)$user['username'],
                'email' => (string)$user['email'],
                'role' => (string)$user['role'],
            ];
            $touch = $db->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?");
            $touch->execute([(int)$user['id']]);
            header('Location: designer.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title data-site-i18n-en="Sign in | Scenarisation" data-site-i18n-fr="Connexion | Scenarisation">Connexion | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260910-login-neutral-icons">
</head>
<body class="login-page">
<?php render_site_nav('login'); ?>
<main class="account-shell with-nav login-account-shell">
    <div class="login-layout">
        <section class="login-intro" aria-labelledby="login-intro-title">
            <h2 id="login-intro-title">Scenarisation</h2>
            <p class="login-intro-copy" data-site-i18n-en="Design, analyse and share your learning scenarios." data-site-i18n-fr="Concevez, analysez et partagez vos scénarios pédagogiques.">Concevez, analysez et partagez vos scénarios pédagogiques.</p>
            <ul class="login-benefits">
                <li><i class="fa-solid fa-diagram-project" aria-hidden="true"></i><span data-site-i18n-en="Structure a learning sequence" data-site-i18n-fr="Structurer une séquence">Structurer une séquence</span></li>
                <li><i class="fa-solid fa-chart-pie" aria-hidden="true"></i><span data-site-i18n-en="Analyse its balance" data-site-i18n-fr="Analyser son équilibre">Analyser son équilibre</span></li>
                <li><i class="fa-solid fa-share-nodes" aria-hidden="true"></i><span data-site-i18n-en="Share your work" data-site-i18n-fr="Partager ses productions">Partager ses productions</span></li>
            </ul>
            <div class="login-learning-rhythm" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span><span></span>
            </div>
        </section>

        <section class="account-card">
        <h1 data-site-i18n-en="Sign in" data-site-i18n-fr="Connexion">Connexion</h1>
        <p class="account-copy" data-site-i18n-en="Sign in to save and access your work." data-site-i18n-fr="Connectez-vous pour sauvegarder et retrouver vos productions.">Connectez-vous pour sauvegarder et retrouver vos productions.</p>
        <form method="post" class="account-form">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autocomplete="username">
            <label for="password" data-site-i18n-en="Password" data-site-i18n-fr="Mot de passe">Mot de passe</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
            <a class="account-form-link" href="forgot-password.php">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                <span data-site-i18n-en="Forgot password?" data-site-i18n-fr="Mot de passe oublié&nbsp;?">Mot de passe oublié&nbsp;?</span>
            </a>
            <button type="submit" data-site-i18n-en="Sign in" data-site-i18n-fr="Se connecter">Se connecter</button>
        </form>
        <?php if ($error !== ''): ?>
            <p class="account-message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($verificationRequired): ?>
            <p class="account-footer"><a href="verify-email.php" data-site-i18n-en="Resend the verification email" data-site-i18n-fr="Renvoyer l’email de vérification">Renvoyer l’email de vérification</a></p>
        <?php endif; ?>
        <div class="account-signup-action">
            <p data-site-i18n-en="New to Scenarisation?" data-site-i18n-fr="Pas encore de compte&nbsp;?">Pas encore de compte&nbsp;?</p>
            <a class="account-secondary-button" href="signup.php">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                <span data-site-i18n-en="Create an account" data-site-i18n-fr="Créer un compte">Créer un compte</span>
            </a>
        </div>
        </section>
    </div>
</main>
<?php render_site_footer(); ?>
</body>
</html>
