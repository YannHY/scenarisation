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
    <meta name="description" content="Mentions légales du site Scenarisation.">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title>Mentions légales | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="legal-page">
<?php render_site_nav('legal'); ?>
<main class="legal-shell">
    <article class="legal-card">
        <h1>Mentions légales</h1>
        <p class="legal-updated"><strong>Dernière mise à jour&nbsp;: <time datetime="2026-09-05">5 septembre 2026</time></strong></p>

        <h2>Édition et publication</h2>
        <p>Le site <strong>Scenarisation</strong>, accessible à l’adresse <a href="<?= h($publicUrl) ?>"><?= h($publicUrl) ?></a>, est un service non commercial créé et édité par <strong>Yann Houry</strong>, avec la contribution de <strong>François Jourde</strong>.</p>
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
        <p>Les conditions applicables aux contenus originaux du site, au code source et aux scénarios publiés par les utilisateurs sont détaillées dans la page <a href="licence-reutilisation.php">Licence et réutilisation</a>.</p>
        <p>Les marques, dénominations, bibliothèques, ressources et contenus appartenant à des tiers demeurent la propriété de leurs titulaires respectifs et sont soumis à leurs propres conditions.</p>

        <h2>Contenus publiés par les utilisateurs</h2>
        <p>Les utilisateurs sont responsables des scénarios pédagogiques, textes, liens et autres contenus qu’ils enregistrent ou publient. Ils doivent s’assurer qu’ils disposent des droits et autorisations nécessaires et qu’ils ne portent pas atteinte aux droits d’autrui, à la confidentialité ou à la protection des données personnelles.</p>
        <p>Pour signaler un contenu manifestement illicite, une atteinte à des droits ou la présence indue de données personnelles, adressez une demande suffisamment précise au directeur de la publication depuis la <a href="https://www.ralentirtravaux.com/contact/contact.php" rel="noopener noreferrer">page de contact</a>, en indiquant l’adresse du contenu concerné, le motif du signalement et, le cas échéant, les justificatifs utiles.</p>

        <h2>Utilisation du formulaire d’avis</h2>
        <p>Le formulaire d’avis est destiné aux retours sincères portant sur l’utilisation et l’amélioration de Scenarisation. Il ne doit pas être utilisé pour transmettre des contenus illicites, injurieux, publicitaires, automatisés, ni des données personnelles ou confidentielles concernant l’utilisateur ou un tiers.</p>
        <p>Les commentaires ne sont pas publiés. Ils peuvent être consultés par les administrateurs autorisés, exploités sous forme agrégée pour améliorer le service, puis supprimés conformément à la <a href="politique-confidentialite.php">politique de confidentialité</a>. Les soumissions abusives peuvent être bloquées.</p>

        <h2>Données personnelles</h2>
        <p>Les informations relatives aux comptes, aux scénarios enregistrés, aux retours utilisateurs, aux cookies, au stockage local et aux services externes figurent dans la <a href="politique-confidentialite.php">politique de confidentialité</a>.</p>

        <h2>Liens externes et disponibilité</h2>
        <p>Scenarisation peut contenir des liens vers des ressources externes choisies par l’éditeur ou ajoutées par les utilisateurs. L’éditeur ne contrôle pas en permanence ces sites et ne peut garantir leur disponibilité, leur exactitude ou leurs pratiques. Tout contenu manifestement illicite ou lien problématique peut être signalé.</p>
        <p>Le service est fourni sans garantie de disponibilité continue. Des interruptions peuvent intervenir pour maintenance, mise à jour, incident technique ou cause indépendante de l’éditeur.</p>
    </article>
</main>
<?php render_site_footer(); ?>
</body>
</html>
