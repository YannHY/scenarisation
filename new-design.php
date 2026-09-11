<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
app_start_session();
$choices = [
    ['designer.php?new=1', 'fa-plus', 'Créer un scénario', 'Create a scenario', 'Partez d’une page blanche et donnez forme à votre propre scénario pédagogique.', 'Start with a blank page and shape your own learning scenario.', 'Partir d’une page blanche', 'Start from scratch'],
    ['models.php', 'fa-layer-group', 'Importer un modèle', 'Import a template', 'Explorez les modèles, visualisez leur contenu et choisissez celui que vous souhaitez adapter.', 'Browse templates, preview their content and choose one to adapt.', 'Explorer les modèles', 'Explore templates'],
    ['share.php', 'fa-file-import', 'Importer un scénario partagé', 'Import a shared scenario', 'Importez un scénario de la communauté ou un fichier transmis par un collègue.', 'Import a community scenario or a file shared by a colleague.', 'Voir les scénarios partagés', 'Browse shared scenarios'],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nouveau scénario | Scenarisation</title>
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml">
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/interface.css?v=<?= hash_file('sha256', __DIR__ . '/css/interface.css') ?>">
    <link rel="stylesheet" href="css/account-ui.css?v=<?= hash_file('sha256', __DIR__ . '/css/account-ui.css') ?>">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
    <link rel="stylesheet" href="css/new-design.css?v=<?= hash_file('sha256', __DIR__ . '/css/new-design.css') ?>">
    <script defer src="js/scenario-file-transfer.js?v=<?= hash_file('sha256', __DIR__ . '/js/scenario-file-transfer.js') ?>"></script>
    <script defer src="js/new-design.js?v=<?= hash_file('sha256', __DIR__ . '/js/new-design.js') ?>"></script>
</head>
<body class="new-design-page">
<a class="skip-link" href="#main-content" data-site-i18n-en="Skip to main content" data-site-i18n-fr="Aller au contenu principal">Aller au contenu principal</a>
<?php render_site_nav('new-design'); ?>
<main id="main-content" class="new-design-main">
    <div class="new-design-heading">
        <h1 data-site-i18n-en="How would you like to start?" data-site-i18n-fr="Comment souhaitez-vous commencer ?">Comment souhaitez-vous commencer ?</h1>
        <p data-site-i18n-en="Create a scenario or import existing content." data-site-i18n-fr="Créez un scénario ou importez un contenu existant.">Créez un scénario ou importez un contenu existant.</p>
    </div>
    <div class="new-design-choices">
        <?php foreach ($choices as [$href, $icon, $titleFr, $titleEn, $descriptionFr, $descriptionEn, $actionFr, $actionEn]): ?>
            <a class="new-design-choice" href="<?= h($href) ?>"<?= $href !== 'designer.php?new=1' ? ' data-import-dialog aria-haspopup="dialog"' : '' ?>>
                <span class="new-design-choice-icon" aria-hidden="true"><i class="fa-solid <?= h($icon) ?>"></i></span>
                <h2 data-site-i18n-en="<?= h($titleEn) ?>" data-site-i18n-fr="<?= h($titleFr) ?>"><?= h($titleFr) ?></h2>
                <p data-site-i18n-en="<?= h($descriptionEn) ?>" data-site-i18n-fr="<?= h($descriptionFr) ?>"><?= h($descriptionFr) ?></p>
                <span class="new-design-choice-action"><span data-site-i18n-en="<?= h($actionEn) ?>" data-site-i18n-fr="<?= h($actionFr) ?>"><?= h($actionFr) ?></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>
        <?php endforeach; ?>
    </div>
</main>
<dialog id="new-design-import" class="modal import-modal new-design-import" aria-labelledby="new-design-import-title">
    <h2 id="new-design-import-title" class="modal-title"></h2>
    <section id="new-design-file-section" class="import-source hidden" aria-labelledby="new-design-file-heading">
        <h3 id="new-design-file-heading" class="import-section-title" data-site-i18n-fr="Depuis mon ordinateur" data-site-i18n-en="From my computer">Depuis mon ordinateur</h3>
        <div id="new-design-drop-zone" class="import-drop-zone">
            <span class="import-drop-icon" aria-hidden="true"><i class="fa-solid fa-file-arrow-down"></i></span>
            <div class="import-drop-copy">
                <p class="import-drop-title" data-site-i18n-fr="Glissez-déposez un fichier ici" data-site-i18n-en="Drop a file here">Glissez-déposez un fichier ici</p>
                <p class="import-drop-hint" data-site-i18n-fr="ou choisissez-le sur votre ordinateur" data-site-i18n-en="or choose it from your computer">ou choisissez-le sur votre ordinateur</p>
            </div>
            <button id="new-design-file-button" class="btn btn-light" type="button"><span class="btn-label"><i class="fa-solid fa-folder-open btn-icon-inline" aria-hidden="true"></i><span data-site-i18n-fr="Choisir un fichier…" data-site-i18n-en="Choose a file…">Choisir un fichier…</span></span></button>
            <input id="new-design-file-input" type="file" accept=".ldj,.json,.csv,.xlsx,.md,.markdown" hidden>
        </div>
        <p class="import-file-formats">LDJ, JSON, CSV, Excel, Markdown · 5 Mo max.</p>
        <p id="new-design-file-status" class="import-models-status" role="status" aria-live="polite"></p>
    </section>
    <div id="new-design-import-filters" class="import-models-filters">
        <input id="new-design-import-search" class="panel-input" type="search" aria-label="Rechercher" placeholder="Rechercher…">
        <select id="new-design-import-family" class="panel-select" aria-label="Famille de modèles"></select>
    </div>
    <p id="new-design-import-status" class="import-models-status" role="status" aria-live="polite"></p>
    <div id="new-design-import-content" class="import-models-list"></div>
    <div class="modal-actions">
        <button id="new-design-import-back" class="btn btn-light hidden" type="button">Retour</button>
        <button id="new-design-import-close" class="btn btn-light" type="button">Fermer</button>
    </div>
    <div class="import-drop-active-message" role="status" aria-live="polite">
        <span class="import-drop-active-icon" aria-hidden="true"><i class="fa-solid fa-file-import"></i></span>
        <span id="new-design-drop-message" class="import-drop-active-label"></span>
    </div>
</dialog>
<?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
