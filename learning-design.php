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
    <title>Learning design | Scénarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20261124-rem">
    <link rel="stylesheet" href="css/account-ui.css?v=20261124-rem">
    <link rel="stylesheet" href="css/account-pages.css?v=20261124-rem">
    <link rel="stylesheet" href="css/learning-design.css?v=<?= hash_file('sha256', __DIR__ . '/css/learning-design.css') ?>" />
<?php render_refined_styles(); ?>
</head>
<body class="learning-design-page">
<?php render_site_nav(); ?>
<main class="ld-shell with-nav">
    <header class="ld-hero">
        <p class="ld-kicker" data-i18n-fr="Documentation" data-i18n-en="Documentation">Documentation</p>
        <h1 class="ld-title" data-i18n-fr="Qu'est-ce que le learning design ?" data-i18n-en="What is learning design?">Qu'est-ce que le learning design ?</h1>
        <p class="ld-lead" data-i18n-fr="Le learning design consiste à concevoir le processus d'apprentissage lui-même : non seulement les contenus à transmettre, mais les actions qui permettront aux élèves de les comprendre, de les transformer et de les réutiliser." data-i18n-en="Learning design means designing the learning process itself: not only the content to be taught, but the actions that help learners understand, transform, and reuse it.">Le learning design consiste à concevoir le processus d'apprentissage lui-même&nbsp;: non seulement les contenus à transmettre, mais les actions qui permettront aux élèves de les comprendre, de les transformer et de les réutiliser.</p>
    </header>

    <blockquote class="ld-quote">
        <p data-i18n-fr="Les enseignants agissent comme des ingénieurs de conception : ils s'appuient sur la science quand elle améliore leur pratique, observent ce qui se passe en classe, puis réajustent leur enseignement à partir des retours." data-i18n-en="Teachers act like design engineers: they use science when it improves their practice, observe what happens in class, and redesign their teaching from feedback.">Les enseignants agissent comme des ingénieurs de conception&nbsp;: ils s'appuient sur la science quand elle améliore leur pratique, observent ce qui se passe en classe, puis réajustent leur enseignement à partir des retours.</p>
        <cite data-i18n-fr="Diana Laurillard" data-i18n-en="Diana Laurillard">Diana Laurillard</cite>
    </blockquote>

    <section class="ld-section">
        <h2 data-i18n-fr="L'idée centrale" data-i18n-en="Core Idea">L'idée centrale</h2>
        <p data-i18n-fr="La question de départ n'est pas seulement : « Que dois-je présenter ? » Elle devient : « Que doit faire l'élève pour comprendre quelque chose ? » Cette bascule déplace l'attention du contenu vers l'activité réelle de l'apprenant." data-i18n-en="The starting question is not only: “What should I present?” It becomes: “What must the learner do in order to understand something?” This shift moves attention from content to the learner’s actual activity.">La question de départ n'est pas seulement&nbsp;: «&nbsp;Que dois-je présenter&nbsp;?&nbsp;» Elle devient&nbsp;: «&nbsp;Que doit faire l'élève pour comprendre quelque chose&nbsp;?&nbsp;» Cette bascule déplace l'attention du contenu vers l'activité réelle de l'apprenant.</p>
        <p data-i18n-fr="Un scénario pédagogique peut donc être lu comme une succession d'expériences : écouter, chercher, discuter, essayer, produire, recevoir un retour, recommencer. Le learning design aide à rendre cette succession explicite, visible et améliorable." data-i18n-en="A teaching scenario can therefore be read as a sequence of experiences: listening, searching, discussing, trying, producing, receiving feedback, and trying again. Learning design makes that sequence explicit, visible, and easier to improve.">Un scénario pédagogique peut donc être lu comme une succession d'expériences&nbsp;: écouter, chercher, discuter, essayer, produire, recevoir un retour, recommencer. Le learning design aide à rendre cette succession explicite, visible et améliorable.</p>
    </section>

    <section class="ld-section">
        <h2 data-i18n-fr="Les six types d'apprentissage" data-i18n-en="The Six Learning Types">Les six types d'apprentissage</h2>
        <p><span data-i18n-fr="Le modèle utilisé ici reprend les six types d'apprentissage associés au " data-i18n-en="The model used here follows the six learning types associated with ">Le modèle utilisé ici reprend les six types d'apprentissage associés au </span><a class="ld-link" href="cadre-conversationnel.php" data-i18n-fr="Cadre conversationnel de Diana Laurillard" data-i18n-en="Diana Laurillard’s Conversational Framework">Cadre conversationnel de Diana Laurillard</a><span data-i18n-fr=". Une séquence solide ne les mobilise pas forcément tous au même niveau, mais elle gagne à combiner plusieurs formes d'activité." data-i18n-en=". A strong sequence does not necessarily use them all equally, but it benefits from combining several kinds of activity.">. Une séquence solide ne les mobilise pas forcément tous au même niveau, mais elle gagne à combiner plusieurs formes d'activité.</span></p>
        <div class="ld-type-grid">
            <article class="ld-type-card" style="--ld-type:var(--read)">
                <div class="ld-card-head">
                    <span class="ld-icon"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span>
                    <h3 data-i18n-fr="Acquisition" data-i18n-en="Acquisition">Acquisition</h3>
                </div>
                <p data-i18n-fr="Écouter un enseignant, lire un livre ou une page web, regarder une vidéo." data-i18n-en="Listening to a teacher, reading a book or web page, watching a video.">Écouter un enseignant, lire un livre ou une page web, regarder une vidéo.</p>
            </article>
            <article class="ld-type-card" style="--ld-type:var(--collaborate)">
                <div class="ld-card-head">
                    <span class="ld-icon"><i class="fa-solid fa-people-group" aria-hidden="true"></i></span>
                    <h3 data-i18n-fr="Collaboration" data-i18n-en="Collaboration">Collaboration</h3>
                </div>
                <p data-i18n-fr="Réaliser un projet en petit groupe, discuter avec d'autres élèves, développer de nouvelles idées." data-i18n-en="Carrying out a small-group project, discussing with other students, developing new ideas.">Réaliser un projet en petit groupe, discuter avec d'autres élèves, développer de nouvelles idées.</p>
            </article>
            <article class="ld-type-card" style="--ld-type:var(--discuss)">
                <div class="ld-card-head">
                    <span class="ld-icon"><i class="fa-solid fa-comments" aria-hidden="true"></i></span>
                    <h3 data-i18n-fr="Discussion" data-i18n-en="Discussion">Discussion</h3>
                </div>
                <p data-i18n-fr="Partager ses connaissances, tenir compte du point de vue des autres, défendre une opinion." data-i18n-en="Sharing knowledge, considering other viewpoints, defending an opinion.">Partager ses connaissances, tenir compte du point de vue des autres, défendre une opinion.</p>
            </article>
            <article class="ld-type-card" style="--ld-type:var(--investigate)">
                <div class="ld-card-head">
                    <span class="ld-icon"><i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i></span>
                    <h3 data-i18n-fr="Investigation" data-i18n-en="Investigation">Investigation</h3>
                </div>
                <p data-i18n-fr="Rechercher et sélectionner des informations, les comprendre, puis évaluer la qualité des résultats." data-i18n-en="Searching for and selecting information, understanding it, then evaluating the quality of the results.">Rechercher et sélectionner des informations, les comprendre, puis évaluer la qualité des résultats.</p>
            </article>
            <article class="ld-type-card" style="--ld-type:var(--practice)">
                <div class="ld-card-head">
                    <span class="ld-icon"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></span>
                    <h3 data-i18n-fr="Pratique" data-i18n-en="Practice">Pratique</h3>
                </div>
                <p data-i18n-fr="Exprimer ce qui a été appris, recevoir des commentaires, revoir son approche et essayer à nouveau." data-i18n-en="Expressing what has been learned, receiving comments, reviewing the approach, and trying again.">Exprimer ce qui a été appris, recevoir des commentaires, revoir son approche et essayer à nouveau.</p>
            </article>
            <article class="ld-type-card" style="--ld-type:var(--produce)">
                <div class="ld-card-head">
                    <span class="ld-icon"><i class="fa-solid fa-hammer" aria-hidden="true"></i></span>
                    <h3 data-i18n-fr="Production" data-i18n-en="Production">Production</h3>
                </div>
                <p data-i18n-fr="Produire une présentation ou une dissertation, consolider les informations sous plusieurs angles, soumettre le résultat à l'évaluation." data-i18n-en="Producing a presentation or essay, consolidating information from several angles, submitting the result for assessment.">Produire une présentation ou une dissertation, consolider les informations sous plusieurs angles, soumettre le résultat à l'évaluation.</p>
            </article>
        </div>
    </section>

    <section class="ld-section">
        <h2 data-i18n-fr="Concevoir avec Scénarisation" data-i18n-en="Designing With Scénarisation">Concevoir avec Scénarisation</h2>
        <p data-i18n-fr="L'outil développé à l'University College London par l'équipe de Diana Laurillard aide les enseignants à concevoir des activités pédagogiques, à les partager et à analyser leur équilibre. On commence par décrire le contexte : titre, sujet, description, objectifs, acquis attendus et taille du groupe." data-i18n-en="The tool developed at University College London by Diana Laurillard’s team helps teachers design teaching activities, share them, and analyze their balance. The process starts by describing the context: title, topic, description, objectives, expected outcomes, and group size.">L'outil développé à l'University College London par l'équipe de Diana Laurillard aide les enseignants à concevoir des activités pédagogiques, à les partager et à analyser leur équilibre. On commence par décrire le contexte&nbsp;: titre, sujet, description, objectifs, acquis attendus et taille du groupe.</p>
        <div class="ld-step-grid">
            <article class="ld-step-card">
                <h3 data-i18n-fr="1. Décrire le contexte" data-i18n-en="1. Describe the context">1. Décrire le contexte</h3>
                <p data-i18n-fr="Clarifier la commande, les objectifs, les résultats attendus, le public et les contraintes." data-i18n-en="Clarify the brief, objectives, expected outcomes, audience, and constraints.">Clarifier la commande, les objectifs, les résultats attendus, le public et les contraintes.</p>
            </article>
            <article class="ld-step-card">
                <h3 data-i18n-fr="2. Créer les activités" data-i18n-en="2. Create activities">2. Créer les activités</h3>
                <p data-i18n-fr="Structurer le cours en activités d'enseignement et d'apprentissage, chacune reliée à un type d'apprentissage." data-i18n-en="Structure the course into teaching and learning activities, each linked to a learning type.">Structurer le cours en activités d'enseignement et d'apprentissage, chacune reliée à un type d'apprentissage.</p>
            </article>
            <article class="ld-step-card">
                <h3 data-i18n-fr="3. Visualiser l'équilibre" data-i18n-en="3. Visualize the balance">3. Visualiser l'équilibre</h3>
                <p data-i18n-fr="Comparer le temps prévu avec la durée réellement scénarisée et observer la proportion des six types d'apprentissage." data-i18n-en="Compare the planned learning time with the designed duration and observe the proportion of the six learning types.">Comparer le temps prévu avec la durée réellement scénarisée et observer la proportion des six types d'apprentissage.</p>
            </article>
            <article class="ld-step-card">
                <h3 data-i18n-fr="4. Ajuster le scénario" data-i18n-en="4. Refine the scenario">4. Ajuster le scénario</h3>
                <p data-i18n-fr="Décider des améliorations à apporter : ajouter une phase de discussion, renforcer la pratique, prévoir une production ou intégrer davantage de feedback." data-i18n-en="Decide what to improve: add a discussion phase, strengthen practice, plan a production task, or include more feedback.">Décider des améliorations à apporter&nbsp;: ajouter une phase de discussion, renforcer la pratique, prévoir une production ou intégrer davantage de feedback.</p>
            </article>
        </div>
    </section>

    <section class="ld-section">
        <h2 data-i18n-fr="Acquis d'apprentissage et Bloom" data-i18n-en="Learning Outcomes and Bloom">Acquis d'apprentissage et Bloom</h2>
        <p data-i18n-fr="Les acquis d'apprentissage peuvent être reliés à la taxonomie révisée de Bloom. Celle-ci aide à choisir des verbes d'action adaptés au niveau cognitif visé : se souvenir, comprendre, appliquer, analyser, évaluer ou créer." data-i18n-en="Learning outcomes can be linked to the revised Bloom taxonomy. It helps choose action verbs that match the intended cognitive level: remember, understand, apply, analyze, evaluate, or create.">Les acquis d'apprentissage peuvent être reliés à la taxonomie révisée de Bloom. Celle-ci aide à choisir des verbes d'action adaptés au niveau cognitif visé&nbsp;: se souvenir, comprendre, appliquer, analyser, évaluer ou créer.</p>
        <div class="ld-callout">
            <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
            <p><span data-i18n-fr="Pour formuler vos acquis, utilisez le tableau déjà intégré à l'application : " data-i18n-en="To write outcomes, use the table already included in the application: ">Pour formuler vos acquis, utilisez le tableau déjà intégré à l'application&nbsp;: </span><a class="ld-link" href="bloom.php" data-i18n-fr="voir la taxonomie de Bloom" data-i18n-en="view Bloom's taxonomy">voir la taxonomie de Bloom</a>.</p>
        </div>
    </section>
</main>
<?php render_site_footer(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function applyLearningDesignLanguage(lang) {
        document.documentElement.lang = lang === 'en' ? 'en' : 'fr';
        document.title = lang === 'en' ? 'Learning design | Scénarisation' : 'Learning design | Scénarisation';
        document.querySelectorAll('[data-i18n-fr]').forEach(function (el) {
            var value = lang === 'en' ? el.dataset.i18nEn : el.dataset.i18nFr;
            if (!value) return;
            el.textContent = value;
        });
    }

    var lang = 'fr';
    try {
        lang = localStorage.getItem('learningDesignerLang') || 'fr';
    } catch (error) {
        lang = 'fr';
    }
    applyLearningDesignLanguage(lang);

    var langSelect = document.getElementById('lang-select');
    if (langSelect) {
        langSelect.addEventListener('change', function () {
            applyLearningDesignLanguage(langSelect.value);
        });
    }
});
</script>
</body>
</html>
