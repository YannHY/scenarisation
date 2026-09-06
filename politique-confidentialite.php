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
    <meta name="description" content="Politique de confidentialité de Learning Designer.">
    <link rel="icon" href="assets/favicon.svg?v=20260804" type="image/svg+xml" sizes="any">
    <title>Politique de confidentialité | Learning Designer</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="legal-page">
<?php render_site_nav('privacy'); ?>
<main class="legal-shell">
    <article class="legal-card">
        <h1>Politique de confidentialité</h1>
        <p class="legal-updated"><strong>Dernière mise à jour&nbsp;: <time datetime="2026-09-05">5 septembre 2026</time></strong></p>

        <p class="legal-lead">Cette politique explique quelles données sont traitées lors de l’utilisation de Learning Designer, pour quelles finalités, pendant combien de temps et quels sont vos droits.</p>

        <h2>Responsable du traitement</h2>
        <p>Le responsable du traitement est <strong>Yann Houry</strong>, éditeur de Learning Designer. Pour toute question relative à vos données personnelles ou pour exercer vos droits, utilisez la <a href="https://www.ralentirtravaux.com/contact/contact.php" rel="noopener noreferrer">page de contact de Ralentir Travaux</a>.</p>

        <h2>Données traitées et finalités</h2>

        <h3>Navigation, fonctionnement et sécurité</h3>
        <p>À chaque requête, l’infrastructure d’hébergement reçoit les informations techniques nécessaires à la transmission de la page, notamment l’adresse IP, la date et l’heure ainsi que la ressource demandée. OVHcloud peut enregistrer certaines de ces informations dans les journaux techniques de l’hébergement, avec notamment le type de navigateur transmis par le navigateur.</p>
        <p>Learning Designer ne constitue pas de journal de navigation propre et n’utilise pas l’adresse IP pour suivre ses visiteurs. Lors de certaines opérations sensibles, l’application vérifie uniquement l’en-tête <code>Origin</code> ou <code>Referer</code>, lorsqu’il est disponible, afin de s’assurer que la requête provient du site. Certaines erreurs techniques peuvent également être consignées dans le journal du serveur.</p>
        <p>Ces traitements reposent sur l’intérêt légitime de l’éditeur à assurer le fonctionnement, la sécurité et le diagnostic technique du service.</p>

        <h3>Création et gestion d’un compte</h3>
        <p>La création d’un compte entraîne l’enregistrement du nom d’utilisateur choisi, de l’adresse électronique <code>@florimont.ch</code>, du mot de passe sous forme hachée, du rôle et de l’état du compte, ainsi que des dates de création, de vérification de l’adresse et de dernière connexion.</p>
        <p>Des jetons temporaires, enregistrés sous forme hachée, sont utilisés pour vérifier l’adresse électronique et réinitialiser le mot de passe. Ces données servent à créer et sécuriser le compte, permettre la connexion, envoyer les messages indispensables au service et administrer les accès. Leur traitement est nécessaire à l’exécution du service demandé par l’utilisateur.</p>
        <p>Le nom d’utilisateur, l’adresse électronique et le mot de passe sont obligatoires pour créer un compte. L’application reste consultable sans compte, mais la sauvegarde sur le serveur, la publication et certaines fonctions de gestion ne sont alors pas disponibles.</p>

        <h3>Scénarios pédagogiques enregistrés</h3>
        <p>Lorsque vous sauvegardez un design dans votre compte, le serveur conserve son titre, son contenu structuré, ses métadonnées pédagogiques, ses dates de création et de mise à jour, ainsi que son rattachement à votre compte. Ces données servent à enregistrer, retrouver, modifier, importer et exporter vos travaux.</p>
        <p>Le contenu d’un design est librement saisi par son auteur. Il ne doit pas contenir de données personnelles ou sensibles concernant des élèves, collègues ou tiers, sauf si l’auteur dispose d’une base légale et des autorisations nécessaires. Learning Designer n’est pas conçu comme un dossier scolaire ni comme un outil de suivi individuel des élèves.</p>

        <h3>Partage et publication</h3>
        <p>Lorsque vous publiez un design, le serveur enregistre un identifiant de partage, son état de publication et, le cas échéant, la licence Creative Commons choisie et la date d’inscription au catalogue.</p>
        <ul>
            <li>Un design publié <strong>par lien</strong> devient accessible à toute personne qui possède ce lien.</li>
            <li>Un design ajouté au <strong>catalogue public</strong> rend également visibles son contenu, son titre, sa description, le nom d’utilisateur de son auteur, sa date de mise à jour et la licence choisie.</li>
        </ul>
        <p>La publication est facultative et résulte de l’action de l’utilisateur. Un design peut être retiré du catalogue ou dépublié depuis le compte. Des copies déjà téléchargées ou réutilisées par des tiers peuvent toutefois subsister.</p>

        <h3>Jetons de ligne de commande</h3>
        <p>Si vous créez un jeton pour le CLI <code>learning</code>, le serveur conserve son nom, son empreinte cryptographique, un préfixe permettant de l’identifier, sa date de création, sa dernière date d’utilisation et, le cas échéant, sa date de révocation. Le jeton complet n’est affiché qu’au moment de sa création.</p>

        <h3>Retours sur l’application</h3>
        <p>Le bouton d’avis permet d’enregistrer une appréciation, un commentaire facultatif, la page consultée, la langue de l’interface et la date de l’envoi. Le retour n’est pas rattaché au compte de l’utilisateur. Il sert exclusivement à comprendre la satisfaction générale et à améliorer Learning Designer.</p>
        <p>Pour limiter les envois automatisés, l’application produit une empreinte technique pseudonyme à partir de l’adresse IP et du type de navigateur. L’adresse IP et le type de navigateur ne sont pas conservés dans la base de retours. L’empreinte change chaque jour et est effacée des retours après 24 heures.</p>

        <h3>Préférences et brouillon dans le navigateur</h3>
        <p>Learning Designer utilise le stockage local du navigateur pour mémoriser la langue, le thème clair ou sombre, certains réglages d’affichage et le design en cours d’édition. Le brouillon est séparé entre l’espace invité et le compte connecté. Ces informations restent normalement sur l’appareil jusqu’à leur remplacement ou leur suppression depuis les réglages du navigateur.</p>

        <h2>Cookies et traceurs</h2>
        <p>Un cookie de session est utilisé pour maintenir la connexion, protéger l’accès au compte, sécuriser les requêtes et délivrer le jeton temporaire du formulaire d’avis. Il est configuré pour ne pas être accessible au JavaScript, n’est envoyé que sur une connexion sécurisée lorsque HTTPS est actif, et expire à la fermeture du navigateur. Il est strictement nécessaire au service.</p>
        <p>Les éléments enregistrés dans le stockage local servent aux préférences d’interface, à la sauvegarde locale demandée par l’utilisateur et au fonctionnement de l’éditeur. Ils ne sont pas utilisés pour établir un profil publicitaire.</p>
        <p><strong>Learning Designer n’intègre, à la date de cette politique, aucun outil de mesure d’audience, réseau publicitaire ou traceur de profilage.</strong></p>

        <h2>Services externes et transferts</h2>
        <p>Le site charge certaines ressources techniques depuis des services externes&nbsp;:</p>
        <ul>
            <li><a href="https://fonts.google.com/" rel="noopener noreferrer">Google Fonts</a> pour les polices d’interface&nbsp;;</li>
            <li><a href="https://cdnjs.com/" rel="noopener noreferrer">cdnjs, opéré par Cloudflare</a>, pour les icônes Font Awesome et, lors de certaines opérations d’import Excel, la bibliothèque SheetJS&nbsp;;</li>
            <li><a href="https://www.ovhcloud.com/" rel="noopener noreferrer">OVHcloud</a> pour l’hébergement du site et de sa base de données.</li>
        </ul>
        <p>Ces prestataires peuvent recevoir l’adresse IP, l’adresse de la ressource demandée et des informations techniques nécessaires à sa transmission. Selon leur organisation, certaines données techniques peuvent être traitées hors de l’Espace économique européen avec les garanties prévues par leurs politiques. Consultez les politiques de confidentialité de <a href="https://policies.google.com/privacy?hl=fr" rel="noopener noreferrer">Google</a>, de <a href="https://www.cloudflare.com/fr-fr/privacypolicy/" rel="noopener noreferrer">Cloudflare</a> et d’<a href="https://www.ovhcloud.com/fr/personal-data-protection/" rel="noopener noreferrer">OVHcloud</a>.</p>
        <p>Lorsque vous ouvrez un lien ajouté à un design ou suivez un lien vers un autre site, la politique de confidentialité de ce service externe s’applique.</p>

        <h2>Destinataires</h2>
        <p>Les données des comptes et des designs sont accessibles à Yann Houry et, lorsque cela est nécessaire à l’administration technique, aux administrateurs autorisés de Learning Designer. Les appréciations et commentaires transmis par le formulaire d’avis sont également consultables par ces administrateurs. OVHcloud peut traiter ces données pour assurer l’hébergement. Les prestataires externes cités ci-dessus ne reçoivent que les informations techniques nécessaires lorsque leurs ressources sont appelées.</p>
        <p>Les données des comptes et des designs privés ne sont ni vendues, ni louées, ni utilisées à des fins publicitaires.</p>

        <h2>Durées de conservation</h2>
        <ul>
            <li><strong>Compte et designs enregistrés&nbsp;:</strong> jusqu’à la suppression du design ou du compte par son titulaire, ou par un administrateur habilité lorsque la gestion du service l’exige. La suppression du compte entraîne celle des designs et des jetons CLI associés.</li>
            <li><strong>Lien de vérification de l’adresse électronique&nbsp;:</strong> validité de 24 heures. Son empreinte peut rester associée au compte jusqu’à sa validation, son remplacement ou la suppression du compte.</li>
            <li><strong>Lien de réinitialisation du mot de passe&nbsp;:</strong> validité d’une heure. Son empreinte est supprimée lors de son utilisation et peut sinon rester associée au compte jusqu’à son remplacement ou la suppression du compte.</li>
            <li><strong>Jeton CLI&nbsp;:</strong> jusqu’à sa révocation ou la suppression du compte. Les informations relatives à un jeton révoqué peuvent rester associées au compte à des fins de sécurité jusqu’à la suppression de celui-ci.</li>
            <li><strong>Design public&nbsp;:</strong> jusqu’à sa dépublication, son retrait du catalogue ou la suppression du design ou du compte.</li>
            <li><strong>Retours sur l’application&nbsp;:</strong> 24 mois au maximum. L’empreinte technique utilisée contre les envois abusifs est effacée après 24 heures.</li>
            <li><strong>Stockage local&nbsp;:</strong> jusqu’à son remplacement ou sa suppression dans le navigateur.</li>
            <li><strong>Journaux techniques&nbsp;:</strong> selon les durées nécessaires à la sécurité et au diagnostic, ainsi que les durées appliquées par l’hébergeur.</li>
        </ul>

        <h2>Sécurité</h2>
        <p>Les mots de passe sont enregistrés sous forme hachée. Les liens de vérification, de réinitialisation et les jetons CLI sont conservés sous forme d’empreintes cryptographiques. Les sessions utilisent des paramètres de protection adaptés et les opérations sensibles font l’objet de contrôles d’origine et d’autorisation.</p>
        <p>Aucun système ne pouvant garantir une sécurité absolue, chaque utilisateur doit choisir un mot de passe robuste, conserver ses jetons CLI secrets et révoquer sans délai tout jeton suspect.</p>

        <h2>Vos droits</h2>
        <p>Selon le traitement concerné et la réglementation applicable, vous pouvez demander l’accès à vos données, leur rectification ou leur effacement, la limitation de leur traitement, leur portabilité, ou vous opposer à certains traitements. Vous pouvez modifier les informations du compte, supprimer vos designs, révoquer vos jetons et supprimer votre compte depuis l’espace personnel.</p>
        <p>Un retour anonyme n’étant pas rattaché à un compte, son identification pour répondre à une demande d’accès ou d’effacement peut être impossible sans indications précises sur sa date, sa page et son contenu. Pour toute demande, utilisez la <a href="https://www.ralentirtravaux.com/contact/contact.php" rel="noopener noreferrer">page de contact</a>. Une vérification raisonnable de l’identité du demandeur peut être nécessaire. Vous pouvez également adresser une réclamation à la <a href="https://www.cnil.fr/" rel="noopener noreferrer">Commission nationale de l’informatique et des libertés (CNIL)</a>.</p>

        <h2>Mise à jour</h2>
        <p>Cette politique peut évoluer avec les fonctionnalités de Learning Designer, les services utilisés ou la réglementation. La date de sa dernière mise à jour est indiquée en haut de la page.</p>
    </article>
</main>
<?php render_site_footer(); ?>
</body>
</html>
