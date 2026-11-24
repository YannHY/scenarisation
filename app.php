<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// La navigation lit la session : elle doit démarrer avant tout HTML.
app_start_session();
$release = app_mac_latest_release();
$macVersion = $release['version'];
$minimumMacOS = preg_replace('/\.0$/', '', $release['minimumSystem']);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Scénarisation pour Mac : concevez vos scénarios pédagogiques hors ligne, synchronisez-les avec votre compte et travaillez à plusieurs.">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title data-site-i18n-en="Mac app | Scénarisation" data-site-i18n-fr="App Mac | Scénarisation">App Mac | Scénarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=<?= hash_file('sha256', __DIR__ . '/css/interface.css') ?>">
    <link rel="stylesheet" href="css/account-ui.css?v=<?= hash_file('sha256', __DIR__ . '/css/account-ui.css') ?>">
    <link rel="stylesheet" href="css/account-pages.css?v=<?= hash_file('sha256', __DIR__ . '/css/account-pages.css') ?>">
    <link rel="stylesheet" href="css/app-mac.css?v=<?= hash_file('sha256', __DIR__ . '/css/app-mac.css') ?>">
<?php render_refined_styles(); ?>
    <script src="js/app-mac.js?v=<?= hash_file('sha256', __DIR__ . '/js/app-mac.js') ?>"></script>
