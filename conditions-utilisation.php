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
    <meta name="description" content="Conditions d’utilisation, licences et règles de réutilisation du service Scénarisation.">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title>Conditions d’utilisation et licences | Scénarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260912-breadcrumb-alignment">
</head>
<body class="legal-page">
<?php render_site_nav('terms'); ?>
<main class="legal-shell">
    <article class="legal-card">
        <h1>Conditions d’utilisation et licences</h1>
        <p class="legal-updated"><strong>Version du <time datetime="<?= h(TERMS_VERSION) ?>">26 septembre 2026</time></strong></p>
        <p class="legal-lead">Ces conditions encadrent l’utilisation de Scénarisation, un service non commercial de création, de sauvegarde et de partage de scénarios pédagogiques. Elles précisent également les licences applicables aux contenus du site, au code source et aux créations publiées par les utilisateurs.</p>

        <h2>Service et accès</h2>
        <p>Scénarisation est édité par Yann Houry et François Jourde. Les coordonnées de l’éditeur et de l’hébergeur figurent dans les <a href="https://scenarisation.eu/mentions-legales.php">mentions légales</a>.</p>
        <p>L’application peut être consultée sans compte. Un compte permet notamment de sauvegarder des scénarios sur le serveur et de les publier.</p>
        <p>Scénarisation est conçu pour être utilisé par des adultes. Vous devez avoir au moins treize ans pour l'utiliser. Si nous apprenons que nous avons recueilli des données personnelles concernant un enfant de moins de treize ans, nous les supprimerons dans les plus brefs délais. Si vous pensez que nous détenons des informations concernant un enfant de moins de treize ans, veuillez nous contacter à l’adresse <a href="mailto:yannhoury@ralentirtravaux.com">yannhoury@ralentirtravaux.com</a>.</p>

        <h2>Création et sécurité du compte</h2>
        <p>Lors de son inscription, l’utilisateur accepte ces conditions au moyen de la case prévue dans le formulaire. La date et la version acceptée sont enregistrées avec le compte.</p>
        <p>L’utilisateur fournit une adresse électronique valide, protège son mot de passe et ses jetons de connexion et signale tout accès suspect à son compte. Il ne doit pas tenter d’accéder aux comptes d’autrui, contourner les protections du service ou perturber son fonctionnement.</p>

        <h2>Contenus et usages autorisés</h2>
        <p>L’utilisateur est responsable des textes, scénarios, liens et ressources qu’il importe, enregistre ou publie. Il doit disposer des droits et autorisations nécessaires et respecter les droits des tiers.</p>
        <p>L’utilisateur s’engage à ne pas publier ni partager dans ses scénarios de contenus protégés par le droit d’auteur (copyright) dont il ne détient pas les droits nécessaires, sauf autorisation du titulaire des droits, licence compatible avec le partage envisagé ou exception légale applicable. Cette obligation concerne notamment les textes, extraits de manuels, images, photographies, vidéos et fichiers joints. La mention de la source ou de l’auteur ne suffit pas, à elle seule, à autoriser leur publication.</p>
        <p>Les scénarios et commentaires ne doivent pas contenir de contenus illicites ni d’informations confidentielles. Les scénarios ne doivent pas contenir de données personnelles concernant des élèves, collègues ou tiers sans base légale et autorisations appropriées. Scénarisation n’est pas un outil de suivi individuel des élèves.</p>
        <p>Les avis doivent porter sur l’utilisation du service. Les envois injurieux, publicitaires ou automatisés sont interdits. Les commentaires sont réservés aux administrateurs et ne sont pas publiés.</p>

        <h2 id="licences-reutilisation">Licences et réutilisation</h2>
        <p>Les règles ci-dessous distinguent les contenus propres à Scénarisation, son code source, les créations publiées par les utilisateurs et les éléments appartenant à des tiers.</p>

        <h3>Contenus pédagogiques et éditoriaux</h3>
        <p>Sauf mention contraire, les textes d’aide, modèles génériques, prompts, schémas et autres contenus pédagogiques originaux publiés par Scénarisation sont proposés sous licence <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.fr" rel="license noopener noreferrer">Creative Commons Attribution – Partage dans les mêmes conditions 4.0 International (CC BY-SA 4.0)</a>.</p>
        <p>Vous pouvez les copier, les partager et les adapter, y compris à des fins commerciales, à condition&nbsp;:</p>
        <ul>
            <li>de créditer Yann Houry et François Jourde – Scénarisation&nbsp;;</li>
            <li>d’ajouter, dans la mesure du possible, un lien vers la ressource d’origine&nbsp;;</li>
            <li>de mentionner la licence CC BY-SA 4.0 et d’ajouter un lien vers celle-ci&nbsp;;</li>
            <li>d’indiquer clairement les modifications effectuées&nbsp;;</li>
            <li>de diffuser toute adaptation sous la même licence.</li>
        </ul>
        <p class="legal-attribution"><strong>Exemple d’attribution&nbsp;:</strong><br>«&nbsp;Adapté de Scénarisation, Yann Houry et François Jourde, sous licence CC BY-SA 4.0 – [lien vers la ressource d’origine]. Modifications&nbsp;: [description].&nbsp;»</p>

        <h3>Code source</h3>
        <p>Le <a href="https://github.com/YannHY/scenarisation" rel="noopener noreferrer">code source de Scénarisation</a> est proposé sous licence <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.fr" rel="license noopener noreferrer">Creative Commons Attribution – Partage dans les mêmes conditions 4.0 International (CC BY-SA 4.0)</a>, dont le texte intégral figure dans le fichier <a href="https://github.com/YannHY/scenarisation/blob/main/LICENSE" rel="license noopener noreferrer"><code>LICENSE</code></a> présent dans le dépôt.</p>
        <p>Cette licence ne porte que sur les éléments pour lesquels les auteurs détiennent les droits nécessaires. Les bibliothèques, polices, icônes, extraits, contributions antérieures et autres composants appartenant à des tiers conservent leurs propres licences.</p>

        <h3>Scénarios créés et publiés par les utilisateurs</h3>
        <p>Les scénarios pédagogiques créés, importés ou enregistrés dans l’application restent sous la responsabilité de leurs auteurs. Scénarisation ne revendique aucun droit de propriété sur ces productions.</p>
        <ul>
            <li><strong>Partage par lien&nbsp;:</strong> le scénario est consultable en lecture seule par toute personne disposant du lien. La mise à disposition du lien n’accorde, à elle seule, aucune licence de réutilisation.</li>
            <li><strong>Publication dans le catalogue&nbsp;:</strong> l’auteur choisit l’une des licences Creative Commons 4.0 proposées. La licence sélectionnée est affichée avec le scénario et fixe les droits de réutilisation accordés au public.</li>
            <li><strong>Retrait&nbsp;:</strong> l’auteur peut retirer un scénario du catalogue ou révoquer son lien. Une licence Creative Commons déjà accordée demeure toutefois valable pour les copies reçues avant le retrait, conformément à ses conditions.</li>
        </ul>
        <p>Avant toute publication, l’auteur doit vérifier qu’il possède les droits nécessaires sur l’ensemble du contenu et qu’aucune donnée personnelle, confidentielle ou relative à un élève n’y figure sans base légale et autorisation appropriées.</p>

        <h3>Contenus et droits de tiers</h3>
        <p>Les licences présentées dans cette section ne s’appliquent pas automatiquement aux œuvres, citations, photographies, illustrations, vidéos, marques, logiciels ou ressources externes appartenant à des tiers. Leurs crédits, licences et conditions propres doivent être respectés.</p>
        <p>Scénarisation est inspiré de l’<a href="https://www.ucl.ac.uk/learning-designer/" rel="noopener noreferrer">UCL Learning Designer</a> et s’appuie sur le <a href="https://github.com/jourde/learning-designer-revised" rel="noopener noreferrer">travail de François Jourde</a>. Ces références n’emportent aucun transfert des marques ou droits détenus par leurs titulaires respectifs.</p>

        <h3>Commentaires transmis avec un avis</h3>
        <p>Les commentaires facultatifs et anonymes envoyés au moyen du formulaire d’avis ne sont pas publiés et ne sont pas placés sous la licence Creative Commons du site. Leur auteur autorise uniquement leur lecture, leur analyse et leur utilisation interne par les administrateurs de Scénarisation afin de corriger, évaluer et améliorer le service.</p>
        <p>L’auteur du commentaire doit disposer des droits nécessaires sur son contenu et s’abstenir d’y inclure des informations personnelles ou confidentielles.</p>

        <h3>Questions et demandes</h3>
        <p>Pour demander une autorisation particulière ou signaler un contenu dont les droits vous appartiennent, utilisez la <a href="https://www.ralentirtravaux.com/contact/contact.php" rel="noopener noreferrer">page de contact de Ralentir Travaux</a>.</p>

        <h2>Données personnelles</h2>
        <p>La <a href="politique-confidentialite.php">politique de confidentialité</a> décrit les données traitées, leurs finalités, leur durée de conservation et les droits de l’utilisateur. L’acceptation des présentes conditions n’est pas un consentement à des usages publicitaires des données.</p>

        <h2>Disponibilité et sauvegardes</h2>
        <p>Le service peut être interrompu pour maintenance, mise à jour ou incident technique. Une disponibilité continue n’est pas garantie. Des incidents peuvent entraîner la perte, l’altération ou l’indisponibilité de scénarios et de données. L’utilisateur est responsable de la conservation de copies de sauvegarde et s’engage à exporter régulièrement ses scénarios sur un support distinct du service.</p>
        <p>Scénarisation peut contenir des liens vers des ressources externes choisies par l’éditeur ou ajoutées par les utilisateurs. L’éditeur ne contrôle pas en permanence ces sites et ne peut garantir leur disponibilité, leur exactitude ou leurs pratiques.</p>

        <h2>Responsabilité</h2>
        <p>Les contenus partagés ou publiés sont placés sous la responsabilité de leurs auteurs. Leur mise à disposition sur Scénarisation ne vaut pas validation ni approbation par l’éditeur ou les contributeurs du service. Il appartient à chaque utilisateur de vérifier leur exactitude, les droits de réutilisation et leur adéquation à son contexte pédagogique.</p>
        <p>Dans les limites autorisées par la loi applicable, l’éditeur et les contributeurs du service déclinent toute responsabilité pour les contenus partagés par les utilisateurs et les conséquences de leur utilisation, ainsi que pour les pertes de scénarios ou de données et les préjudices qui en résultent en cas de panne, d’erreur, d’interruption ou d’autre incident technique.</p>
        <p>Ces limitations n’excluent ni les obligations légales propres à l’éditeur, notamment concernant le traitement des signalements de contenus illicites, ni les responsabilités qui ne peuvent être exclues ou limitées par la loi. Elles ne privent pas l’utilisateur des droits impératifs dont il bénéficie, notamment en cas de manquement du service à ses obligations.</p>

        <h2>Gestion et suppression du compte</h2>
        <p>L’utilisateur peut supprimer son compte depuis son espace personnel. Cette suppression entraîne celle des scénarios et des jetons CLI associés, selon les modalités décrites dans la politique de confidentialité. Toutefois, cette suppression n’entraîne pas celle des scénarios qui ont été dérivés à partir des scénarios de l’utilisateur.</p>
        <p>Les administrateurs peuvent bloquer les soumissions abusives, retirer un contenu illicite ou désactiver un compte qui compromet la sécurité du service ou méconnaît ces règles. Pour signaler un contenu ou demander un réexamen, utilisez la <a href="https://www.ralentirtravaux.com/contact/contact.php" rel="noopener noreferrer">page de contact de l’éditeur</a> en précisant le compte ou le contenu concerné.</p>

        <h2>Évolution des conditions</h2>
        <p>Ces conditions peuvent évoluer avec le service. Chaque version porte une date. Une modification de cette page ne modifie pas la version d’acceptation enregistrée pour un compte.</p>
    </article>
</main>
<?php render_site_footer(); ?>
</body>
</html>
