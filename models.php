<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

/**
 * Bibliothèque de modèles de scénarios pédagogiques.
 *
 * Chaque modèle est décrit dans une notation compacte, puis développé par
 * ld_model_design() en un document identique à celui produit par le concepteur
 * (voir hydrateState() dans js/interface.js).
 *
 * Notation compacte d'une activité :
 *   t     type d'apprentissage : read | investigate | practice | produce | discuss | collaborate
 *   d     durée en minutes
 *   g     groupe    : whole (défaut) | subgroups | individual
 *   tm    enseignement : directed | guided | supported | independent
 *   tp    ancien raccourci conservé : absent devient independent, present devient undefined
 *   sync  rythme    : sync (défaut) | async
 *   loc   mode de formation : onsite (en classe, défaut) | location_based | online | hybrid | other
 *   ev    évaluation: none (défaut) | diagnostic | formative | summative | certificative
 *   aias  niveau 1-5, 'na' (non applicable) ou absent (à décider)
 *   desc  description destinée à l'enseignant
 *   inst  consigne destinée aux élèves
 *   notes notes de l'activité
 *
 * Les contenus à personnaliser sont signalés par des jalons entre crochets,
 * du type [MATIÈRE] ou [NOTION], collectés automatiquement par
 * ld_model_placeholders() pour être affichés à l'enseignant.
 */

const LD_MODEL_AIAS_VERSION = '2.1';

const LD_MODEL_BLOOM_LABELS = [
    'souvenir' => ['fr' => 'Se souvenir', 'en' => 'Remember'],
    'comprendre' => ['fr' => 'Comprendre', 'en' => 'Understand'],
    'appliquer' => ['fr' => 'Appliquer', 'en' => 'Apply'],
    'analyser' => ['fr' => 'Analyser', 'en' => 'Analyze'],
    'evaluer' => ['fr' => 'Évaluer', 'en' => 'Evaluate'],
    'creer' => ['fr' => 'Créer', 'en' => 'Create'],
];

const LD_MODEL_TYPE_LABELS = [
    'read' => ['fr' => 'Lire / Regarder / Écouter', 'en' => 'Read / Watch / Listen'],
    'investigate' => ['fr' => 'Investiguer', 'en' => 'Investigate'],
    'practice' => ['fr' => 'Pratiquer', 'en' => 'Practise'],
    'produce' => ['fr' => 'Produire', 'en' => 'Produce'],
    'discuss' => ['fr' => 'Discuter', 'en' => 'Discuss'],
    'collaborate' => ['fr' => 'Collaborer', 'en' => 'Collaborate'],
    'undefined' => ['fr' => 'Non défini', 'en' => 'Undefined'],
];

/**
 * Titres anglais des moments, dans le même ordre que les moments de chaque
 * modèle. Les contenus français restent la source du modèle ; ce catalogue
 * évite qu'un changement de langue laisse le plan des cartes en français.
 */
const LD_MODEL_MOMENT_TITLES_EN = [
    'introduire-chapitre' => [
        '1. Opening hook',
        '2. What I think I know',
        '3. Framing the problem',
        '4. First structured input',
        '5. Written summary and chapter outline',
    ],
    'reactiver-prerequis' => [
        '1. Diagnostic quiz',
        '2. Peer correction and discussion',
        '3. Targeted review',
    ],
    'classe-inversee' => [
        '1. Learning resource at home',
        '2. Comprehension check',
        '3. Group application task',
        '4. Whole-class review and consolidation',
    ],
    'cours-dialogue' => [
        '1. Opening question',
        '2. Interactive input 1',
        '3. Written reformulation',
        '4. Interactive input 2',
        '5. Completing the summary',
    ],
    'etude-documents' => [
        '1. Contextualisation',
        '2. Extracting information from the sources',
        '3. Comparing findings',
        '4. Written response',
    ],
    'modelage-pratique-autonomie' => [
        '1. Modelling: I do',
        '2. Guided practice: we do',
        '3. Practice in pairs',
        '4. Independent practice',
    ],
    'cours-magistral-sequence' => [
        '1. First input block and check',
        '2. Second input block and check',
        '3. Third input block and check',
        '4. Reviewing difficulties',
    ],
    'entrainement-differencie' => [
        '1. Quick diagnostic',
        '2. Three parallel workshops',
        '3. Sharing methods',
    ],
    'resolution-problemes' => [
        '1. Presenting the problem',
        '2. Individual investigation',
        '3. Group discussion',
        '4. Two groups present their work',
        '5. Consolidation',
    ],
    'revision-avant-evaluation' => [
        '1. Chapter mind map',
        '2. Team quiz challenge',
        '3. Targeted remediation',
    ],
    'debat-argumente' => [
        '1. Framing the controversy',
        '2. Building the argument file',
        '3. Assigning roles',
        '4. Structured debate',
        '5. Vote and metacognitive review',
    ],
    'expose-eleve' => [
        '1. Preparation outside class',
        '2. Presentations',
        '3. Peer assessment',
        '4. Reviewing the content',
    ],
    'jeu-de-role' => [
        '1. Distributing roles and briefing packs',
        '2. Preparation in delegations',
        '3. Simulation',
        '4. Debrief',
    ],
    'cercle-lecture' => [
        '1. Rereading the source text',
        '2. Structured round-table discussion',
        '3. Reflective writing',
    ],
    'tache-complexe' => [
        '1. Mission and success criteria',
        '2. Allocating tasks and planning',
        '3. Production',
        '4. Cross-presentation',
        '5. Self-assessment',
    ],
    'ecriture-guidee' => [
        '1. Analysing an expert model',
        '2. Planning and drafting',
        '3. Criteria-based peer review',
        '4. Rewriting',
    ],
    'production-mediatique' => [
        '1. Analysing the target format',
        '2. Storyboarding',
        '3. Production',
        '4. Publication and feedback',
        '5. Technical and editorial review',
    ],
    'demarche-investigation' => [
        '1. Trigger situation',
        '2. Formulating hypotheses',
        '3. Designing the protocol',
        '4. Experimentation and measurements',
        '5. Interpretation and conclusion',
    ],
    'recherche-documentaire' => [
        '1. Framing the research question',
        '2. Research and referenced collection',
        '3. Cross-checking and assessing reliability',
        '4. Referenced summary sheet',
    ],
    'evaluation-correction-active' => [
        '1. Assessment',
        '2. Analysing one’s own errors',
        '3. Remediation workshops',
        '4. Progress plan',
    ],
    'bilan-metacognitif' => [
        '1. Reviewing the stated learning outcomes',
        '2. Self-positioning',
        '3. Reflective writing',
        '4. Sharing effective strategies',
    ],
    'evaluation-par-les-pairs' => [
        '1. Understanding the rubric',
        '2. Peer assessment',
        '3. Oral feedback to a classmate',
        '4. Improving one’s production',
    ],
    'ia-generative-encadree' => [
        '1. Framing authorised use',
        '2. First production without AI',
        '3. AI-assisted improvement and prompt log',
        '4. Before-and-after comparison and justification',
    ],
    'esprit-critique-ia' => [
        '1. What I think I know about AI',
        '2. Testing its probabilistic nature',
        '3. Hunting for fabricated sources',
        '4. Building a verification protocol',
        '5. Revisiting the initial hypotheses',
    ],
    'sequence-cinq-seances' => [
        'Lesson 1. Launch',
        'Lesson 2. Input',
        'Lesson 3. Practice',
        'Lesson 4. Production',
        'Lesson 5. Assessment',
    ],
    'sortie-pedagogique' => [
        '1. Before: preparation in class',
        '2. During: observations on site',
        '3. After: analysis and production',
    ],
    'remediation-post-evaluation' => [
        '1. Diagnosing the recurring error',
        '2. Explicit reteaching',
        '3. Targeted practice',
        '4. Exit check',
    ],
    'premiere-seance-annee' => [
        '1. Introductions and framework for the year',
        '2. Co-constructing the class agreement',
        '3. Diagnostic assessment',
        '4. Getting started',
    ],
];

const LD_MODEL_PLACEHOLDERS_EN = [
    '[CAPSULE / DOCUMENT]' => '[LEARNING RESOURCE / DOCUMENT]',
    '[CHAPITRE]' => '[CHAPTER]',
    '[CONTROVERSE]' => '[CONTROVERSY]',
    '[CORPUS / SOURCES]' => '[SOURCE SET / SOURCES]',
    '[DATE]' => '[DATE]',
    '[DOCUMENT 1]' => '[DOCUMENT 1]',
    '[DOCUMENT 2]' => '[DOCUMENT 2]',
    '[DOCUMENT DÉCLENCHEUR]' => '[TRIGGER DOCUMENT]',
    '[DURÉE EXPOSÉ]' => '[PRESENTATION LENGTH]',
    '[EXEMPLE 1]' => '[EXAMPLE 1]',
    '[EXEMPLE 2]' => '[EXAMPLE 2]',
    '[EXEMPLE EXPERT]' => '[EXPERT MODEL]',
    '[LIEU DE VISITE]' => '[VISIT LOCATION]',
    '[MATIÈRE]' => '[SUBJECT]',
    '[MÉTHODE / PROCÉDURE]' => '[METHOD / PROCEDURE]',
    '[MISSION]' => '[MISSION]',
    '[NIVEAU]' => '[LEVEL]',
    '[NOMBRE]' => '[NUMBER]',
    '[NOTION 1]' => '[CONCEPT 1]',
    '[NOTION 2]' => '[CONCEPT 2]',
    '[NOTION MAL ACQUISE]' => '[CONCEPT NOT YET MASTERED]',
    '[NOTION VOISINE]' => '[RELATED CONCEPT]',
    '[OUTIL DE PRODUCTION]' => '[PRODUCTION TOOL]',
    '[OUTIL IA]' => '[AI TOOL]',
    '[PHÉNOMÈNE ÉTUDIÉ]' => '[PHENOMENON STUDIED]',
    '[PRÉREQUIS 1]' => '[PREREQUISITE 1]',
    '[PRÉREQUIS 2]' => '[PREREQUISITE 2]',
    '[PROBLÈME]' => '[PROBLEM]',
    '[PRODUCTION ATTENDUE]' => '[EXPECTED OUTPUT]',
    '[PRODUCTION DE RETOUR]' => '[FOLLOW-UP OUTPUT]',
    '[PRODUCTION ÉVALUÉE]' => '[ASSESSED OUTPUT]',
    '[PRODUCTION MÉDIATIQUE]' => '[MEDIA OUTPUT]',
    '[PUBLIC VISÉ]' => '[TARGET AUDIENCE]',
    '[QUESTION]' => '[QUESTION]',
    '[QUESTION DE DISCUSSION]' => '[DISCUSSION QUESTION]',
    '[QUESTION DE RECHERCHE]' => '[RESEARCH QUESTION]',
    '[QUESTION DE VISITE]' => '[VISIT QUESTION]',
    '[SÉQUENCE]' => '[UNIT]',
    '[SITUATION SIMULÉE]' => '[SIMULATED SITUATION]',
    '[SUJET]' => '[TOPIC]',
    '[SUJET D’ÉCRITURE]' => '[WRITING PROMPT]',
    '[SUJET VÉRIFIÉ]' => '[TOPIC TO VERIFY]',
    '[SUJETS D’EXPOSÉ]' => '[PRESENTATION TOPICS]',
    '[TÂCHE]' => '[TASK]',
    '[TÂCHE D’APPLICATION]' => '[APPLICATION TASK]',
    '[TEXTE]' => '[TEXT]',
    '[TYPE D’ÉCRIT]' => '[TEXT TYPE]',
    '[ÉLÉMENT 1]' => '[ELEMENT 1]',
    '[ÉLÉMENT 2]' => '[ELEMENT 2]',
    '[ÉLÉMENT 3]' => '[ELEMENT 3]',
];

$LD_MODEL_CONTENT_EN = require __DIR__ . '/lib/model-content-en.php';

$LD_MODEL_FAMILIES = [
    'entrer' => [
        'fr' => 'Entrer dans un contenu',
        'en' => 'Enter a new content',
        'icon' => 'fa-solid fa-door-open',
        'descFr' => 'Lancer un chapitre, réactiver des prérequis, exploiter un travail préparatoire.',
        'descEn' => 'Launch a chapter, reactivate prior knowledge, exploit preparatory work.',
    ],
    'comprendre' => [
        'fr' => 'Faire comprendre',
        'en' => 'Build understanding',
        'icon' => 'fa-solid fa-lightbulb',
        'descFr' => 'Transmettre, expliciter, faire travailler des documents et des procédures.',
        'descEn' => 'Teach, make thinking explicit, work on documents and procedures.',
    ],
    'entrainer' => [
        'fr' => "S'entraîner et consolider",
        'en' => 'Practise and consolidate',
        'icon' => 'fa-solid fa-dumbbell',
        'descFr' => 'Faire pratiquer, différencier, préparer une évaluation.',
        'descEn' => 'Practise, differentiate, prepare for assessment.',
    ],
    'argumenter' => [
        'fr' => 'Parler et argumenter',
        'en' => 'Speak and argue',
        'icon' => 'fa-solid fa-comments',
        'descFr' => 'Débattre, exposer, jouer un rôle, discuter un texte.',
        'descEn' => 'Debate, present, role-play, discuss a text.',
    ],
    'produire' => [
        'fr' => 'Produire et créer',
        'en' => 'Produce and create',
        'icon' => 'fa-solid fa-pen-ruler',
        'descFr' => 'Mener une tâche complexe, écrire, réaliser un média, enquêter.',
        'descEn' => 'Run a complex task, write, make media, investigate.',
    ],
    'evaluer' => [
        'fr' => 'Évaluer et faire le bilan',
        'en' => 'Assess and review',
        'icon' => 'fa-solid fa-clipboard-check',
        'descFr' => 'Évaluer, corriger activement, remédier, faire le point.',
        'descEn' => 'Assess, correct actively, remediate, take stock.',
    ],
    'ia' => [
        'fr' => "Usages encadrés de l'IA",
        'en' => 'Framed use of AI',
        'icon' => 'fa-solid fa-robot',
        'descFr' => "Faire travailler avec l'IA générative en explicitant le niveau AIAS attendu.",
        'descEn' => 'Work with generative AI while making the expected AIAS level explicit.',
    ],
    'organiser' => [
        'fr' => "Canevas d'organisation",
        'en' => 'Planning canvases',
        'icon' => 'fa-solid fa-diagram-project',
        'descFr' => 'Squelettes de séquence, sortie, remédiation, rentrée.',
        'descEn' => 'Sequence skeletons, field trip, remediation, first lesson.',
    ],
];

/**
 * Développe la notation compacte d'une activité en activité complète.
 */
function ld_model_localized_text(mixed $frenchValue, mixed $englishValue, string $lang): string
{
    $text = (string)($lang === 'en' && is_string($englishValue) ? $englishValue : $frenchValue);
    return $lang === 'en' ? strtr($text, LD_MODEL_PLACEHOLDERS_EN) : $text;
}

function ld_model_activity(array $spec, string $prefix, array $translation = [], string $lang = 'fr'): array
{
    $aias = $spec['aias'] ?? null;
    if (is_int($aias) && $aias >= 1 && $aias <= 5) {
        $aiasState = ['version' => LD_MODEL_AIAS_VERSION, 'status' => 'specified', 'level' => $aias];
    } elseif ($aias === 'na') {
        $aiasState = ['version' => LD_MODEL_AIAS_VERSION, 'status' => 'not_applicable', 'level' => null];
    } else {
        $aiasState = ['version' => LD_MODEL_AIAS_VERSION, 'status' => 'undecided', 'level' => null];
    }

    $teachingMode = (string)($spec['tm'] ?? '');
    if (!in_array($teachingMode, ['directed', 'guided', 'supported', 'independent'], true)) {
        $teachingMode = (string)($spec['tp'] ?? 'present') === 'absent'
            ? 'independent'
            : 'undefined';
    }

    return [
        'id' => $prefix,
        'type' => (string)($spec['t'] ?? 'undefined'),
        'duration' => max(1, (int)($spec['d'] ?? 5)),
        'groupMode' => (string)($spec['g'] ?? 'whole'),
        'teachingMode' => $teachingMode,
        'syncMode' => (string)($spec['sync'] ?? 'sync'),
        'locationMode' => (string)($spec['loc'] ?? 'onsite'),
        'evaluationMode' => (string)($spec['ev'] ?? 'none'),
        'aias' => $aiasState,
        'description' => ld_model_localized_text($spec['desc'] ?? '', $translation['description'] ?? null, $lang),
        'instructions' => ld_model_localized_text($spec['inst'] ?? '', $translation['instructions'] ?? null, $lang),
        'notes' => ld_model_localized_text($spec['notes'] ?? '', $translation['notes'] ?? null, $lang),
        'tools' => [],
    ];
}

/**
 * Durée totale conçue, en minutes.
 */
function ld_model_minutes(array $model): int
{
    $total = 0;
    foreach ($model['moments'] as $moment) {
        foreach ($moment['a'] as $activity) {
            $total += max(1, (int)($activity['d'] ?? 5));
        }
    }
    return $total;
}

function ld_model_activity_count(array $model): int
{
    $count = 0;
    foreach ($model['moments'] as $moment) {
        $count += count($moment['a']);
    }
    return $count;
}

/**
 * Document complet, prêt à être chargé par le concepteur.
 */
function ld_model_design(array $model, string $lang = 'fr'): array
{
    global $LD_MODEL_CONTENT_EN;

    $lang = $lang === 'en' ? 'en' : 'fr';
    $translation = $lang === 'en'
        ? ($LD_MODEL_CONTENT_EN[(string)$model['id']] ?? [])
        : [];
    $minutes = ld_model_minutes($model);
    $dayHours = 7;

    $outcomes = [];
    foreach (($model['outcomes'] ?? []) as $index => $outcome) {
        [$category, $verb, $text] = $outcome;
        $translatedOutcome = $translation['outcomes'][$index] ?? [];
        $outcomes[] = [
            'id' => 'model-' . $model['id'] . '-o' . ($index + 1),
            'category' => $category,
            'categoryLabel' => LD_MODEL_BLOOM_LABELS[$category][$lang] ?? '',
            'verb' => ld_model_localized_text($verb, $translatedOutcome['verb'] ?? null, $lang),
            'text' => ld_model_localized_text($text, $translatedOutcome['text'] ?? null, $lang),
        ];
    }

    $sessions = [];
    foreach ($model['moments'] as $momentIndex => $moment) {
        $translatedMoment = $translation['moments'][$momentIndex] ?? [];
        $activities = [];
        foreach ($moment['a'] as $activityIndex => $activity) {
            $activities[] = ld_model_activity(
                $activity,
                'model-' . $model['id'] . '-m' . ($momentIndex + 1) . '-a' . ($activityIndex + 1),
                $translatedMoment['activities'][$activityIndex] ?? [],
                $lang
            );
        }
        $englishMomentTitles = LD_MODEL_MOMENT_TITLES_EN[(string)$model['id']] ?? [];
        $sessions[] = [
            'id' => 'model-' . $model['id'] . '-m' . ($momentIndex + 1),
            'title' => $lang === 'en'
                ? (string)($englishMomentTitles[$momentIndex] ?? $moment['t'])
                : (string)$moment['t'],
            'objectives' => ld_model_localized_text($moment['o'] ?? '', $translatedMoment['objectives'] ?? null, $lang),
            'intentions' => ld_model_localized_text($moment['i'] ?? '', $translatedMoment['intentions'] ?? null, $lang),
            'notes' => ld_model_localized_text($moment['n'] ?? '', $translatedMoment['notes'] ?? null, $lang),
            'notesExpanded' => false,
            'activities' => $activities,
        ];
    }

    return [
        'allNotesExpanded' => false,
        'intentionsCollapsed' => false,
        'topPanelCollapsed' => false,
        'meta' => [
            'name' => (string)$model[$lang === 'en' ? 'titleEn' : 'titleFr'],
            'uiLanguage' => $lang,
            'dayHours' => $dayHours,
            'learningDays' => 0,
            'learningHours' => intdiv($minutes, 60),
            'learningMinutes' => $minutes % 60,
            'modeDelivery' => (string)($model['mode'] ?? 'onsite'),
            'schoolSystem' => '',
            'schoolLevel' => '',
            'sizeClass' => '',
            'designers' => '',
            'trainers' => '',
            'description' => ld_model_localized_text($model['description'] ?? '', $translation['description'] ?? null, $lang),
            'command' => ld_model_localized_text($model['command'] ?? '', $translation['command'] ?? null, $lang),
            'personas' => ld_model_localized_text($model['personas'] ?? '', $translation['personas'] ?? null, $lang),
            'sliders' => $outcomes,
            'activeTab' => 'settings',
            'boardLayout' => 'columns',
            'modelId' => (string)$model['id'],
        ],
        'sessions' => $sessions,
        'partitionLineConfig' => [
            ['type' => 'locationMode', 'label' => $lang === 'en' ? 'Classroom-based' : 'En classe', 'value' => 'onsite', 'visible' => true],
            ['type' => 'locationMode', 'label' => $lang === 'en' ? 'Location-based' : 'Sur site', 'value' => 'location_based', 'visible' => true],
            ['type' => 'locationMode', 'label' => $lang === 'en' ? 'Online' : 'En ligne', 'value' => 'online', 'visible' => true],
            ['type' => 'locationMode', 'label' => $lang === 'en' ? 'Blended' : 'Hybride', 'value' => 'hybrid', 'visible' => true],
            ['type' => 'locationMode', 'label' => $lang === 'en' ? 'Other' : 'Autre', 'value' => 'other', 'visible' => true],
        ],
    ];
}

/**
 * Aperçu de la structure : un moment par ligne, avec ses activités typées.
 */
