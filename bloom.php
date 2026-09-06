<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// La navigation lit la session : elle doit démarrer avant tout HTML.
app_start_session();

$levels = [
    [
        'number' => 1,
        'labelFr' => 'Se souvenir',
        'labelEn' => 'Remember',
        'descriptionFr' => 'Retrouver, reconnaître ou rappeler des informations déjà rencontrées.',
        'descriptionEn' => 'Retrieve, recognize, or recall previously encountered information.',
        'verbsFr' => ['Citer', 'Définir', 'Décrire', 'Énumérer', 'Identifier', 'Lister', 'Localiser', 'Mémoriser', 'Nommer', 'Rappeler', 'Reconnaître', 'Reproduire'],
        'verbsEn' => ['Cite', 'Define', 'Describe', 'Enumerate', 'Identify', 'List', 'Locate', 'Memorize', 'Name', 'Recall', 'Recognize', 'Reproduce'],
    ],
    [
        'number' => 2,
        'labelFr' => 'Comprendre',
        'labelEn' => 'Understand',
        'descriptionFr' => 'Construire du sens à partir d’un message, d’un document, d’un exemple ou d’une explication.',
        'descriptionEn' => 'Construct meaning from a message, document, example, or explanation.',
        'verbsFr' => ['Clarifier', 'Classer', 'Comparer', 'Distinguer', 'Exemplifier', 'Expliquer', 'Illustrer', 'Inférer', 'Interpréter', 'Paraphraser', 'Reformuler', 'Résumer'],
        'verbsEn' => ['Clarify', 'Classify', 'Compare', 'Distinguish', 'Exemplify', 'Explain', 'Illustrate', 'Infer', 'Interpret', 'Paraphrase', 'Summarize', 'Translate'],
    ],
    [
        'number' => 3,
        'labelFr' => 'Appliquer',
        'labelEn' => 'Apply',
        'descriptionFr' => 'Utiliser une procédure, une méthode ou une connaissance dans une situation donnée.',
        'descriptionEn' => 'Use a procedure, method, or piece of knowledge in a given situation.',
        'verbsFr' => ['Appliquer', 'Calculer', 'Choisir', 'Compléter', 'Construire', 'Démontrer', 'Employer', 'Exécuter', 'Mettre en œuvre', 'Pratiquer', 'Produire', 'Résoudre', 'Utiliser'],
        'verbsEn' => ['Apply', 'Calculate', 'Choose', 'Complete', 'Construct', 'Demonstrate', 'Execute', 'Implement', 'Practice', 'Produce', 'Resolve', 'Use'],
    ],
    [
        'number' => 4,
        'labelFr' => 'Analyser',
        'labelEn' => 'Analyze',
        'descriptionFr' => 'Décomposer une information, repérer ses relations internes et comprendre son organisation.',
        'descriptionEn' => 'Break information into parts, identify relationships, and understand its organization.',
        'verbsFr' => ['Analyser', 'Attribuer', 'Comparer', 'Contraster', 'Décomposer', 'Déconstruire', 'Différencier', 'Discriminer', 'Distinguer', 'Examiner', 'Expérimenter', 'Organiser', 'Questionner', 'Structurer'],
        'verbsEn' => ['Analyze', 'Attribute', 'Compare', 'Contrast', 'Deconstruct', 'Differentiate', 'Discriminate', 'Distinguish', 'Examine', 'Experiment', 'Organize', 'Question', 'Structure'],
    ],
    [
        'number' => 5,
        'labelFr' => 'Évaluer',
        'labelEn' => 'Evaluate',
        'descriptionFr' => 'Porter un jugement argumenté à partir de critères, de preuves ou de contraintes.',
        'descriptionEn' => 'Make a reasoned judgment based on criteria, evidence, or constraints.',
        'verbsFr' => ['Apprécier', 'Argumenter', 'Choisir', 'Comparer', 'Conclure', 'Critiquer', 'Décider', 'Défendre', 'Estimer', 'Évaluer', 'Juger', 'Justifier', 'Recommander', 'Sélectionner'],
        'verbsEn' => ['Appreciate', 'Argue', 'Choose', 'Compare', 'Conclude', 'Criticize', 'Decide', 'Defend', 'Estimate', 'Evaluate', 'Judge', 'Justify', 'Recommend', 'Select'],
    ],
    [
        'number' => 6,
        'labelFr' => 'Créer',
        'labelEn' => 'Create',
        'descriptionFr' => 'Assembler des éléments pour produire une œuvre, une solution, un modèle ou un projet original.',
        'descriptionEn' => 'Put elements together to produce an original work, solution, model, or project.',
        'verbsFr' => ['Assembler', 'Combiner', 'Composer', 'Concevoir', 'Construire', 'Créer', 'Développer', 'Élaborer', 'Formuler', 'Générer', 'Imaginer', 'Inventer', 'Organiser', 'Planifier', 'Produire'],
        'verbsEn' => ['Assemble', 'Combine', 'Compose', 'Conceive', 'Construct', 'Create', 'Design', 'Develop', 'Elaborate', 'Formulate', 'Generate', 'Imagine', 'Invent', 'Organize', 'Plan', 'Produce'],
    ],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title>Taxonomie de Bloom | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
    <style>
        body.bloom-page { background: #fff; }
        .bloom-shell {
            width: min(var(--content-shell-width, 1180px), calc(100vw - var(--content-shell-gutter, 36px)));
            margin: 40px auto 80px;
        }
        .bloom-header {
            margin: 0 0 var(--page-section-gap);
            padding-bottom: 24px;
        }
        .bloom-kicker {
            margin: 0 0 8px;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 12px;
            font-weight: 800;
        }
        .bloom-title {
            margin: 0 0 14px;
        }
        .bloom-subtitle {
            width: 100%;
            max-width: none;
            margin: 0;
            color: var(--muted);
            line-height: var(--content-leading);
            font-size: 15px;
        }
        .bloom-poster-title {
            margin: var(--page-section-gap) 0 14px;
            border-radius: 6px 6px 0 0;
            background: #12295a;
            color: #fff;
            padding: 18px 22px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: clamp(18px, 3vw, 32px);
            font-weight: 900;
        }
        .bloom-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(150px, 1fr));
            gap: 12px;
        }
        .bloom-level {
            min-height: 760px;
            border: 1px solid rgba(15, 23, 42, 0.10);
            border-radius: 7px;
            background: var(--bloom-bg);
            padding: 24px 18px 28px;
            text-align: center;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            display: grid;
            grid-template-rows: 66px 102px 140px 1fr;
            align-items: start;
        }
        .bloom-level-1 { --bloom-bg: #c7b7e6; --bloom-ink: #2d2452; }
        .bloom-level-2 { --bloom-bg: #b9cef0; --bloom-ink: #19355f; }
        .bloom-level-3 { --bloom-bg: #c5d8b5; --bloom-ink: #243f24; }
        .bloom-level-4 { --bloom-bg: #ffe69a; --bloom-ink: #5c4300; }
        .bloom-level-5 { --bloom-bg: #ffc889; --bloom-ink: #603109; }
        .bloom-level-6 { --bloom-bg: #f69a96; --bloom-ink: #651f25; }
        .bloom-icon {
            display: grid;
            place-items: center;
            color: color-mix(in srgb, var(--bloom-bg) 80%, #000 20%);
            font-size: 56px;
        }
        .bloom-level h2 {
            margin: 0;
            padding: 6px 10px;
            border-radius: 6px;
            background: color-mix(in srgb, var(--bloom-bg) 80%, #000 20%);
            overflow-wrap: anywhere;
            white-space: nowrap;
            align-self: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        html body.bloom-page .bloom-level h2 {
            color: var(--bloom-ink);
        }
        .bloom-description {
            margin: 0;
            color: var(--bloom-ink);
            line-height: 1.48;
            font-size: 12px;
            font-weight: 700;
            align-self: start;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .bloom-verbs {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .bloom-verb {
            color: #111827;
            padding: 5px 0;
            font-size: clamp(15px, 1.35vw, 20px);
            line-height: 1.28;
            font-weight: 650;
        }
        [data-theme="dark"] body.bloom-page {
            background: #181816;
        }
        [data-theme="dark"] .bloom-subtitle {
            color: var(--text-body);
        }
        [data-theme="dark"] .bloom-poster-title {
            background: #0f1f44;
        }
        [data-theme="dark"] .bloom-level {
            border-color: rgba(15, 23, 42, 0.18);
            filter: saturate(0.88) brightness(0.92);
        }
        @media (max-width: 900px) {
            .bloom-grid {
                grid-template-columns: repeat(3, minmax(180px, 1fr));
            }
            .bloom-level {
                min-height: 640px;
            }
        }
        @media (max-width: 720px) {
            .bloom-grid {
                grid-template-columns: 1fr;
            }
            .bloom-poster-title {
                font-size: 18px;
            }
            .bloom-level {
                min-height: 0;
                grid-template-rows: auto auto auto auto;
                row-gap: 12px;
            }
            .bloom-level h2 {
                white-space: normal;
            }
        }
    </style>
</head>
<body class="bloom-page">
<?php render_site_nav(); ?>
<main class="bloom-shell with-nav">
    <header class="bloom-header">
        <p class="bloom-kicker" data-i18n-fr="Documentation" data-i18n-en="Documentation">Documentation</p>
        <h1 class="bloom-title" data-i18n-fr="Taxonomie révisée de Bloom" data-i18n-en="Revised Bloom's Taxonomy">Taxonomie révisée de Bloom</h1>
        <p class="bloom-subtitle" data-i18n-fr="Cette page rassemble les six niveaux cognitifs utilisés dans Scenarisation pour formuler des acquis d'apprentissage avec des verbes d'action adaptés." data-i18n-en="This page gathers the six cognitive levels used in Scenarisation to write learning outcomes with suitable action verbs.">Cette page rassemble les six niveaux cognitifs utilisés dans Scenarisation pour formuler des acquis d'apprentissage avec des verbes d'action adaptés.</p>
    </header>
    <div class="bloom-poster-title" data-i18n-fr="Verbes d'action de la taxonomie de Bloom" data-i18n-en="Bloom's Taxonomy Action Verbs">Verbes d'action de la taxonomie de Bloom</div>
    <section class="bloom-grid" aria-label="Niveaux de la taxonomie" data-i18n-attr="aria-label" data-i18n-fr="Niveaux de la taxonomie" data-i18n-en="Taxonomy levels">
        <?php foreach ($levels as $level): ?>
            <?php
            $icons = [
                1 => 'fa-brain',
                2 => 'fa-puzzle-piece',
                3 => 'fa-pencil',
                4 => 'fa-chart-line',
                5 => 'fa-lightbulb',
                6 => 'fa-hammer',
            ];
            ?>
            <article class="bloom-level bloom-level-<?= (int)$level['number'] ?>">
                <h2 data-bloom-fr="<?= h($level['labelFr']) ?>" data-bloom-en="<?= h($level['labelEn']) ?>"><?= h($level['labelFr']) ?></h2>
                <div class="bloom-icon"><i class="fa-solid <?= h($icons[(int)$level['number']] ?? 'fa-circle') ?>" aria-hidden="true"></i></div>
                <p class="bloom-description" data-bloom-fr="<?= h($level['descriptionFr']) ?>" data-bloom-en="<?= h($level['descriptionEn']) ?>"><?= h($level['descriptionFr']) ?></p>
                <ul class="bloom-verbs">
                    <?php foreach ($level['verbsFr'] as $index => $verbFr): ?>
                        <li class="bloom-verb" data-bloom-fr="<?= h($verbFr) ?>" data-bloom-en="<?= h($level['verbsEn'][$index] ?? $verbFr) ?>"><?= h($verbFr) ?></li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </section>
</main>
<?php render_site_footer(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function applyBloomLanguage(lang) {
        document.documentElement.lang = lang === 'en' ? 'en' : 'fr';
        document.title = lang === 'en' ? "Bloom's Taxonomy | Scenarisation" : 'Taxonomie de Bloom | Scenarisation';
        document.querySelectorAll('[data-i18n-fr]').forEach(function (el) {
            var value = lang === 'en' ? el.dataset.i18nEn : el.dataset.i18nFr;
            if (!value) return;
            var attrs = (el.dataset.i18nAttr || '').split(',').map(function (attr) {
                return attr.trim();
            }).filter(Boolean);
            if (attrs.length) {
                attrs.forEach(function (attr) { el.setAttribute(attr, value); });
            } else {
                el.textContent = value;
            }
        });
        document.querySelectorAll('[data-bloom-fr]').forEach(function (el) {
            el.textContent = lang === 'en' ? el.dataset.bloomEn : el.dataset.bloomFr;
        });
    }

    var lang = 'fr';
    try {
        lang = localStorage.getItem('learningDesignerLang') || 'fr';
    } catch (error) {
        lang = 'fr';
    }
    applyBloomLanguage(lang);

    var langSelect = document.getElementById('lang-select');
    if (langSelect) {
        langSelect.addEventListener('change', function () {
            applyBloomLanguage(langSelect.value);
        });
    }
});
</script>
</body>
</html>
