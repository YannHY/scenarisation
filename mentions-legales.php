<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

app_start_session();
$publicUrl = app_base_url();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Mentions légales du site Scénarisation.">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title>Mentions légales | Scénarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260912-breadcrumb-alignment">
</head>
<body class="legal-page">
<?php render_site_nav('legal'); ?>
<main class="legal-shell">
    <article class="legal-card">
        <h1>Mentions légales</h1>
        <p class="legal-updated"><strong>Dernière mise à jour&nbsp;: <time datetime="2026-09-26">26 septembre 2026</time></strong></p>

        <h2>Édition et publication</h2>
        <p>Le site <strong>Scénarisation</strong>, accessible à l’adresse <a href="<?= h($publicUrl) ?>"><?= h($publicUrl) ?></a>, est un service non commercial créé et édité par <strong>Yann Houry</strong> et <strong>François Jourde</strong>.</p>
        <p>Le directeur de la publication est Yann Houry.</p>
        <p>Pour contacter l’éditeur, signaler un contenu ou exercer un droit de réponse, utilisez la <a href="https://www.ralentirtravaux.com/contact/contact.php" rel="noopener noreferrer">page de contact de Ralentir Travaux</a>.</p>

        <h2>Hébergement</h2>
        <address>
            <strong>OVH SAS</strong><br>
            2 rue Kellermann<br>
            59100 Roubaix<br>
            France<br>
            Téléphone&nbsp;: 1007 depuis la France, ou +33&nbsp;9&nbsp;72&nbsp;10&nbsp;10&nbsp;07 depuis l’étranger<br>
            RCS Lille Métropole&nbsp;: 424&nbsp;761&nbsp;419
        </address>
        <p><a href="https://www.ovhcloud.com/fr/terms-and-conditions/" rel="noopener noreferrer">Site et informations légales d’OVHcloud</a></p>

        <h2>Propriété intellectuelle</h2>
        <p>Les conditions applicables aux contenus originaux du site, au code source, aux éléments appartenant à des tiers et aux scénarios publiés par les utilisateurs sont détaillées dans les <a href="conditions-utilisation.php#licences-reutilisation">conditions d’utilisation et licences</a>.</p>

        <h2>Données personnelles</h2>
        <p>Les informations relatives aux comptes, aux scénarios enregistrés, aux retours utilisateurs, aux cookies, au stockage local et aux services externes figurent dans la <a href="politique-confidentialite.php">politique de confidentialité</a>.</p>
    </article>
</main>
<?php render_site_footer(); ?>
</body>
</html>
