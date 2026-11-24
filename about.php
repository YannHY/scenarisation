<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// La navigation lit la session : elle doit démarrer avant tout HTML.
app_start_session();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title data-site-i18n-en="About | Scénarisation" data-site-i18n-fr="À propos | Scénarisation">À propos | Scénarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20261124-rem">
    <link rel="stylesheet" href="css/account-ui.css?v=20261124-rem">
    <link rel="stylesheet" href="css/account-pages.css?v=20261124-rem">
    <link rel="stylesheet" href="css/about.css?v=<?= hash_file('sha256', __DIR__ . '/css/about.css') ?>">
<?php render_refined_styles(); ?>
</head>
<body class="about-page">
<?php render_site_nav('about'); ?>
<main class="about-shell with-nav">
    <header class="about-hero">
        <img class="about-mark" src="assets/favicon.svg?v=20260906-scenarisation" alt="" width="112" height="112">
        <p class="about-kicker" data-site-i18n-en="About" data-site-i18n-fr="À propos">À propos</p>
        <h1 class="about-name">Scénarisation</h1>
        <p class="about-version-pill"><span data-site-i18n-en="Version" data-site-i18n-fr="Version">Version</span> <?= h(APP_VERSION) ?></p>
        <p class="about-lead" data-site-i18n-en="A tool for designing, analysing and sharing learning scenarios, centred on what students actually do."
           data-site-i18n-fr="Un outil pour concevoir, analyser et partager des scénarios pédagogiques centrés sur l’activité des élèves.">Un outil pour concevoir, analyser et partager des scénarios pédagogiques centrés sur l’activité des élèves.</p>
        <div class="about-actions">
            <a class="btn btn-primary about-action" href="help.php"><i class="fa-regular fa-life-ring btn-icon-inline" aria-hidden="true"></i><span data-site-i18n-en="Help" data-site-i18n-fr="Aide">Aide</span></a>
            <a class="btn about-action" href="news.php"><i class="fa-regular fa-star btn-icon-inline" aria-hidden="true"></i><span data-site-i18n-en="What’s new" data-site-i18n-fr="Nouveautés">Nouveautés</span></a>
        </div>
    </header>

    <section class="about-credits" aria-labelledby="about-credits-title">
        <h2 id="about-credits-title" class="about-credits-title" data-site-i18n-en="Credits" data-site-i18n-fr="Crédits">Crédits</h2>
        <div class="about-grid">
            <article class="about-item">
                <span class="about-icon" aria-hidden="true"><i class="fa-regular fa-lightbulb"></i></span>
                <h3 data-site-i18n-en="Inspiration" data-site-i18n-fr="Inspiration">Inspiration</h3>
                <p><a href="https://www.ucl.ac.uk/learning-designer/" target="_blank" rel="noopener noreferrer">UCL Learning Designer</a></p>
                <p class="about-item-detail">UCL Knowledge Lab, UCL Institute of Education, 2013–2026</p>
            </article>
            <article class="about-item">
                <span class="about-icon" aria-hidden="true"><i class="fa-solid fa-pen-nib"></i></span>
                <h3 data-site-i18n-en="Design and development" data-site-i18n-fr="Conception et développement">Conception et développement</h3>
                <p>Yann Houry &amp; François Jourde</p>
                <p class="about-item-detail">2026</p>
            </article>
            <article class="about-item">
                <span class="about-icon" aria-hidden="true"><i class="fa-solid fa-code-branch"></i></span>
                <h3 data-site-i18n-en="Source code" data-site-i18n-fr="Code source">Code source</h3>
                <p><a href="https://github.com/YannHY/scenarisation" target="_blank" rel="noopener noreferrer">github.com/YannHY/scenarisation</a></p>
                <p class="about-item-detail"><span data-site-i18n-en="Based on" data-site-i18n-fr="Basé sur">Basé sur</span> <a href="https://github.com/jourde" target="_blank" rel="noopener noreferrer">github.com/jourde</a></p>
            </article>
            <article class="about-item">
                <span class="about-icon" aria-hidden="true"><i class="fa-brands fa-creative-commons"></i></span>
                <h3 data-site-i18n-en="Licence" data-site-i18n-fr="Licence">Licence</h3>
                <p><a href="conditions-utilisation.php#licences-reutilisation">CC BY-SA 4.0</a></p>
                <p class="about-item-detail" data-site-i18n-en="Attribution, ShareAlike" data-site-i18n-fr="Attribution, partage dans les mêmes conditions">Attribution, partage dans les mêmes conditions</p>
            </article>
        </div>
    </section>
</main>
<?php render_site_footer(); ?>
</body>
</html>
