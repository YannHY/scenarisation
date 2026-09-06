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
    <meta name="description" content="Conditions de licence et de réutilisation des contenus de Scenarisation.">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title>Licence et réutilisation | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="legal-page">
<?php render_site_nav('license'); ?>
<main class="legal-shell">
    <article class="legal-card">
        <h1>Licence et réutilisation</h1>
        <p class="legal-updated"><strong>Dernière mise à jour&nbsp;: <time datetime="2026-09-05">5 septembre 2026</time></strong></p>

        <p class="legal-lead">Cette page distingue les contenus propres à Scenarisation, son code source, les créations publiées par les utilisateurs et les éléments appartenant à des tiers.</p>

        <h2>Contenus pédagogiques et éditoriaux</h2>
        <p>Sauf mention contraire, les textes d’aide, modèles génériques, prompts, schémas et autres contenus pédagogiques originaux publiés par Scenarisation sont proposés sous licence <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.fr" rel="license noopener noreferrer">Creative Commons Attribution – Partage dans les mêmes conditions 4.0 International (CC BY-SA 4.0)</a>.</p>
        <p>Vous pouvez les copier, les partager et les adapter, y compris à des fins commerciales, à condition&nbsp;:</p>
        <ul>
            <li>de créditer Yann Houry et François Jourde – Scenarisation&nbsp;;</li>
            <li>d’ajouter, dans la mesure du possible, un lien vers la ressource d’origine&nbsp;;</li>
            <li>de mentionner la licence CC BY-SA 4.0 et d’ajouter un lien vers celle-ci&nbsp;;</li>
            <li>d’indiquer clairement les modifications effectuées&nbsp;;</li>
            <li>de diffuser toute adaptation sous la même licence.</li>
        </ul>
        <p class="legal-attribution"><strong>Exemple d’attribution&nbsp;:</strong><br>«&nbsp;Adapté de Scenarisation, Yann Houry et François Jourde, sous licence CC BY-SA 4.0 – [lien vers la ressource d’origine]. Modifications&nbsp;: [description].&nbsp;»</p>

        <h2>Code source</h2>
        <p>Le <a href="https://github.com/YannHY/learning-designer" rel="noopener noreferrer">code source de Scenarisation</a> est réutilisable selon le fichier <a href="https://github.com/YannHY/learning-designer/blob/main/LICENSE" rel="license noopener noreferrer"><code>LICENSE</code></a> présent dans le dépôt. Dans sa version en vigueur à la date indiquée en haut de cette page, ce fichier applique l’outil juridique <a href="https://creativecommons.org/publicdomain/zero/1.0/deed.fr" rel="license noopener noreferrer">CC0 1.0 Universal</a>.</p>
        <p>CC0 ne peut porter que sur les éléments pour lesquels la personne qui l’applique détient les droits nécessaires. Les bibliothèques, polices, icônes, extraits, contributions antérieures et autres composants appartenant à des tiers conservent leurs propres licences.</p>

        <h2>Designs créés et publiés par les utilisateurs</h2>
        <p>Les scénarios pédagogiques créés, importés ou enregistrés dans l’application restent sous la responsabilité de leurs auteurs. Scenarisation ne revendique aucun droit de propriété sur ces productions.</p>
        <ul>
            <li><strong>Partage par lien&nbsp;:</strong> le design est consultable en lecture seule par toute personne disposant du lien. La mise à disposition du lien n’accorde, à elle seule, aucune licence de réutilisation.</li>
            <li><strong>Publication dans le catalogue&nbsp;:</strong> l’auteur choisit l’une des licences Creative Commons 4.0 proposées. La licence sélectionnée est affichée avec le design et fixe les droits de réutilisation accordés au public.</li>
            <li><strong>Retrait&nbsp;:</strong> l’auteur peut retirer un design du catalogue ou révoquer son lien. Une licence Creative Commons déjà accordée demeure toutefois valable pour les copies reçues avant le retrait, conformément à ses conditions.</li>
        </ul>
        <p>Avant toute publication, l’auteur doit vérifier qu’il possède les droits nécessaires sur l’ensemble du contenu et qu’aucune donnée personnelle, confidentielle ou relative à un élève n’y figure sans base légale et autorisation appropriées.</p>

        <h2>Contenus et droits de tiers</h2>
        <p>Les licences présentées sur cette page ne s’appliquent pas automatiquement aux œuvres, citations, photographies, illustrations, vidéos, marques, logiciels ou ressources externes appartenant à des tiers. Leurs crédits, licences et conditions propres doivent être respectés.</p>
        <p>Scenarisation est inspiré de l’<a href="https://www.ucl.ac.uk/learning-designer/" rel="noopener noreferrer">UCL Learning Designer</a> et s’appuie sur le <a href="https://github.com/jourde/learning-designer-revised" rel="noopener noreferrer">travail de François Jourde</a>. Ces références n’emportent aucun transfert des marques ou droits détenus par leurs titulaires respectifs.</p>

        <h2>Commentaires transmis avec un avis</h2>
        <p>Les commentaires facultatifs envoyés au moyen du formulaire d’avis ne sont pas publiés et ne sont pas placés sous la licence Creative Commons du site. Leur auteur autorise uniquement leur lecture, leur analyse et leur utilisation interne par les administrateurs de Scenarisation afin de corriger, évaluer et améliorer le service.</p>
        <p>L’auteur du commentaire doit disposer des droits nécessaires sur son contenu et s’abstenir d’y inclure des informations personnelles ou confidentielles. Les règles détaillées figurent dans les <a href="mentions-legales.php">mentions légales</a> et la <a href="politique-confidentialite.php">politique de confidentialité</a>.</p>

        <h2>Questions et demandes</h2>
        <p>Pour demander une autorisation particulière ou signaler un contenu dont les droits vous appartiennent, utilisez la <a href="https://www.ralentirtravaux.com/contact/contact.php" rel="noopener noreferrer">page de contact de Ralentir Travaux</a>.</p>
    </article>
</main>
<?php render_site_footer(); ?>
</body>
</html>
