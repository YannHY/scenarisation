<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$message = '';
$error = '';
$email = '';

try {
    $db = app_db();
} catch (Throwable) {
    $db = null;
    $error = 'Le stockage utilisateur n’est pas disponible pour le moment.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_same_origin_post();
    $email = trim((string)($_POST['email'] ?? ''));

    if ($db === null) {
        $error = 'Le stockage utilisateur n’est pas disponible pour le moment.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Saisissez une adresse email valide.';
    } else {
        $stmt = $db->prepare("SELECT id, username, email, status, password_reset_sent_at
            FROM users
            WHERE email = ?
            LIMIT 1");
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if ($account
            && (string)$account['status'] === 'active'
            && (int)($account['password_reset_sent_at'] ?? 0) <= time() - PASSWORD_RESET_RESEND_DELAY_SECONDS
        ) {
            try {
                $token = create_password_reset_token($db, (int)$account['id']);
                if (!send_password_reset_message((string)$account['email'], (string)$account['username'], $token)) {
                    error_log('Learning Designer : échec d’envoi d’un email de réinitialisation pour le compte ' . (int)$account['id'] . '.');
                }
            } catch (Throwable $exception) {
                error_log('Learning Designer : échec de préparation d’une réinitialisation de mot de passe : ' . $exception->getMessage());
            }
        }

        // La réponse reste identique, que l'adresse existe ou non, afin de ne
        // pas permettre l'énumération des comptes.
        $message = 'Si un compte actif correspond à cette adresse, un lien de réinitialisation vient de lui être envoyé. Pensez à vérifier les courriers indésirables.';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260804" type="image/svg+xml" sizes="any">
    <title>Mot de passe oublié | Learning Designer</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260903-pagefind-dark">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="login-page">
<?php render_site_nav('forgot_password'); ?>
<main class="account-shell with-nav">
    <section class="account-card">
        <p class="account-kicker">Learning Designer</p>
        <h1>Mot de passe oublié</h1>
        <p class="account-copy">Indiquez l’adresse email de votre compte. Vous recevrez un lien valable pendant une heure pour choisir un nouveau mot de passe.</p>

        <?php if ($message !== ''): ?>
            <p class="account-message success" role="status"><?= h($message) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="account-message error" role="alert"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if ($message === ''): ?>
            <form method="post" class="account-form">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required autocomplete="email" value="<?= h($email) ?>">
                <button type="submit">Envoyer le lien</button>
            </form>
            <p class="account-footer"><a href="login.php">Revenir à la connexion</a></p>
        <?php endif; ?>
    </section>
</main>
<?php render_site_footer(); ?>
</body>
</html>
