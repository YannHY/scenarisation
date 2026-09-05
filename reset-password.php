<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$message = '';
$error = '';
$completed = false;
$tokenValid = false;
$account = null;
$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$token = trim((string)($requestMethod === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '')));

try {
    $db = app_db();
} catch (Throwable) {
    $db = null;
    $error = 'Le stockage utilisateur n’est pas disponible pour le moment.';
}

if ($db !== null) {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $error = 'Ce lien de réinitialisation est invalide.';
    } else {
        $tokenHash = hash('sha256', $token);
        $stmt = $db->prepare("SELECT id, password_reset_expires_at
            FROM users
            WHERE password_reset_token_hash = ? AND status = 'active'
            LIMIT 1");
        $stmt->execute([$tokenHash]);
        $account = $stmt->fetch();

        if (!$account) {
            $error = 'Ce lien est invalide ou a déjà été utilisé.';
        } elseif ((int)($account['password_reset_expires_at'] ?? 0) < time()) {
            $error = 'Ce lien a expiré. Demandez-en un nouveau.';
        } else {
            $tokenValid = true;
        }
    }
}

if ($db !== null && $requestMethod === 'POST') {
    require_same_origin_post();
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');

    if ($tokenValid && ($password === '' || $confirmation === '')) {
        $error = 'Renseignez et confirmez votre nouveau mot de passe.';
    } elseif ($tokenValid && strlen($password) < 8) {
        $error = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    } elseif ($tokenValid && $password !== $confirmation) {
        $error = 'La confirmation ne correspond pas au nouveau mot de passe.';
    } elseif ($tokenValid) {
        $update = $db->prepare("UPDATE users
            SET password_hash = ?,
                password_reset_token_hash = NULL,
                password_reset_expires_at = NULL,
                password_reset_sent_at = NULL
            WHERE id = ?
              AND password_reset_token_hash = ?
              AND password_reset_expires_at >= ?
              AND status = 'active'");
        $update->execute([
            password_hash($password, PASSWORD_DEFAULT),
            (int)$account['id'],
            $tokenHash,
            time(),
        ]);

        if ($update->rowCount() === 1) {
            app_start_session();
            unset($_SESSION['user']);
            session_regenerate_id(true);
            $completed = true;
            $tokenValid = false;
            $error = '';
            $message = 'Votre mot de passe a été modifié. Vous pouvez maintenant vous connecter.';
        } else {
            $tokenValid = false;
            $error = 'Ce lien est invalide, expiré ou a déjà été utilisé.';
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260804" type="image/svg+xml" sizes="any">
    <title>Nouveau mot de passe | Learning Designer</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260903-pagefind-dark">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="login-page">
<?php render_site_nav('reset_password'); ?>
<main class="account-shell with-nav">
    <section class="account-card">
        <p class="account-kicker">Learning Designer</p>
        <h1>Nouveau mot de passe</h1>

        <?php if ($message !== ''): ?>
            <p class="account-message success" role="status"><?= h($message) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="account-message error" role="alert"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if ($tokenValid): ?>
            <p class="account-copy">Choisissez un nouveau mot de passe d’au moins 8 caractères.</p>
            <form method="post" class="account-form">
                <input type="hidden" name="token" value="<?= h($token) ?>">
                <label for="password">Nouveau mot de passe</label>
                <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
                <label for="password_confirmation">Confirmation</label>
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
                <button type="submit">Modifier le mot de passe</button>
            </form>
        <?php elseif (!$completed): ?>
            <p class="account-footer"><a href="forgot-password.php">Demander un nouveau lien</a></p>
        <?php endif; ?>

        <p class="account-footer"><a href="login.php">Revenir à la connexion</a></p>
    </section>
</main>
<?php render_site_footer(); ?>
</body>
</html>