function ld_model_outline(array $model): array
{
    $outline = [];
    $englishTitles = LD_MODEL_MOMENT_TITLES_EN[(string)$model['id']] ?? [];
    foreach ($model['moments'] as $momentIndex => $moment) {
        $activities = [];
        foreach ($moment['a'] as $activity) {
            $type = (string)($activity['t'] ?? 'undefined');
            $activities[] = [
                'type' => $type,
                'typeLabelFr' => LD_MODEL_TYPE_LABELS[$type]['fr'] ?? $type,
                'typeLabelEn' => LD_MODEL_TYPE_LABELS[$type]['en'] ?? $type,
                'minutes' => max(1, (int)($activity['d'] ?? 5)),
            ];
        }
        $titleFr = (string)$moment['t'];
        $outline[] = [
            // `title` is retained for older clients of the JSON endpoint.
            'title' => $titleFr,
            'titleFr' => $titleFr,
            'titleEn' => (string)($englishTitles[$momentIndex] ?? $titleFr),
            'activities' => $activities,
        ];
    }
    return $outline;
}

/**
 * Jalons [ENTRE CROCHETS] présents dans le modèle : ce que l'enseignant doit compléter.
 */
function ld_model_placeholders(array $model): array
{
    $haystack = json_encode($model, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($haystack)) {
        return [];
    }
    if (!preg_match_all('/\[[A-ZÀ-Ÿ0-9][^\[\]]{0,60}\]/u', $haystack, $matches)) {
        return [];
    }
    $found = array_values(array_unique($matches[0]));
    sort($found, SORT_NATURAL | SORT_FLAG_CASE);
    return $found;
}

function ld_model_placeholders_en(array $placeholders): array
{
    return array_map(
        static fn (string $placeholder): string => LD_MODEL_PLACEHOLDERS_EN[$placeholder] ?? $placeholder,
        $placeholders
    );
}

/**
 * Fiche d'un modèle, sans le document complet (utilisée par la fenêtre d'import).
 */
function ld_model_entry(array $model, array $families): array
{
    $family = $families[$model['family']] ?? ['fr' => '', 'en' => '', 'icon' => 'fa-solid fa-shapes'];
    $placeholdersFr = ld_model_placeholders($model);
    return [
        'id' => (string)$model['id'],
        'family' => (string)$model['family'],
        'familyLabelFr' => (string)$family['fr'],
        'familyLabelEn' => (string)$family['en'],
        'icon' => (string)($model['icon'] ?? $family['icon']),
        'titleFr' => (string)$model['titleFr'],
        'titleEn' => (string)$model['titleEn'],
        'summaryFr' => (string)$model['summaryFr'],
        'summaryEn' => (string)$model['summaryEn'],
        'keywords' => (string)($model['keywords'] ?? ''),
        'mode' => (string)($model['mode'] ?? 'onsite'),
        'minutes' => ld_model_minutes($model),
        'momentCount' => count($model['moments']),
        'activityCount' => ld_model_activity_count($model),
        'outcomeCount' => count($model['outcomes'] ?? []),
        // `placeholders` is retained for older clients of the JSON endpoint.
        'placeholders' => $placeholdersFr,
        'placeholdersFr' => $placeholdersFr,
        'placeholdersEn' => ld_model_placeholders_en($placeholdersFr),
        'outline' => ld_model_outline($model),
    ];
}

/**
 * Durée lisible : « 55 min », « 1 h 50 ».
 */
function ld_model_duration_label(int $minutes, string $lang = 'fr'): string
{
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    $hourUnit = $lang === 'en' ? 'h' : 'h';
    if ($rest === 0) {
        return $hours . ' ' . $hourUnit;
    }
    return $hours . ' ' . $hourUnit . ' ' . str_pad((string)$rest, 2, '0', STR_PAD_LEFT);
}

