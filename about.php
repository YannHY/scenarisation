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
    <title>À propos | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="about-page">
<?php render_site_nav('about'); ?>
<main class="about-shell with-nav">
    <div class="about-card">
        <h1 id="about-title" class="about-title">À propos</h1>
        <p class="about-version">Version 0.9</p>
        <p id="about-intro" class="about-intro">Scenarisation est un outil de scénarisation pédagogique. <a href="help.php">Consultez la page d’aide</a> pour découvrir ses fonctionnalités et apprendre à l’utiliser.</p>

        <hr class="about-divider">

        <p id="about-meta" class="about-meta">
            Inspiré de l'<a href="https://www.ucl.ac.uk/learning-designer/" target="_blank" rel="noopener noreferrer">UCL Learning Designer</a> (UCL Knowledge Lab, UCL Institute of Education, 2013–2026).<br>
            Conçu et développé par Yann Houry &amp; François Jourde (2026) · <abbr title="Creative Commons Attribution - Partage dans les mêmes conditions">CC BY-SA</abbr><br>
            Code source&nbsp;: <a href="https://github.com/YannHY/learning-designer" target="_blank" rel="noopener noreferrer">github.com/YannHY/learning-designer</a> (basé sur <a href="https://github.com/jourde" target="_blank" rel="noopener noreferrer">github.com/jourde</a>)
        </p>
    </div>
</main>
<?php render_site_footer(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var translations = {
        fr: {
            title: 'À propos',
            intro: 'Scenarisation est un outil de scénarisation pédagogique. <a href="help.php">Consultez la page d’aide</a> pour découvrir ses fonctionnalités et apprendre à l’utiliser.',
            meta: 'Inspiré de l\'<a href="https://www.ucl.ac.uk/learning-designer/" target="_blank" rel="noopener noreferrer">UCL Learning Designer</a> (UCL Knowledge Lab, UCL Institute of Education, 2013–2026).<br>Conçu et développé par Yann Houry &amp; François Jourde (2026) · <abbr title="Creative Commons Attribution - Partage dans les mêmes conditions">CC BY-SA</abbr><br>Code source&nbsp;: <a href="https://github.com/YannHY/learning-designer" target="_blank" rel="noopener noreferrer">github.com/YannHY/learning-designer</a> (basé sur <a href="https://github.com/jourde" target="_blank" rel="noopener noreferrer">github.com/jourde</a>)'
        },
        en: {
            title: 'About',
            intro: 'Scenarisation is a learning design tool. <a href="help.php">Visit the help page</a> to discover its features and learn how to use it.',
            meta: 'Inspired by the <a href="https://www.ucl.ac.uk/learning-designer/" target="_blank" rel="noopener noreferrer">UCL Learning Designer</a> (UCL Knowledge Lab, UCL Institute of Education, 2013–2026).<br>Designed and developed by Yann Houry &amp; François Jourde (2026) · <abbr title="Creative Commons Attribution - ShareAlike">CC BY-SA</abbr><br>Source code: <a href="https://github.com/YannHY/learning-designer" target="_blank" rel="noopener noreferrer">github.com/YannHY/learning-designer</a> (based on <a href="https://github.com/jourde" target="_blank" rel="noopener noreferrer">github.com/jourde</a>)'
        }
    };

    function applyAboutLanguage(lang) {
        var selected = lang === 'en' ? 'en' : 'fr';
        var content = translations[selected];
        document.documentElement.lang = selected;
        document.title = content.title + ' | Scenarisation';
        document.getElementById('about-title').textContent = content.title;
        document.getElementById('about-intro').innerHTML = content.intro;
        document.getElementById('about-meta').innerHTML = content.meta;
    }

    var lang = 'fr';
    try {
        lang = localStorage.getItem('learningDesignerLang') || 'fr';
    } catch (error) {
        lang = 'fr';
    }
    applyAboutLanguage(lang);

    var langSelect = document.getElementById('lang-select');
    if (langSelect) {
        langSelect.addEventListener('change', function () {
            applyAboutLanguage(langSelect.value);
            try {
                localStorage.setItem('learningDesignerLang', langSelect.value);
            } catch (error) {
            }
        });
    }
});
</script>
</body>
</html>
