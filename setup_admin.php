<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$error = '';
try {
    $db = app_db();
    if (!is_admin_seed_needed($db)) {
        header('Location: login.php');
        exit;
    }
} catch (Throwable $e) {
    $db = null;
    $error = 'Le stockage utilisateur n’a pas pu etre initialise. Verifiez la configuration ou les droits d’ecriture du dossier data/.';
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
    } elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caracteres.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, status, email_verified_at) VALUES (?, ?, ?, 'admin', 'active', CURRENT_TIMESTAMP)");
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Impossible de creer ce compte (email ou nom deja utilise ?).';
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
    <title>Premier compte admin | Learning Designer</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260903-pagefind-dark">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="setup-page">
<?php render_site_nav(); ?>
<main class="account-shell with-nav">
    <section class="account-card">
        <p class="account-kicker">Configuration initiale</p>
        <h1>Creer le premier compte admin</h1>
        <p class="account-copy">Cette page n’apparait que tant qu’aucun administrateur n’existe dans la base.</p>
        <form method="post" class="account-form">
            <label for="username">Nom d’utilisateur</label>
            <input id="username" name="username" type="text" required autocomplete="nickname">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autocomplete="username">
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
            <button type="submit">Creer l’administrateur</button>
        </form>
        <?php if ($error !== ''): ?>
            <p class="account-message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </section>
</main>
<?php render_site_footer(); ?>
</body>
</html>