</head>
<body class="mac-app-page">
<?php render_site_nav('mac-app'); ?>
<main class="mac-shell">
    <header class="mac-hero">
        <img class="mac-icon" src="assets/scenarisation-mac-icon.png" alt="" width="160" height="160">
        <p class="mac-kicker" data-site-i18n-en="Scénarisation for Mac" data-site-i18n-fr="Scénarisation pour Mac">Scénarisation pour Mac</p>
        <h1 class="mac-title">
            <span data-site-i18n-en="Design your scenarios," data-site-i18n-fr="Concevez vos scénarios,">Concevez vos scénarios,</span>
            <span class="mac-title-accent" data-site-i18n-en="even offline" data-site-i18n-fr="même hors ligne">même hors ligne</span>
        </h1>
        <p class="mac-lead"
           data-site-i18n-en="A native app to design learning scenarios, sync them with your account, work with colleagues and get help from the AI assistant of your choice."
           data-site-i18n-fr="Une app native pour concevoir vos scénarios pédagogiques, les synchroniser avec votre compte, travailler à plusieurs et vous faire aider par l’assistant IA de votre choix.">Une app native pour concevoir vos scénarios pédagogiques, les synchroniser avec votre compte, travailler à plusieurs et vous faire aider par l’assistant IA de votre choix.</p>
        <div class="mac-actions">
            <a class="btn btn-primary mac-download" href="<?= h($release['url']) ?>">
                <i class="fa-brands fa-apple" aria-hidden="true"></i>
                <span data-site-i18n-en="Download" data-site-i18n-fr="Télécharger">Télécharger</span>
            </a>
            <a class="mac-more" href="#fonctionnalites"><span data-site-i18n-en="See the features" data-site-i18n-fr="Voir les fonctionnalités">Voir les fonctionnalités</span> <i class="fa-solid fa-arrow-down" aria-hidden="true"></i></a>
        </div>
        <p class="mac-requirements" data-site-i18n-en="Version <?= h($macVersion) ?> · macOS <?= h($minimumMacOS) ?> or later · Apple silicon"
           data-site-i18n-fr="Version <?= h($macVersion) ?> · macOS <?= h($minimumMacOS) ?> ou ultérieur · Puce Apple">Version <?= h($macVersion) ?> · macOS <?= h($minimumMacOS) ?> ou ultérieur · Puce Apple</p>
    </header>

    <!-- Fenêtre de l’app reconstituée d’après l’app réelle. Comme une capture d’écran, elle reste en français :
         l’app n’est pas traduite. Le contenu dépasse volontairement le cadre, comme dans une fenêtre qui défile. -->
    <div class="mac-window" aria-hidden="true" lang="fr">
        <svg class="mac-sprite" focusable="false">
            <symbol id="mac-sparkles" viewBox="0 0 24 24">
                <path d="M10 4c.6 5.500 2.500 7.900 8 9-5.500 1.100-7.400 3.500-8 9-.6-5.500-2.500-7.900-8-9 5.500-1.100 7.400-3.500 8-9Z"/>
                <path d="M18.500 2c.3 2.400 1.100 3.200 3.500 3.500-2.400.3-3.200 1.100-3.500 3.500-.3-2.400-1.100-3.200-3.500-3.500 2.400-.3 3.200-1.100 3.500-3.500Z"/>
            </symbol>
        </svg>
        <div class="mac-window-scale">
            <div class="mac-sidebar">
                <div class="mac-sidebar-top">
                    <span class="mac-lights"><i></i><i></i><i></i></span>
                    <span class="mac-round"><i class="mac-sidebar-glyph"></i></span>
                </div>
                <span class="mac-search"><i class="fa-solid fa-magnifying-glass"></i> Rechercher</span>
                <span class="mac-nav-heading">Scénario</span>
                <span class="mac-nav-item is-active"><i class="fa-solid fa-table-columns"></i> Conception</span>
                <span class="mac-nav-item"><i class="fa-solid fa-sliders"></i> Paramètres</span>
                <span class="mac-nav-item"><i class="fa-solid fa-chart-pie"></i> Analyse</span>
                <span class="mac-nav-item"><i class="fa-solid fa-chart-simple"></i> Chronologie</span>
                <span class="mac-nav-item"><svg class="mac-svg"><use href="#mac-sparkles"/></svg> Assistant IA</span>
                <span class="mac-nav-heading">Moments</span>
                <span class="mac-nav-moment type-investigate"><b>1</b> <span>Séance 1 — Abonnement ou pas&nbsp;?</span> <em>55 min</em></span>
                <span class="mac-nav-moment type-practice"><b>2</b> <span>Séance 2 — Représenter, calculer</span> <em>55 min</em></span>
                <span class="mac-nav-moment type-collaborate"><b>3</b> <span>Séance 3 — Modéliser l’empreinte</span> <em>55 min</em></span>
                <span class="mac-nav-moment type-practice"><b>4</b> <span>Séance 4 — Esprit critique</span> <em>55 min</em></span>
                <span class="mac-add-moment"><i class="fa-solid fa-plus"></i> Ajouter un moment</span>
            </div>
            <div class="mac-main">
                <div class="mac-toolbar">
                    <span class="mac-lights"><i></i><i></i><i></i></span>
                    <span class="mac-round"><i class="fa-solid fa-house"></i></span>
                    <span class="mac-doc">
                        <strong>Fonctions affines - coûts et empreinte carbone de nos déplacements.json</strong>
                        <span>220 min · 13 activités — Modifié</span>
                    </span>
                    <span class="mac-tool-group mac-tool-layout">
                        <i class="fa-solid fa-list-ul"></i>
                        <i class="fa-solid fa-table-columns is-selected"></i>
                        <i class="fa-solid fa-table-cells"></i>
                    </span>
                    <span class="mac-tool-group">
                        <i class="fa-regular fa-eye is-on"></i>
                        <i class="fa-solid fa-layer-group"></i>
                        <i class="fa-solid fa-download"></i>
                        <i class="is-on"><svg class="mac-svg"><use href="#mac-sparkles"/></svg></i>
                        <i class="fa-solid fa-arrow-up-from-bracket"></i>
                        <i class="fa-solid fa-cloud"></i>
                        <i class="fa-regular fa-circle-user"></i>
                    </span>
                </div>
                <div class="mac-content">
                    <div class="mac-canvas">
                        <strong class="mac-scenario-title">Fonctions affines&nbsp;: coûts et empreinte carbone de nos déplacements</strong>
                        <span class="mac-scenario-meta">
                            <span><i class="fa-regular fa-clock"></i> 220 min</span>
                            <span><i class="fa-regular fa-clone"></i> 4 moments</span>
                            <span><i class="fa-solid fa-list-ul"></i> 13 activités</span>
                        </span>
                        <span class="mac-strip mac-strip-scenario">
                            <i class="type-practice mac-dur-10"></i><i class="type-investigate mac-dur-20"></i><i class="type-discuss mac-dur-15"></i><i class="type-read mac-dur-10"></i>
                            <i class="type-investigate mac-dur-15"></i><i class="type-read mac-dur-10"></i><i class="type-practice mac-dur-30"></i>
                            <i class="type-read mac-dur-10"></i><i class="type-collaborate mac-dur-30"></i><i class="type-discuss mac-dur-15"></i>
                            <i class="type-investigate mac-dur-15"></i><i class="type-practice mac-dur-30"></i><i class="type-produce mac-dur-10"></i>
                        </span>
                        <div class="mac-board">
                            <div class="mac-moment">
                                <span class="mac-strip"><i class="type-practice mac-dur-10"></i><i class="type-investigate mac-dur-20"></i><i class="type-discuss mac-dur-15"></i><i class="type-read mac-dur-10"></i></span>
                                <span class="mac-moment-label"><i class="fa-solid fa-grip-lines"></i> Moment <i class="fa-solid fa-ellipsis"></i></span>
                                <strong class="mac-moment-title">Séance 1 — Abonnement ou pas&nbsp;? Découvrir les fonctions affines</strong>
                                <span class="mac-label">Objectifs du moment <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                <p class="mac-field">Faire émerger la notion de fonction affine à partir d’une comparaison de tarifs&nbsp;; relier tableau de valeurs, expression algébrique et graphique&nbsp;; interpréter le point d’intersection de deux droites.</p>
                                <span class="mac-label">Intentions pédagogiques <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                <p class="mac-field">Un diagnostic rapide permet de former des groupes hétérogènes et de repérer les élèves à accompagner. La situation-problème, proche du quotidien des élèves, donne du sens à <i class="mac-math">b</i> (montant fixe) et à <i class="mac-math">a</i> (montant par trajet) avant toute définition.</p>
                                <span class="mac-label mac-label-strong">Activités <em><i class="fa-regular fa-clock"></i> 55 min</em></span>
                                <div class="mac-act type-practice">
                                    <div class="mac-act-bar">
                                        <i class="fa-solid fa-grip-lines mac-grip"></i><i class="fa-solid fa-dumbbell"></i>
                                        <span class="mac-dur"><i class="fa-regular fa-clock"></i> <b>10</b> min</span>
                                        <i class="fa-regular fa-user"></i><i class="fa-solid fa-chalkboard-user"></i><i class="fa-regular fa-clock"></i><i class="fa-solid fa-building-columns"></i><i class="fa-solid fa-magnifying-glass"></i><b>1</b>
                                        <span class="mac-cap"><i class="fa-solid fa-graduation-cap"></i></span>
                                        <i class="fa-solid fa-ellipsis mac-menu"></i>
                                    </div>
                                    <div class="mac-act-body">
                                        <span class="mac-tags"><span><i class="fa-solid fa-graduation-cap"></i> Socle commun D1.3.1 <i class="fa-solid fa-xmark"></i></span><span><i class="fa-solid fa-graduation-cap"></i> Socle commun D4.1.10 <i class="fa-solid fa-xmark"></i></span></span>
                                        <span class="mac-label">Description de l’activité <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                        <p class="mac-field">Questions flash sur ardoise pour vérifier les prérequis&nbsp;: proportionnalité, fonction linéaire, lecture graphique et calcul littéral.</p>
                                        <span class="mac-label">Consignes pour les élèves <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                        <div class="mac-field">
                                            <p>Répondez sur votre ardoise, sans calculatrice, en une minute par question.</p>
                                            <ol>
                                                <li>Soit <i class="mac-math">f(x) = 3x</i>. Calculez <i class="mac-math">f(−4)</i>.</li>
                                                <li>Calculez <i class="mac-math">2x + 5</i> pour <i class="mac-math">x = −3</i>.</li>
                                                <li>Sur le graphique projeté, lisez l’image de <i class="mac-math">2</i>.</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                                <div class="mac-act type-investigate">
                                    <div class="mac-act-bar">
                                        <i class="fa-solid fa-grip-lines mac-grip"></i><i class="fa-solid fa-magnifying-glass"></i>
                                        <span class="mac-dur"><i class="fa-regular fa-clock"></i> <b>20</b> min</span>
                                        <i class="fa-solid fa-user-group"></i><i class="fa-solid fa-bezier-curve"></i><i class="fa-regular fa-clock"></i><i class="fa-solid fa-building-columns"></i><i class="fa-regular fa-pen-to-square"></i><b>1</b>
                                        <span class="mac-cap"><i class="fa-solid fa-graduation-cap"></i></span>
                                        <i class="fa-solid fa-ellipsis mac-menu"></i>
                                    </div>
                                    <div class="mac-act-body">
                                        <span class="mac-tags"><span><i class="fa-solid fa-graduation-cap"></i> Socle commun D2.1.6 <i class="fa-solid fa-xmark"></i></span><span><i class="fa-solid fa-graduation-cap"></i> Socle commun D4.1.6 <i class="fa-solid fa-xmark"></i></span></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mac-moment">
                                <span class="mac-strip"><i class="type-investigate mac-dur-15"></i><i class="type-read mac-dur-10"></i><i class="type-practice mac-dur-30"></i></span>
                                <span class="mac-moment-label"><i class="fa-solid fa-grip-lines"></i> Moment <i class="fa-solid fa-ellipsis"></i></span>
                                <strong class="mac-moment-title">Séance 2 — Représenter, calculer, déterminer</strong>
                                <span class="mac-label">Objectifs du moment <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                <p class="mac-field">Comprendre l’influence de <i class="mac-math">a</i> et <i class="mac-math">b</i> sur la droite&nbsp;; calculer des images et des antécédents&nbsp;; déterminer une fonction affine à partir de deux points.</p>
                                <span class="mac-label">Intentions pédagogiques <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                <p class="mac-field">L’exploration dynamique avec GeoGebra fait formuler des conjectures avant la méthode. Le plan de travail asynchrone, organisé en trois parcours, permet à chacun de progresser à son rythme.</p>
                                <span class="mac-label mac-label-strong">Activités <em><i class="fa-regular fa-clock"></i> 55 min</em></span>
                                <div class="mac-act type-investigate">
                                    <div class="mac-act-bar">
                                        <i class="fa-solid fa-grip-lines mac-grip"></i><i class="fa-solid fa-magnifying-glass"></i>
                                        <span class="mac-dur"><i class="fa-regular fa-clock"></i> <b>15</b> min</span>
                                        <i class="fa-solid fa-user-group"></i><i class="fa-solid fa-bezier-curve"></i><i class="fa-regular fa-clock"></i><i class="fa-solid fa-building-columns"></i><i class="fa-regular fa-pen-to-square"></i><b>1</b>
                                        <span class="mac-cap"><i class="fa-solid fa-graduation-cap"></i></span>
                                        <i class="fa-solid fa-ellipsis mac-menu"></i>
                                    </div>
                                    <div class="mac-act-body">
                                        <span class="mac-tags"><span><i class="fa-solid fa-graduation-cap"></i> Socle commun D4.1.4 <i class="fa-solid fa-xmark"></i></span><span><i class="fa-solid fa-graduation-cap"></i> Socle commun D4.1.5 <i class="fa-solid fa-xmark"></i></span></span>
                                        <span class="mac-label">Description de l’activité <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                        <div class="mac-field">
                                            <p>Exploration dynamique avec GeoGebra&nbsp;: en binômes, les élèves font varier les paramètres <i class="mac-math">a</i> et <i class="mac-math">b</i> de la fonction <i class="mac-math">f(x) = ax + b</i> à l’aide de curseurs et formulent des conjectures sur leur effet.</p>
                                            <p class="mac-link">GeoGebra Classic</p>
                                        </div>
                                        <span class="mac-label">Consignes pour les élèves <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                        <div class="mac-field">
                                            <p>Dans GeoGebra, créez deux curseurs <code>a</code> et <code>b</code>, puis saisissez <code>f(x) = a*x + b</code>.</p>
                                            <ol>
                                                <li>Faites varier <i class="mac-math">a</i>. Que se passe-t-il quand <i class="mac-math">a &gt; 0</i>, quand <i class="mac-math">a &lt; 0</i>&nbsp;?</li>
                                                <li>Faites varier <i class="mac-math">b</i>. En quel point la droite coupe-t-elle l’axe des ordonnées&nbsp;?</li>
                                                <li>Rédigez trois conjectures.</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mac-moment">
                                <span class="mac-strip"><i class="type-read mac-dur-10"></i><i class="type-collaborate mac-dur-30"></i><i class="type-discuss mac-dur-15"></i></span>
                                <span class="mac-moment-label"><i class="fa-solid fa-grip-lines"></i> Moment <i class="fa-solid fa-ellipsis"></i></span>
                                <strong class="mac-moment-title">Séance 3 — Modéliser l’empreinte carbone de nos déplacements</strong>
                                <span class="mac-label">Objectifs du moment <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                <p class="mac-field">Utiliser une fonction affine pour modéliser une situation réelle&nbsp;; traiter des données au tableur&nbsp;; interpréter un seuil dans un contexte de développement durable.</p>
                                <span class="mac-label">Intentions pédagogiques <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                <p class="mac-field">Le réinvestissement se fait dans un contexte d’éducation au développement durable&nbsp;: les émissions d’un véhicule sur sa durée de vie comprennent une part fixe (fabrication) et une part proportionnelle à la distance parcourue (usage), ce qui conduit naturellement au modèle affine.</p>
                                <span class="mac-label mac-label-strong">Activités <em><i class="fa-regular fa-clock"></i> 55 min</em></span>
                                <div class="mac-act type-read">
                                    <div class="mac-act-bar">
                                        <i class="fa-solid fa-grip-lines mac-grip"></i><i class="fa-solid fa-book-open"></i>
                                        <span class="mac-dur"><i class="fa-regular fa-clock"></i> <b>10</b> min</span>
                                        <i class="fa-solid fa-users"></i><i class="fa-solid fa-chalkboard-user"></i><i class="fa-regular fa-clock"></i><i class="fa-solid fa-building-columns"></i><i class="fa-regular fa-circle"></i><b>1</b>
                                        <span class="mac-cap"><i class="fa-solid fa-graduation-cap"></i></span>
                                        <i class="fa-solid fa-ellipsis mac-menu"></i>
                                    </div>
                                    <div class="mac-act-body">
                                        <span class="mac-tags"><span><i class="fa-solid fa-graduation-cap"></i> GreenComp GC2.1.K03 <i class="fa-solid fa-xmark"></i></span><span><i class="fa-solid fa-graduation-cap"></i> Socle commun D4.3.2 <i class="fa-solid fa-xmark"></i></span></span>
                                        <span class="mac-label">Description de l’activité <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                        <div class="mac-field">
                                            <p>Présentation du contexte&nbsp;: l’empreinte carbone d’un véhicule se calcule sur tout son cycle de vie. Les émissions totales sont donc modélisées par une fonction affine de la distance <i class="mac-math">d</i>&nbsp;:</p>
                                            <p class="mac-formula"><i class="mac-math">E(d) = a d + b</i></p>
                                            <p class="mac-link">Impact CO2 (ADEME)</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mac-act type-collaborate mac-act-live">
                                    <span class="mac-act-author">Marie</span>
                                    <div class="mac-act-bar">
                                        <i class="fa-solid fa-grip-lines mac-grip"></i><i class="fa-solid fa-users"></i>
                                        <span class="mac-dur"><i class="fa-regular fa-clock"></i> <b>30</b> min</span>
                                        <i class="fa-solid fa-user-group"></i><i class="fa-solid fa-child-reaching"></i><i class="fa-regular fa-clock"></i><i class="fa-solid fa-building-columns"></i><i class="fa-regular fa-pen-to-square"></i><b>1</b>
                                        <span class="mac-cap"><i class="fa-solid fa-graduation-cap"></i></span>
                                        <i class="fa-solid fa-ellipsis mac-menu"></i>
                                    </div>
                                    <div class="mac-act-body">
                                        <span class="mac-tags"><span><i class="fa-solid fa-graduation-cap"></i> CRCN 1.3.N3.1 <i class="fa-solid fa-xmark"></i></span><span><i class="fa-solid fa-graduation-cap"></i> CRCN 1.3.N3.2 <i class="fa-solid fa-xmark"></i></span><span><i class="fa-solid fa-graduation-cap"></i> GreenComp GC2.1.S04 <i class="fa-solid fa-xmark"></i></span></span>
                                        <span class="mac-label">Description de l’activité <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                        <p class="mac-field">Modélisation collaborative au tableur partagé&nbsp;: chaque groupe compare deux modes de déplacement de la fiche de données et cherche à partir de quelle distance l’un devient moins émetteur que l’autre.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mac-moment">
                                <span class="mac-strip"><i class="type-investigate mac-dur-15"></i><i class="type-practice mac-dur-30"></i><i class="type-produce mac-dur-10"></i></span>
                                <span class="mac-moment-label"><i class="fa-solid fa-grip-lines"></i> Moment <i class="fa-solid fa-ellipsis"></i></span>
                                <strong class="mac-moment-title">Séance 4 — Esprit critique, évaluation et bilan</strong>
                                <span class="mac-label">Objectifs du moment <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                <p class="mac-field">Vérifier et corriger un raisonnement&nbsp;; mobiliser seul les acquis de la semaine&nbsp;; s’autoévaluer.</p>
                                <span class="mac-label">Intentions pédagogiques <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                <p class="mac-field">La correction d’une solution d’IA erronée réactive la méthode de la séance 2 tout en exerçant l’esprit critique. L’évaluation sommative individuelle se fait sans IA.</p>
                                <span class="mac-label mac-label-strong">Activités <em><i class="fa-regular fa-clock"></i> 55 min</em></span>
                                <div class="mac-act type-investigate">
                                    <div class="mac-act-bar">
                                        <i class="fa-solid fa-grip-lines mac-grip"></i><i class="fa-solid fa-magnifying-glass"></i>
                                        <span class="mac-dur"><i class="fa-regular fa-clock"></i> <b>15</b> min</span>
                                        <i class="fa-solid fa-user-group"></i><i class="fa-solid fa-bezier-curve"></i><i class="fa-regular fa-clock"></i><i class="fa-solid fa-building-columns"></i><i class="fa-regular fa-pen-to-square"></i><b>3</b>
                                        <span class="mac-cap"><i class="fa-solid fa-graduation-cap"></i></span>
                                        <i class="fa-solid fa-ellipsis mac-menu"></i>
                                    </div>
                                    <div class="mac-act-body">
                                        <span class="mac-tags"><span><i class="fa-solid fa-graduation-cap"></i> Pix IA 2.3 <i class="fa-solid fa-xmark"></i></span><span><i class="fa-solid fa-graduation-cap"></i> Socle commun D3.3.5 <i class="fa-solid fa-xmark"></i></span></span>
                                        <span class="mac-label">Description de l’activité <i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                        <p class="mac-field">«&nbsp;Corriger l’IA&nbsp;»&nbsp;: en binômes, les élèves analysent la réponse d’une IA générative à un exercice et repèrent l’erreur qu’elle contient.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mac-inspector">
                        <div class="mac-inspector-head">
                            <span class="mac-assistant-icon"><svg class="mac-svg"><use href="#mac-sparkles"/></svg></span>
                            <span class="mac-doc">
                                <strong>Assistant</strong>
                                <span>ChatGPT · GPT-6.1-Sol <i class="fa-solid fa-chevron-down"></i></span>
                            </span>
                            <i class="fa-solid fa-plus"></i>
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <strong class="mac-inspector-title">Construisons votre scénario.</strong>
                        <p class="mac-inspector-intro">Décrivez le public, les objectifs et le temps disponible. Vous pouvez aussi demander une adaptation du scénario ouvert.</p>
                        <span class="mac-suggestion"><i class="fa-regular fa-file-lines"></i> Crée un scénario pédagogique complet à partir de mes documents</span>
                        <span class="mac-suggestion"><svg class="mac-svg"><use href="#mac-sparkles"/></svg> Construire une séance de 55 minutes</span>
                        <span class="mac-suggestion"><svg class="mac-svg"><use href="#mac-sparkles"/></svg> Ajouter une évaluation formative</span>
                        <span class="mac-suggestion"><svg class="mac-svg"><use href="#mac-sparkles"/></svg> Clarifier les consignes</span>
                        <span class="mac-context-link"><i class="fa-regular fa-address-card"></i> Enregistrer mon contexte d’enseignement (matière, niveau, système scolaire…)</span>
                        <span class="mac-composer"><i class="fa-solid fa-plus"></i> <span>Décrivez ce que vous souhaitez…</span> <i class="fa-solid fa-arrow-up mac-send"></i></span>
                        <span class="mac-inspector-foot"><span>Abonnement ChatGPT utilisé</span> <b>Gérer l’utilisation</b></span>
                        <span class="mac-inspector-foot">Scénario, échanges et documents joints · transmis à ChatGPT.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ce que l’app embarque : chiffres repris de ses catalogues. -->
    <section class="mac-section mac-builtin" aria-labelledby="mac-builtin-title">
        <h2 id="mac-builtin-title" class="mac-builtin-title" data-site-i18n-en="Included in the app" data-site-i18n-fr="Inclus dans l’app">Inclus dans l’app</h2>
        <dl class="mac-stats">
            <div class="mac-stat">
                <dt data-site-i18n-en="competencies" data-site-i18n-fr="compétences">compétences</dt>
                <dd>1&nbsp;198</dd>
            </div>
            <div class="mac-stat">
                <dt data-site-i18n-en="competency frameworks" data-site-i18n-fr="référentiels">référentiels</dt>
                <dd>8</dd>
            </div>
            <div class="mac-stat">
                <dt data-site-i18n-en="ready-to-use templates" data-site-i18n-fr="modèles prêts à l’emploi">modèles prêts à l’emploi</dt>
                <dd>28</dd>
            </div>
            <div class="mac-stat">
                <dt data-site-i18n-en="school systems" data-site-i18n-fr="systèmes scolaires">systèmes scolaires</dt>
                <dd>13</dd>
            </div>
        </dl>
        <p class="mac-builtin-note"
           data-site-i18n-en="Florimont, French Common Core, GreenComp, DigComp 3.0, PER romand, CRCN, Pix and Pix AI, Bloom’s taxonomy and the full help: nothing to download, no server needed to design."
           data-site-i18n-fr="Florimont, Socle commun, GreenComp, DigComp 3.0, PER romand, CRCN, Pix et Pix IA, la taxonomie de Bloom et l’aide complète&nbsp;: rien à télécharger, aucun serveur nécessaire pour concevoir.">Florimont, Socle commun, GreenComp, DigComp 3.0, PER romand, CRCN, Pix et Pix IA, la taxonomie de Bloom et l’aide complète&nbsp;: rien à télécharger, aucun serveur nécessaire pour concevoir.</p>
    </section>

    <section id="fonctionnalites" class="mac-section" aria-labelledby="mac-features-title">
        <h2 id="mac-features-title" class="mac-section-title" data-site-i18n-en="Features" data-site-i18n-fr="Fonctionnalités">Fonctionnalités</h2>

        <article class="mac-showcase">
            <div class="mac-showcase-text">
                <p class="mac-eyebrow" data-site-i18n-en="Design and analyse" data-site-i18n-fr="Concevoir et analyser">Concevoir et analyser</p>
                <h3 data-site-i18n-en="Analyse your scenario" data-site-i18n-fr="Analysez votre scénario">Analysez votre scénario</h3>
                <p data-site-i18n-en="Each moment gathers its objectives, activities and notes. The analysis follows your durations and shows, at a glance, what your students will actually do."
                   data-site-i18n-fr="Chaque moment réunit ses objectifs, ses activités et ses notes. L’analyse suit vos durées et montre, d’un coup d’œil, ce que vos élèves feront réellement.">Chaque moment réunit ses objectifs, ses activités et ses notes. L’analyse suit vos durées et montre, d’un coup d’œil, ce que vos élèves feront réellement.</p>
                <ul class="mac-points">
                    <li data-site-i18n-en="Moments as a list, columns or a grid (⌘1, ⌘2, ⌘3)" data-site-i18n-fr="Moments en liste, en colonnes ou en grille (⌘1, ⌘2, ⌘3)">Moments en liste, en colonnes ou en grille (⌘1, ⌘2, ⌘3)</li>
                    <li data-site-i18n-en="Seven breakdowns: learning types, modalities, assessment, groups, place of AI…" data-site-i18n-fr="Sept répartitions&nbsp;: types d’apprentissage, modalités, évaluation, groupes, place de l’IA…">Sept répartitions&nbsp;: types d’apprentissage, modalités, évaluation, groupes, place de l’IA…</li>
                    <li data-site-i18n-en="A configurable timeline, and every change can be undone" data-site-i18n-fr="Une chronologie configurable, et chaque modification peut être annulée">Une chronologie configurable, et chaque modification peut être annulée</li>
                </ul>
            </div>
            <div class="mac-visual mac-visual-analysis" aria-hidden="true">
                <span class="mac-visual-label" data-site-i18n-en="Learning types · 220 min" data-site-i18n-fr="Types d’apprentissage · 220 min">Types d’apprentissage · 220 min</span>
                <div class="mac-analysis">
                    <span class="mac-donut"></span>
                    <ul class="mac-legend">
                        <li class="type-practice"><span data-site-i18n-en="Practice" data-site-i18n-fr="Pratiquer">Pratiquer</span><em>70 min</em></li>
                        <li class="type-investigate"><span data-site-i18n-en="Investigate" data-site-i18n-fr="Investiguer">Investiguer</span><em>50 min</em></li>
                        <li class="type-read"><span data-site-i18n-en="Read / Watch / Listen" data-site-i18n-fr="Lire / Regarder / Écouter">Lire / Regarder / Écouter</span><em>30 min</em></li>
                        <li class="type-discuss"><span data-site-i18n-en="Discuss" data-site-i18n-fr="Discuter">Discuter</span><em>30 min</em></li>
                        <li class="type-collaborate"><span data-site-i18n-en="Collaborate" data-site-i18n-fr="Collaborer">Collaborer</span><em>30 min</em></li>
                        <li class="type-produce"><span data-site-i18n-en="Produce" data-site-i18n-fr="Produire">Produire</span><em>10 min</em></li>
                    </ul>
                </div>
                <span class="mac-visual-label" data-site-i18n-en="Timeline" data-site-i18n-fr="Chronologie">Chronologie</span>
                <div class="mac-timeline">
                    <span data-site-i18n-en="Session 1" data-site-i18n-fr="Séance 1">Séance 1</span>
                    <span class="mac-timeline-bar"><i class="type-practice mac-dur-10">PRA 10m</i><i class="type-investigate mac-dur-20">INV 20m</i><i class="type-discuss mac-dur-15">DIS 15m</i><i class="type-read mac-dur-10">LIR 10m</i></span>
                    <span data-site-i18n-en="Session 2" data-site-i18n-fr="Séance 2">Séance 2</span>
                    <span class="mac-timeline-bar"><i class="type-investigate mac-dur-15">INV 15m</i><i class="type-read mac-dur-10">LIR 10m</i><i class="type-practice mac-dur-30">PRA 30m</i></span>
                    <span data-site-i18n-en="Session 3" data-site-i18n-fr="Séance 3">Séance 3</span>
                    <span class="mac-timeline-bar"><i class="type-read mac-dur-10">LIR 10m</i><i class="type-collaborate mac-dur-30">COL 30m</i><i class="type-discuss mac-dur-15">DIS 15m</i></span>
                    <span data-site-i18n-en="Session 4" data-site-i18n-fr="Séance 4">Séance 4</span>
                    <span class="mac-timeline-bar"><i class="type-investigate mac-dur-15">INV 15m</i><i class="type-practice mac-dur-30">PRA 30m</i><i class="type-produce mac-dur-10">PRO 10m</i></span>
                </div>
            </div>
        </article>

        <article class="mac-showcase is-reversed">
            <div class="mac-showcase-text">
                <p class="mac-eyebrow" data-site-i18n-en="Sync and collaborate" data-site-i18n-fr="Synchroniser et collaborer">Synchroniser et collaborer</p>
                <h3 data-site-i18n-en="Work together" data-site-i18n-fr="Travaillez à plusieurs">Travaillez à plusieurs</h3>
                <p data-site-i18n-en="Sign in with your Scénarisation account: your scenarios follow you between the app and the site. Invite colleagues and prepare together, each on their own device."
                   data-site-i18n-fr="Connectez-vous avec votre compte Scénarisation&nbsp;: vos scénarios vous suivent entre l’app et le site. Invitez des collègues et préparez ensemble, chacun sur son appareil.">Connectez-vous avec votre compte Scénarisation&nbsp;: vos scénarios vous suivent entre l’app et le site. Invitez des collègues et préparez ensemble, chacun sur son appareil.</p>
                <ul class="mac-points">
                    <li data-site-i18n-en="Automatic sync; offline changes are sent as soon as you are back online" data-site-i18n-fr="Synchronisation automatique&nbsp;; les modifications faites hors connexion partent dès le retour du réseau">Synchronisation automatique&nbsp;; les modifications faites hors connexion partent dès le retour du réseau</li>
                    <li data-site-i18n-en="Invite colleagues to edit or simply view a scenario" data-site-i18n-fr="Invitation de collègues, pour modifier ou simplement consulter">Invitation de collègues, pour modifier ou simplement consulter</li>
                    <li data-site-i18n-en="Changes appear within seconds and are merged field by field, from the app or the site" data-site-i18n-fr="Modifications visibles en quelques secondes et fusionnées champ par champ, depuis l’app comme depuis le site">Modifications visibles en quelques secondes et fusionnées champ par champ, depuis l’app comme depuis le site</li>
                </ul>
            </div>
            <div class="mac-visual mac-visual-share" aria-hidden="true">
                <span class="mac-visual-label" data-site-i18n-en="Teachers" data-site-i18n-fr="Enseignants">Enseignants</span>
                <ul class="mac-people">
                    <li>
                        <b class="mac-avatar is-ink"><i class="fa-solid fa-user"></i></b>
                        <span class="mac-person"><strong data-site-i18n-en="You" data-site-i18n-fr="Vous">Vous</strong><span data-site-i18n-en="On this Mac" data-site-i18n-fr="Sur ce Mac">Sur ce Mac</span></span>
                        <em data-site-i18n-en="Owner" data-site-i18n-fr="Propriétaire">Propriétaire</em>
                    </li>
                    <li>
                        <b class="mac-avatar is-coral">ML</b>
                        <span class="mac-person"><strong>Marie L.</strong><span class="is-live" data-site-i18n-en="Present · Session 3" data-site-i18n-fr="Présente · Séance 3">Présente · Séance 3</span></span>
                        <em data-site-i18n-en="Can edit" data-site-i18n-fr="Peut modifier">Peut modifier</em>
                    </li>
                    <li>
                        <b class="mac-avatar is-teal">KB</b>
                        <span class="mac-person"><strong>Karim B.</strong><span class="is-live" data-site-i18n-en="Present · on the site" data-site-i18n-fr="Présent · sur le site">Présent · sur le site</span></span>
                        <em data-site-i18n-en="Can view" data-site-i18n-fr="Peut consulter">Peut consulter</em>
                    </li>
                </ul>
                <div class="mac-act type-collaborate is-remote">
                    <span class="mac-act-author" data-site-i18n-en="Marie is writing…" data-site-i18n-fr="Marie écrit…">Marie écrit…</span>
                    <div class="mac-act-bar">
                        <i class="fa-solid fa-grip-lines mac-grip"></i><i class="fa-solid fa-users"></i>
                        <span class="mac-dur"><i class="fa-regular fa-clock"></i> <b>30</b> min</span>
                        <i class="fa-solid fa-user-group"></i><i class="fa-regular fa-clock"></i><i class="fa-solid fa-building-columns"></i><i class="fa-regular fa-pen-to-square"></i>
                        <i class="fa-solid fa-ellipsis mac-menu"></i>
                    </div>
                    <div class="mac-act-body">
                        <p class="mac-field" data-site-i18n-en="Collaborative modelling in a shared spreadsheet: each group compares two modes of transport." data-site-i18n-fr="Modélisation collaborative au tableur partagé&nbsp;: chaque groupe compare deux modes de déplacement.">Modélisation collaborative au tableur partagé&nbsp;: chaque groupe compare deux modes de déplacement.</p>
                    </div>
                </div>
                <span class="mac-sync-line"><i class="fa-solid fa-circle-check"></i> <span data-site-i18n-en="Synced with scenarisation.eu" data-site-i18n-fr="Synchronisé avec scenarisation.eu">Synchronisé avec scenarisation.eu</span></span>
            </div>
        </article>

        <article class="mac-showcase">
            <div class="mac-showcase-text">
                <p class="mac-eyebrow" data-site-i18n-en="AI assistant" data-site-i18n-fr="Assistant IA">Assistant IA</p>
                <h3 data-site-i18n-en="Use the AI of your choice" data-site-i18n-fr="Utilisez l’IA de votre choix">Utilisez l’IA de votre choix</h3>
                <p data-site-i18n-en="Connect the service of your choice: the assistant builds a scenario with you, improves a field or explains a teaching choice. Nothing changes without your approval."
                   data-site-i18n-fr="Connectez le service de votre choix&nbsp;: l’assistant construit un scénario avec vous, améliore un champ ou explique un choix pédagogique. Rien ne change sans votre accord.">Connectez le service de votre choix&nbsp;: l’assistant construit un scénario avec vous, améliore un champ ou explique un choix pédagogique. Rien ne change sans votre accord.</p>
                <ul class="mac-points">
                    <li data-site-i18n-en="Create with AI: a short framing dialogue, then a complete scenario" data-site-i18n-fr="Créer avec l’IA&nbsp;: un court dialogue de cadrage, puis un scénario complet">Créer avec l’IA&nbsp;: un court dialogue de cadrage, puis un scénario complet</li>
                    <li data-site-i18n-en="AI help on a single field: rephrase, shorten, clarify" data-site-i18n-fr="Aide IA sur un seul champ&nbsp;: reformuler, raccourcir, préciser">Aide IA sur un seul champ&nbsp;: reformuler, raccourcir, préciser</li>
                    <li data-site-i18n-en="Preview every proposal before applying it; it can always be undone" data-site-i18n-fr="Aperçu de chaque proposition avant de l’appliquer&nbsp;; toujours annulable">Aperçu de chaque proposition avant de l’appliquer&nbsp;; toujours annulable</li>
                </ul>
            </div>
            <div class="mac-visual mac-visual-ai" aria-hidden="true">
                <div class="mac-provider-group">
                    <span class="mac-visual-label" data-site-i18n-en="With your subscription" data-site-i18n-fr="Avec votre abonnement">Avec votre abonnement</span>
                    <span class="mac-providers"><span>ChatGPT</span><span>Claude</span><span>Gemini</span><span>Grok</span></span>
                </div>
                <div class="mac-provider-group">
                    <span class="mac-visual-label" data-site-i18n-en="On this Mac" data-site-i18n-fr="Sur ce Mac">Sur ce Mac</span>
                    <span class="mac-providers"><span>Ollama</span><span>LM Studio</span></span>
                </div>
                <div class="mac-provider-group">
                    <span class="mac-visual-label" data-site-i18n-en="With an API key" data-site-i18n-fr="Avec une clé API">Avec une clé API</span>
                    <span class="mac-providers"><span>OpenAI</span><span>Mistral</span><span data-site-i18n-en="Another compatible service" data-site-i18n-fr="Autre service compatible">Autre service compatible</span></span>
                </div>
                <div class="mac-field-help">
                    <span class="mac-field-help-title"><i class="fa-solid fa-wand-magic-sparkles"></i> <span data-site-i18n-en="AI help · Instructions for students" data-site-i18n-fr="Aide IA · Consignes pour les élèves">Aide IA · Consignes pour les élèves</span></span>
                    <span class="mac-field-help-text" data-site-i18n-en="Find the false step in the AI’s answer and explain the error in one sentence." data-site-i18n-fr="Repérez l’étape fausse dans la réponse de l’IA et expliquez l’erreur en une phrase.">Repérez l’étape fausse dans la réponse de l’IA et expliquez l’erreur en une phrase.</span>
                    <span class="mac-proposal-actions">
                        <span class="mac-pill" data-site-i18n-en="Rephrase" data-site-i18n-fr="Reformuler"
                              data-mac-result-en="Find the step where the AI’s answer goes wrong, then justify the error in one sentence."
                              data-mac-result-fr="Trouvez l’étape où la réponse de l’IA se trompe, puis justifiez l’erreur en une phrase.">Reformuler</span>
                        <span class="mac-pill" data-site-i18n-en="Shorten" data-site-i18n-fr="Raccourcir"
                              data-mac-result-en="Spot the AI’s error and explain it in one sentence."
                              data-mac-result-fr="Repérez l’erreur de l’IA et expliquez-la en une phrase.">Raccourcir</span>
                        <span class="mac-pill" data-site-i18n-en="Clarify" data-site-i18n-fr="Préciser"
                              data-mac-result-en="Find the false step in the AI’s answer, quote the line concerned and explain the error in one sentence."
                              data-mac-result-fr="Repérez l’étape fausse dans la réponse de l’IA, citez la ligne concernée et expliquez l’erreur en une phrase.">Préciser</span>
                        <span class="mac-pill is-strong" data-site-i18n-en="Replace" data-site-i18n-fr="Remplacer">Remplacer</span>
                    </span>
                </div>
            </div>
        </article>
    </section>

    <section class="mac-section" aria-labelledby="mac-more-title">
        <h2 id="mac-more-title" class="mac-section-title" data-site-i18n-en="Other features" data-site-i18n-fr="Autres fonctionnalités">Autres fonctionnalités</h2>
        <div class="mac-grid">
            <article class="mac-feature">
                <span class="mac-feature-icon" aria-hidden="true"><i class="fa-solid fa-table-columns"></i></span>
                <h3 data-site-i18n-en="A native app" data-site-i18n-fr="Une app native">Une app native</h3>
                <p data-site-i18n-en="Sidebar, toolbar and inspector follow macOS conventions, with Liquid Glass on macOS 26 and your light or dark appearance."
                   data-site-i18n-fr="Barre latérale, barre d’outils et inspecteur suivent les codes de macOS, avec Liquid Glass sur macOS 26 et votre apparence claire ou sombre.">Barre latérale, barre d’outils et inspecteur suivent les codes de macOS, avec Liquid Glass sur macOS 26 et votre apparence claire ou sombre.</p>
            </article>
            <article class="mac-feature">
                <span class="mac-feature-icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
                <h3 data-site-i18n-en="28 templates" data-site-i18n-fr="28 modèles">28 modèles</h3>
                <p data-site-i18n-en="The site’s library, in eight families, with search, filters and a preview before importing."
                   data-site-i18n-fr="La bibliothèque du site, en huit familles, avec recherche, filtres et aperçu avant import.">La bibliothèque du site, en huit familles, avec recherche, filtres et aperçu avant import.</p>
            </article>
            <article class="mac-feature">
                <span class="mac-feature-icon" aria-hidden="true"><i class="fa-solid fa-file-export"></i></span>
                <h3 data-site-i18n-en="Import and export" data-site-i18n-fr="Importer et exporter">Importer et exporter</h3>
                <p data-site-i18n-en="Open JSON, Markdown, CSV, Excel, HTML, Word and LDJ files. Export to JSON, Markdown, HTML, Word, Excel or CSV, in a teacher or student version."
                   data-site-i18n-fr="Ouvrez des fichiers JSON, Markdown, CSV, Excel, HTML, Word et LDJ. Exportez en JSON, Markdown, HTML, Word, Excel ou CSV, en version enseignant ou élève.">Ouvrez des fichiers JSON, Markdown, CSV, Excel, HTML, Word et LDJ. Exportez en JSON, Markdown, HTML, Word, Excel ou CSV, en version enseignant ou élève.</p>
            </article>
            <article class="mac-feature">
                <span class="mac-feature-icon" aria-hidden="true"><i class="fa-solid fa-square-root-variable"></i></span>
                <h3 data-site-i18n-en="Markdown and LaTeX" data-site-i18n-fr="Markdown et LaTeX">Markdown et LaTeX</h3>
                <p data-site-i18n-en="Long fields accept Markdown and LaTeX, with a formatting bar, a formula helper and a live preview."
                   data-site-i18n-fr="Les champs longs acceptent le Markdown et le LaTeX, avec une barre de formatage, un assistant de formules et un aperçu en direct.">Les champs longs acceptent le Markdown et le LaTeX, avec une barre de formatage, un assistant de formules et un aperçu en direct.</p>
            </article>
            <article class="mac-feature">
                <span class="mac-feature-icon" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
                <h3 data-site-i18n-en="Built-in help" data-site-i18n-fr="Aide intégrée">Aide intégrée</h3>
                <p data-site-i18n-en="The complete guide and the pages on learning design, Bloom’s taxonomy and the Conversational Framework, available offline."
                   data-site-i18n-fr="Le guide complet et les pages sur le learning design, la taxonomie de Bloom et le Cadre conversationnel, consultables hors ligne.">Le guide complet et les pages sur le learning design, la taxonomie de Bloom et le Cadre conversationnel, consultables hors ligne.</p>
            </article>
            <article class="mac-feature">
                <span class="mac-feature-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                <h3 data-site-i18n-en="Site search" data-site-i18n-fr="Recherche du site">Recherche du site</h3>
                <p data-site-i18n-en="From the sidebar, search the help, templates, shared scenarios, competencies and prompts of scenarisation.eu."
                   data-site-i18n-fr="Depuis la barre latérale, interrogez l’aide, les modèles, les scénarios partagés, les compétences et les prompts de scenarisation.eu.">Depuis la barre latérale, interrogez l’aide, les modèles, les scénarios partagés, les compétences et les prompts de scenarisation.eu.</p>
            </article>
        </div>
    </section>

    <section class="mac-section mac-privacy" aria-labelledby="mac-privacy-title">
        <h2 id="mac-privacy-title" class="mac-section-title" data-site-i18n-en="Privacy" data-site-i18n-fr="Confidentialité">Confidentialité</h2>
        <div class="mac-privacy-points">
            <div>
                <span class="mac-feature-icon" aria-hidden="true"><i class="fa-solid fa-folder"></i></span>
                <h3 data-site-i18n-en="Files on your Mac" data-site-i18n-fr="Des fichiers sur votre Mac">Des fichiers sur votre Mac</h3>
                <p data-site-i18n-en="Your scenarios are files stored in the folder of your choice." data-site-i18n-fr="Vos scénarios sont des fichiers, rangés dans le dossier de votre choix.">Vos scénarios sont des fichiers, rangés dans le dossier de votre choix.</p>
            </div>
            <div>
                <span class="mac-feature-icon" aria-hidden="true"><i class="fa-solid fa-arrows-rotate"></i></span>
                <h3 data-site-i18n-en="The site, only if you sign in" data-site-i18n-fr="Le site, si vous vous connectez">Le site, si vous vous connectez</h3>
                <p data-site-i18n-en="They only reach the site if you sign in to your account." data-site-i18n-fr="Ils ne rejoignent le site que si vous vous connectez à votre compte.">Ils ne rejoignent le site que si vous vous connectez à votre compte.</p>
            </div>
            <div>
                <span class="mac-feature-icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
                <h3 data-site-i18n-en="AI, only when you write to it" data-site-i18n-fr="L’IA, quand vous lui écrivez">L’IA, quand vous lui écrivez</h3>
                <p data-site-i18n-en="The assistant only receives a scenario when you write to it. With Ollama or LM Studio, everything stays on your Mac." data-site-i18n-fr="L’assistant ne reçoit un scénario que lorsque vous lui écrivez. Avec Ollama ou LM Studio, tout reste sur votre Mac.">L’assistant ne reçoit un scénario que lorsque vous lui écrivez. Avec Ollama ou LM Studio, tout reste sur votre Mac.</p>
            </div>
        </div>
    </section>

    <section class="mac-section" aria-labelledby="mac-install-title">
        <h2 id="mac-install-title" class="mac-section-title" data-site-i18n-en="Install in three steps" data-site-i18n-fr="Installer en trois étapes">Installer en trois étapes</h2>
        <ol class="mac-steps">
            <li>
                <span class="mac-step-number" aria-hidden="true">1</span>
                <h3 data-site-i18n-en="Download" data-site-i18n-fr="Télécharger">Télécharger</h3>
                <p data-site-i18n-en="Download the disk image. The app is signed and notarised by Apple."
                   data-site-i18n-fr="Téléchargez l’image disque. L’app est signée et notarisée par Apple.">Téléchargez l’image disque. L’app est signée et notarisée par Apple.</p>
            </li>
            <li>
                <span class="mac-step-number" aria-hidden="true">2</span>
                <h3 data-site-i18n-en="Install" data-site-i18n-fr="Installer">Installer</h3>
                <p data-site-i18n-en="Open the image and drag Scénarisation into your Applications folder."
                   data-site-i18n-fr="Ouvrez l’image et faites glisser Scénarisation dans le dossier Applications.">Ouvrez l’image et faites glisser Scénarisation dans le dossier Applications.</p>
            </li>
            <li>
                <span class="mac-step-number" aria-hidden="true">3</span>
                <h3 data-site-i18n-en="Get started" data-site-i18n-fr="Commencer">Commencer</h3>
                <p data-site-i18n-en="Create a scenario, start from a template or sign in to find your scenarios. Updates install from the app."
                   data-site-i18n-fr="Créez un scénario, partez d’un modèle ou connectez-vous pour retrouver vos scénarios. Les mises à jour s’installent depuis l’app.">Créez un scénario, partez d’un modèle ou connectez-vous pour retrouver vos scénarios. Les mises à jour s’installent depuis l’app.</p>
            </li>
        </ol>
    </section>

    <section class="mac-final" aria-labelledby="mac-final-title">
        <img class="mac-final-icon" src="assets/scenarisation-mac-icon.png" alt="" width="96" height="96" loading="lazy">
        <div class="mac-final-text">
            <h2 id="mac-final-title" data-site-i18n-en="Scénarisation for Mac" data-site-i18n-fr="Scénarisation pour Mac">Scénarisation pour Mac</h2>
            <p data-site-i18n-en="Version <?= h($macVersion) ?> · macOS <?= h($minimumMacOS) ?> or later · Mac with Apple silicon"
               data-site-i18n-fr="Version <?= h($macVersion) ?> · macOS <?= h($minimumMacOS) ?> ou ultérieur · Mac avec puce Apple">Version <?= h($macVersion) ?> · macOS <?= h($minimumMacOS) ?> ou ultérieur · Mac avec puce Apple</p>
        </div>
        <div class="mac-actions mac-final-actions">
            <a class="btn btn-primary mac-download" href="<?= h($release['url']) ?>">
                <i class="fa-brands fa-apple" aria-hidden="true"></i>
                <span data-site-i18n-en="Download" data-site-i18n-fr="Télécharger">Télécharger</span>
            </a>
            <a class="mac-more" href="news.php"><span data-site-i18n-en="What’s new" data-site-i18n-fr="Nouveautés">Nouveautés</span> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </section>
</main>
<?php render_site_footer(); ?>
</body>
</html>
