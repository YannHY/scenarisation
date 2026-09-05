<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

app_start_session();
$message = '';
$error = '';
$verified = false;
$confirmationToken = '';
$pendingEmail = trim((string)($_SESSION['pending_verification_email'] ?? ''));

try {
    $db = app_db();
} catch (Throwable) {
    $db = null;
    $error = 'Le stockage utilisateur n’est pas disponible pour le moment.';
}

$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$token = trim((string)($_GET['token'] ?? ''));
$submittedToken = trim((string)($_POST['token'] ?? ''));

if ($db !== null && $requestMethod === 'POST' && $submittedToken !== '') {
    require_same_origin_post();
    if (!preg_match('/^[a-f0-9]{64}$/', $submittedToken)) {
        $error = 'Ce lien de vérification est invalide.';
    } else {
        $tokenHash = hash('sha256', $submittedToken);
        $stmt = $db->prepare("SELECT id, email, email_verified_at, email_verification_expires_at
            FROM users
            WHERE email_verification_token_hash = ?
            LIMIT 1");
        $stmt->execute([$tokenHash]);
        $account = $stmt->fetch();

        if (!$account) {
            $error = 'Ce lien est invalide ou a déjà été utilisé. Si votre adresse a déjà été confirmée, essayez de vous connecter.';
        } elseif (!empty($account['email_verified_at'])) {
            $verified = true;
            $message = 'Votre adresse email est déjà vérifiée. Vous pouvez vous connecter.';
        } elseif ((int)$account['email_verification_expires_at'] < time()) {
            $pendingEmail = (string)$account['email'];
            $_SESSION['pending_verification_email'] = $pendingEmail;
            $error = 'Ce lien a expiré. Vous pouvez en demander un nouveau ci-dessous.';
        } else {
            $update = $db->prepare("UPDATE users
                SET email_verified_at = CURRENT_TIMESTAMP,
                    email_verification_sent_at = NULL
                WHERE id = ? AND email_verified_at IS NULL AND email_verification_token_hash = ?");
            $update->execute([(int)$account['id'], $tokenHash]);
            if ($update->rowCount() === 1) {
                unset($_SESSION['pending_verification_email']);
                $verified = true;
                $message = 'Votre adresse email est vérifiée. Vous pouvez maintenant vous connecter.';
            } else {
                $error = 'Ce lien est invalide ou a déjà été utilisé. Si votre adresse a déjà été confirmée, essayez de vous connecter.';
            }
        }
    }
} elseif ($db !== null && $requestMethod === 'POST') {
    require_same_origin_post();
    $pendingEmail = trim((string)($_POST['email'] ?? $pendingEmail));

    if (!filter_var($pendingEmail, FILTER_VALIDATE_EMAIL) || !is_florimont_email($pendingEmail)) {
        $error = 'Saisissez une adresse email @florimont.ch valide.';
    } else {
        $stmt = $db->prepare("SELECT id, username, email, email_verified_at, email_verification_sent_at
            FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$pendingEmail]);
        $account = $stmt->fetch();

        if (!$account || !empty($account['email_verified_at'])) {
            $message = 'Si ce compte attend une vérification, un nouvel email va lui être envoyé.';
        } elseif ((int)($account['email_verification_sent_at'] ?? 0) > time() - EMAIL_VERIFICATION_RESEND_DELAY_SECONDS) {
            $error = 'Un email vient déjà d’être envoyé. Patientez une minute avant de réessayer.';
        } else {
            try {
                $newToken = create_email_verification_token($db, (int)$account['id']);
                if (send_email_verification_message((string)$account['email'], (string)$account['username'], $newToken)) {
                    mark_email_verification_sent($db, (int)$account['id']);
                    $_SESSION['pending_verification_email'] = (string)$account['email'];
                    $message = 'Un nouveau lien de vérification vient de vous être envoyé.';
                } else {
                    $error = 'L’email n’a pas pu être envoyé. Vérifiez la configuration d’envoi du serveur puis réessayez.';
                }
            } catch (Throwable) {
                $error = 'Le nouveau lien de vérification n’a pas pu être créé. Réessayez plus tard.';
            }
        }
    }
} elseif ($db !== null && $token !== '') {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $error = 'Ce lien de vérification est invalide.';
    } else {
        $tokenHash = hash('sha256', $token);
        $stmt = $db->prepare("SELECT email, email_verified_at, email_verification_expires_at
            FROM users
            WHERE email_verification_token_hash = ?
            LIMIT 1");
        $stmt->execute([$tokenHash]);
        $account = $stmt->fetch();

        if (!$account) {
            $error = 'Ce lien est invalide ou a déjà été utilisé. Si votre adresse a déjà été confirmée, essayez de vous connecter.';
        } elseif (!empty($account['email_verified_at'])) {
            $verified = true;
            $message = 'Votre adresse email est déjà vérifiée. Vous pouvez vous connecter.';
        } elseif ((int)$account['email_verification_expires_at'] < time()) {
            $pendingEmail = (string)$account['email'];
            $_SESSION['pending_verification_email'] = $pendingEmail;
            $error = 'Ce lien a expiré. Vous pouvez en demander un nouveau ci-dessous.';
        } else {
            // A GET request only presents the confirmation. Mail security
            // scanners frequently open links and must not consume the token.
            $confirmationToken = $token;
        }
    }
} elseif ($token === '' && (string)($_GET['sent'] ?? '') === '1') {
    $message = 'Un lien de vérification valable 24 heures vient de vous être envoyé.';
} elseif ($token === '' && (string)($_GET['sent'] ?? '') === '0') {
    $error = 'Le compte a été créé, mais l’email n’a pas pu être envoyé. Vous pouvez réessayer ci-dessous.';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260804" type="image/svg+xml" sizes="any">
    <title>Vérifier l’email | Learning Designer</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260903-pagefind-dark">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="signup-page">
<?php render_site_nav('verify_email'); ?>
<main class="account-shell with-nav">
    <section class="account-card">
        <p class="account-kicker">Learning Designer</p>
        <h1>Vérifier votre email</h1>

        <?php if ($message !== ''): ?>
            <p class="account-message success"><?= h($message) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="account-message error"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if ($verified): ?>
            <p class="account-footer"><a href="login.php">Se connecter</a></p>
        <?php elseif ($confirmationToken !== ''): ?>
            <p class="account-copy">Cliquez sur le bouton ci-dessous pour confirmer que cette adresse email vous appartient.</p>
            <form method="post" class="account-form">
                <input type="hidden" name="token" value="<?= h($confirmationToken) ?>">
                <button type="submit">Confirmer mon adresse email</button>
            </form>
            <p class="account-footer"><a href="login.php">Revenir à la connexion</a></p>
        <?php else: ?>
            <p class="account-copy">Consultez votre boîte de réception et, si nécessaire, vos courriers indésirables.</p>
            <form method="post" class="account-form">
                <label for="email">Renvoyer le lien à</label>
                <input id="email" name="email" type="email" required autocomplete="email" placeholder="@florimont.ch" value="<?= h($pendingEmail) ?>">
                <button type="submit">Renvoyer l’email</button>
            </form>
            <p class="account-footer"><a href="login.php">Revenir à la connexion</a></p>
        <?php endif; ?>
    </section>
</main>
<?php render_site_footer(); ?>
</body>
</html>
