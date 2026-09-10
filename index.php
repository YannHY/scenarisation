<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// La navigation lit la session : elle doit démarrer avant tout HTML.
app_start_session();

$homeTitle = 'Scenarisation | Concevoir des expériences d’apprentissage';
$homeDescription = 'Scenarisation aide les enseignants à concevoir, analyser et partager des scénarios pédagogiques.';
$homeUrl = rtrim(app_base_url(), '/') . '/';
$homeOgImageUrl = rtrim(app_base_url(), '/') . '/assets/og-home.png?v=20260906-scenarisation';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <meta name="description" content="<?= h($homeDescription) ?>">
    <link rel="canonical" href="<?= h($homeUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Scenarisation">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="<?= h($homeTitle) ?>">
    <meta property="og:description" content="<?= h($homeDescription) ?>">
    <meta property="og:url" content="<?= h($homeUrl) ?>">
    <meta property="og:image" content="<?= h($homeOgImageUrl) ?>">
    <meta property="og:image:secure_url" content="<?= h($homeOgImageUrl) ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Scenarisation — Donnez forme aux expériences d’apprentissage">
    <title><?= h($homeTitle) ?></title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
    <link rel="stylesheet" href="css/home.css?v=20260826-action-palette">
</head>
<body class="home-page">
<a class="skip-link" href="#main-content"
   data-site-i18n-en="Skip to main content"
   data-site-i18n-fr="Aller au contenu principal">Aller au contenu principal</a>
<?php render_site_nav('home'); ?>