$LD_SCENARIO_MODELS = [

    // ── Entrer dans un contenu ──────────────────────────────────────────────
    [
        'id' => 'introduire-chapitre',
        'family' => 'entrer',
        'icon' => 'fa-solid fa-door-open',
        'titleFr' => 'Introduire un chapitre',
        'titleEn' => 'Introduce a chapter',
        'summaryFr' => 'Accroche, représentations initiales, problématisation, premier apport et trace écrite : la séance d’ouverture d’un nouveau chapitre.',
        'summaryEn' => 'Hook, prior conceptions, problem framing, first input and written record: the opening lesson of a new chapter.',
        'keywords' => 'accroche, problématisation, représentations initiales, plan, ouverture, question directrice',
        'mode' => 'onsite',
        'description' => 'Séance d’ouverture de [CHAPITRE] en [MATIÈRE], [NIVEAU]. Les élèves partent de leurs représentations, formulent la question directrice, reçoivent un premier apport structuré et repartent avec le plan du chapitre.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : entrée dans [CHAPITRE].',
        'personas' => '1) Rendre visibles les représentations initiales sur [NOTION 1]. 2) Installer la question directrice qui guidera tout le chapitre.',
        'outcomes' => [
            ['souvenir', 'Nommer', 'Nommer les notions clés de [CHAPITRE] et leur définition minimale.'],
            ['comprendre', 'Reformuler', 'Reformuler avec ses mots la question directrice de [CHAPITRE].'],
            ['comprendre', 'Expliquer', 'Expliquer [NOTION 1] à partir de l’exemple étudié en classe.'],
            ['analyser', 'Distinguer', 'Distinguer ce qui relève de [NOTION 1] de ce qui relève de [NOTION VOISINE].'],
        ],
        'moments' => [
            [
                't' => '1. Accroche',
                'o' => 'Susciter l’intérêt et créer un besoin de savoir à partir d’un document déclencheur.',
                'i' => 'Accroche brève et concrète : elle sert à ouvrir un écart entre ce que les élèves croient et ce qu’ils observent, pas à exposer le contenu.',
                'n' => 'Projeter [DOCUMENT DÉCLENCHEUR] avant l’entrée en classe. Ne rien expliquer à ce stade.',
                'a' => [
                    ['t' => 'read', 'd' => 8, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Projection de [DOCUMENT DÉCLENCHEUR] (image, extrait vidéo, chiffre, titre de presse) sans commentaire. Les élèves notent ce qu’ils observent et ce qui les surprend.',
                     'inst' => 'Regarde [DOCUMENT DÉCLENCHEUR] en silence. Note deux choses : ce que tu observes, et ce qui te surprend.'],
                ],
            ],
            [
                't' => '2. Ce que je crois savoir',
                'o' => 'Faire émerger les représentations initiales et repérer les conceptions erronées.',
                'i' => 'Diagnostic à valeur de conflit sociocognitif : rien n’est corrigé ici. Les formulations d’élèves sont conservées pour être reprises en fin de chapitre.',
                'n' => 'Noter au tableau trois formulations d’élèves, sans les corriger, et les photographier.',
                'a' => [
                    ['t' => 'discuss', 'd' => 10, 'g' => 'whole', 'ev' => 'diagnostic', 'aias' => 'na',
                     'desc' => 'Trois affirmations sur [NOTION 1] soumises au vote à main levée, puis justification par deux élèves de camps opposés. Aucune validation à ce stade.',
                     'inst' => 'Pour chaque affirmation projetée : d’accord / pas d’accord / je ne sais pas. Si je t’interroge, dis ce qui te fait penser cela, sans chercher la bonne réponse.'],
                ],
            ],
            [
                't' => '3. Problématisation',
                'o' => 'Transformer l’étonnement initial en question directrice du chapitre.',
                'i' => 'La question est d’abord formulée par les élèves, puis reformulée par l’enseignant. Elle reste affichée pendant tout le chapitre.',
                'a' => [
                    ['t' => 'discuss', 'd' => 7, 'g' => 'subgroups', 'aias' => 'na',
                     'desc' => 'Par deux, les élèves écrivent la question à laquelle le chapitre devra répondre. Deux ou trois propositions sont retenues puis fusionnées.',
                     'inst' => 'Avec ton voisin, écris en une phrase la question à laquelle ce chapitre doit répondre. Elle commence par « Comment », « Pourquoi » ou « Dans quelle mesure ».'],
                ],
            ],
            [
                't' => '4. Premier apport structuré',
                'o' => 'Donner les notions et le vocabulaire minimaux pour entrer dans le chapitre.',
                'i' => 'Apport court, découpé, suivi d’une reformulation écrite : l’attention en exposé décroche au-delà d’une quinzaine de minutes.',
                'a' => [
                    ['t' => 'read', 'd' => 12, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Exposé de l’enseignant sur [NOTION 1] et [NOTION 2], appuyé sur l’exemple du document déclencheur.',
                     'inst' => 'Écoute et note uniquement les mots nouveaux et leurs définitions. La trace écrite viendra ensuite.'],
                    ['t' => 'practice', 'd' => 5, 'g' => 'individual', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Reformulation écrite de la notion centrale, relevée oralement auprès de trois élèves choisis au hasard.',
                     'inst' => 'En deux lignes, explique [NOTION 1] avec tes mots, sans regarder tes notes.'],
                ],
            ],
            [
                't' => '5. Trace écrite et plan du chapitre',
                'o' => 'Fixer le vocabulaire, la question directrice et l’itinéraire du chapitre.',
                'i' => 'Trace écrite co-construite plutôt que dictée : l’élève doit pouvoir retrouver seul la logique du chapitre.',
                'a' => [
                    ['t' => 'produce', 'd' => 13, 'g' => 'individual', 'aias' => 'na',
                     'desc' => 'Complétion d’une fiche de chapitre : question directrice, définitions, plan en trois parties, échéances.',
                     'inst' => 'Complète la fiche de chapitre : la question directrice, les définitions, le plan et la date de l’évaluation.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'reactiver-prerequis',
        'family' => 'entrer',
        'icon' => 'fa-solid fa-rotate-left',
        'titleFr' => 'Réactiver les prérequis',
        'titleEn' => 'Reactivate prior knowledge',
        'summaryFr' => 'Demi-séance de diagnostic : quiz de positionnement, correction entre pairs, reprise ciblée des points fragiles.',
        'summaryEn' => 'Half-lesson diagnostic: positioning quiz, peer correction, targeted review of weak points.',
        'keywords' => 'prérequis, diagnostic, quiz, remise à niveau, correction entre pairs',
        'mode' => 'onsite',
        'description' => 'Demi-séance de 30 min avant d’entamer [CHAPITRE] en [MATIÈRE]. Objectif : savoir précisément ce qui est acquis de [PRÉREQUIS 1] et [PRÉREQUIS 2] avant d’avancer.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : les acquis de [PRÉREQUIS 1] et [PRÉREQUIS 2] conditionnent l’entrée dans [CHAPITRE].',
        'personas' => '1) Mesurer l’état réel des prérequis. 2) Traiter immédiatement les deux erreurs les plus fréquentes.',
        'outcomes' => [
            ['souvenir', 'Rappeler', 'Rappeler la définition et la propriété principale de [PRÉREQUIS 1].'],
            ['appliquer', 'Appliquer', 'Appliquer [PRÉREQUIS 2] à un exercice court sans aide.'],
            ['analyser', 'Examiner', 'Examiner sa propre copie pour situer précisément l’origine de son erreur.'],
        ],
        'moments' => [
            [
                't' => '1. Quiz de positionnement',
                'o' => 'Mesurer ce qui est réellement disponible en mémoire avant le nouveau chapitre.',
                'i' => 'Test sans note et sans document : sa fonction est de rendre les lacunes visibles à l’élève lui-même, pas de sanctionner.',
                'n' => 'Prévoir [NOMBRE] questions courtes, dont deux au moins portent sur l’erreur classique de [PRÉREQUIS 2].',
                'a' => [
                    ['t' => 'practice', 'd' => 10, 'g' => 'individual', 'ev' => 'diagnostic', 'aias' => 'na',
                     'desc' => 'Quiz court sur [PRÉREQUIS 1] et [PRÉREQUIS 2], sans note et sans documents.',
                     'inst' => 'Réponds seul, sans tes notes. Ce test n’est pas noté : ce qui compte est de repérer ce que tu ne maîtrises pas encore.'],
                ],
            ],
            [
                't' => '2. Correction commentée entre pairs',
                'o' => 'Faire expliciter les procédures et confronter les démarches.',
                'i' => 'La correction par un pair oblige à verbaliser la procédure ; l’enseignant circule et relève les erreurs récurrentes pour le moment suivant.',
                'a' => [
                    ['t' => 'discuss', 'd' => 10, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Échange des copies par deux : chaque élève explique à l’autre comment il a procédé, sans donner la réponse toute faite.',
                     'inst' => 'Échange ta copie avec ton voisin. Pour chaque écart, explique-lui comment tu as raisonné. Entourez ensemble les questions où vous n’êtes pas d’accord.'],
                ],
            ],
            [
                't' => '3. Reprise ciblée',
                'o' => 'Corriger les deux points les plus fragiles avant d’entrer dans le nouveau chapitre.',
                'i' => 'Reprise volontairement limitée à deux points : mieux vaut réinstaller solidement l’essentiel que survoler tout le programme antérieur.',
                'a' => [
                    ['t' => 'read', 'd' => 10, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Reprise au tableau des deux erreurs les plus fréquentes relevées, avec un contre-exemple explicite.',
                     'inst' => 'Note la procédure corrigée dans ton cahier, à l’endroit où tu t’étais trompé, et signale d’une couleur ce qui reste à revoir chez toi.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'classe-inversee',
        'family' => 'entrer',
        'icon' => 'fa-solid fa-arrows-rotate',
        'titleFr' => 'Classe inversée',
        'titleEn' => 'Flipped classroom',
        'summaryFr' => 'Capsule travaillée à la maison, vérification de compréhension en classe, puis tâche d’application en groupes et mise en commun.',
        'summaryEn' => 'Learning resource studied at home, comprehension check in class, then a group application task and whole-class review.',
        'keywords' => 'classe inversée, capsule, travail à la maison, hybride, application',
        'mode' => 'hybrid',
        'description' => 'Séance hybride sur [NOTION 1] : 20 min de travail asynchrone à la maison sur [CAPSULE / DOCUMENT], puis 55 min en classe consacrées à l’application et à la levée des obstacles.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : [NOTION 1]. Le temps de classe est réservé à ce qui ne peut pas se faire seul.',
        'personas' => '1) Déplacer l’exposition au contenu hors classe. 2) Consacrer le temps de présence à la mise en application et au traitement des difficultés.',
        'outcomes' => [
            ['comprendre', 'Expliquer', 'Expliquer [NOTION 1] à partir de la capsule travaillée à la maison.'],
            ['appliquer', 'Appliquer', 'Appliquer [NOTION 1] à une situation nouvelle en groupe.'],
            ['analyser', 'Questionner', 'Questionner un point resté obscur en formulant une question précise.'],
        ],
        'moments' => [
            [
                't' => '1. Capsule à la maison',
                'o' => 'Prendre connaissance du contenu et repérer ce qui reste incompris.',
                'i' => 'La consigne exige une trace écrite et une question : sans production, le visionnage reste passif et la séance suivante s’effondre.',
                'n' => 'Déposer [CAPSULE / DOCUMENT] au moins 48 h avant. Prévoir une solution pour les élèves sans connexion.',
                'a' => [
                    ['t' => 'read', 'd' => 20, 'g' => 'individual', 'tp' => 'absent', 'sync' => 'async', 'loc' => 'online',
                     'desc' => 'Visionnage ou lecture de [CAPSULE / DOCUMENT] avec prise de notes guidée et rédaction d’une question.',
                     'inst' => 'Regarde [CAPSULE / DOCUMENT] avant le cours. Complète la fiche de notes, puis écris une question sur ce que tu n’as pas compris. Tu l’apportes en classe.'],
                ],
            ],
            [
                't' => '2. Vérification de compréhension',
                'o' => 'Vérifier que la capsule a été travaillée et récolter les obstacles.',
                'i' => 'Contrôle rapide et sans note : il régule l’entrée en tâche et permet de renoncer à un exposé devenu inutile.',
                'a' => [
                    ['t' => 'practice', 'd' => 10, 'g' => 'individual', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Quiz de trois questions sur la capsule, suivi du recueil au tableau des questions apportées par les élèves.',
                     'inst' => 'Réponds aux trois questions, puis inscris ta question au tableau ou dans le mur collaboratif.'],
                ],
            ],
            [
                't' => '3. Tâche d’application en groupes',
                'o' => 'Mobiliser [NOTION 1] sur une situation nouvelle, à plusieurs.',
                'i' => 'Le cœur du temps de classe : l’enseignant circule, n’intervient que sur demande, et note les démarches à faire expliciter ensuite.',
                'a' => [
                    ['t' => 'collaborate', 'd' => 30, 'g' => 'subgroups', 'aias' => 2,
                     'desc' => 'Résolution en groupes de [TÂCHE D’APPLICATION], avec production d’une réponse commune argumentée.',
                     'inst' => 'En groupe de trois, traitez [TÂCHE D’APPLICATION]. Vous rendez une réponse commune et vous devez pouvoir justifier chaque étape.'],
                ],
            ],
            [
                't' => '4. Mise en commun et institutionnalisation',
                'o' => 'Comparer les démarches et fixer la version de référence.',
                'i' => 'Deux groupes seulement passent, choisis pour leurs démarches différentes : la comparaison vaut mieux que la répétition.',
                'a' => [
                    ['t' => 'discuss', 'd' => 15, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Passage de deux groupes aux démarches contrastées, puis formalisation par l’enseignant de la version de référence.',
                     'inst' => 'Écoute les deux démarches présentées, repère celle qui est la plus proche de la tienne, puis recopie la version de référence.'],
                ],
            ],
        ],
    ],

    // ── Faire comprendre ────────────────────────────────────────────────────
    [
        'id' => 'cours-dialogue',
        'family' => 'comprendre',
        'icon' => 'fa-solid fa-chalkboard-user',
        'titleFr' => 'Cours dialogué et prise de notes structurée',
        'titleEn' => 'Dialogic lesson with structured note-taking',
        'summaryFr' => 'Deux apports interactifs séparés par une reformulation écrite, refermés par une synthèse à compléter.',
        'summaryEn' => 'Two interactive inputs separated by a written reformulation, closed by a synthesis to complete.',
        'keywords' => 'cours dialogué, prise de notes, exposé, reformulation, synthèse',
        'mode' => 'onsite',
        'description' => 'Séance de transmission sur [NOTION 1] en [MATIÈRE], [NIVEAU]. L’exposé est découpé en deux blocs entre lesquels les élèves reformulent par écrit ; la synthèse finale est complétée en classe.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : [NOTION 1] et [NOTION 2].',
        'personas' => '1) Transmettre un contenu exigeant en maintenant l’attention. 2) Faire produire une trace écrite utilisable pour réviser.',
        'outcomes' => [
            ['souvenir', 'Définir', 'Définir [NOTION 1] et [NOTION 2] avec le vocabulaire attendu.'],
            ['comprendre', 'Expliquer', 'Expliquer le lien entre [NOTION 1] et [NOTION 2].'],
            ['comprendre', 'Illustrer', 'Illustrer [NOTION 1] par un exemple autre que celui du cours.'],
        ],
        'moments' => [
            [
                't' => '1. Question d’entrée',
                'o' => 'Réactiver ce qui précède et annoncer ce que la séance va permettre de comprendre.',
                'i' => 'Un seul point d’appui, formulé comme une question : l’élève doit savoir dès la première minute ce qu’il saura faire à la fin.',
                'a' => [
                    ['t' => 'discuss', 'd' => 5, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Question d’entrée sur [PRÉREQUIS 1], deux réponses recueillies, annonce de l’objectif de la séance.',
                     'inst' => 'Note la question au tableau et l’objectif de la séance en haut de ta page.'],
                ],
            ],
            [
                't' => '2. Apport interactif 1',
                'o' => 'Installer [NOTION 1] et son vocabulaire.',
                'i' => 'Exposé entrecoupé de questions adressées nommément : le questionnement au hasard maintient l’engagement de toute la classe.',
                'a' => [
                    ['t' => 'read', 'd' => 15, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Exposé sur [NOTION 1], appuyé sur [DOCUMENT 1], avec trois questions posées nommément en cours de route.',
                     'inst' => 'Prends des notes dans la colonne de droite de ta fiche. Laisse la colonne de gauche vide pour l’instant.'],
                ],
            ],
            [
                't' => '3. Reformulation écrite',
                'o' => 'Obliger chaque élève à traiter l’information au lieu de la recopier.',
                'i' => 'Pause d’intégration : cinq minutes d’écriture individuelle valent mieux que cinq minutes d’exposé supplémentaires.',
                'a' => [
                    ['t' => 'produce', 'd' => 5, 'g' => 'individual', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Rédaction en trois lignes de ce qui vient d’être compris, avec lecture de deux productions à voix haute.',
                     'inst' => 'Dans la colonne de gauche, résume en trois lignes ce que tu viens de comprendre, avec tes mots.'],
                ],
            ],
            [
                't' => '4. Apport interactif 2',
                'o' => 'Installer [NOTION 2] et la relier à [NOTION 1].',
                'i' => 'Le second bloc porte explicitement sur la relation entre les deux notions : c’est là que se joue la compréhension, pas dans la définition isolée.',
                'a' => [
                    ['t' => 'read', 'd' => 15, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Exposé sur [NOTION 2] et son articulation avec [NOTION 1], avec un contre-exemple explicite.',
                     'inst' => 'Note comment [NOTION 2] se relie à [NOTION 1], et note aussi le contre-exemple donné.'],
                ],
            ],
            [
                't' => '5. Synthèse complétée',
                'o' => 'Produire la trace écrite de référence de la séance.',
                'i' => 'Synthèse à trous plutôt que dictée : l’élève doit choisir les mots, ce qui révèle immédiatement ce qui n’est pas compris.',
                'a' => [
                    ['t' => 'produce', 'd' => 15, 'g' => 'individual', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Complétion de la synthèse à trous, corrigée collectivement sur les deux points les plus manqués.',
                     'inst' => 'Complète la synthèse sans regarder tes notes, puis vérifie avec tes notes en changeant de couleur.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'etude-documents',
        'family' => 'comprendre',
        'icon' => 'fa-solid fa-file-lines',
        'titleFr' => 'Étude de documents guidée',
        'titleEn' => 'Guided document study',
        'summaryFr' => 'Contextualisation, prélèvement d’informations en binômes sur un corpus, confrontation des relevés, réponse rédigée.',
        'summaryEn' => 'Context setting, paired information gathering on a corpus, comparison of findings, written answer.',
        'keywords' => 'documents, corpus, prélèvement, confrontation, réponse organisée',
        'mode' => 'onsite',
        'description' => 'Séance d’analyse de [DOCUMENT 1] et [DOCUMENT 2] en [MATIÈRE], [NIVEAU]. Les élèves prélèvent, confrontent, puis rédigent une réponse organisée à [QUESTION].',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : travailler [NOTION 1] à partir de documents.',
        'personas' => '1) Faire pratiquer le prélèvement d’informations pertinentes. 2) Faire rédiger une réponse appuyée sur des preuves citées.',
        'outcomes' => [
            ['comprendre', 'Interpréter', 'Interpréter [DOCUMENT 1] en tenant compte de sa nature et de son auteur.'],
            ['analyser', 'Comparer', 'Comparer les informations de [DOCUMENT 1] et [DOCUMENT 2].'],
            ['appliquer', 'Rédiger', 'Rédiger une réponse organisée citant au moins deux éléments prélevés.'],
        ],
        'moments' => [
            [
                't' => '1. Contextualisation',
                'o' => 'Situer les documents et énoncer la question à laquelle ils vont servir à répondre.',
                'i' => 'La question est donnée avant la lecture : sans elle, les élèves paraphrasent au lieu de prélever.',
                'a' => [
                    ['t' => 'read', 'd' => 5, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Présentation de la nature, de l’auteur et de la date de chaque document, puis énoncé de [QUESTION].',
                     'inst' => 'Note la nature, l’auteur et la date de chaque document, puis recopie [QUESTION] en haut de ta feuille.'],
                ],
            ],
            [
                't' => '2. Prélèvement sur le corpus',
                'o' => 'Repérer et classer les informations utiles à la question.',
                'i' => 'Travail en binômes avec un tableau de relevé imposé : la contrainte de format empêche la recopie linéaire du document.',
                'n' => 'Prévoir un tableau de relevé à deux colonnes. Circuler et relancer par « qu’est-ce qui te permet de dire cela ? ».',
                'a' => [
                    ['t' => 'investigate', 'd' => 20, 'g' => 'subgroups', 'aias' => 1,
                     'desc' => 'Prélèvement en binômes dans [DOCUMENT 1] et [DOCUMENT 2], reporté dans un tableau à deux colonnes : information relevée / ce qu’elle prouve.',
                     'inst' => 'À deux, remplissez le tableau : dans la colonne de gauche, l’information exacte du document ; à droite, ce qu’elle prouve pour répondre à la question.'],
                ],
            ],
            [
                't' => '3. Confrontation des relevés',
                'o' => 'Comparer les prélèvements et trancher les désaccords d’interprétation.',
                'i' => 'Les désaccords sont le matériau du moment : ils font apparaître la différence entre lire un document et l’interpréter.',
                'a' => [
                    ['t' => 'discuss', 'd' => 15, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Mise en commun des relevés, arbitrage des interprétations divergentes, construction du plan de réponse au tableau.',
                     'inst' => 'Annonce une information que tu as relevée et ce qu’elle prouve. Si tu n’es pas d’accord avec un camarade, dis sur quel passage tu t’appuies.'],
                ],
            ],
            [
                't' => '4. Réponse rédigée',
                'o' => 'Rédiger une réponse organisée et appuyée sur des preuves.',
                'i' => 'La rédaction est individuelle et notée formativement : elle vérifie que le travail collectif a bien été approprié.',
                'a' => [
                    ['t' => 'produce', 'd' => 15, 'g' => 'individual', 'ev' => 'formative', 'aias' => 1,
                     'desc' => 'Rédaction individuelle d’une réponse à [QUESTION] en un paragraphe structuré, citant au moins deux prélèvements.',
                     'inst' => 'Rédige un paragraphe qui répond à [QUESTION]. Tu cites au moins deux informations des documents et tu précises chaque fois de quel document elles viennent.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'modelage-pratique-autonomie',
        'family' => 'comprendre',
        'icon' => 'fa-solid fa-person-chalkboard',
        'titleFr' => 'Modelage, pratique guidée, autonomie',
        'titleEn' => 'Modelling, guided practice, independent work',
        'summaryFr' => 'Enseignement explicite d’une méthode : je fais, nous faisons, tu fais — avec relâchement progressif de l’étayage.',
        'summaryEn' => 'Explicit teaching of a method: I do, we do, you do — with gradual release of support.',
        'keywords' => 'enseignement explicite, modelage, étayage, méthode, procédure, autonomie',
        'mode' => 'onsite',
        'description' => 'Séance d’enseignement explicite de [MÉTHODE / PROCÉDURE] en [MATIÈRE], [NIVEAU]. L’étayage est retiré par paliers : démonstration commentée, résolution collective, binômes, puis travail seul.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : maîtriser [MÉTHODE / PROCÉDURE].',
        'personas' => '1) Rendre visible le raisonnement d’un expert. 2) Amener chaque élève à exécuter seul la procédure sans aide.',
        'outcomes' => [
            ['comprendre', 'Décrire', 'Décrire les étapes de [MÉTHODE / PROCÉDURE] dans l’ordre.'],
            ['appliquer', 'Exécuter', 'Exécuter [MÉTHODE / PROCÉDURE] sur un exercice simple sans aide.'],
            ['analyser', 'Discriminer', 'Discriminer les situations où [MÉTHODE / PROCÉDURE] s’applique de celles où elle ne s’applique pas.'],
        ],
        'moments' => [
            [
                't' => '1. Modelage : je fais',
                'o' => 'Rendre audible le raisonnement qui accompagne chaque étape.',
                'i' => 'L’enseignant verbalise ses décisions et ses hésitations : c’est le raisonnement, non le résultat, qui doit être rendu visible.',
                'n' => 'Ne pas interroger les élèves pendant ce temps. Écrire chaque étape au tableau au fur et à mesure.',
                'a' => [
                    ['t' => 'read', 'd' => 10, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Résolution de [EXEMPLE 1] par l’enseignant, en explicitant à voix haute chaque décision et les erreurs possibles.',
                     'inst' => 'Écoute sans écrire pour l’instant. Repère à quel moment je choisis entre deux possibilités.'],
                ],
            ],
            [
                't' => '2. Pratique guidée : nous faisons',
                'o' => 'Refaire la procédure collectivement, étape par étape, en corrigeant à chaud.',
                'i' => 'Chaque étape est validée par la classe avant de passer à la suivante : les erreurs se corrigent immédiatement, avant d’être automatisées.',
                'a' => [
                    ['t' => 'practice', 'd' => 15, 'g' => 'whole', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Résolution de [EXEMPLE 2] au tableau, une étape par élève interrogé, avec validation collective à chaque étape.',
                     'inst' => 'On avance étape par étape. Écris chaque étape après validation, et lève la main dès qu’une étape te paraît fausse.'],
                ],
            ],
            [
                't' => '3. Pratique en binômes',
                'o' => 'S’entraîner avec l’aide d’un pair avant le travail seul.',
                'i' => 'Palier intermédiaire : l’élève qui explique consolide, celui qui écoute reçoit une reformulation à sa portée.',
                'a' => [
                    ['t' => 'practice', 'd' => 15, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 1,
                     'desc' => 'Deux exercices traités en binômes, l’un explique tandis que l’autre écrit, puis les rôles s’échangent.',
                     'inst' => 'À deux : sur le premier exercice, l’un explique et l’autre écrit. Vous échangez les rôles sur le second.'],
                ],
            ],
            [
                't' => '4. Pratique autonome',
                'o' => 'Vérifier que la procédure est exécutable sans aide.',
                'i' => 'Travail seul avec corrigé disponible en fin de temps : l’autocorrection donne un retour immédiat sans attendre la séance suivante.',
                'a' => [
                    ['t' => 'practice', 'd' => 15, 'g' => 'individual', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Série d’exercices d’application traitée seul, suivie d’une autocorrection à partir du corrigé distribué.',
                     'inst' => 'Traite les exercices seul, sans aide. À la fin, corrige-toi avec le corrigé et entoure les étapes où tu t’es trompé.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'cours-magistral-sequence',
        'family' => 'comprendre',
        'icon' => 'fa-solid fa-layer-group',
        'titleFr' => 'Cours magistral séquencé',
        'titleEn' => 'Chunked lecture',
        'summaryFr' => 'Trois blocs d’apport de 12 min, chacun suivi d’une micro-tâche de vérification, puis bilan des obstacles.',
        'summaryEn' => 'Three 12-minute input blocks, each followed by a short check task, then a review of obstacles.',
        'keywords' => 'cours magistral, exposé, attention, micro-tâche, vérification',
        'mode' => 'onsite',
        'description' => 'Séance d’apport dense sur [NOTION 1] en [MATIÈRE], [NIVEAU], découpée en trois blocs courts. Chaque bloc est suivi d’une tâche de cinq minutes qui vérifie la compréhension avant de poursuivre.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : contenu notionnel dense sur [NOTION 1].',
        'personas' => '1) Transmettre un contenu long sans perdre l’attention. 2) Détecter les incompréhensions avant la fin de la séance.',
        'outcomes' => [
            ['souvenir', 'Lister', 'Lister les trois éléments constitutifs de [NOTION 1].'],
            ['comprendre', 'Expliquer', 'Expliquer chacun des trois éléments avec un exemple.'],
            ['analyser', 'Structurer', 'Structurer ses notes en distinguant définition, exemple et limite.'],
        ],
        'moments' => [
            [
                't' => '1. Premier bloc et vérification',
                'o' => 'Installer le premier élément de [NOTION 1].',
                'i' => 'Douze minutes d’apport puis cinq de tâche : la micro-tâche ne sert pas à noter mais à savoir si l’on peut poursuivre.',
                'a' => [
                    ['t' => 'read', 'd' => 12, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Apport sur [ÉLÉMENT 1] de [NOTION 1], avec un exemple et une limite explicites.',
                     'inst' => 'Note la définition, l’exemple et la limite de [ÉLÉMENT 1] dans les trois colonnes de ta fiche.'],
                    ['t' => 'practice', 'd' => 5, 'g' => 'individual', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Micro-tâche : une question d’application sur [ÉLÉMENT 1], vérifiée par un sondage à main levée.',
                     'inst' => 'Réponds à la question projetée en une phrase, puis lève la main selon la réponse que tu as choisie.'],
                ],
            ],
            [
                't' => '2. Deuxième bloc et vérification',
                'o' => 'Installer le deuxième élément et le relier au premier.',
                'i' => 'Le bloc s’ouvre sur le résultat de la micro-tâche précédente : l’apport est ajusté à ce qui n’est pas passé.',
                'a' => [
                    ['t' => 'read', 'd' => 12, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Apport sur [ÉLÉMENT 2], articulé à [ÉLÉMENT 1], après reprise de la question manquée.',
                     'inst' => 'Note ce qui relie [ÉLÉMENT 2] à [ÉLÉMENT 1]. Souligne le mot qui fait la différence entre les deux.'],
                    ['t' => 'practice', 'd' => 5, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Micro-tâche en binômes : classer trois cas selon qu’ils relèvent de l’un ou l’autre élément.',
                     'inst' => 'Avec ton voisin, classe les trois cas projetés. Vous devez pouvoir justifier chaque classement en une phrase.'],
                ],
            ],
            [
                't' => '3. Troisième bloc et vérification',
                'o' => 'Installer le troisième élément et boucler l’ensemble.',
                'i' => 'Le dernier bloc est le plus court et le plus concret : la charge cognitive est maximale en fin de séance.',
                'a' => [
                    ['t' => 'read', 'd' => 12, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Apport sur [ÉLÉMENT 3] et récapitulation de l’architecture d’ensemble de [NOTION 1].',
                     'inst' => 'Complète le schéma d’ensemble distribué en y plaçant les trois éléments.'],
                ],
            ],
            [
                't' => '4. Bilan des obstacles',
                'o' => 'Identifier ce qui reste incompris avant la prochaine séance.',
                'i' => 'Recueil écrit anonyme : il donne un point de départ précis à la séance suivante et évite le « tout le monde a compris ? ».',
                'a' => [
                    ['t' => 'discuss', 'd' => 9, 'g' => 'whole', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Chaque élève écrit sur un papier le point le plus obscur ; trois papiers sont tirés et traités immédiatement.',
                     'inst' => 'Écris sur un papier, sans ton nom, le point que tu as le moins compris. Je tire trois papiers et on les traite tout de suite.'],
                ],
            ],
        ],
    ],

    // ── S’entraîner et consolider ───────────────────────────────────────────
    [
        'id' => 'entrainement-differencie',
        'family' => 'entrainer',
        'icon' => 'fa-solid fa-layer-group',
        'titleFr' => 'Séance d’entraînement différenciée',
        'titleEn' => 'Differentiated practice lesson',
        'summaryFr' => 'Diagnostic express puis trois ateliers — consolidation, entraînement, approfondissement — et mise en commun des méthodes.',
        'summaryEn' => 'Quick diagnostic, then three workshops — consolidation, practice, extension — and pooling of methods.',
        'keywords' => 'différenciation, ateliers, plan de travail, autonomie, remédiation',
        'mode' => 'onsite',
        'description' => 'Séance d’entraînement sur [NOTION 1] en [MATIÈRE], [NIVEAU]. Un diagnostic de cinq minutes oriente chaque élève vers l’un des trois ateliers ; la mise en commun porte sur les méthodes, non sur les résultats.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : consolider [NOTION 1] avec des besoins hétérogènes.',
        'personas' => '1) Donner à chacun un travail à sa portée immédiate. 2) Faire circuler les méthodes entre les niveaux.',
        'outcomes' => [
            ['appliquer', 'Appliquer', 'Appliquer [NOTION 1] à des exercices de difficulté croissante.'],
            ['evaluer', 'Décider', 'Décider quel atelier correspond à son propre niveau de maîtrise.'],
            ['comprendre', 'Expliquer', 'Expliquer à un camarade la méthode utilisée pour résoudre un exercice.'],
        ],
        'moments' => [
            [
                't' => '1. Diagnostic express',
                'o' => 'Situer chaque élève avant de l’orienter vers un atelier.',
                'i' => 'Deux questions suffisent : le but est l’orientation, pas la mesure fine. L’élève choisit ensuite lui-même, ce qui engage sa responsabilité.',
                'n' => 'Préparer les trois ateliers en trois piles de fiches sur trois tables distinctes.',
                'a' => [
                    ['t' => 'practice', 'd' => 5, 'g' => 'individual', 'ev' => 'diagnostic', 'aias' => 'na',
                     'desc' => 'Deux questions calibrées sur [NOTION 1], corrigées immédiatement au tableau, chaque élève se situant lui-même.',
                     'inst' => 'Traite les deux questions, puis corrige-toi au tableau. Selon ton résultat, choisis ton atelier : les deux justes, atelier 3 ; une juste, atelier 2 ; aucune, atelier 1.'],
                ],
            ],
            [
                't' => '2. Trois ateliers en parallèle',
                'o' => 'Faire travailler chaque élève sur des tâches adaptées à son niveau de maîtrise.',
                'i' => 'Les trois ateliers portent sur la même notion, à des degrés d’étayage différents : c’est le soutien qui varie, pas l’objectif.',
                'n' => 'Rester principalement sur l’atelier 1. Prévoir un corrigé autonome pour les ateliers 2 et 3.',
                'a' => [
                    ['t' => 'practice', 'd' => 35, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 1,
                     'desc' => 'Atelier 1 : reprise guidée avec exemple résolu à côté. Atelier 2 : exercices d’application standard. Atelier 3 : problème ouvert sur [NOTION 1]. L’enseignant se tient auprès de l’atelier 1.',
                     'inst' => 'Installe-toi à la table de ton atelier. Tu peux changer d’atelier en cours de séance si c’est trop facile ou trop difficile. Le corrigé est sur la table.'],
                ],
            ],
            [
                't' => '3. Mise en commun des méthodes',
                'o' => 'Faire expliciter et circuler les procédures efficaces.',
                'i' => 'La mise en commun porte sur « comment tu as fait » et non sur « quelle est la réponse » : c’est ce qui rend le moment utile aux trois niveaux.',
                'a' => [
                    ['t' => 'discuss', 'd' => 15, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Un élève par atelier expose sa méthode ; l’enseignant fait apparaître ce qui est commun aux trois procédures.',
                     'inst' => 'Un élève par atelier explique comment il a procédé. Note dans ton cahier la méthode qui te paraît la plus sûre.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'resolution-problemes',
        'family' => 'entrainer',
        'icon' => 'fa-solid fa-puzzle-piece',
        'titleFr' => 'Résolution de problèmes en groupes',
        'titleEn' => 'Group problem solving',
        'summaryFr' => 'Recherche individuelle, confrontation en groupe, passage au tableau de deux groupes, institutionnalisation.',
        'summaryEn' => 'Individual search, group comparison, two groups at the board, formalisation.',
        'keywords' => 'problème, recherche, groupes, démarche, institutionnalisation',
        'mode' => 'onsite',
        'description' => 'Séance de résolution de [PROBLÈME] en [MATIÈRE], [NIVEAU]. Chaque élève cherche d’abord seul, puis le groupe confronte les démarches ; deux groupes seulement exposent, choisis pour leurs procédures différentes.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : mobiliser [NOTION 1] dans une situation de recherche.',
        'personas' => '1) Faire chercher avant d’enseigner la méthode. 2) Faire comparer plusieurs démarches valides.',
        'outcomes' => [
            ['appliquer', 'Résoudre', 'Résoudre [PROBLÈME] en mobilisant [NOTION 1].'],
            ['analyser', 'Comparer', 'Comparer deux démarches de résolution et en repérer les avantages.'],
            ['evaluer', 'Justifier', 'Justifier chaque étape de sa démarche devant la classe.'],
        ],
        'moments' => [
            [
                't' => '1. Présentation du problème',
                'o' => 'S’assurer que la situation et la question sont comprises, sans donner de piste.',
                'i' => 'Aucune indication de méthode : la dévolution du problème suppose que les élèves cherchent avant que l’enseignant n’enseigne.',
                'a' => [
                    ['t' => 'read', 'd' => 5, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Lecture de [PROBLÈME] et reformulation par un élève de ce qui est cherché. Aucune piste de résolution n’est donnée.',
                     'inst' => 'Lis le problème, puis reformule à voix haute ce qu’on cherche exactement. Ne commence pas encore à résoudre.'],
                ],
            ],
            [
                't' => '2. Recherche individuelle',
                'o' => 'Laisser à chacun le temps d’engager une démarche personnelle.',
                'i' => 'Temps individuel préalable indispensable : sans lui, le groupe est monopolisé par les élèves les plus rapides.',
                'a' => [
                    ['t' => 'investigate', 'd' => 10, 'g' => 'individual', 'aias' => 1,
                     'desc' => 'Recherche seul au brouillon, sans échange. L’enseignant circule sans répondre aux demandes de validation.',
                     'inst' => 'Cherche seul, au brouillon, pendant dix minutes. Même une piste incomplète est utile : garde tes essais.'],
                ],
            ],
            [
                't' => '3. Confrontation en groupe',
                'o' => 'Comparer les pistes et construire une réponse commune argumentée.',
                'i' => 'Le groupe doit produire une seule réponse : la contrainte force l’argumentation plutôt que la juxtaposition des brouillons.',
                'a' => [
                    ['t' => 'collaborate', 'd' => 20, 'g' => 'subgroups', 'aias' => 2,
                     'desc' => 'Groupes de trois ou quatre : chacun expose sa piste, le groupe retient une démarche et l’écrit proprement sur une affiche.',
                     'inst' => 'Chacun présente sa piste, même incomplète. Le groupe choisit une démarche, l’écrit sur l’affiche, et prépare la justification de chaque étape.'],
                ],
            ],
            [
                't' => '4. Passage de deux groupes',
                'o' => 'Faire comparer publiquement deux démarches différentes.',
                'i' => 'Deux groupes suffisent, choisis pour la différence de leurs procédures : la comparaison est plus formatrice que la répétition.',
                'a' => [
                    ['t' => 'discuss', 'd' => 15, 'g' => 'whole', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Deux affiches contrastées présentées et questionnées par la classe ; l’enseignant fait expliciter l’avantage de chaque procédure.',
                     'inst' => 'Écoute les deux démarches, puis pose une question sur le point que tu n’as pas suivi. Note la démarche que tu réutiliseras.'],
                ],
            ],
            [
                't' => '5. Institutionnalisation',
                'o' => 'Fixer la méthode de référence et son domaine de validité.',
                'i' => 'Ce moment court est indispensable : sans formalisation, la recherche reste un souvenir d’activité sans savoir stabilisé.',
                'a' => [
                    ['t' => 'read', 'd' => 5, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Formalisation par l’enseignant de la méthode de référence, avec ses conditions d’emploi.',
                     'inst' => 'Recopie la méthode de référence et la condition qui indique quand on peut l’utiliser.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'revision-avant-evaluation',
        'family' => 'entrainer',
        'icon' => 'fa-solid fa-list-check',
        'titleFr' => 'Révision avant évaluation',
        'titleEn' => 'Revision before assessment',
        'summaryFr' => 'Carte mentale du chapitre en groupes, quiz-défi par équipes, remédiation sur les erreurs les plus fréquentes.',
        'summaryEn' => 'Group mind map of the chapter, team quiz challenge, remediation on the most frequent errors.',
        'keywords' => 'révision, carte mentale, quiz, remédiation, mémorisation',
        'mode' => 'onsite',
        'description' => 'Séance de révision de [CHAPITRE] en [MATIÈRE], [NIVEAU], la veille ou l’avant-veille de l’évaluation. Les élèves reconstruisent l’architecture du chapitre avant de s’auto-tester.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : évaluation de [CHAPITRE] prévue le [DATE].',
        'personas' => '1) Faire reconstruire l’organisation du chapitre par les élèves. 2) Traiter les erreurs les plus probables avant l’évaluation.',
        'outcomes' => [
            ['souvenir', 'Rappeler', 'Rappeler sans notes les notions et définitions de [CHAPITRE].'],
            ['analyser', 'Organiser', 'Organiser les notions de [CHAPITRE] en une carte mentale hiérarchisée.'],
            ['evaluer', 'Apprécier', 'Apprécier son propre niveau de préparation et cibler ce qui reste à réviser.'],
        ],
        'moments' => [
            [
                't' => '1. Carte mentale du chapitre',
                'o' => 'Reconstruire de mémoire l’organisation des notions du chapitre.',
                'i' => 'Rappel actif sans notes : la tentative de récupération en mémoire est plus efficace que la relecture, même quand elle échoue.',
                'n' => 'Interdire cahiers et manuels pendant les dix premières minutes, puis autoriser la vérification.',
                'a' => [
                    ['t' => 'produce', 'd' => 20, 'g' => 'subgroups', 'aias' => 1,
                     'desc' => 'Construction d’une carte mentale de [CHAPITRE] en groupes de trois, d’abord sans notes puis complétée avec le cahier dans une autre couleur.',
                     'inst' => 'Sans vos cahiers, construisez la carte du chapitre à trois. Au bout de dix minutes, ouvrez le cahier et complétez ce qui manque dans une autre couleur.'],
                ],
            ],
            [
                't' => '2. Quiz-défi par équipes',
                'o' => 'Tester la restitution rapide et repérer les points fragiles collectifs.',
                'i' => 'Le format ludique maintient l’engagement, mais chaque question manquée est relevée : le quiz sert de diagnostic pour le moment suivant.',
                'a' => [
                    ['t' => 'practice', 'd' => 20, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Quiz en équipes sur [CHAPITRE], une réponse écrite par équipe et par question ; l’enseignant note les questions les plus manquées.',
                     'inst' => 'Une seule réponse par équipe, écrite sur l’ardoise. On lève l’ardoise au signal. Notez les questions que votre équipe a manquées.'],
                ],
            ],
            [
                't' => '3. Remédiation ciblée',
                'o' => 'Reprendre les deux ou trois erreurs les plus fréquentes du quiz.',
                'i' => 'Reprise pilotée par les résultats du quiz, non par le plan du chapitre : on traite ce qui a effectivement manqué.',
                'a' => [
                    ['t' => 'read', 'd' => 15, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Reprise des questions les plus manquées, avec explicitation de la confusion qui les produit, puis annonce du format de l’évaluation.',
                     'inst' => 'Note la correction des questions reprises, puis écris les deux points que tu dois encore réviser chez toi ce soir.'],
                ],
            ],
        ],
    ],

    // ── Parler et argumenter ────────────────────────────────────────────────
    [
        'id' => 'debat-argumente',
        'family' => 'argumenter',
        'icon' => 'fa-solid fa-scale-balanced',
        'titleFr' => 'Débat argumenté',
        'titleEn' => 'Structured debate',
        'summaryFr' => 'Deux séances : constitution du dossier d’arguments et répartition des rôles, puis joute réglée, vote et retour métacognitif.',
        'summaryEn' => 'Two lessons: building the argument file and assigning roles, then the regulated debate, vote and metacognitive review.',
        'keywords' => 'débat, argumentation, controverse, rôles, oral, esprit critique',
        'mode' => 'onsite',
        'description' => 'Deux séances de 55 min sur la controverse [CONTROVERSE] en [MATIÈRE], [NIVEAU]. La première prépare le dossier d’arguments, la seconde tient le débat et l’analyse.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : travailler l’argumentation orale sur [CONTROVERSE].',
        'personas' => '1) Faire construire une argumentation appuyée sur des sources. 2) Faire distinguer un argument d’une opinion.',
        'outcomes' => [
            ['comprendre', 'Expliquer', 'Expliquer les deux positions en présence dans [CONTROVERSE].'],
            ['analyser', 'Distinguer', 'Distinguer un argument étayé d’une opinion non étayée.'],
            ['evaluer', 'Argumenter', 'Argumenter une position en s’appuyant sur au moins deux sources identifiées.'],
            ['evaluer', 'Critiquer', 'Critiquer un argument adverse en visant le raisonnement et non la personne.'],
        ],
        'moments' => [
            [
                't' => '1. Cadrage de la controverse',
                'o' => 'Poser la question débattue et vérifier qu’elle est réellement discutable.',
                'i' => 'Une controverse authentique, aux deux positions défendables : un faux débat produit un exercice de rhétorique creux.',
                'n' => 'Vérifier que la question ne porte pas sur un fait établi. Rappeler les règles de prise de parole.',
                'a' => [
                    ['t' => 'read', 'd' => 10, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Présentation de [CONTROVERSE], de ses enjeux, et des règles du débat. Distinction explicite entre fait, opinion et argument.',
                     'inst' => 'Note la question du débat et les trois règles de prise de parole. Écris aussi, pour toi seul, la position que tu aurais spontanément.'],
                ],
            ],
            [
                't' => '2. Constitution du dossier d’arguments',
                'o' => 'Construire une argumentation étayée par des sources vérifiables.',
                'i' => 'Les camps sont attribués, non choisis : défendre une position que l’on ne partage pas oblige à comprendre l’autre raisonnement.',
                'a' => [
                    ['t' => 'investigate', 'd' => 35, 'g' => 'subgroups', 'aias' => 2,
                     'desc' => 'Recherche en groupes dans [CORPUS / SOURCES], avec fiche d’argument imposée : affirmation, preuve, source, objection prévisible.',
                     'inst' => 'Votre camp vous est attribué. Constituez quatre fiches d’argument : l’affirmation, la preuve, la source précise, et l’objection que l’autre camp vous fera.'],
                ],
            ],
            [
                't' => '3. Répartition des rôles',
                'o' => 'Organiser la prise de parole et préparer l’ordre des interventions.',
                'i' => 'Des rôles nommés — porte-parole, répondant, observateur — garantissent que tous les élèves ont une tâche pendant la joute.',
                'a' => [
                    ['t' => 'collaborate', 'd' => 10, 'g' => 'subgroups', 'aias' => 'na',
                     'desc' => 'Attribution dans chaque camp des rôles de porte-parole, de répondant et d’observateur, avec ordre de passage des arguments.',
                     'inst' => 'Répartissez les rôles : deux porte-parole, deux répondants, le reste en observateurs avec la grille d’observation.'],
                ],
            ],
            [
                't' => '4. Joute réglée',
                'o' => 'Tenir le débat en respectant le cadre et en répondant aux arguments adverses.',
                'i' => 'Le temps de parole est chronométré et l’enseignant n’arbitre pas le fond : son rôle est de faire respecter le cadre.',
                'n' => 'Chronomètre visible. Noter au tableau les arguments effectivement échangés pour le retour métacognitif.',
                'a' => [
                    ['t' => 'discuss', 'd' => 30, 'g' => 'whole', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Débat en trois tours : exposé des positions, réponses croisées, conclusions. Les observateurs remplissent la grille.',
                     'inst' => 'Deux minutes par prise de parole. Tu commences toujours par reformuler l’argument auquel tu réponds. Observateurs : remplissez la grille.'],
                ],
            ],
            [
                't' => '5. Vote et retour métacognitif',
                'o' => 'Analyser ce qui a rendu certains arguments plus convaincants que d’autres.',
                'i' => 'Le moment décisif : on n’évalue pas qui a gagné mais quels procédés ont emporté l’adhésion, et lesquels n’étaient pas des arguments.',
                'a' => [
                    ['t' => 'discuss', 'd' => 25, 'g' => 'whole', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Vote à bulletin secret, puis analyse collective des trois arguments les plus efficaces et des deux plus faibles, grille d’observation à l’appui.',
                     'inst' => 'Vote pour la position qui t’a le plus convaincu, même si ce n’est pas ton camp. Puis dis quel argument t’a fait changer d’avis, et pourquoi.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'expose-eleve',
        'family' => 'argumenter',
        'icon' => 'fa-solid fa-person-chalkboard',
        'titleFr' => 'Exposé d’élève',
        'titleEn' => 'Student presentation',
        'summaryFr' => 'Préparation hors classe à la grille critériée, trois passages en classe, évaluation par les pairs, reprise des contenus.',
        'summaryEn' => 'Out-of-class preparation with a criteria grid, three presentations, peer assessment, content review.',
        'keywords' => 'exposé, oral, grille critériée, évaluation par les pairs, prise de parole',
        'mode' => 'hybrid',
        'description' => 'Séance de passage d’exposés sur [SUJETS D’EXPOSÉ] en [MATIÈRE], [NIVEAU]. La préparation se fait hors classe avec la grille d’évaluation connue à l’avance ; l’enseignant reprend les contenus après les passages.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : travailler la prise de parole en continu sur [SUJETS D’EXPOSÉ].',
        'personas' => '1) Faire produire un exposé structuré et sourcé. 2) Faire écouter activement les exposés par le reste de la classe.',
        'outcomes' => [
            ['creer', 'Concevoir', 'Concevoir un exposé structuré de [DURÉE EXPOSÉ] sur [SUJET].'],
            ['appliquer', 'Employer', 'Employer un support visuel qui appuie le propos sans le dupliquer.'],
            ['evaluer', 'Évaluer', 'Évaluer un exposé à l’aide d’une grille critériée.'],
        ],
        'moments' => [
            [
                't' => '1. Préparation hors classe',
                'o' => 'Produire un exposé structuré, sourcé et minuté.',
                'i' => 'La grille d’évaluation est fournie avec le sujet : l’élève doit savoir sur quoi il sera évalué avant de commencer.',
                'n' => 'Distribuer la grille au moment de l’attribution des sujets, au moins deux semaines avant.',
                'a' => [
                    ['t' => 'produce', 'd' => 60, 'g' => 'subgroups', 'tp' => 'absent', 'sync' => 'async', 'loc' => 'online', 'aias' => 2,
                     'desc' => 'Préparation de l’exposé hors classe : plan, support visuel, sources identifiées, minutage. La grille critériée est fournie dès l’attribution du sujet.',
                     'inst' => 'Prépare ton exposé de [DURÉE EXPOSÉ] : un plan en trois parties, un support sobre, tes sources indiquées. Répète à voix haute au moins une fois en te chronométrant.'],
                ],
            ],
            [
                't' => '2. Passages',
                'o' => 'Exposer devant la classe en respectant le cadre et le temps.',
                'i' => 'Les auditeurs ont une tâche écrite pendant les passages : sans elle, l’exposé ne concerne que celui qui parle.',
                'a' => [
                    ['t' => 'discuss', 'd' => 24, 'g' => 'whole', 'ev' => 'summative', 'aias' => 'na',
                     'desc' => 'Trois exposés de [DURÉE EXPOSÉ], chacun suivi d’une question de la classe. Les auditeurs remplissent la grille et la fiche de notes.',
                     'inst' => 'Pendant chaque exposé, remplis la grille et note les deux informations que tu retiens. Prépare une question à poser.'],
                ],
            ],
            [
                't' => '3. Évaluation par les pairs',
                'o' => 'Confronter les évaluations et expliciter les critères de réussite.',
                'i' => 'La confrontation des grilles fait apparaître les désaccords d’interprétation des critères, ce qui les affine pour tous.',
                'a' => [
                    ['t' => 'practice', 'd' => 15, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Comparaison des grilles par groupes de trois, puis restitution orale d’un point fort et d’un axe de progrès par exposé.',
                     'inst' => 'Comparez vos grilles à trois. Pour chaque exposé, formulez un point fort précis et un axe de progrès formulé comme un conseil.'],
                ],
            ],
            [
                't' => '4. Reprise des contenus',
                'o' => 'Valider, corriger et compléter les contenus présentés.',
                'i' => 'Moment indispensable : sans reprise, les erreurs de contenu des exposés restent dans les notes des élèves.',
                'a' => [
                    ['t' => 'read', 'd' => 16, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Rectification des imprécisions relevées et apport des éléments non traités par les exposés.',
                     'inst' => 'Corrige tes notes d’après ce qui est rectifié, et ajoute les éléments qui n’ont pas été présentés.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'jeu-de-role',
        'family' => 'argumenter',
        'icon' => 'fa-solid fa-masks-theater',
        'titleFr' => 'Jeu de rôle ou simulation',
        'titleEn' => 'Role play or simulation',
        'summaryFr' => 'Procès, conférence de presse, négociation ou comité d’experts : rôles distribués, préparation en délégations, simulation, débriefing.',
        'summaryEn' => 'Trial, press conference, negotiation or expert panel: roles assigned, preparation in delegations, simulation, debriefing.',
        'keywords' => 'jeu de rôle, simulation, procès, négociation, incarnation, débriefing',
        'mode' => 'onsite',
        'description' => 'Simulation de [SITUATION SIMULÉE] en [MATIÈRE], [NIVEAU]. Chaque délégation défend une position documentée ; le débriefing final fait le lien entre la fiction et le contenu du programme.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : comprendre les positions en jeu dans [SITUATION SIMULÉE].',
        'personas' => '1) Faire comprendre un système d’acteurs de l’intérieur. 2) Faire défendre une position selon des contraintes imposées.',
        'outcomes' => [
            ['comprendre', 'Expliquer', 'Expliquer les intérêts défendus par chaque acteur de [SITUATION SIMULÉE].'],
            ['appliquer', 'Employer', 'Employer le vocabulaire et les contraintes propres au rôle attribué.'],
            ['analyser', 'Attribuer', 'Attribuer une position à l’acteur qui la défend et expliquer pourquoi.'],
            ['evaluer', 'Juger', 'Juger ce que la simulation reproduit fidèlement et ce qu’elle simplifie.'],
        ],
        'moments' => [
            [
                't' => '1. Distribution des rôles et des dossiers',
                'o' => 'Attribuer les rôles et faire comprendre les contraintes de chacun.',
                'i' => 'Chaque dossier contient une contrainte non négociable : c’est elle qui empêche la simulation de tourner au débat d’opinions.',
                'n' => 'Préparer un dossier par rôle : intérêts, contrainte non négociable, deux données chiffrées.',
                'a' => [
                    ['t' => 'read', 'd' => 10, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Présentation de [SITUATION SIMULÉE], du déroulé et des rôles. Remise d’un dossier par délégation.',
                     'inst' => 'Lis ton dossier : ce que ton acteur veut obtenir, et la contrainte qu’il ne peut pas franchir. Tu joues ce rôle, pas ton opinion.'],
                ],
            ],
            [
                't' => '2. Préparation en délégations',
                'o' => 'Construire la position de la délégation et sa stratégie de prise de parole.',
                'i' => 'La délégation doit anticiper les positions adverses : c’est cette anticipation qui produit la compréhension du système d’acteurs.',
                'a' => [
                    ['t' => 'collaborate', 'd' => 15, 'g' => 'subgroups', 'aias' => 2,
                     'desc' => 'Préparation en délégations : position d’ouverture, deux arguments chiffrés, ligne rouge, anticipation des adversaires.',
                     'inst' => 'Préparez votre déclaration d’ouverture en une minute, deux arguments avec des chiffres, et ce que vous refuserez de céder.'],
                ],
            ],
            [
                't' => '3. Simulation',
                'o' => 'Tenir la simulation en respectant les rôles et le protocole.',
                'i' => 'L’enseignant tient la présidence de séance et non l’arbitrage du fond : il fait respecter les tours de parole et le temps.',
                'a' => [
                    ['t' => 'discuss', 'd' => 20, 'g' => 'whole', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Déroulé en trois temps : déclarations d’ouverture, échanges libres encadrés, tentative d’accord ou verdict.',
                     'inst' => 'Reste dans ton rôle jusqu’à la fin. Tu t’adresses aux autres délégations, pas à moi. Note ce que les autres obtiennent de toi.'],
                ],
            ],
            [
                't' => '4. Débriefing',
                'o' => 'Faire le lien entre la simulation et le contenu du programme.',
                'i' => 'Sans débriefing, la simulation reste un jeu : c’est ici que les élèves sortent du rôle et nomment le savoir travaillé.',
                'a' => [
                    ['t' => 'discuss', 'd' => 10, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Sortie de rôle explicite, puis mise au jour des mécanismes réels que la simulation a reproduits et de ce qu’elle a simplifié.',
                     'inst' => 'Tu quittes ton rôle. Dis une chose que tu as comprise en jouant ce personnage, et une chose que la simulation a rendue trop simple.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'cercle-lecture',
        'family' => 'argumenter',
        'icon' => 'fa-solid fa-book-open-reader',
        'titleFr' => 'Cercle de lecture ou discussion réflexive',
        'titleEn' => 'Reading circle or reflective discussion',
        'summaryFr' => 'Relecture du texte, tour de table réglé sur une question ouverte, écrit réflexif personnel.',
        'summaryEn' => 'Re-reading the text, regulated round-table on an open question, personal reflective writing.',
        'keywords' => 'cercle de lecture, discussion, texte, question ouverte, écrit réflexif',
        'mode' => 'onsite',
        'description' => 'Séance de discussion à partir de [TEXTE] en [MATIÈRE], [NIVEAU]. La discussion se tient selon un protocole strict de prise de parole ; elle se referme sur un écrit personnel.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : interpréter [TEXTE] et confronter les lectures.',
        'personas' => '1) Faire formuler une interprétation appuyée sur le texte. 2) Faire écouter et reprendre la parole d’autrui.',
        'outcomes' => [
            ['comprendre', 'Interpréter', 'Interpréter un passage de [TEXTE] en s’appuyant sur ses mots.'],
            ['analyser', 'Questionner', 'Questionner une interprétation en citant le texte.'],
            ['creer', 'Formuler', 'Formuler par écrit une position personnelle nourrie de la discussion.'],
        ],
        'moments' => [
            [
                't' => '1. Relecture du texte support',
                'o' => 'Revenir au texte avec la question de discussion en tête.',
                'i' => 'La relecture est silencieuse et outillée : sans passage repéré, la discussion glisse vers l’échange d’opinions générales.',
                'a' => [
                    ['t' => 'read', 'd' => 10, 'g' => 'individual', 'aias' => 'na',
                     'desc' => 'Relecture silencieuse de [TEXTE] avec repérage de deux passages en lien avec [QUESTION DE DISCUSSION].',
                     'inst' => 'Relis le texte et surligne deux passages qui te semblent utiles pour répondre à la question. Note à côté pourquoi.'],
                ],
            ],
            [
                't' => '2. Tour de table réglé',
                'o' => 'Confronter les interprétations en s’appuyant sur le texte.',
                'i' => 'Protocole strict : on ne prend la parole qu’après avoir repris ce qu’a dit le précédent, ce qui force l’écoute réelle.',
                'n' => 'Disposer les tables en cercle. L’enseignant ne valide pas les interprétations, il relance par « où le vois-tu ? ».',
                'a' => [
                    ['t' => 'discuss', 'd' => 25, 'g' => 'whole', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Discussion en cercle sur [QUESTION DE DISCUSSION] : chaque intervention reprend la précédente et cite un passage.',
                     'inst' => 'Avant de donner ton avis, reformule ce qu’a dit la personne précédente. Puis cite le passage sur lequel tu t’appuies.'],
                ],
            ],
            [
                't' => '3. Écrit réflexif',
                'o' => 'Fixer par écrit une position personnelle nourrie par la discussion.',
                'i' => 'L’écrit final individualise le bénéfice de la discussion et laisse une trace évaluable de la pensée de chaque élève.',
                'a' => [
                    ['t' => 'produce', 'd' => 20, 'g' => 'individual', 'ev' => 'formative', 'aias' => 1,
                     'desc' => 'Rédaction individuelle d’une réponse argumentée à [QUESTION DE DISCUSSION], mentionnant un apport de la discussion.',
                     'inst' => 'Rédige ta réponse en un paragraphe. Cite un passage du texte, et indique une idée d’un camarade qui a modifié ta lecture.'],
                ],
            ],
        ],
    ],

    // ── Produire et créer ───────────────────────────────────────────────────
    [
        'id' => 'tache-complexe',
        'family' => 'produire',
        'icon' => 'fa-solid fa-diagram-project',
        'titleFr' => 'Tâche complexe en groupes',
        'titleEn' => 'Complex task in groups',
        'summaryFr' => 'Deux séances : mission et critères de réussite, planification, production en groupes, présentation croisée, auto-évaluation.',
        'summaryEn' => 'Two lessons: mission and success criteria, planning, group production, cross-presentation, self-assessment.',
        'keywords' => 'tâche complexe, projet, groupes, critères de réussite, production',
        'mode' => 'onsite',
        'description' => 'Deux séances de 55 min consacrées à [MISSION] en [MATIÈRE], [NIVEAU]. Les groupes disposent des critères de réussite dès le départ et présentent leur production à un autre groupe avant de s’auto-évaluer.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : mobiliser [NOTION 1] et [NOTION 2] dans une production.',
        'personas' => '1) Faire mobiliser plusieurs notions dans une même tâche. 2) Faire travailler la coopération et la répartition du travail.',
        'outcomes' => [
            ['appliquer', 'Mettre en œuvre', 'Mettre en œuvre [NOTION 1] et [NOTION 2] dans une production commune.'],
            ['creer', 'Élaborer', 'Élaborer [PRODUCTION ATTENDUE] conforme aux critères de réussite annoncés.'],
            ['evaluer', 'Évaluer', 'Évaluer sa production à l’aide des critères de réussite.'],
            ['analyser', 'Organiser', 'Organiser le travail du groupe en tâches réparties et tenues.'],
        ],
        'moments' => [
            [
                't' => '1. Mission et critères de réussite',
                'o' => 'Comprendre ce qui est attendu et à quoi la réussite se reconnaîtra.',
                'i' => 'Les critères sont donnés avant la production, jamais après : sans eux, les élèves travaillent à l’aveugle et l’évaluation paraît arbitraire.',
                'n' => 'Afficher les critères de réussite au tableau pendant toute la durée du projet.',
                'a' => [
                    ['t' => 'read', 'd' => 10, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Présentation de [MISSION], de [PRODUCTION ATTENDUE] et des quatre critères de réussite, avec un exemple de production réussie.',
                     'inst' => 'Note la mission et les quatre critères de réussite. Regarde l’exemple projeté : repère ce qui le rend conforme aux critères.'],
                ],
            ],
            [
                't' => '2. Répartition et planification',
                'o' => 'Répartir les tâches et fixer les échéances internes au groupe.',
                'i' => 'La planification écrite rend la coopération vérifiable et permet de repérer les groupes en difficulté dès la première séance.',
                'a' => [
                    ['t' => 'collaborate', 'd' => 15, 'g' => 'subgroups', 'aias' => 'na',
                     'desc' => 'Remplissage d’une fiche de projet par groupe : tâches, responsable de chaque tâche, échéances, ressources nécessaires.',
                     'inst' => 'Remplissez la fiche de projet : qui fait quoi, pour quand. Chaque membre du groupe doit avoir une tâche nommée.'],
                ],
            ],
            [
                't' => '3. Production',
                'o' => 'Réaliser [PRODUCTION ATTENDUE] en respectant les critères.',
                'i' => 'L’enseignant n’intervient que sur demande et renvoie systématiquement aux critères affichés plutôt qu’à la solution.',
                'a' => [
                    ['t' => 'produce', 'd' => 55, 'g' => 'subgroups', 'aias' => 3,
                     'desc' => 'Réalisation de [PRODUCTION ATTENDUE] en groupes, avec point d’étape à mi-parcours mené par l’enseignant.',
                     'inst' => 'Produisez en suivant votre fiche de projet. À la moitié du temps, vérifiez votre avancement contre les quatre critères affichés.'],
                ],
            ],
            [
                't' => '4. Présentation croisée',
                'o' => 'Présenter sa production à un autre groupe et recevoir un retour critérié.',
                'i' => 'Présentation en binômes de groupes plutôt que devant la classe entière : chacun présente réellement et le temps de parole est multiplié.',
                'a' => [
                    ['t' => 'discuss', 'd' => 20, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Chaque groupe présente à un autre groupe, qui rend un retour structuré par les quatre critères de réussite.',
                     'inst' => 'Présentez votre production au groupe qui vous fait face en cinq minutes. Le groupe qui écoute répond critère par critère.'],
                ],
            ],
            [
                't' => '5. Auto-évaluation',
                'o' => 'Situer sa production et identifier ce qui resterait à améliorer.',
                'i' => 'L’auto-évaluation critériée après retour des pairs est plus lucide et prépare la note attribuée par l’enseignant.',
                'a' => [
                    ['t' => 'practice', 'd' => 15, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Positionnement du groupe sur chaque critère avec justification écrite, puis identification d’un point à améliorer.',
                     'inst' => 'Positionnez votre production sur chaque critère et justifiez en une phrase. Écrivez la première chose que vous corrigeriez avec une heure de plus.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'ecriture-guidee',
        'family' => 'produire',
        'icon' => 'fa-solid fa-pen-nib',
        'titleFr' => 'Écriture guidée par étapes',
        'titleEn' => 'Guided writing in stages',
        'summaryFr' => 'Analyse d’un modèle expert, plan, brouillon, relecture croisée à critères, réécriture.',
        'summaryEn' => 'Analysis of an expert model, plan, draft, criteria-based peer review, rewriting.',
        'keywords' => 'écriture, rédaction, brouillon, relecture croisée, réécriture, modèle expert',
        'mode' => 'onsite',
        'description' => 'Séance de rédaction de [TYPE D’ÉCRIT] en [MATIÈRE], [NIVEAU]. Le texte passe par un modèle analysé, un brouillon, une relecture par un pair et une réécriture effective en classe.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : maîtriser l’écriture de [TYPE D’ÉCRIT].',
        'personas' => '1) Faire percevoir les caractéristiques du genre attendu. 2) Faire réécrire effectivement, et non seulement rédiger.',
        'outcomes' => [
            ['analyser', 'Analyser', 'Analyser un exemple de [TYPE D’ÉCRIT] pour en dégager les caractéristiques.'],
            ['creer', 'Rédiger', 'Rédiger un [TYPE D’ÉCRIT] conforme aux critères identifiés.'],
            ['evaluer', 'Critiquer', 'Critiquer le texte d’un pair à partir de critères explicites.'],
            ['appliquer', 'Modifier', 'Modifier son texte à partir des remarques reçues.'],
        ],
        'moments' => [
            [
                't' => '1. Analyse d’un modèle expert',
                'o' => 'Dégager les caractéristiques du genre à produire.',
                'i' => 'Partir d’un texte réussi et faire nommer par les élèves ce qui le rend réussi : la grille de critères est ainsi construite, non subie.',
                'a' => [
                    ['t' => 'investigate', 'd' => 10, 'g' => 'subgroups', 'aias' => 'na',
                     'desc' => 'Analyse en binômes de [EXEMPLE EXPERT] : repérage de la structure, des marques de langue, de la longueur, puis mise en commun sous forme de critères.',
                     'inst' => 'À deux, repérez comment ce texte est construit : combien de parties, quels mots de liaison, quelle longueur. Vous proposez trois critères pour la classe.'],
                ],
            ],
            [
                't' => '2. Plan et brouillon',
                'o' => 'Organiser ses idées avant de rédiger.',
                'i' => 'Le plan est validé avant la rédaction : corriger une organisation prend deux minutes, corriger un texte entier en prend vingt.',
                'a' => [
                    ['t' => 'produce', 'd' => 15, 'g' => 'individual', 'aias' => 1,
                     'desc' => 'Rédaction d’un plan puis d’un premier jet de [TYPE D’ÉCRIT] sur [SUJET D’ÉCRITURE]. L’enseignant valide les plans en circulant.',
                     'inst' => 'Écris d’abord ton plan en trois lignes et fais-le valider. Rédige ensuite ton premier jet sans t’arrêter sur l’orthographe.'],
                ],
            ],
            [
                't' => '3. Relecture croisée à critères',
                'o' => 'Recevoir un retour précis, adossé aux critères construits.',
                'i' => 'Le relecteur ne corrige pas : il signale à l’aide des critères, ce qui laisse la responsabilité du texte à son auteur.',
                'a' => [
                    ['t' => 'discuss', 'd' => 15, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Échange des brouillons par deux, annotation à partir des critères, puis retour oral de trois minutes par texte.',
                     'inst' => 'Lis le texte de ton camarade avec la grille. Tu ne corriges pas : tu signales où un critère n’est pas respecté et tu expliques oralement.'],
                ],
            ],
            [
                't' => '4. Réécriture',
                'o' => 'Produire une seconde version qui intègre les retours.',
                'i' => 'La réécriture a lieu en classe, pas à la maison : c’est le geste que l’on cherche à enseigner et il doit être accompagné.',
                'a' => [
                    ['t' => 'produce', 'd' => 15, 'g' => 'individual', 'ev' => 'formative', 'aias' => 1,
                     'desc' => 'Seconde version rédigée en tenant compte des remarques, avec mention explicite des deux modifications les plus importantes.',
                     'inst' => 'Réécris ton texte en tenant compte des remarques. En bas de la page, note les deux changements les plus importants que tu as faits.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'production-mediatique',
        'family' => 'produire',
        'icon' => 'fa-solid fa-podcast',
        'titleFr' => 'Production médiatique',
        'titleEn' => 'Media production',
        'summaryFr' => 'Trois séances : analyse du genre visé, scénarisation, réalisation, diffusion et bilan éditorial. Podcast, capsule, affiche ou une de journal.',
        'summaryEn' => 'Three lessons: analysis of the target genre, storyboarding, production, publication and editorial review.',
        'keywords' => 'podcast, vidéo, affiche, média, storyboard, EMI, diffusion',
        'mode' => 'onsite',
        'description' => 'Projet de trois séances : réaliser [PRODUCTION MÉDIATIQUE] sur [SUJET] en [MATIÈRE], [NIVEAU]. Le genre est d’abord analysé, la production est scénarisée avant d’être réalisée, puis diffusée à un public réel.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : traiter [SUJET] et travailler l’éducation aux médias.',
        'personas' => '1) Faire produire un média conforme aux codes de son genre. 2) Faire vérifier et citer les sources utilisées.',
        'outcomes' => [
            ['analyser', 'Analyser', 'Analyser les codes de [PRODUCTION MÉDIATIQUE] à partir d’exemples professionnels.'],
            ['creer', 'Concevoir', 'Concevoir le scénario de [PRODUCTION MÉDIATIQUE] sur [SUJET].'],
            ['appliquer', 'Utiliser', 'Utiliser les outils de réalisation et d’édition nécessaires.'],
            ['evaluer', 'Justifier', 'Justifier ses choix éditoriaux et le traitement de ses sources.'],
        ],
        'moments' => [
            [
                't' => '1. Analyse du genre visé',
                'o' => 'Identifier les codes du média à produire.',
                'i' => 'On ne produit bien qu’un genre que l’on a d’abord démonté : deux exemples professionnels analysés valent mieux qu’une consigne de format.',
                'n' => 'Sélectionner deux exemples professionnels contrastés de [PRODUCTION MÉDIATIQUE].',
                'a' => [
                    ['t' => 'investigate', 'd' => 20, 'g' => 'subgroups', 'aias' => 1,
                     'desc' => 'Analyse de deux exemples professionnels : durée, structure, ton, place des sources. Construction collective de la grille de production.',
                     'inst' => 'À trois, analysez les deux exemples : combien de temps, quelles parties, quel ton, comment les sources sont citées. Vous proposez trois règles pour notre production.'],
                ],
            ],
            [
                't' => '2. Scénarisation',
                'o' => 'Écrire le scénario ou le storyboard avant toute réalisation.',
                'i' => 'La réalisation ne commence pas avant validation du scénario : sans cette étape, les élèves refont indéfiniment des prises sans progresser.',
                'a' => [
                    ['t' => 'produce', 'd' => 35, 'g' => 'subgroups', 'aias' => 2,
                     'desc' => 'Rédaction du scénario ou storyboard de [PRODUCTION MÉDIATIQUE], avec sources identifiées pour chaque affirmation. Validation par l’enseignant.',
                     'inst' => 'Écrivez votre scénario minuté. Pour chaque affirmation que vous avancez, notez la source à côté. Faites valider avant de passer à la réalisation.'],
                ],
            ],
            [
                't' => '3. Réalisation',
                'o' => 'Réaliser la production conforme au scénario validé.',
                'i' => 'Les rôles techniques sont attribués nommément pour éviter que la réalisation soit accaparée par un seul élève du groupe.',
                'a' => [
                    ['t' => 'produce', 'd' => 55, 'g' => 'subgroups', 'aias' => 3,
                     'desc' => 'Enregistrement, montage ou mise en page de [PRODUCTION MÉDIATIQUE] avec [OUTIL DE PRODUCTION], rôles techniques répartis.',
                     'inst' => 'Réalisez votre production en suivant le scénario validé. Chacun tient son rôle technique. Sauvegardez et nommez votre fichier selon la consigne.'],
                ],
            ],
            [
                't' => '4. Diffusion et retours',
                'o' => 'Confronter la production à un public et recueillir ses réactions.',
                'i' => 'La diffusion à un public réel change le niveau d’exigence : elle est la raison pour laquelle le projet vaut mieux qu’un exercice.',
                'a' => [
                    ['t' => 'discuss', 'd' => 30, 'g' => 'whole', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Diffusion des productions devant [PUBLIC VISÉ], avec retours structurés par la grille construite en séance 1.',
                     'inst' => 'Présentez votre production. Pendant les autres présentations, remplissez la grille et notez une réussite à retenir pour la prochaine fois.'],
                ],
            ],
            [
                't' => '5. Bilan technique et éditorial',
                'o' => 'Faire le bilan des choix éditoriaux et des difficultés techniques.',
                'i' => 'Le bilan sépare ce qui relève du contenu et ce qui relève de la technique : les élèves confondent souvent les deux dans leur auto-évaluation.',
                'a' => [
                    ['t' => 'discuss', 'd' => 25, 'g' => 'whole', 'aias' => 1,
                     'desc' => 'Retour sur les choix de traitement du sujet, la vérification des sources et les obstacles techniques rencontrés.',
                     'inst' => 'Dis un choix éditorial dont tu es satisfait, une source que tu as écartée et pourquoi, et une difficulté technique que tu saurais éviter.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'demarche-investigation',
        'family' => 'produire',
        'icon' => 'fa-solid fa-flask',
        'titleFr' => 'Démarche d’investigation',
        'titleEn' => 'Inquiry-based lesson',
        'summaryFr' => 'Situation déclenchante, hypothèses, conception du protocole, expérimentation, interprétation et conclusion.',
        'summaryEn' => 'Triggering situation, hypotheses, protocol design, experiment, interpretation and conclusion.',
        'keywords' => 'investigation, sciences, hypothèse, protocole, expérience, conclusion',
        'mode' => 'onsite',
        'description' => 'Séance d’investigation sur [PHÉNOMÈNE ÉTUDIÉ] en [MATIÈRE], [NIVEAU]. Les élèves formulent des hypothèses, concoivent le protocole, mesurent puis concluent en confrontant hypothèse et résultat.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : expliquer [PHÉNOMÈNE ÉTUDIÉ] par la démarche expérimentale.',
        'personas' => '1) Faire construire une hypothèse testable. 2) Faire concevoir un protocole qui isole la variable étudiée.',
        'outcomes' => [
            ['creer', 'Formuler', 'Formuler une hypothèse testable sur [PHÉNOMÈNE ÉTUDIÉ].'],
            ['creer', 'Concevoir', 'Concevoir un protocole qui ne fait varier qu’un seul paramètre.'],
            ['appliquer', 'Exécuter', 'Exécuter le protocole et relever les mesures avec l’unité correcte.'],
            ['evaluer', 'Conclure', 'Conclure en confrontant les résultats obtenus à l’hypothèse de départ.'],
        ],
        'moments' => [
            [
                't' => '1. Situation déclenchante',
                'o' => 'Faire surgir une question à laquelle l’observation seule ne répond pas.',
                'i' => 'La situation doit résister : si l’explication est immédiatement disponible, l’expérience devient une simple illustration.',
                'a' => [
                    ['t' => 'read', 'd' => 5, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Présentation de [PHÉNOMÈNE ÉTUDIÉ] sous forme d’observation surprenante ou de résultat contre-intuitif.',
                     'inst' => 'Observe et note la question que cela pose. Pas encore d’explication.'],
                ],
            ],
            [
                't' => '2. Formulation des hypothèses',
                'o' => 'Produire des hypothèses testables et les écrire au tableau.',
                'i' => 'Toutes les hypothèses sont écrites, y compris les fausses : elles seront confrontées aux résultats, ce qui donne son sens à la mesure.',
                'a' => [
                    ['t' => 'discuss', 'd' => 10, 'g' => 'subgroups', 'aias' => 'na',
                     'desc' => 'Formulation par groupes d’hypothèses au format « si… alors… », toutes reportées au tableau sans validation.',
                     'inst' => 'À trois, écrivez votre hypothèse sous la forme « si… alors… ». Elle doit pouvoir être vérifiée par une expérience.'],
                ],
            ],
            [
                't' => '3. Conception du protocole',
                'o' => 'Concevoir une expérience qui teste l’hypothèse en isolant une variable.',
                'i' => 'C’est le moment le plus formateur et le plus souvent escamoté : donner le protocole tout fait supprime l’essentiel de la démarche.',
                'n' => 'Valider chaque protocole avant manipulation, en vérifiant le témoin et la variable isolée.',
                'a' => [
                    ['t' => 'collaborate', 'd' => 10, 'g' => 'subgroups', 'aias' => 2,
                     'desc' => 'Rédaction du protocole par groupe : matériel, variable testée, témoin, mesures à relever. Validation par l’enseignant avant manipulation.',
                     'inst' => 'Écrivez votre protocole : le matériel, ce que vous faites varier, ce que vous gardez identique, ce que vous mesurez. Faites valider avant de manipuler.'],
                ],
            ],
            [
                't' => '4. Expérimentation et mesures',
                'o' => 'Réaliser l’expérience et relever les mesures rigoureusement.',
                'i' => 'Les résultats sont consignés dans un tableau donné à l’avance : la rigueur du relevé conditionne l’interprétation.',
                'a' => [
                    ['t' => 'investigate', 'd' => 20, 'g' => 'subgroups', 'aias' => 1,
                     'desc' => 'Réalisation de l’expérience et relevé des mesures dans un tableau, avec consignes de sécurité rappelées.',
                     'inst' => 'Réalisez l’expérience et remplissez le tableau de mesures, avec les unités. Notez aussi ce qui ne s’est pas passé comme prévu.'],
                ],
            ],
            [
                't' => '5. Interprétation et conclusion',
                'o' => 'Confronter les résultats aux hypothèses et rédiger la conclusion.',
                'i' => 'On revient explicitement aux hypothèses écrites au tableau : une hypothèse infirmée est un résultat, non un échec.',
                'a' => [
                    ['t' => 'produce', 'd' => 10, 'g' => 'individual', 'ev' => 'formative', 'aias' => 1,
                     'desc' => 'Rédaction individuelle de la conclusion, reprenant l’hypothèse de départ et le résultat obtenu, avec les limites de l’expérience.',
                     'inst' => 'Rédige ta conclusion : rappelle l’hypothèse, dis si elle est confirmée ou non, et indique une limite de l’expérience.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'recherche-documentaire',
        'family' => 'produire',
        'icon' => 'fa-solid fa-magnifying-glass',
        'titleFr' => 'Recherche documentaire et vérification des sources',
        'titleEn' => 'Documentary research and source checking',
        'summaryFr' => 'Cadrage de la question, recherche sourcée en binômes, croisement et évaluation de fiabilité, fiche de synthèse référencée.',
        'summaryEn' => 'Framing the question, sourced paired research, cross-checking and reliability assessment, referenced summary sheet.',
        'keywords' => 'EMI, recherche documentaire, sources, fiabilité, croisement, référence',
        'mode' => 'onsite',
        'description' => 'Séance de recherche sur [QUESTION DE RECHERCHE] en [MATIÈRE], [NIVEAU], un poste par binôme. Les élèves doivent croiser deux sources indépendantes et justifier leur fiabilité.',
        'command' => 'Éducation aux médias et à l’information : rechercher, vérifier et référencer une information sur [QUESTION DE RECHERCHE].',
        'personas' => '1) Faire construire une requête efficace. 2) Faire évaluer la fiabilité d’une source et la citer correctement.',
        'outcomes' => [
            ['appliquer', 'Utiliser', 'Utiliser des mots-clés pertinents pour construire une requête.'],
            ['analyser', 'Distinguer', 'Distinguer une source institutionnelle d’un contenu d’opinion ou promotionnel.'],
            ['evaluer', 'Évaluer', 'Évaluer la fiabilité d’une source en croisant deux références indépendantes.'],
            ['creer', 'Rédiger', 'Rédiger une fiche de synthèse citant correctement ses sources.'],
        ],
        'moments' => [
            [
                't' => '1. Cadrage de la question de recherche',
                'o' => 'Transformer un sujet vague en question précise et en mots-clés.',
                'i' => 'La qualité de la recherche dépend presque entièrement de la formulation initiale : ce temps de cadrage n’est pas une perte de temps.',
                'a' => [
                    ['t' => 'read', 'd' => 10, 'g' => 'whole', 'aias' => 1,
                     'desc' => 'Décomposition de [QUESTION DE RECHERCHE] en trois mots-clés, avec démonstration au tableau de deux requêtes de qualité inégale.',
                     'inst' => 'Écris la question, puis les trois mots-clés que tu vas utiliser. Compare avec la requête projetée : laquelle est la plus précise ?'],
                ],
            ],
            [
                't' => '2. Recherche et collecte sourcée',
                'o' => 'Collecter des informations en conservant systématiquement leur origine.',
                'i' => 'Le tableau de collecte impose de noter la source en même temps que l’information : ajoutée après coup, elle est toujours approximative.',
                'n' => 'Un poste par binôme. Interdire le copier-coller sans mention de l’URL et de la date de consultation.',
                'a' => [
                    ['t' => 'investigate', 'd' => 25, 'g' => 'subgroups', 'aias' => 2,
                     'desc' => 'Recherche en binômes, collecte dans un tableau à trois colonnes : information / source précise / date de consultation.',
                     'inst' => 'À deux, remplissez le tableau. Aucune information sans sa source exacte et la date. Vous devez trouver deux sources différentes pour chaque information importante.'],
                ],
            ],
            [
                't' => '3. Croisement et évaluation de fiabilité',
                'o' => 'Confronter les sources et justifier un verdict de fiabilité.',
                'i' => 'On évalue la source, pas seulement l’information : qui publie, dans quel but, avec quelles preuves. Le désaccord entre binômes est exploité.',
                'a' => [
                    ['t' => 'discuss', 'd' => 10, 'g' => 'whole', 'ev' => 'formative', 'aias' => 1,
                     'desc' => 'Mise en commun des sources trouvées, classement collectif par fiabilité, avec justification par l’auteur, l’intention et les preuves apportées.',
                     'inst' => 'Annonce une source que tu as utilisée et dis pourquoi tu lui fais confiance : qui publie, dans quel but, avec quelles preuves.'],
                ],
            ],
            [
                't' => '4. Fiche de synthèse référencée',
                'o' => 'Produire une synthèse courte, exacte et correctement référencée.',
                'i' => 'La contrainte de longueur oblige à hiérarchiser : une synthèse illimitée redevient un copier-coller.',
                'a' => [
                    ['t' => 'produce', 'd' => 10, 'g' => 'individual', 'ev' => 'formative', 'aias' => 2,
                     'desc' => 'Rédaction d’une fiche de synthèse de dix lignes maximum répondant à [QUESTION DE RECHERCHE], avec bibliographie de deux sources.',
                     'inst' => 'Rédige dix lignes maximum qui répondent à la question, avec tes mots. En bas, cite tes deux sources : auteur, titre, site, date.'],
                ],
            ],
        ],
    ],

    // ── Évaluer et faire le bilan ───────────────────────────────────────────
    [
        'id' => 'evaluation-correction-active',
        'family' => 'evaluer',
        'icon' => 'fa-solid fa-clipboard-check',
        'titleFr' => 'Évaluation sommative et correction active',
        'titleEn' => 'Summative assessment and active correction',
        'summaryFr' => 'Deux séances : l’évaluation, puis l’analyse de ses propres erreurs, la remédiation en ateliers et un plan de progrès.',
        'summaryEn' => 'Two lessons: the assessment, then error analysis, workshop remediation and a progress plan.',
        'keywords' => 'évaluation, sommative, correction active, analyse d’erreurs, remédiation',
        'mode' => 'onsite',
        'description' => 'Évaluation de [CHAPITRE] en [MATIÈRE], [NIVEAU], suivie d’une séance de correction active où les élèves classent leurs propres erreurs avant de remédier en ateliers.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : évaluer les acquis de [CHAPITRE].',
        'personas' => '1) Mesurer les acquis de manière fiable. 2) Faire de la correction un moment d’apprentissage et non de distribution de notes.',
        'outcomes' => [
            ['appliquer', 'Appliquer', 'Appliquer les notions de [CHAPITRE] dans les exercices de l’évaluation.'],
            ['analyser', 'Examiner', 'Examiner sa copie pour classer ses erreurs par type.'],
            ['evaluer', 'Décider', 'Décider des deux points à retravailler en priorité.'],
        ],
        'moments' => [
            [
                't' => '1. Évaluation',
                'o' => 'Mesurer les acquis de [CHAPITRE] dans des conditions équitables.',
                'i' => 'Le barème est indiqué sur le sujet et les types d’exercices sont ceux travaillés en classe : l’évaluation vérifie l’enseignement, elle ne le déborde pas.',
                'n' => 'Prévoir un sujet aménagé pour les élèves à besoins particuliers. Barème visible sur le sujet.',
                'a' => [
                    ['t' => 'practice', 'd' => 55, 'g' => 'individual', 'ev' => 'summative', 'aias' => 1,
                     'desc' => 'Évaluation écrite sur [CHAPITRE] : restitution, application et une question de raisonnement, barème annoncé.',
                     'inst' => 'Travaille seul, sans documents. Le barème est indiqué à côté de chaque question : commence par ce que tu sais faire.'],
                ],
            ],
            [
                't' => '2. Analyse de ses propres erreurs',
                'o' => 'Classer ses erreurs par type pour en comprendre l’origine.',
                'i' => 'Le classement par type — lecture de consigne, connaissance manquante, erreur de méthode, inattention — transforme la note en information utilisable.',
                'a' => [
                    ['t' => 'investigate', 'd' => 20, 'g' => 'individual', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Chaque élève reprend sa copie et classe ses erreurs dans quatre catégories : consigne mal lue, connaissance manquante, méthode, inattention.',
                     'inst' => 'Reprends ta copie et classe chaque erreur dans l’une des quatre cases. Compte combien tu en as dans chaque case.'],
                ],
            ],
            [
                't' => '3. Remédiation en ateliers',
                'o' => 'Retravailler ce qui a échoué, selon le type d’erreur commis.',
                'i' => 'Les ateliers sont organisés par type d’erreur, non par note : deux élèves de niveaux différents peuvent partager la même difficulté de méthode.',
                'a' => [
                    ['t' => 'practice', 'd' => 25, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 1,
                     'desc' => 'Trois ateliers de remédiation correspondant aux erreurs les plus fréquentes, chaque élève rejoignant celui de sa catégorie dominante.',
                     'inst' => 'Rejoins l’atelier qui correspond à la case où tu as le plus d’erreurs. Refais l’exercice raté dans sa version corrigée.'],
                ],
            ],
            [
                't' => '4. Plan de progrès',
                'o' => 'Formuler deux objectifs personnels précis pour le chapitre suivant.',
                'i' => 'Deux objectifs seulement, écrits et vérifiables : un plan de progrès long n’est jamais relu.',
                'a' => [
                    ['t' => 'produce', 'd' => 10, 'g' => 'individual', 'aias' => 'na',
                     'desc' => 'Rédaction dans le cahier de deux objectifs personnels vérifiables, relus au début de la séance suivante.',
                     'inst' => 'Écris deux objectifs pour la prochaine évaluation, formulés de façon vérifiable, du type « je relis la consigne avant de répondre ».'],
                ],
            ],
        ],
    ],

    [
        'id' => 'bilan-metacognitif',
        'family' => 'evaluer',
        'icon' => 'fa-solid fa-brain',
        'titleFr' => 'Bilan métacognitif de fin de séquence',
        'titleEn' => 'Metacognitive review at the end of a sequence',
        'summaryFr' => 'Demi-séance : retour sur les acquis annoncés, auto-positionnement, écrit réflexif, mise en commun des stratégies efficaces.',
        'summaryEn' => 'Half-lesson: review of stated outcomes, self-positioning, reflective writing, pooling of effective strategies.',
        'keywords' => 'métacognition, bilan, auto-positionnement, stratégies, fin de séquence',
        'mode' => 'onsite',
        'description' => 'Demi-séance de 30 min à la fin de [SÉQUENCE]. Les élèves se positionnent sur chacun des acquis annoncés au début, écrivent ce qui résiste, puis échangent leurs stratégies de travail.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : clôture de [SÉQUENCE].',
        'personas' => '1) Faire prendre conscience du chemin parcouru. 2) Faire circuler les stratégies d’apprentissage efficaces entre élèves.',
        'outcomes' => [
            ['evaluer', 'Apprécier', 'Apprécier son niveau de maîtrise sur chacun des acquis de [SÉQUENCE].'],
            ['analyser', 'Examiner', 'Examiner sa manière de travailler et en repérer un point faible.'],
            ['comprendre', 'Expliquer', 'Expliquer à la classe une stratégie de travail qui a fonctionné.'],
        ],
        'moments' => [
            [
                't' => '1. Retour sur les acquis annoncés',
                'o' => 'Relire les acquis visés au début de la séquence.',
                'i' => 'Le bilan part des acquis effectivement annoncés : c’est ce qui rend le contrat pédagogique lisible d’un bout à l’autre.',
                'a' => [
                    ['t' => 'read', 'd' => 5, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Projection des acquis annoncés au début de [SÉQUENCE] et rappel des principales activités menées.',
                     'inst' => 'Relis les acquis affichés au début de la séquence. Pour chacun, prépare-toi à dire où tu en es.'],
                ],
            ],
            [
                't' => '2. Auto-positionnement',
                'o' => 'Situer précisément son niveau de maîtrise sur chaque acquis.',
                'i' => 'Positionnement écrit et individuel avant tout échange : l’avis du voisin déforme fortement l’auto-évaluation quand elle est publique.',
                'a' => [
                    ['t' => 'practice', 'd' => 10, 'g' => 'individual', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Positionnement sur une échelle à quatre niveaux pour chaque acquis, avec obligation de citer une preuve concrète par acquis maîtrisé.',
                     'inst' => 'Positionne-toi sur chaque acquis. Pour chaque acquis que tu déclares maîtrisé, cite un exercice précis qui le prouve.'],
                ],
            ],
            [
                't' => '3. Écrit réflexif',
                'o' => 'Expliciter ce qui a été compris et ce qui résiste encore.',
                'i' => 'Un écrit court en deux volets : ce qui est acquis, ce qui résiste. La seconde partie est le vrai matériau pour la séquence suivante.',
                'a' => [
                    ['t' => 'produce', 'd' => 10, 'g' => 'individual', 'aias' => 'na',
                     'desc' => 'Rédaction en deux paragraphes : ce que j’ai compris et qui me servira, ce qui résiste et comment je vais m’y prendre.',
                     'inst' => 'Écris deux paragraphes : « ce que j’ai compris et qui me servira » et « ce qui résiste, et ce que je vais faire pour y arriver ».'],
                ],
            ],
            [
                't' => '4. Mise en commun des stratégies',
                'o' => 'Faire circuler les manières de travailler qui ont fonctionné.',
                'i' => 'Ce sont les stratégies des élèves, non celles de l’enseignant, qui sont recevables ici : elles sont crédibles et immédiatement imitables.',
                'a' => [
                    ['t' => 'discuss', 'd' => 5, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Trois élèves exposent une méthode de travail qui leur a réellement servi pendant la séquence.',
                     'inst' => 'Si tu as trouvé une méthode qui marche pour toi, décris-la en deux phrases. Note celle d’un camarade que tu vas essayer.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'evaluation-par-les-pairs',
        'family' => 'evaluer',
        'icon' => 'fa-solid fa-users-viewfinder',
        'titleFr' => 'Évaluation par les pairs à grille critériée',
        'titleEn' => 'Peer assessment with a criteria grid',
        'summaryFr' => 'Appropriation de la grille sur un exemple, évaluation croisée des productions, retour oral, ajustement de sa production.',
        'summaryEn' => 'Grid appropriation on an example, cross-assessment of work, oral feedback, adjustment of one’s own work.',
        'keywords' => 'évaluation par les pairs, grille critériée, retour, critères, ajustement',
        'mode' => 'onsite',
        'description' => 'Séance d’évaluation croisée de [PRODUCTION ÉVALUÉE] en [MATIÈRE], [NIVEAU]. La grille est d’abord éprouvée collectivement sur un exemple, avant d’être appliquée aux productions de la classe.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : évaluer [PRODUCTION ÉVALUÉE] à partir de critères explicites.',
        'personas' => '1) Faire comprendre les critères en les appliquant. 2) Faire améliorer sa production après retour d’un pair.',
        'outcomes' => [
            ['comprendre', 'Interpréter', 'Interpréter chaque critère de la grille sur un exemple concret.'],
            ['evaluer', 'Évaluer', 'Évaluer la production d’un pair en justifiant chaque niveau attribué.'],
            ['appliquer', 'Modifier', 'Modifier sa propre production à partir du retour reçu.'],
        ],
        'moments' => [
            [
                't' => '1. Appropriation de la grille',
                'o' => 'Comprendre chaque critère en l’appliquant à un exemple commun.',
                'i' => 'Une grille non éprouvée collectivement produit des évaluations incohérentes : ce calibrage initial est ce qui rend l’évaluation par les pairs fiable.',
                'n' => 'Utiliser un exemple anonyme, de préférence d’une autre classe ou d’une année précédente.',
                'a' => [
                    ['t' => 'read', 'd' => 15, 'g' => 'whole', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Évaluation collective d’un exemple anonyme avec la grille, en discutant les écarts de notation critère par critère.',
                     'inst' => 'Note l’exemple projeté avec la grille, seul d’abord. On compare ensuite : sur quel critère nos avis diffèrent-ils le plus ?'],
                ],
            ],
            [
                't' => '2. Évaluation croisée',
                'o' => 'Évaluer la production d’un pair en justifiant chaque niveau.',
                'i' => 'L’obligation de justifier par écrit chaque niveau attribué empêche l’évaluation de complaisance comme l’évaluation punitive.',
                'a' => [
                    ['t' => 'practice', 'd' => 25, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Évaluation croisée par deux avec la grille, chaque niveau attribué devant être justifié par un élément précis de la production.',
                     'inst' => 'Évalue la production de ton camarade critère par critère. Pour chaque niveau, écris l’élément précis qui te fait choisir ce niveau.'],
                ],
            ],
            [
                't' => '3. Retour oral au camarade',
                'o' => 'Formuler un retour utilisable, orienté vers l’amélioration.',
                'i' => 'Le format imposé — un point fort, un conseil actionnable — protège l’auteur et rend le retour exploitable.',
                'a' => [
                    ['t' => 'discuss', 'd' => 10, 'g' => 'subgroups', 'aias' => 'na',
                     'desc' => 'Retour oral en binôme, selon le format imposé : un point fort précis, puis un conseil formulé comme une action à faire.',
                     'inst' => 'Dis à ton camarade un point fort précis, puis un conseil qui commence par un verbe d’action. Pas de jugement global.'],
                ],
            ],
            [
                't' => '4. Ajustement de sa production',
                'o' => 'Modifier immédiatement sa production à partir du retour reçu.',
                'i' => 'Sans temps de reprise en classe, l’évaluation par les pairs ne modifie rien : cette étape courte est ce qui la rend utile.',
                'a' => [
                    ['t' => 'produce', 'd' => 5, 'g' => 'individual', 'aias' => 'na',
                     'desc' => 'Reprise immédiate de la production sur le point signalé, avec mention du changement effectué.',
                     'inst' => 'Corrige tout de suite le point signalé par ton camarade, et note en marge ce que tu as changé.'],
                ],
            ],
        ],
    ],

    // ── Usages encadrés de l’IA ─────────────────────────────────────────────
    [
        'id' => 'ia-generative-encadree',
        'family' => 'ia',
        'icon' => 'fa-solid fa-robot',
        'titleFr' => 'Séance avec IA générative encadrée',
        'titleEn' => 'Lesson with framed generative AI use',
        'summaryFr' => 'Cadrage de l’usage autorisé, production sans IA, amélioration assistée avec journal des prompts, comparaison avant/après.',
        'summaryEn' => 'Framing the permitted use, production without AI, AI-assisted improvement with a prompt log, before/after comparison.',
        'keywords' => 'IA générative, AIAS, prompt, journal, comparaison, usage encadré',
        'mode' => 'onsite',
        'description' => 'Séance de 55 min sur [TÂCHE] en [MATIÈRE], [NIVEAU], un poste par élève. L’élève produit d’abord sans IA, puis améliore avec l’IA en conservant la trace de ses prompts, et justifie enfin ses choix.',
        'command' => 'Usage raisonné et documenté de l’IA générative dans [MATIÈRE]. Niveau AIAS annoncé et vérifiable.',
        'personas' => '1) Rendre l’usage de l’IA visible et discutable. 2) Faire évaluer la valeur ajoutée réelle de l’assistance.',
        'outcomes' => [
            ['creer', 'Produire', 'Produire une première version de [TÂCHE] sans aucune assistance.'],
            ['appliquer', 'Utiliser', 'Utiliser un prompt précis et conserver la trace de ses échanges.'],
            ['analyser', 'Comparer', 'Comparer sa version initiale et sa version assistée point par point.'],
            ['evaluer', 'Justifier', 'Justifier chaque proposition de l’IA retenue ou écartée.'],
        ],
        'moments' => [
            [
                't' => '1. Cadrage de l’usage autorisé',
                'o' => 'Savoir exactement ce qui est permis, ce qui est interdit et ce qui doit être tracé.',
                'i' => 'Le niveau AIAS est annoncé et écrit : sans cadre explicite, l’usage de l’IA se règle en fonction de ce que chaque élève croit toléré.',
                'n' => 'Afficher le niveau AIAS de la séance au tableau. Rappeler que le journal des prompts sera relevé.',
                'a' => [
                    ['t' => 'read', 'd' => 10, 'g' => 'whole', 'aias' => 1,
                     'desc' => 'Annonce du niveau AIAS de la séance, de ce qui est autorisé, et du format du journal des prompts qui sera rendu.',
                     'inst' => 'Note le niveau d’usage autorisé aujourd’hui et ce que tu devras rendre : ta première version, ton journal de prompts, ta version finale.'],
                ],
            ],
            [
                't' => '2. Première production sans IA',
                'o' => 'Produire une première version personnelle, sans aucune assistance.',
                'i' => 'La version sans IA est la référence de comparaison et la preuve du travail personnel : sans elle, aucun progrès n’est mesurable.',
                'a' => [
                    ['t' => 'produce', 'd' => 15, 'g' => 'individual', 'ev' => 'formative', 'aias' => 1,
                     'desc' => 'Rédaction d’une première version de [TÂCHE], enregistrée et horodatée avant toute utilisation de l’IA.',
                     'inst' => 'Produis ta première version seul, sans IA. Enregistre-la sous le nom « v1 » avant de continuer : elle ne sera plus modifiée.'],
                ],
            ],
            [
                't' => '3. Amélioration assistée et journal des prompts',
                'o' => 'Améliorer sa production avec l’IA en conservant la trace des échanges.',
                'i' => 'Le journal des prompts déplace l’attention du résultat vers la démarche : c’est lui qui rend l’usage évaluable.',
                'a' => [
                    ['t' => 'produce', 'd' => 15, 'g' => 'individual', 'aias' => 3,
                     'desc' => 'Amélioration de la version 1 avec [OUTIL IA], chaque prompt et chaque décision (retenu / écarté) étant consignés dans un journal.',
                     'inst' => 'Améliore ta v1 avec l’IA. Pour chaque échange, copie ton prompt dans le journal et note si tu retiens ou si tu écartes la proposition, et pourquoi.'],
                ],
            ],
            [
                't' => '4. Comparaison avant/après et justification',
                'o' => 'Mesurer l’apport réel de l’assistance et défendre ses choix.',
                'i' => 'La discussion porte sur les propositions écartées autant que sur celles retenues : c’est là que se lit le jugement de l’élève.',
                'a' => [
                    ['t' => 'discuss', 'd' => 15, 'g' => 'whole', 'ev' => 'formative', 'aias' => 1,
                     'desc' => 'Confrontation collective des versions 1 et 2, avec justification de deux propositions retenues et d’une proposition écartée.',
                     'inst' => 'Présente une chose que l’IA a réellement améliorée, et une proposition que tu as refusée en disant pourquoi.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'esprit-critique-ia',
        'family' => 'ia',
        'icon' => 'fa-solid fa-magnifying-glass-chart',
        'titleFr' => 'Atelier esprit critique face à l’IA',
        'titleEn' => 'Critical thinking workshop on AI',
        'summaryFr' => 'Représentations initiales, test du caractère probabiliste, chasse aux sources fabriquées, construction d’un protocole de vérification.',
        'summaryEn' => 'Prior conceptions, testing probabilistic behaviour, hunting fabricated sources, building a verification protocol.',
        'keywords' => 'EMI, esprit critique, hallucination, sources, vérification, IA générative',
        'mode' => 'onsite',
        'description' => 'Séance de 55 min, un poste par élève avec accès à [OUTIL IA]. Les élèves testent le caractère probabiliste d’un modèle de langage, vérifient les sources d’une réponse générée, puis construisent un protocole de vérification en quatre étapes.',
        'command' => 'Éducation aux médias et à l’information : esprit critique face aux contenus générés par IA.',
        'personas' => '1) Faire comprendre le fonctionnement probabiliste d’un modèle de langage. 2) Entraîner une méthode de vérification et de croisement des sources.',
        'outcomes' => [
            ['comprendre', 'Expliquer', 'Expliquer qu’un modèle de langage prédit le mot le plus probable et ne vérifie pas ce qu’il affirme.'],
            ['analyser', 'Distinguer', 'Distinguer, dans une réponse d’IA, les affirmations vérifiables des sources inexistantes.'],
            ['appliquer', 'Appliquer', 'Appliquer un protocole de vérification en quatre étapes à des affirmations générées.'],
            ['evaluer', 'Évaluer', 'Évaluer la fiabilité d’une information en croisant deux sources indépendantes.'],
            ['creer', 'Rédiger', 'Rédiger une fiche-protocole personnelle de vérification.'],
        ],
        'moments' => [
            [
                't' => '1. Ce que je crois savoir de l’IA',
                'o' => 'Faire émerger les représentations initiales et repérer les conceptions erronées.',
                'i' => 'Conflit sociocognitif d’entrée : les élèves se positionnent avant tout apport, ce qui rend la déstabilisation du moment 3 efficace.',
                'n' => 'Ordinateurs fermés. Noter trois formulations d’élèves au tableau, sans les corriger, et les photographier.',
                'a' => [
                    ['t' => 'discuss', 'd' => 7, 'g' => 'whole', 'ev' => 'diagnostic', 'aias' => 1,
                     'desc' => 'Vote à main levée sur trois affirmations projetées, puis justification par deux élèves de camps opposés. Rien n’est validé.',
                     'inst' => 'Ordinateur fermé. Pour chaque affirmation projetée, lève la main : d’accord / pas d’accord / je ne sais pas. Si je t’interroge, dis ce qui te fait penser cela.'],
                ],
            ],
            [
                't' => '2. Test du caractère probabiliste',
                'o' => 'Observer qu’une même question produit des réponses différentes.',
                'i' => 'L’expérience est menée par les élèves eux-mêmes : constater la variation a plus d’effet qu’une explication du mécanisme.',
                'a' => [
                    ['t' => 'investigate', 'd' => 13, 'g' => 'subgroups', 'aias' => 3,
                     'desc' => 'Même question posée trois fois à [OUTIL IA] par chaque binôme, relevé des différences entre les trois réponses obtenues.',
                     'inst' => 'À deux, posez exactement la même question trois fois dans des conversations neuves. Notez ce qui change entre les trois réponses.'],
                ],
            ],
            [
                't' => '3. Chasse aux sources fabriquées',
                'o' => 'Vérifier une à une les références produites par l’IA.',
                'i' => 'Moment de déstabilisation : découvrir soi-même une référence inventée vaut mieux que se l’entendre annoncer.',
                'n' => 'Choisir une question dont la réponse générée cite des références précises et vérifiables.',
                'a' => [
                    ['t' => 'investigate', 'd' => 15, 'g' => 'subgroups', 'aias' => 3,
                     'desc' => 'Vérification de chaque référence citée dans une réponse générée sur [SUJET VÉRIFIÉ] : existence, auteur, contenu réel.',
                     'inst' => 'Pour chaque source citée par l’IA, cherche si elle existe vraiment : le titre, l’auteur, la date. Classe-la en « vérifiée », « introuvable » ou « déformée ».'],
                ],
            ],
            [
                't' => '4. Construction du protocole de vérification',
                'o' => 'Formaliser une méthode de vérification réutilisable.',
                'i' => 'Le protocole est produit par les élèves à partir de ce qu’ils viennent de faire : une méthode donnée d’avance n’est pas appliquée.',
                'a' => [
                    ['t' => 'produce', 'd' => 12, 'g' => 'individual', 'ev' => 'formative', 'aias' => 2,
                     'desc' => 'Rédaction d’une fiche-protocole personnelle en quatre étapes, enregistrée et nommée correctement.',
                     'inst' => 'Rédige ta fiche-protocole en quatre étapes, dans l’ordre où tu les appliqueras la prochaine fois. Enregistre-la sous le nom demandé.'],
                ],
            ],
            [
                't' => '5. Retour sur les hypothèses de départ',
                'o' => 'Confronter les affirmations du début de séance à ce qui a été observé.',
                'i' => 'La boucle est refermée sur les formulations notées au moment 1 : c’est ce retour qui fixe l’apprentissage.',
                'a' => [
                    ['t' => 'discuss', 'd' => 8, 'g' => 'whole', 'aias' => 1,
                     'desc' => 'Reprise des trois affirmations initiales et arbitrage collectif à la lumière des observations faites.',
                     'inst' => 'Reprends les trois affirmations du début. Pour chacune, dis si tu la maintiens et sur quelle observation d’aujourd’hui tu t’appuies.'],
                ],
            ],
        ],
    ],

    // ── Canevas d’organisation ──────────────────────────────────────────────
    [
        'id' => 'sequence-cinq-seances',
        'family' => 'organiser',
        'icon' => 'fa-solid fa-timeline',
        'titleFr' => 'Séquence complète en cinq séances',
        'titleEn' => 'Full sequence in five lessons',
        'summaryFr' => 'Squelette de séquence : lancement, apports, entraînement, production, évaluation. Les moments sont posés et minutés, le contenu reste à écrire.',
        'summaryEn' => 'Sequence skeleton: launch, input, practice, production, assessment. Moments are set and timed, content remains to be written.',
        'keywords' => 'séquence, progression, canevas, planification, cinq séances',
        'mode' => 'onsite',
        'description' => 'Canevas de séquence sur [CHAPITRE] en [MATIÈRE], [NIVEAU], en cinq séances de 55 min. Chaque séance a une fonction distincte dans la progression ; les activités sont à préciser selon le contenu.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : séquence complète sur [CHAPITRE].',
        'personas' => '1) Répartir les fonctions pédagogiques sur cinq séances. 2) Garantir un temps de production et un temps d’évaluation.',
        'outcomes' => [
            ['comprendre', 'Expliquer', 'Expliquer les notions centrales de [CHAPITRE].'],
            ['appliquer', 'Appliquer', 'Appliquer [NOTION 1] dans des exercices d’entraînement.'],
            ['creer', 'Élaborer', 'Élaborer [PRODUCTION ATTENDUE] mobilisant l’ensemble du chapitre.'],
            ['evaluer', 'Évaluer', 'Évaluer sa maîtrise des acquis annoncés en début de séquence.'],
        ],
        'moments' => [
            [
                't' => 'Séance 1. Lancement',
                'o' => 'Ouvrir le chapitre, recueillir les représentations, poser la question directrice.',
                'i' => 'La séance de lancement ne transmet presque rien : elle installe le besoin de savoir et le contrat de la séquence.',
                'n' => 'Annoncer les acquis visés et la date de l’évaluation dès cette séance.',
                'a' => [
                    ['t' => 'read', 'd' => 10, 'g' => 'whole',
                     'desc' => 'Accroche à partir de [DOCUMENT DÉCLENCHEUR] et annonce des acquis visés.',
                     'inst' => 'Note les acquis visés et la date de l’évaluation en première page du chapitre.'],
                    ['t' => 'discuss', 'd' => 20, 'g' => 'whole', 'ev' => 'diagnostic',
                     'desc' => 'Recueil des représentations initiales sur [NOTION 1] et formulation de la question directrice.',
                     'inst' => 'Dis ce que tu crois savoir sur [NOTION 1], puis écris la question à laquelle le chapitre doit répondre.'],
                    ['t' => 'read', 'd' => 25, 'g' => 'whole',
                     'desc' => 'Premier apport structuré sur [NOTION 1] et vocabulaire minimal du chapitre.',
                     'inst' => 'Note les définitions du chapitre. À compléter selon le contenu de votre discipline.'],
                ],
            ],
            [
                't' => 'Séance 2. Apports',
                'o' => 'Installer les notions et les documents de référence du chapitre.',
                'i' => 'Séance d’apport découpée en deux blocs avec vérification intermédiaire, pour ne pas dépasser la durée d’attention utile.',
                'a' => [
                    ['t' => 'read', 'd' => 25, 'g' => 'whole',
                     'desc' => 'Apport sur [NOTION 2], appuyé sur [DOCUMENT 1]. À préciser selon le contenu.',
                     'inst' => 'Prends des notes structurées : définition, exemple, limite.'],
                    ['t' => 'investigate', 'd' => 20, 'g' => 'subgroups',
                     'desc' => 'Travail sur documents en binômes pour appliquer les notions apportées.',
                     'inst' => 'À deux, exploitez le document avec le tableau de relevé.'],
                    ['t' => 'produce', 'd' => 10, 'g' => 'individual', 'ev' => 'formative',
                     'desc' => 'Trace écrite de synthèse de la séance.',
                     'inst' => 'Complète la synthèse de la séance sans regarder tes notes.'],
                ],
            ],
            [
                't' => 'Séance 3. Entraînement',
                'o' => 'Faire pratiquer et différencier selon les besoins constatés.',
                'i' => 'Séance pilotée par les erreurs relevées aux séances précédentes : son contenu ne peut pas être fixé à l’avance.',
                'a' => [
                    ['t' => 'practice', 'd' => 15, 'g' => 'whole', 'ev' => 'formative',
                     'desc' => 'Reprise collective des erreurs les plus fréquentes des séances 1 et 2.',
                     'inst' => 'Corrige dans ton cahier l’erreur reprise au tableau.'],
                    ['t' => 'practice', 'd' => 30, 'g' => 'subgroups', 'ev' => 'formative',
                     'desc' => 'Ateliers d’entraînement différenciés sur [NOTION 1] et [NOTION 2].',
                     'inst' => 'Choisis l’atelier qui correspond à ton besoin. Le corrigé est sur la table.'],
                    ['t' => 'discuss', 'd' => 10, 'g' => 'whole',
                     'desc' => 'Mise en commun des méthodes efficaces.',
                     'inst' => 'Note la méthode d’un camarade que tu vas réutiliser.'],
                ],
            ],
            [
                't' => 'Séance 4. Production',
                'o' => 'Mobiliser l’ensemble du chapitre dans une production.',
                'i' => 'La production intervient avant l’évaluation, et non après : elle est le dernier entraînement, dans des conditions proches de la tâche évaluée.',
                'a' => [
                    ['t' => 'read', 'd' => 10, 'g' => 'whole',
                     'desc' => 'Présentation de [PRODUCTION ATTENDUE] et de ses critères de réussite.',
                     'inst' => 'Note les critères de réussite de la production.'],
                    ['t' => 'produce', 'd' => 35, 'g' => 'subgroups',
                     'desc' => 'Réalisation de [PRODUCTION ATTENDUE] en groupes.',
                     'inst' => 'Produisez en vérifiant votre avancement contre les critères affichés.'],
                    ['t' => 'discuss', 'd' => 10, 'g' => 'whole', 'ev' => 'formative',
                     'desc' => 'Retours croisés entre groupes sur les productions.',
                     'inst' => 'Donne à un autre groupe un point fort et un conseil actionnable.'],
                ],
            ],
            [
                't' => 'Séance 5. Évaluation',
                'o' => 'Évaluer les acquis annoncés en début de séquence.',
                'i' => 'L’évaluation porte exactement sur les acquis affichés à la séance 1 et sur des formats travaillés en classe.',
                'a' => [
                    ['t' => 'practice', 'd' => 45, 'g' => 'individual', 'ev' => 'summative',
                     'desc' => 'Évaluation écrite sur [CHAPITRE], barème annoncé sur le sujet.',
                     'inst' => 'Travaille seul, sans documents. Commence par ce que tu sais faire.'],
                    ['t' => 'produce', 'd' => 10, 'g' => 'individual',
                     'desc' => 'Auto-positionnement sur les acquis annoncés en séance 1.',
                     'inst' => 'Positionne-toi sur chaque acquis annoncé au début du chapitre.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'sortie-pedagogique',
        'family' => 'organiser',
        'icon' => 'fa-solid fa-map-location-dot',
        'titleFr' => 'Sortie ou visite pédagogique',
        'titleEn' => 'Field trip or educational visit',
        'summaryFr' => 'Trois temps : préparation en classe, relevés sur place, exploitation et production au retour.',
        'summaryEn' => 'Three phases: classroom preparation, on-site data collection, exploitation and production on return.',
        'keywords' => 'sortie, visite, terrain, relevés, exploitation, préparation',
        'mode' => 'hybrid',
        'description' => 'Sortie à [LIEU DE VISITE] en [MATIÈRE], [NIVEAU]. La visite est encadrée par une préparation qui fixe la question de travail et par une exploitation qui produit une trace.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : étudier [NOTION 1] sur le terrain à [LIEU DE VISITE].',
        'personas' => '1) Faire de la sortie un temps de collecte de données, pas de contemplation. 2) Produire au retour une trace exploitable en classe.',
        'outcomes' => [
            ['souvenir', 'Identifier', 'Identifier sur le terrain les éléments liés à [NOTION 1].'],
            ['appliquer', 'Utiliser', 'Utiliser une fiche de relevé pour collecter des données sur place.'],
            ['analyser', 'Analyser', 'Analyser les données collectées au regard de [QUESTION DE VISITE].'],
            ['creer', 'Élaborer', 'Élaborer [PRODUCTION DE RETOUR] à partir des relevés.'],
        ],
        'moments' => [
            [
                't' => '1. Avant : préparation en classe',
                'o' => 'Poser la question de travail et s’approprier la fiche de relevé.',
                'i' => 'Une sortie sans question préalable produit des souvenirs, pas des apprentissages : la fiche de relevé est ce qui transforme la visite en enquête.',
                'n' => 'Distribuer la fiche de relevé et vérifier les autorisations et le matériel.',
                'a' => [
                    ['t' => 'read', 'd' => 15, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Présentation de [LIEU DE VISITE], de [QUESTION DE VISITE] et du déroulé pratique de la sortie.',
                     'inst' => 'Note la question de travail de la sortie et ce que tu devras rapporter.'],
                    ['t' => 'practice', 'd' => 15, 'g' => 'subgroups', 'aias' => 'na',
                     'desc' => 'Appropriation de la fiche de relevé par un exercice de simulation en classe.',
                     'inst' => 'À deux, remplissez la fiche de relevé sur l’exemple projeté, pour vérifier que vous savez quoi noter.'],
                ],
            ],
            [
                't' => '2. Pendant : relevés sur place',
                'o' => 'Collecter les données nécessaires pour répondre à la question de visite.',
                'i' => 'Le temps sur place est réparti entre observation libre et relevés dirigés : les deux sont nécessaires et se gênent si on ne les distingue pas.',
                'a' => [
                    ['t' => 'investigate', 'd' => 90, 'g' => 'subgroups', 'loc' => 'hybrid', 'aias' => 2,
                     'desc' => 'Relevés sur place : observations, photographies, mesures ou entretiens, selon la fiche préparée.',
                     'inst' => 'Remplissez la fiche de relevé par groupe. Photographiez ce que vous ne pouvez pas noter. Une photo sans légende ne servira à rien.'],
                    ['t' => 'discuss', 'd' => 20, 'g' => 'subgroups', 'loc' => 'hybrid', 'aias' => 'na',
                     'desc' => 'Point d’étape sur place : vérification que chaque groupe a bien les données nécessaires.',
                     'inst' => 'Avant de repartir, vérifiez que votre fiche est complète. Il ne sera pas possible de revenir.'],
                ],
            ],
            [
                't' => '3. Après : exploitation et production',
                'o' => 'Exploiter les relevés pour répondre à la question de visite.',
                'i' => 'L’exploitation a lieu au plus près de la sortie : au-delà d’une semaine, les relevés deviennent illisibles pour leurs auteurs.',
                'a' => [
                    ['t' => 'collaborate', 'd' => 25, 'g' => 'subgroups', 'aias' => 2,
                     'desc' => 'Mise en commun et tri des relevés par groupe, en réponse à [QUESTION DE VISITE].',
                     'inst' => 'Triez vos relevés : ce qui répond à la question, ce qui n’y répond pas. Écartez ce qui ne sert pas.'],
                    ['t' => 'produce', 'd' => 30, 'g' => 'subgroups', 'ev' => 'formative', 'aias' => 2,
                     'desc' => 'Réalisation de [PRODUCTION DE RETOUR] à partir des données collectées.',
                     'inst' => 'Réalisez votre production en vous appuyant sur vos relevés. Chaque affirmation doit renvoyer à une observation faite sur place.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'remediation-post-evaluation',
        'family' => 'organiser',
        'icon' => 'fa-solid fa-screwdriver-wrench',
        'titleFr' => 'Séance de remédiation post-évaluation',
        'titleEn' => 'Post-assessment remediation lesson',
        'summaryFr' => 'Diagnostic des erreurs récurrentes, reprise explicite, réentraînement ciblé, vérification de sortie.',
        'summaryEn' => 'Diagnosis of recurring errors, explicit re-teaching, targeted practice, exit check.',
        'keywords' => 'remédiation, erreurs, reprise, réentraînement, vérification',
        'mode' => 'onsite',
        'description' => 'Séance de remédiation après l’évaluation de [CHAPITRE] en [MATIÈRE], [NIVEAU]. Elle traite exclusivement les deux erreurs les plus fréquentes de la classe et vérifie qu’elles sont levées.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : reprendre [NOTION MAL ACQUISE] après l’évaluation de [CHAPITRE].',
        'personas' => '1) Traiter en profondeur deux difficultés plutôt que de tout survoler. 2) Vérifier avant la fin de séance que la difficulté est levée.',
        'outcomes' => [
            ['analyser', 'Examiner', 'Examiner l’origine de son erreur sur [NOTION MAL ACQUISE].'],
            ['appliquer', 'Exécuter', 'Exécuter correctement la procédure liée à [NOTION MAL ACQUISE].'],
            ['comprendre', 'Distinguer', 'Distinguer la procédure correcte de la confusion qui produit l’erreur.'],
        ],
        'moments' => [
            [
                't' => '1. Diagnostic de l’erreur récurrente',
                'o' => 'Faire identifier par les élèves l’erreur type et ce qui la produit.',
                'i' => 'On part de copies réelles anonymisées : l’élève reconnaît son erreur dans celle d’un autre, ce qui la rend discutable sans exposer personne.',
                'n' => 'Anonymiser deux copies présentant l’erreur type. Ne jamais nommer les auteurs.',
                'a' => [
                    ['t' => 'investigate', 'd' => 15, 'g' => 'subgroups', 'ev' => 'diagnostic', 'aias' => 'na',
                     'desc' => 'Analyse en binômes de deux productions anonymes portant l’erreur type : repérage de l’endroit exact et de la cause probable.',
                     'inst' => 'À deux, trouvez à quel endroit exact la copie se trompe, et dites ce que l’élève a probablement confondu.'],
                ],
            ],
            [
                't' => '2. Reprise explicite',
                'o' => 'Réinstaller la procédure correcte en la contrastant avec l’erreur.',
                'i' => 'La procédure correcte est présentée à côté de l’erreur, non à sa place : c’est le contraste qui empêche le retour de la confusion.',
                'a' => [
                    ['t' => 'read', 'd' => 15, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Présentation en deux colonnes de la procédure correcte et de la procédure erronée sur [NOTION MAL ACQUISE].',
                     'inst' => 'Note les deux colonnes : à gauche ce qu’il faut faire, à droite l’erreur à éviter, et ce qui les distingue.'],
                ],
            ],
            [
                't' => '3. Réentraînement ciblé',
                'o' => 'Refaire la procédure jusqu’à l’exécuter sans hésitation.',
                'i' => 'Exercices nombreux et courts sur le seul point traité : la répétition espacée sur un point précis est plus efficace qu’un exercice long et composite.',
                'a' => [
                    ['t' => 'practice', 'd' => 20, 'g' => 'individual', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Série de six exercices courts sur [NOTION MAL ACQUISE], avec autocorrection après chaque paire.',
                     'inst' => 'Fais les exercices deux par deux, et corrige-toi après chaque paire avant de continuer.'],
                ],
            ],
            [
                't' => '4. Vérification de sortie',
                'o' => 'Vérifier avant la fin de la séance que la difficulté est levée.',
                'i' => 'Le billet de sortie donne une information immédiate et nominative : sans lui, la remédiation n’est jamais évaluée.',
                'a' => [
                    ['t' => 'practice', 'd' => 5, 'g' => 'individual', 'ev' => 'formative', 'aias' => 'na',
                     'desc' => 'Billet de sortie : un exercice unique sur le point travaillé, relevé et corrigé par l’enseignant.',
                     'inst' => 'Traite l’exercice du billet de sortie et remets-le en partant. Il n’est pas noté, il me dit si c’est acquis.'],
                ],
            ],
        ],
    ],

    [
        'id' => 'premiere-seance-annee',
        'family' => 'organiser',
        'icon' => 'fa-solid fa-flag',
        'titleFr' => 'Première séance de l’année',
        'titleEn' => 'First lesson of the year',
        'summaryFr' => 'Présentation, contrat de classe co-construit, diagnostic des acquis, mise en route sur une première tâche.',
        'summaryEn' => 'Introductions, co-built class agreement, prior knowledge check, start of a first task.',
        'keywords' => 'rentrée, première séance, contrat de classe, règles, diagnostic',
        'mode' => 'onsite',
        'description' => 'Première séance de l’année en [MATIÈRE], [NIVEAU]. Elle installe le cadre de travail, mesure les acquis de l’année précédente et met la classe au travail dès le premier jour.',
        'command' => 'Programme de [MATIÈRE], [NIVEAU] : installer les conditions de travail de l’année.',
        'personas' => '1) Installer un cadre de travail explicite et co-construit. 2) Mesurer les acquis pour ajuster la progression.',
        'outcomes' => [
            ['souvenir', 'Rappeler', 'Rappeler les règles de travail de la classe.'],
            ['comprendre', 'Expliquer', 'Expliquer ce qui sera évalué en [MATIÈRE] cette année.'],
            ['appliquer', 'Appliquer', 'Appliquer les acquis de l’année précédente à une tâche courte.'],
        ],
        'moments' => [
            [
                't' => '1. Présentation et cadre de l’année',
                'o' => 'Présenter le programme, les modalités d’évaluation et le matériel attendu.',
                'i' => 'Cadre annoncé brièvement et par écrit : ce qui est dit oralement le premier jour n’est pas retenu.',
                'n' => 'Prévoir une fiche de présentation de l’année à coller dans le cahier.',
                'a' => [
                    ['t' => 'read', 'd' => 12, 'g' => 'whole', 'aias' => 'na',
                     'desc' => 'Présentation des grandes parties du programme de l’année, des modalités d’évaluation et du matériel.',
                     'inst' => 'Colle la fiche de présentation dans ton cahier et note les dates importantes.'],
                ],
            ],
            [
                't' => '2. Contrat de classe co-construit',
                'o' => 'Établir avec la classe les règles de travail et leurs raisons.',
                'i' => 'Les règles sont proposées par les élèves puis arbitrées : une règle dont on a énoncé la raison est mieux tenue qu’une règle imposée.',
                'a' => [
                    ['t' => 'discuss', 'd' => 18, 'g' => 'subgroups', 'aias' => 'na',
                     'desc' => 'Chaque groupe propose trois règles de travail avec leur justification ; la classe en retient cinq, affichées ensuite.',
                     'inst' => 'À quatre, proposez trois règles de travail et dites à quoi chacune sert. On en retiendra cinq pour l’année.'],
                ],
            ],
            [
                't' => '3. Diagnostic des acquis',
                'o' => 'Mesurer ce qui est disponible des années précédentes.',
                'i' => 'Diagnostic non noté, annoncé comme tel : il sert à ajuster la progression, et sa fonction doit être dite pour éviter l’anxiété de rentrée.',
                'a' => [
                    ['t' => 'practice', 'd' => 15, 'g' => 'individual', 'ev' => 'diagnostic', 'aias' => 'na',
                     'desc' => 'Test de positionnement sur [PRÉREQUIS 1] et [PRÉREQUIS 2], non noté, corrigé lors de la séance suivante.',
                     'inst' => 'Réponds seul. Ce test n’est pas noté : il me sert à savoir d’où l’on part.'],
                ],
            ],
            [
                't' => '4. Mise en route',
                'o' => 'Mettre la classe au travail sur une première tâche courte et réussie.',
                'i' => 'Terminer par une tâche courte et réussie : la première séance donne le ton de l’année, mieux vaut qu’elle finisse sur du travail effectif.',
                'a' => [
                    ['t' => 'investigate', 'd' => 10, 'g' => 'subgroups', 'aias' => 'na',
                     'desc' => 'Première tâche courte sur [DOCUMENT 1], choisie pour être réussie par tous en dix minutes.',
                     'inst' => 'À deux, traitez la tâche du document. Vous devez avoir terminé avant la fin de l’heure.'],
                ],
            ],
        ],
    ],
];

// ── Dispatch ────────────────────────────────────────────────────────────────

$LD_MODELS_BY_ID = [];
foreach ($LD_SCENARIO_MODELS as $ldModel) {
    $LD_MODELS_BY_ID[$ldModel['id']] = $ldModel;
}

$requestedFormat = strtolower(trim((string)($_GET['format'] ?? '')));
$requestedModelId = trim((string)($_GET['model'] ?? ''));
$requestedLanguage = strtolower(trim((string)($_GET['lang'] ?? ''))) === 'en' ? 'en' : 'fr';

if ($requestedFormat === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if ($requestedModelId !== '') {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        if (!isset($LD_MODELS_BY_ID[$requestedModelId])) {
            http_response_code(404);
            echo json_encode(['error' => 'unknown_model'], $flags);
            exit;
        }
        $model = $LD_MODELS_BY_ID[$requestedModelId];
        $design = ld_model_design($model, $requestedLanguage);
        if (trim((string)($_GET['download'] ?? '')) === '1') {
            header('Content-Disposition: attachment; filename="modele-' . $requestedModelId . '.json"');
            echo json_encode($design, $flags | JSON_PRETTY_PRINT);
            exit;
        }
        echo json_encode([
            'model' => ld_model_entry($model, $LD_MODEL_FAMILIES),
            'design' => $design,
        ], $flags);
        exit;
    }

    header('Cache-Control: public, max-age=600');
    $families = [];
    foreach ($LD_MODEL_FAMILIES as $familyId => $family) {
        $families[] = [
            'id' => $familyId,
            'labelFr' => $family['fr'],
            'labelEn' => $family['en'],
            'icon' => $family['icon'],
            'descFr' => $family['descFr'],
            'descEn' => $family['descEn'],
        ];
    }
    echo json_encode([
        'version' => 3,
        'families' => $families,
        'models' => array_map(
            static fn (array $model): array => ld_model_entry($model, $LD_MODEL_FAMILIES),
            $LD_SCENARIO_MODELS
        ),
    ], $flags);
    exit;
}

$modelsByFamily = [];
foreach ($LD_SCENARIO_MODELS as $ldModel) {
    $modelsByFamily[$ldModel['family']][] = $ldModel;
}
$totalMinutes = 0;
$totalActivities = 0;
foreach ($LD_SCENARIO_MODELS as $ldModel) {
    $totalMinutes += ld_model_minutes($ldModel);
    $totalActivities += ld_model_activity_count($ldModel);
}

// render_site_nav() reads the session; start it before any output is emitted.
current_user();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260804" type="image/svg+xml" sizes="any">
    <title>Modèles de scénarios | Learning Designer</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260903-pagefind-dark">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="models-page">
<?php render_site_nav('models'); ?>
<main class="models-shell with-nav">
    <header class="models-header">
        <p class="models-kicker" data-i18n-fr="Bibliothèque" data-i18n-en="Library">Bibliothèque</p>
        <h1 class="models-title" data-i18n-fr="Modèles de scénarios" data-i18n-en="Scenario templates">Modèles de scénarios</h1>
        <p class="models-subtitle" data-i18n-fr="Des scénarios génériques, préremplis et prêts à l’emploi : moments, durées, types d’apprentissage, modalités, acquis Bloom et niveaux AIAS sont déjà posés. Il reste à remplacer les jalons entre crochets par le contenu de votre discipline." data-i18n-en="Generic, pre-filled, ready-to-use scenarios: moments, durations, learning types, delivery settings, Bloom outcomes and AIAS levels are already set. All that remains is to replace the bracketed placeholders with your own subject content.">Des scénarios génériques, préremplis et prêts à l’emploi : moments, durées, types d’apprentissage, modalités, acquis Bloom et niveaux AIAS sont déjà posés. Il reste à remplacer les jalons entre crochets par le contenu de votre discipline.</p>
        <ul class="models-stats">
            <li class="models-stat"><?= count($LD_SCENARIO_MODELS) ?> <span data-i18n-fr="modèles" data-i18n-en="templates">modèles</span></li>
            <li class="models-stat"><?= count($LD_MODEL_FAMILIES) ?> <span data-i18n-fr="familles" data-i18n-en="families">familles</span></li>
            <li class="models-stat"><?= $totalActivities ?> <span data-i18n-fr="activités préremplies" data-i18n-en="pre-filled activities">activités préremplies</span></li>
        </ul>
    </header>

    <section class="models-howto" aria-labelledby="models-howto-title">
        <h2 id="models-howto-title" data-i18n-fr="Comment utiliser un modèle" data-i18n-en="How to use a template">Comment utiliser un modèle</h2>
        <ol>
            <li data-i18n-fr="Dans le concepteur, cliquez sur « Importer » : la fenêtre propose la bibliothèque de modèles ou un fichier de votre ordinateur." data-i18n-en="In the designer, click “Import”: the dialog offers the template library or a file from your computer.">Dans le concepteur, cliquez sur « Importer » : la fenêtre propose la bibliothèque de modèles ou un fichier de votre ordinateur.</li>
            <li data-i18n-fr="Choisissez un modèle : le scénario complet est chargé, avec ses moments, ses durées et ses consignes." data-i18n-en="Pick a template: the full scenario is loaded, with its moments, durations and instructions.">Choisissez un modèle : le scénario complet est chargé, avec ses moments, ses durées et ses consignes.</li>
            <li data-i18n-fr="Remplacez chaque jalon entre crochets — [MATIÈRE], [CHAPITRE], [NOTION 1] — par le contenu de votre séance, puis ajustez les durées." data-i18n-en="Replace each bracketed placeholder — [SUBJECT], [CHAPTER], [CONCEPT 1] — with your own content, then adjust the durations.">Remplacez chaque jalon entre crochets — [MATIÈRE], [CHAPITRE], [NOTION 1] — par le contenu de votre séance, puis ajustez les durées.</li>
            <li data-i18n-fr="Enregistrez, exportez ou partagez le scénario comme n’importe quel design." data-i18n-en="Save, export or share the scenario like any other design.">Enregistrez, exportez ou partagez le scénario comme n’importe quel design.</li>
        </ol>
    </section>

    <?php foreach ($LD_MODEL_FAMILIES as $familyId => $family): ?>
        <?php if (empty($modelsByFamily[$familyId])) { continue; } ?>
        <section class="models-family" id="famille-<?= h($familyId) ?>" aria-labelledby="famille-<?= h($familyId) ?>-title">
            <div class="models-family-head">
                <i class="<?= h($family['icon']) ?> models-family-icon" aria-hidden="true"></i>
                <h2 id="famille-<?= h($familyId) ?>-title" data-i18n-fr="<?= h($family['fr']) ?>" data-i18n-en="<?= h($family['en']) ?>"><?= h($family['fr']) ?></h2>
            </div>
            <p class="models-family-desc" data-i18n-fr="<?= h($family['descFr']) ?>" data-i18n-en="<?= h($family['descEn']) ?>"><?= h($family['descFr']) ?></p>
            <div class="models-grid">
                <?php foreach ($modelsByFamily[$familyId] as $model): ?>
                    <?php
                    $entry = ld_model_entry($model, $LD_MODEL_FAMILIES);
                    $placeholdersFr = $entry['placeholdersFr'];
                    $placeholdersEn = $entry['placeholdersEn'];
                    ?>
                    <article class="model-card" id="modele-<?= h($entry['id']) ?>">
                        <h3>
                            <i class="<?= h($entry['icon']) ?>" aria-hidden="true"></i>
                            <span data-i18n-fr="<?= h($entry['titleFr']) ?>" data-i18n-en="<?= h($entry['titleEn']) ?>"><?= h($entry['titleFr']) ?></span>
                        </h3>
                        <ul class="model-card-chips">
                            <li class="model-chip"><?= h(ld_model_duration_label($entry['minutes'])) ?></li>
                            <li class="model-chip"><?= (int)$entry['momentCount'] ?> <span data-i18n-fr="moments" data-i18n-en="moments">moments</span></li>
                            <li class="model-chip"><?= (int)$entry['activityCount'] ?> <span data-i18n-fr="activités" data-i18n-en="activities">activités</span></li>
                            <li class="model-chip"><?= (int)$entry['outcomeCount'] ?> <span data-i18n-fr="acquis" data-i18n-en="outcomes">acquis</span></li>
                        </ul>
                        <p class="model-card-summary" data-i18n-fr="<?= h($entry['summaryFr']) ?>" data-i18n-en="<?= h($entry['summaryEn']) ?>"><?= h($entry['summaryFr']) ?></p>
                        <ul class="model-outline">
                            <?php foreach ($entry['outline'] as $moment): ?>
                                <li class="model-outline-moment">
                                    <span class="model-outline-title"
                                          data-i18n-fr="<?= h($moment['titleFr']) ?>"
                                          data-i18n-en="<?= h($moment['titleEn']) ?>"><?= h($moment['titleFr']) ?></span>
                                    <span class="model-outline-acts">
                                        <?php foreach ($moment['activities'] as $activity): ?>
                                            <span class="model-act model-act-<?= h($activity['type']) ?>"
                                                  title="<?= h($activity['typeLabelFr']) ?>"
                                                  data-i18n-attr="title"
                                                  data-i18n-fr="<?= h($activity['typeLabelFr']) ?>"
                                                  data-i18n-en="<?= h($activity['typeLabelEn']) ?>"><?= (int)$activity['minutes'] ?>′</span>
                                        <?php endforeach; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($placeholdersFr): ?>
                            <p class="model-todo">
                                <strong data-i18n-fr="À compléter :" data-i18n-en="To complete:">À compléter :</strong>
                                <span data-i18n-fr="<?= h(implode(' · ', $placeholdersFr)) ?>"
                                      data-i18n-en="<?= h(implode(' · ', $placeholdersEn)) ?>"><?= h(implode(' · ', $placeholdersFr)) ?></span>
                            </p>
                        <?php endif; ?>
                        <div class="model-card-actions">
                            <button class="btn btn-light model-preview-btn" type="button" data-model-id="<?= h($entry['id']) ?>">
                                <i class="fa-solid fa-eye btn-icon-inline" aria-hidden="true"></i>
                                <span data-i18n-fr="Visualiser" data-i18n-en="Preview">Visualiser</span>
                            </button>
                            <a class="btn btn-light import-model-use-action" href="designer.php?model=<?= h(urlencode($entry['id'])) ?>">
                                <i class="fa-solid fa-arrow-right btn-icon-inline" aria-hidden="true"></i>
                                <span data-i18n-fr="Utiliser" data-i18n-en="Use">Utiliser</span>
                            </a>
                            <a class="btn btn-light model-download-action"
                               data-model-id="<?= h($entry['id']) ?>"
                               href="models.php?format=json&amp;model=<?= h(urlencode($entry['id'])) ?>&amp;download=1&amp;lang=fr">
                                <i class="fa-solid fa-download btn-icon-inline" aria-hidden="true"></i>
                                <span data-i18n-fr="Télécharger" data-i18n-en="Download">Télécharger</span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</main>

<div id="model-library-preview-backdrop" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="model-library-preview-title">
    <div class="modal model-library-preview-modal">
        <header class="import-model-preview-header">
            <p class="import-model-preview-eyebrow" data-i18n-fr="Aperçu du scénario" data-i18n-en="Scenario preview">Aperçu du scénario</p>
            <h2 id="model-library-preview-title" class="modal-title"></h2>
            <p id="model-library-preview-summary" class="import-model-preview-summary"></p>
            <div id="model-library-preview-chips" class="import-model-preview-chips"></div>
        </header>
        <p id="model-library-preview-status" class="import-model-preview-status" role="status" aria-live="polite"></p>
        <div id="model-library-preview-content" class="import-model-preview-content"></div>
        <div class="modal-actions model-library-preview-actions">
            <button id="model-library-preview-close" class="btn btn-light" type="button" data-i18n-fr="Fermer" data-i18n-en="Close">Fermer</button>
            <a id="model-library-preview-use" class="btn btn-light import-model-use-action" href="designer.php">
                <i class="fa-solid fa-arrow-right btn-icon-inline" aria-hidden="true"></i>
                <span data-i18n-fr="Utiliser" data-i18n-en="Use">Utiliser</span>
            </a>
        </div>
    </div>
</div>
<?php render_site_footer(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function applyModelsLanguage(lang) {
        document.documentElement.lang = lang === 'en' ? 'en' : 'fr';
        document.title = lang === 'en'
            ? 'Scenario templates | Learning Designer'
            : 'Modèles de scénarios | Learning Designer';
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
        document.querySelectorAll('.model-download-action[data-model-id]').forEach(function (link) {
            link.href = 'models.php?format=json&model=' + encodeURIComponent(link.dataset.modelId)
                + '&download=1&lang=' + (lang === 'en' ? 'en' : 'fr');
        });
    }

    var lang = 'fr';
    try {
        lang = localStorage.getItem('learningDesignerLang') || 'fr';
    } catch (error) {
        lang = 'fr';
    }
    applyModelsLanguage(lang);

    var langSelect = document.getElementById('lang-select');
    if (langSelect) {
        langSelect.addEventListener('change', function () {
            applyModelsLanguage(langSelect.value);
        });
    }

    var previewBackdrop = document.getElementById('model-library-preview-backdrop');
    var previewTitle = document.getElementById('model-library-preview-title');
    var previewSummary = document.getElementById('model-library-preview-summary');
    var previewChips = document.getElementById('model-library-preview-chips');
    var previewStatus = document.getElementById('model-library-preview-status');
    var previewContent = document.getElementById('model-library-preview-content');
    var previewClose = document.getElementById('model-library-preview-close');
    var previewUse = document.getElementById('model-library-preview-use');
    var previewCache = new Map();
    var activePreviewId = '';
    var previewTrigger = null;

    function isEnglish() {
        return document.documentElement.lang === 'en';
    }

    function previewLabel(fr, en) {
        return isEnglish() ? en : fr;
    }

    function formatPreviewDuration(minutes) {
        var total = Math.max(0, Number(minutes) || 0);
        if (total < 60) return total + ' min';
        var hours = Math.floor(total / 60);
        var rest = total % 60;
        return rest ? hours + ' h ' + String(rest).padStart(2, '0') : hours + ' h';
    }

    function addPreviewChip(label) {
        var chip = document.createElement('span');
        chip.className = 'import-model-chip';
        chip.textContent = label;
        previewChips.appendChild(chip);
    }

    function addPreviewText(parent, label, value, className) {
        var text = String(value || '').trim();
        if (!text) return;
        var block = document.createElement('div');
        block.className = 'import-model-preview-text' + (className ? ' ' + className : '');
        var heading = document.createElement('strong');
        heading.textContent = label;
        var content = document.createElement('p');
        content.textContent = text;
        block.append(heading, content);
        parent.appendChild(block);
    }

    function renderLibraryPreview(payload) {
        var entry = payload.model || {};
        var design = payload.design || {};
        var sessions = Array.isArray(design.sessions) ? design.sessions : [];
        var title = isEnglish() ? entry.titleEn : entry.titleFr;
        var summary = isEnglish() ? entry.summaryEn : entry.summaryFr;
        var family = isEnglish() ? entry.familyLabelEn : entry.familyLabelFr;

        previewTitle.textContent = title || (design.meta && design.meta.name) || '';
        previewSummary.textContent = summary || '';
        previewChips.textContent = '';
        addPreviewChip(family || '');
        addPreviewChip(formatPreviewDuration(entry.minutes));
        addPreviewChip(entry.momentCount + ' ' + previewLabel('moments', 'moments'));
        addPreviewChip(entry.activityCount + ' ' + previewLabel('activités', 'activities'));
        previewContent.textContent = '';

        var typeLabels = {
            undefined: previewLabel('Non défini', 'Undefined'),
            read: previewLabel('Lire / Regarder / Écouter', 'Read / Watch / Listen'),
            investigate: previewLabel('Investiguer', 'Investigate'),
            practice: previewLabel('Pratiquer', 'Practise'),
            produce: previewLabel('Produire', 'Produce'),
            discuss: previewLabel('Discuter', 'Discuss'),
            collaborate: previewLabel('Collaborer', 'Collaborate')
        };
        var allowedTypes = Object.keys(typeLabels);

        sessions.forEach(function (session, sessionIndex) {
            var moment = document.createElement('section');
            moment.className = 'import-model-preview-moment';
            var momentHeader = document.createElement('header');
            momentHeader.className = 'import-model-preview-moment-header';
            var number = document.createElement('span');
            number.className = 'import-model-preview-moment-number';
            number.textContent = String(sessionIndex + 1);
            var momentTitle = document.createElement('h3');
            var outlineMoment = Array.isArray(entry.outline) ? entry.outline[sessionIndex] : null;
            momentTitle.textContent = isEnglish()
                ? ((outlineMoment && outlineMoment.titleEn) || session.title || '')
                : ((outlineMoment && (outlineMoment.titleFr || outlineMoment.title)) || session.title || '');
            momentHeader.append(number, momentTitle);
            moment.appendChild(momentHeader);
            addPreviewText(moment, previewLabel('Objectifs du moment', 'Moment objectives'), session.objectives);

            var activities = document.createElement('div');
            activities.className = 'import-model-preview-activities';
            (Array.isArray(session.activities) ? session.activities : []).forEach(function (activity, activityIndex) {
                var type = allowedTypes.includes(activity.type) ? activity.type : 'undefined';
                var activityCard = document.createElement('article');
                activityCard.className = 'import-model-preview-activity type-' + type;
                var activityHeader = document.createElement('header');
                activityHeader.className = 'import-model-preview-activity-header';
                var activityName = document.createElement('strong');
                activityName.textContent = previewLabel('Activité ', 'Activity ') + String(activityIndex + 1);
                var activityMeta = document.createElement('span');
                activityMeta.className = 'import-model-preview-activity-meta';
                var typeLabel = document.createElement('span');
                typeLabel.className = 'import-model-preview-type';
                typeLabel.textContent = typeLabels[type];
                var duration = document.createElement('span');
                duration.textContent = formatPreviewDuration(activity.duration);
                activityMeta.append(typeLabel, duration);
                activityHeader.append(activityName, activityMeta);
                activityCard.appendChild(activityHeader);
                addPreviewText(activityCard, previewLabel('Déroulement', 'Sequence'), activity.description);
                addPreviewText(
                    activityCard,
                    previewLabel('Consigne aux élèves', 'Student instructions'),
                    activity.instructions,
                    'import-model-preview-instructions'
                );
                activities.appendChild(activityCard);
            });
            moment.appendChild(activities);
            previewContent.appendChild(moment);
        });
    }

    function loadLibraryPreview(modelId) {
        var language = isEnglish() ? 'en' : 'fr';
        var cacheKey = language + ':' + modelId;
        if (previewCache.has(cacheKey)) return previewCache.get(cacheKey);
        var request = fetch('models.php?format=json&model=' + encodeURIComponent(modelId) + '&lang=' + language + '&v=3', {
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        }).then(function (payload) {
            if (!payload || !payload.model || !payload.design) throw new Error('Invalid model');
            return payload;
        }).catch(function (error) {
            previewCache.delete(cacheKey);
            throw error;
        });
        previewCache.set(cacheKey, request);
        return request;
    }

    function openLibraryPreview(modelId, trigger) {
        activePreviewId = String(modelId || '');
        if (!activePreviewId) return;
        var requestedId = activePreviewId;
        previewTrigger = trigger;
        previewTitle.textContent = trigger.closest('.model-card').querySelector('h3 span').textContent;
        previewSummary.textContent = '';
        previewChips.textContent = '';
        previewContent.textContent = '';
        previewStatus.textContent = previewLabel('Chargement de l’aperçu…', 'Loading preview…');
        previewStatus.classList.remove('import-model-preview-status-error');
        previewUse.href = 'designer.php?model=' + encodeURIComponent(activePreviewId);
        previewBackdrop.classList.remove('hidden');
        previewBackdrop.setAttribute('aria-hidden', 'false');
        document.body.classList.add('model-library-preview-open');
        previewClose.focus();

        loadLibraryPreview(activePreviewId).then(function (payload) {
            if (activePreviewId !== requestedId) return;
            renderLibraryPreview(payload);
            previewStatus.textContent = '';
        }).catch(function () {
            if (activePreviewId !== requestedId) return;
            previewStatus.textContent = previewLabel(
                'Impossible d’afficher l’aperçu de ce modèle.',
                'This template preview could not be displayed.'
            );
            previewStatus.classList.add('import-model-preview-status-error');
        });
    }

    function closeLibraryPreview() {
        previewBackdrop.classList.add('hidden');
        previewBackdrop.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('model-library-preview-open');
        activePreviewId = '';
        if (previewTrigger) previewTrigger.focus();
        previewTrigger = null;
    }

    document.querySelectorAll('.model-preview-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            openLibraryPreview(button.dataset.modelId, button);
        });
    });
    previewClose.addEventListener('click', closeLibraryPreview);
    previewBackdrop.addEventListener('click', function (event) {
        if (event.target === previewBackdrop) closeLibraryPreview();
    });
    previewBackdrop.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeLibraryPreview();
            return;
        }
        if (event.key !== 'Tab') return;
        var focusable = Array.from(previewBackdrop.querySelectorAll('button:not([disabled]), a[href]'));
        if (!focusable.length) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
});
</script>
</body>
</html>
