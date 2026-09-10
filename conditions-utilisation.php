<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

app_start_session();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Conditions générales d’utilisation du service Scenarisation.">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title>Conditions générales d’utilisation | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="legal-page">
<?php render_site_nav('terms'); ?>
<main class="legal-shell">
    <article class="legal-card">
        <h1>Conditions générales d’utilisation</h1>
        <p class="legal-updated"><strong>Version du <time datetime="<?= h(TERMS_VERSION) ?>">10 septembre 2026</time></strong></p>
        <p class="legal-lead">Ces conditions encadrent l’utilisation de Scenarisation, un service non commercial de création, de sauvegarde et de partage de scénarios pédagogiques.</p>

        <h2>Service et accès</h2>
        <p>Scenarisation est édité par Yann Houry, avec la contribution de François Jourde. Les coordonnées de l’éditeur et de l’hébergeur figurent dans les <a href="mentions-legales.php">mentions légales</a>.</p>
        <p>L’application peut être consultée sans compte. Un compte permet notamment de sauvegarder des scénarios sur le serveur et de les publier. L’inscription en libre-service est réservée aux adresses <code>@florimont.ch</code> et nécessite la vérification de l’adresse électronique.</p>

        <h2>Création et sécurité du compte</h2>
        <p>Lors de son inscription, l’utilisateur accepte ces conditions au moyen de la case prévue dans le formulaire. La date et la version acceptée sont enregistrées avec le compte.</p>
        <p>L’utilisateur fournit une adresse électronique valide, protège son mot de passe et ses jetons de connexion et signale tout accès suspect à son compte. Il ne doit pas tenter d’accéder aux comptes d’autrui, contourner les protections du service ou perturber son fonctionnement.</p>

        <h2>Contenus et usages autorisés</h2>
        <p>L’utilisateur est responsable des textes, scénarios, liens et ressources qu’il importe, enregistre ou publie. Il doit disposer des droits et autorisations nécessaires et respecter les droits des tiers.</p>
        <p>L’utilisateur s’engage à ne pas publier ni partager dans ses scénarios de contenus protégés par le droit d’auteur (copyright) dont il ne détient pas les droits nécessaires, sauf autorisation du titulaire des droits, licence compatible avec le partage envisagé ou exception légale applicable. Cette obligation concerne notamment les textes, extraits de manuels, images, photographies, vidéos et fichiers joints. La mention de la source ou de l’auteur ne suffit pas, à elle seule, à autoriser leur publication.</p>
        <p>Les scénarios et commentaires ne doivent pas contenir de contenus illicites ni d’informations confidentielles. Les scénarios ne doivent pas contenir de données personnelles concernant des élèves, collègues ou tiers sans base légale et autorisations appropriées. Scenarisation n’est pas un outil de suivi individuel des élèves.</p>
        <p>Les avis doivent porter sur l’utilisation du service. Les envois injurieux, publicitaires ou automatisés sont interdits. Les commentaires sont réservés aux administrateurs et ne sont pas publiés.</p>

        <h2>Partage, publication et réutilisation</h2>
        <p>Les créations restent celles de leurs auteurs. Le partage par lien rend un scénario accessible à toute personne disposant du lien, sans accorder à lui seul une licence de réutilisation. La publication dans le catalogue rend le scénario public sous la licence Creative Commons choisie par son auteur.</p>
        <p>L’utilisateur peut retirer un scénario du catalogue ou révoquer son lien. Les licences déjà accordées restent applicables aux copies reçues avant ce retrait, selon leurs conditions. Les règles détaillées relatives aux créations, aux contenus du site et au code source figurent dans <a href="licence-reutilisation.php">Licence et réutilisation</a>.</p>

        <h2>Données personnelles</h2>
        <p>La <a href="politique-confidentialite.php">politique de confidentialité</a> décrit les données traitées, leurs finalités, leur durée de conservation et les droits de l’utilisateur. L’acceptation des présentes conditions n’est pas un consentement à des usages publicitaires des données.</p>

        <h2>Disponibilité et sauvegardes</h2>
        <p>Le service peut être interrompu pour maintenance, mise à jour ou incident technique. Une disponibilité continue n’est pas garantie. Des incidents peuvent entraîner la perte, l’altération ou l’indisponibilité de scénarios et de données. L’utilisateur est responsable de la conservation de copies de sauvegarde et s’engage à exporter régulièrement ses scénarios sur un support distinct du service.</p>

        <h2>Responsabilité</h2>
        <p>Les contenus partagés ou publiés sont placés sous la responsabilité de leurs auteurs. Leur mise à disposition sur Scenarisation ne vaut pas validation ni approbation par l’éditeur ou les contributeurs du service. Il appartient à chaque utilisateur de vérifier leur exactitude, les droits de réutilisation et leur adéquation à son contexte pédagogique.</p>
        <p>Dans les limites autorisées par la loi applicable, l’éditeur et les contributeurs du service déclinent toute responsabilité pour les contenus partagés par les utilisateurs et les conséquences de leur utilisation, ainsi que pour les pertes de scénarios ou de données et les préjudices qui en résultent en cas de panne, d’erreur, d’interruption ou d’autre incident technique.</p>
        <p>Ces limitations n’excluent ni les obligations légales propres à l’éditeur, notamment concernant le traitement des signalements de contenus illicites, ni les responsabilités qui ne peuvent être exclues ou limitées par la loi. Elles ne privent pas l’utilisateur des droits impératifs dont il bénéficie, notamment en cas de manquement du service à ses obligations.</p>

        <h2>Gestion et suppression du compte</h2>
        <p>L’utilisateur peut supprimer son compte depuis son espace personnel. Cette suppression entraîne celle des scénarios et des jetons CLI associés, selon les modalités décrites dans la politique de confidentialité.</p>
        <p>Les administrateurs peuvent bloquer les soumissions abusives, retirer un contenu illicite ou désactiver un compte qui compromet la sécurité du service ou méconnaît ces règles. Pour signaler un contenu ou demander un réexamen, utilisez la <a href="https://www.ralentirtravaux.com/contact/contact.php" rel="noopener noreferrer">page de contact de l’éditeur</a> en précisant le compte ou le contenu concerné.</p>

        <h2>Évolution des conditions</h2>
        <p>Ces conditions peuvent évoluer avec le service. Chaque version porte une date. Une modification de cette page ne modifie pas la version d’acceptation enregistrée pour un compte.</p>
    </article>
</main>
<?php render_site_footer(); ?>
</body>
</html>