<main id="main-content">
    <section class="home-hero" aria-labelledby="home-title">
        <div class="home-hero-copy">
            <p class="home-kicker"
               data-site-i18n-en="Learning design, made visible"
               data-site-i18n-fr="Le scénario pédagogique, rendu visible">Le scénario pédagogique, rendu visible</p>
            <h1 id="home-title"
                data-site-i18n-en="Give shape to learning experiences"
                data-site-i18n-fr="Donnez forme aux expériences d’apprentissage">Donnez forme aux expériences d’apprentissage</h1>
            <p class="home-lead"
               data-site-i18n-en="Design, analyse and share learning scenarios centred on what learners actually do, from the initial intention to the flow of each session."
               data-site-i18n-fr="Concevez, analysez et partagez des scénarios pédagogiques centrés sur l’activité des élèves, de l’intention initiale au déroulement de chaque séance.">Concevez, analysez et partagez des scénarios pédagogiques centrés sur l’activité des élèves, de l’intention initiale au déroulement de chaque séance.</p>
            <div class="home-hero-actions">
                <a class="home-primary-action" href="designer.php">
                    <span data-site-i18n-en="Open the scenario workspace" data-site-i18n-fr="Ouvrir l’interface de conception">Ouvrir l’interface de conception</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a class="home-text-action" href="#features"
                   data-site-i18n-en="Explore the main features"
                   data-site-i18n-fr="Découvrir les fonctionnalités">Découvrir les fonctionnalités</a>
            </div>
        </div>

        <div class="home-wheel-stage" aria-hidden="true">
            <div class="home-wheel"></div>
            <div class="home-wheel-labels">
                <span class="home-wheel-label home-wheel-label-read">
                    <strong data-site-i18n-en="Acquire" data-site-i18n-fr="Acquérir">Acquérir</strong>
                    <small>27 %</small>
                </span>
                <span class="home-wheel-label home-wheel-label-investigate">
                    <strong data-site-i18n-en="Investigate" data-site-i18n-fr="Investiguer">Investiguer</strong>
                    <small>16 %</small>
                </span>
                <span class="home-wheel-label home-wheel-label-practice">
                    <strong data-site-i18n-en="Practise" data-site-i18n-fr="Pratiquer">Pratiquer</strong>
                    <small>21 %</small>
                </span>
                <span class="home-wheel-label home-wheel-label-produce">
                    <strong data-site-i18n-en="Produce" data-site-i18n-fr="Produire">Produire</strong>
                    <small>12 %</small>
                </span>
                <span class="home-wheel-label home-wheel-label-discuss">
                    <strong data-site-i18n-en="Discuss" data-site-i18n-fr="Discuter">Discuter</strong>
                    <small>15 %</small>
                </span>
                <span class="home-wheel-label home-wheel-label-collaborate">
                    <strong data-site-i18n-en="Collaborate" data-site-i18n-fr="Collaborer">Collaborer</strong>
                    <small>9 %</small>
                </span>
            </div>
        </div>
    </section>

    <div class="home-features" id="features">
        <section class="home-feature" aria-labelledby="feature-design-title">
            <div class="home-feature-copy home-reveal">
                <p class="home-feature-kicker"
                   data-site-i18n-en="Design"
                   data-site-i18n-fr="Concevoir">Concevoir</p>
                <div class="home-feature-heading">
                    <p class="home-feature-number">01</p>
                    <h2 id="feature-design-title"
                        data-site-i18n-en="Structure every step"
                        data-site-i18n-fr="Structurez chaque étape">Structurez chaque étape</h2>
                </div>
                <p data-site-i18n-en="Organise your scenario into moments and activities. Set durations, teaching intentions, group formats, assessment methods and learning outcomes without losing sight of the whole."
                   data-site-i18n-fr="Organisez votre scénario en moments et en activités. Précisez les durées, les intentions pédagogiques, les groupes, l’évaluation et les acquis attendus sans perdre la vue d’ensemble.">Organisez votre scénario en moments et en activités. Précisez les durées, les intentions pédagogiques, les groupes, l’évaluation et les acquis attendus sans perdre la vue d’ensemble.</p>
                <a class="home-inline-action" href="help.php#moments-activites">
                    <span data-site-i18n-en="Explore design tools" data-site-i18n-fr="Découvrir les outils de conception">Découvrir les outils de conception</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="home-feature-visual home-sequence-visual home-reveal" aria-hidden="true">
                <div class="home-moment-card">
                    <div class="home-moment-head">
                        <span>01</span>
                        <strong data-site-i18n-en="Discover and question" data-site-i18n-fr="Découvrir et questionner">Découvrir et questionner</strong>
                        <small>35 min</small>
                    </div>
                    <div class="home-activity-row" style="--activity-color: var(--read)">
                        <span class="home-activity-dot"></span>
                        <span data-site-i18n-en="Read · Observe" data-site-i18n-fr="Lire · Observer">Lire · Observer</span>
                        <small>10 min</small>
                    </div>
                    <div class="home-activity-row" style="--activity-color: var(--investigate)">
                        <span class="home-activity-dot"></span>
                        <span data-site-i18n-en="Investigate · Compare" data-site-i18n-fr="Investiguer · Comparer">Investiguer · Comparer</span>
                        <small>15 min</small>
                    </div>
                    <div class="home-activity-row" style="--activity-color: var(--discuss)">
                        <span class="home-activity-dot"></span>
                        <span data-site-i18n-en="Discuss · Formulate" data-site-i18n-fr="Discuter · Formuler">Discuter · Formuler</span>
                        <small>10 min</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="home-feature" aria-labelledby="feature-analyse-title">
            <div class="home-feature-copy home-reveal">
                <p class="home-feature-kicker"
                   data-site-i18n-en="Analyse"
                   data-site-i18n-fr="Analyser">Analyser</p>
                <div class="home-feature-heading">
                    <p class="home-feature-number">02</p>
                    <h2 id="feature-analyse-title"
                        data-site-i18n-en="See the balance"
                        data-site-i18n-fr="Voyez l’équilibre">Voyez l’équilibre</h2>
                </div>
                <p data-site-i18n-en="Visual summaries reveal the distribution of learning types, individual and group work, teaching modes and the overall timeline. They make it easier to spot what deserves another look."
                   data-site-i18n-fr="Les synthèses visuelles montrent la répartition des types d’apprentissage, du travail individuel ou collectif, des modes d’enseignement et du temps. Elles font apparaître ce qui mérite d’être rééquilibré.">Les synthèses visuelles montrent la répartition des types d’apprentissage, du travail individuel ou collectif, des modes d’enseignement et du temps. Elles font apparaître ce qui mérite d’être rééquilibré.</p>
                <a class="home-inline-action" href="help.php#vues-analyses">
                    <span data-site-i18n-en="Explore visual analysis" data-site-i18n-fr="Découvrir les analyses visuelles">Découvrir les analyses visuelles</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="home-feature-visual home-analysis-visual home-reveal" aria-hidden="true">
                <div class="home-analysis-card">
                    <div class="home-mini-wheel"></div>
                    <div class="home-analysis-summary">
                        <span><i style="--metric-color: var(--read)"></i><b>28 %</b><small data-site-i18n-en="Acquire" data-site-i18n-fr="Acquérir">Acquérir</small></span>
                        <span><i style="--metric-color: var(--practice)"></i><b>22 %</b><small data-site-i18n-en="Practise" data-site-i18n-fr="Pratiquer">Pratiquer</small></span>
                        <span><i style="--metric-color: var(--produce)"></i><b>18 %</b><small data-site-i18n-en="Produce" data-site-i18n-fr="Produire">Produire</small></span>
                    </div>
                </div>
                <div class="home-analysis-note">
                    <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                    <span data-site-i18n-en="Balance learning activities" data-site-i18n-fr="Équilibrer les activités d’apprentissage">Équilibrer les activités d’apprentissage</span>
                </div>
            </div>
        </section>

        <section class="home-feature" aria-labelledby="feature-share-title">
            <div class="home-feature-copy home-reveal">
                <p class="home-feature-kicker"
                   data-site-i18n-en="Share"
                   data-site-i18n-fr="Partager">Partager</p>
                <div class="home-feature-heading">
                    <p class="home-feature-number">03</p>
                    <h2 id="feature-share-title"
                        data-site-i18n-en="Share it your way"
                        data-site-i18n-fr="Diffusez simplement">Diffusez simplement</h2>
                </div>
                <p data-site-i18n-en="Publish a private viewing link, list a scenario in the public gallery or export it in the format of your choice. Creative Commons licences and importing make every scenario easier to reuse and adapt."
                   data-site-i18n-fr="Publiez un lien de consultation, rendez un scénario visible dans la galerie ou exportez-le dans le format de votre choix. Les licences Creative Commons et l’import facilitent la réutilisation et l’adaptation de chaque scénario.">Publiez un lien de consultation, rendez un scénario visible dans la galerie ou exportez-le dans le format de votre choix. Les licences Creative Commons et l’import facilitent la réutilisation et l’adaptation de chaque scénario.</p>
                <a class="home-inline-action" href="help.php#sauvegarde-partage">
                    <span data-site-i18n-en="Explore sharing options" data-site-i18n-fr="Découvrir les options de partage">Découvrir les options de partage</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="home-feature-visual home-share-visual home-reveal" aria-hidden="true">
                <div class="home-share-card">
                    <div class="home-share-head">
                        <span class="home-share-main-icon"><i class="fa-solid fa-share-nodes"></i></span>
                        <div>
                            <strong data-site-i18n-en="A scenario ready to share" data-site-i18n-fr="Un scénario prêt à partager">Un scénario prêt à partager</strong>
                            <small data-site-i18n-en="Choose the right access for every audience" data-site-i18n-fr="Choisissez le bon accès pour chaque public">Choisissez le bon accès pour chaque public</small>
                        </div>
                    </div>
                    <div class="home-share-link-row">
                        <i class="fa-solid fa-link"></i>
                        <span>scenarisation/view/atelier-lecture</span>
                        <strong data-site-i18n-en="Active link" data-site-i18n-fr="Lien actif">Lien actif</strong>
                    </div>
                    <div class="home-share-options">
                        <div>
                            <i class="fa-regular fa-eye"></i>
                            <strong data-site-i18n-en="Private link" data-site-i18n-fr="Lien privé">Lien privé</strong>
                            <small data-site-i18n-en="View without editing" data-site-i18n-fr="Consulter sans modifier">Consulter sans modifier</small>
                        </div>
                        <div>
                            <i class="fa-solid fa-earth-europe"></i>
                            <strong data-site-i18n-en="Public gallery" data-site-i18n-fr="Galerie publique">Galerie publique</strong>
                            <small data-site-i18n-en="Publish with a licence" data-site-i18n-fr="Publier avec une licence">Publier avec une licence</small>
                        </div>
                        <div>
                            <i class="fa-solid fa-file-export"></i>
                            <strong data-site-i18n-en="Useful formats" data-site-i18n-fr="Formats utiles">Formats utiles</strong>
                            <small>HTML · Word · JSON · Markdown</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="home-feature" aria-labelledby="feature-ai-title">
            <div class="home-feature-copy home-reveal">
                <p class="home-feature-kicker"
                   data-site-i18n-en="AI & CLI"
                   data-site-i18n-fr="IA & CLI">IA &amp; CLI</p>
                <div class="home-feature-heading">
                    <p class="home-feature-number">04</p>
                    <h2 id="feature-ai-title"
                        data-site-i18n-en="Design with AI"
                        data-site-i18n-fr="Concevez avec l’IA">Concevez avec l’IA</h2>
                </div>
                <p data-site-i18n-en="Describe your teaching goal — or share an existing scenario — with an AI such as Codex or Claude Code. It can structure and enrich every aspect of the scenario, then analyse it and suggest improvements. The CLI creates, enriches and validates the scenario before publication."
                   data-site-i18n-fr="Décrivez votre intention pédagogique — ou confiez un scénario existant — à une IA comme Codex ou Claude Code. Elle peut structurer et enrichir chaque dimension du scénario, puis analyser l’ensemble et proposer des ajustements. Le CLI crée, enrichit et valide le scénario avant sa publication.">Décrivez votre intention pédagogique — ou confiez un scénario existant — à une IA comme Codex ou Claude Code. Elle peut structurer et enrichir chaque dimension du scénario, puis analyser l’ensemble et proposer des ajustements. Le CLI crée, enrichit et valide le scénario avant sa publication.</p>
                <a class="home-inline-action" href="help.php#cli">
                    <span data-site-i18n-en="Discover AI and the CLI" data-site-i18n-fr="Découvrir l’IA et le CLI">Découvrir l’IA et le CLI</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="home-feature-visual home-cli-visual home-reveal" aria-hidden="true">
                <div class="home-terminal">
                    <div class="home-terminal-bar">
                        <span></span><span></span><span></span>
                        <small>learning</small>
                    </div>
                    <div class="home-terminal-body">
                        <p><span class="home-prompt">$</span> scenarisation init design.json</p>
                        <p class="home-terminal-result"><i class="fa-solid fa-check"></i> <span data-site-i18n-en="4 moments · 9 activities" data-site-i18n-fr="4 moments · 9 activités">4 moments · 9 activités</span></p>
                        <p><span class="home-prompt">$</span> scenarisation validate design.json</p>
                        <p class="home-terminal-result"><i class="fa-solid fa-check"></i> <span data-site-i18n-en="Valid scenario, ready to publish" data-site-i18n-fr="Scénario valide, prêt à publier">Scénario valide, prêt à publier</span></p>
                        <p class="home-terminal-cursor"><span class="home-prompt">$</span> <i></i></p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="home-closing">
        <section class="home-quote home-reveal" aria-label="Citation de Diana Laurillard"
                 data-site-i18n-attr="aria-label"
                 data-site-i18n-en="Quote by Diana Laurillard"
            data-site-i18n-fr="Citation de Diana Laurillard">
            <div class="home-quote-inner">
                <blockquote>
                    <p data-site-i18n-en="We borrow, we adopt, we adapt, we test it out, make our own thing with it and then we share our product back with the rest of the community."
                       data-site-i18n-fr="Nous empruntons, nous adoptons, nous adaptons, nous mettons le tout à l’essai, nous en faisons quelque chose qui nous est propre, puis nous partageons à notre tour notre création avec le reste de la communauté.">Nous empruntons, nous adoptons, nous adaptons, nous mettons le tout à l’essai, nous en faisons quelque chose qui nous est propre, puis nous partageons à notre tour notre création avec le reste de la communauté.</p>
                    <footer><cite>Diana Laurillard</cite></footer>
                </blockquote>
            </div>
        </section>

        <section class="home-final-cta" aria-labelledby="home-final-title">
            <div class="home-final-cta-content home-reveal">
                <p class="home-kicker"
                   data-site-i18n-en="Your next scenario starts here"
                   data-site-i18n-fr="Votre prochain scénario commence ici">Votre prochain scénario commence ici</p>
                <h2 id="home-final-title"
                    data-site-i18n-en="Start designing"
                    data-site-i18n-fr="À vous de concevoir">À vous de concevoir</h2>
                <a class="home-primary-action" href="designer.php">
                    <span data-site-i18n-en="Create a scenario" data-site-i18n-fr="Créer un scénario">Créer un scénario</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </section>
    </div>
</main>

<?php require __DIR__ . '/partials/site-footer.php'; ?>
<script src="js/home.js?v=20260729-2"></script>
</body>
</html>
