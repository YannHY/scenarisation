<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$error = '';
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
    $error = 'Le stockage utilisateur n’a pas pu être initialisé. Vérifiez la configuration ou les droits d’écriture du dossier data/.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_same_origin_post();
    $username = sanitize_username((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($db === null) {
        $error = 'Le stockage utilisateur n’est pas disponible pour le moment.';
    } elseif ($username === '' || $email === '' || $password === '') {
        $error = 'Nom d’utilisateur, email et mot de passe requis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (!is_florimont_email($email)) {
        $error = 'L’inscription est réservée aux adresses email @florimont.ch.';
    } elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } else {
        try {
            $token = bin2hex(random_bytes(32));
            $stmt = $db->prepare("INSERT INTO users (
                username,
                email,
                password_hash,
                role,
                status,
                email_verification_token_hash,
                email_verification_expires_at
            ) VALUES (?, ?, ?, 'designer', 'active', ?, ?)");
            $stmt->execute([
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                hash('sha256', $token),
                time() + EMAIL_VERIFICATION_TTL_SECONDS,
            ]);

            $userId = (int)$db->lastInsertId();
            $sent = send_email_verification_message($email, $username, $token);
            if ($sent) {
                mark_email_verification_sent($db, $userId);
            }
            app_start_session();
            $_SESSION['pending_verification_email'] = $email;
            header('Location: verify-email.php?sent=' . ($sent ? '1' : '0'));
            exit;
        } catch (PDOException) {
            $error = 'Impossible de créer ce compte (email ou nom déjà utilisé ?).';
        } catch (Throwable) {
            $error = 'Le compte a été créé, mais le lien de vérification n’a pas pu être préparé. Essayez de le renvoyer depuis la page de connexion.';
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
    <title>Créer un compte | Learning Designer</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260903-pagefind-dark">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="signup-page">
<?php render_site_nav('signup'); ?>
<main class="account-shell with-nav">
    <section class="account-card">
        <p class="account-kicker">Learning Designer</p>
        <h1>Créer un compte</h1>
        <p class="account-copy">Inscrivez-vous avec votre adresse @florimont.ch. Un lien de vérification vous sera envoyé avant votre première connexion.</p>
        <form method="post" class="account-form">
            <label for="username">Nom d’utilisateur</label>
            <input id="username" name="username" type="text" required autocomplete="nickname">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autocomplete="username" placeholder="@florimont.ch">
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
            <button type="submit">Créer mon compte</button>
        </form>
        <p class="account-privacy-notice">Les informations saisies sont nécessaires à la création et à la sécurisation du compte. Consultez la <a href="politique-confidentialite.php">politique de confidentialité</a> pour connaître leur utilisation et vos droits.</p>
        <?php if ($error !== ''): ?>
            <p class="account-message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <p class="account-footer"><a href="login.php">J’ai déjà un compte</a></p>
    </section>
</main>
<?php render_site_footer(); ?>
</body>
</html>
