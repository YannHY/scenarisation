<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo 'Lien invalide.';
    exit;
}

$db = app_db();
$stmt = $db->prepare("SELECT id, title, document_json, license_code, updated_at FROM learning_designs WHERE share_token = ? AND is_published = 1 LIMIT 1");
$stmt->execute([$token]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo 'Cette production n\'est pas disponible ou son lien de partage a été révoqué.';
    exit;
}

$document = json_decode((string)$row['document_json'], true);
if (!is_array($document)) {
    $document = ['sessions' => [], 'meta' => []];
}

$meta     = is_array($document['meta'] ?? null) ? $document['meta'] : [];
$sessions = is_array($document['sessions'] ?? null) ? $document['sessions'] : [];
$title    = trim((string)($meta['name'] ?? ''));
if ($title === '') {
    $title = (string)$row['title'];
}
if ($title === '') {
    $title = 'Production sans titre';
}

$updatedAt = '';
try {
    $dt = new DateTimeImmutable((string)$row['updated_at'], new DateTimeZone('UTC'));
    $updatedAt = $dt->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i');
} catch (Exception) {}

$license = creative_commons_license((string)($row['license_code'] ?? ''));

// ── Label maps ───────────────────────────────────────────────
$LEARNING_TYPES = [
    'undefined'   => ['label' => 'Non défini',               'color' => '#d1d5db'],
    // This page is self-contained and cannot read css/interface.css tokens;
    // keep these in sync with --read…--collaborate there.
    'read'        => ['label' => 'Lire / Regarder / Écouter', 'color' => '#5bddd3'],
    'investigate' => ['label' => 'Investiguer',              'color' => '#f19492'],
    'practice'    => ['label' => 'Pratiquer',                'color' => '#c498ec'],
    'produce'     => ['label' => 'Produire',                 'color' => '#a2d681'],
    'discuss'     => ['label' => 'Discuter',                 'color' => '#85b6f0'],
    'collaborate' => ['label' => 'Collaborer',               'color' => '#e7b959'],
];
$GROUP_MODES    = ['whole' => 'Groupe entier', 'subgroups' => 'Sous-groupes', 'individual' => 'Individuel'];
$TEACHING_MODES = [
    'undefined' => 'Enseignement à définir',
    'directed' => 'Enseignement dirigé',
    'guided' => 'Enseignement guidé',
    'supported' => 'Enseignement accompagné',
    'independent' => 'Enseignement en autonomie',
];
// Compatibilité avec les scénarios publiés avant l'introduction des quatre modes.
$TRAINER_MODES  = ['present' => 'Enseignant présent', 'absent' => 'Enseignant absent'];
$SYNC_MODES     = ['sync' => 'Synchrone', 'async' => 'Asynchrone'];
$LOCATION_MODES = [
    'onsite' => 'En classe',
    'location_based' => 'Sur site',
    'online' => 'En ligne',
    'hybrid' => 'Hybride',
    'other' => 'Autre',
];
$DELIVERY_MODES = ['onsite' => 'Présentiel', 'online' => 'Distanciel', 'hybrid' => 'Hybride'];
$SCHOOL_SYSTEMS = [
    'france' => 'France',
    'switzerland' => 'Suisse (HarmoS)',
    'united_states' => 'États-Unis (K–12)',
    'belgium_french' => 'Belgique — Fédération Wallonie-Bruxelles',
    'belgium_flemish' => 'Belgique — Communauté flamande',
    'belgium_german' => 'Belgique — Communauté germanophone',
    'uk_england' => 'Royaume-Uni — Angleterre',
    'uk_wales' => 'Royaume-Uni — Pays de Galles',
    'uk_scotland' => 'Royaume-Uni — Écosse',
    'uk_northern_ireland' => 'Royaume-Uni — Irlande du Nord',
    'european_schools' => 'Système des Écoles européennes',
    'ib' => 'International Baccalaureate (IB)',
    'isced_2011' => 'International — ISCED 2011 (CITE)',
];
$SCHOOL_LEVELS  = [
    'petite_section' => 'Petite section (PS)',
    'moyenne_section' => 'Moyenne section (MS)',
    'grande_section' => 'Grande section (GS)',
    'cp' => 'CP',
    'ce1' => 'CE1',
    'ce2' => 'CE2',
    'cm1' => 'CM1',
    'cm2' => 'CM2',
    'sixieme' => '6e',
    'cinquieme' => '5e',
    'quatrieme' => '4e',
    'troisieme' => '3e',
    'seconde' => 'Seconde',
    'premiere' => 'Première',
    'terminale' => 'Terminale',
    'ch_1p' => '1P — 1re année primaire',
    'ch_2p' => '2P — 2e année primaire',
    'ch_3p' => '3P — 3e année primaire',
    'ch_4p' => '4P — 4e année primaire',
    'ch_5p' => '5P — 5e année primaire',
    'ch_6p' => '6P — 6e année primaire',
    'ch_7p' => '7P — 7e année primaire',
    'ch_8p' => '8P — 8e année primaire',
    'ch_9s' => '9e — secondaire I',
    'ch_10s' => '10e — secondaire I',
    'ch_11s' => '11e — secondaire I',
    'ch_sec2_1' => 'Secondaire II — 1re année',
    'ch_sec2_2' => 'Secondaire II — 2e année',
    'ch_sec2_3' => 'Secondaire II — 3e année',
    'us_k' => 'Kindergarten (K)',
    'us_grade_1' => 'Grade 1',
    'us_grade_2' => 'Grade 2',
    'us_grade_3' => 'Grade 3',
    'us_grade_4' => 'Grade 4',
    'us_grade_5' => 'Grade 5',
    'us_grade_6' => 'Grade 6',
    'us_grade_7' => 'Grade 7',
    'us_grade_8' => 'Grade 8',
    'us_grade_9' => 'Grade 9',
    'us_grade_10' => 'Grade 10',
    'us_grade_11' => 'Grade 11',
    'us_grade_12' => 'Grade 12',
    'uk_england_nursery' => 'Nursery',
    'uk_england_reception' => 'Reception',
    'uk_wales_nursery' => 'Nursery',
    'uk_wales_reception' => 'Reception',
    'uk_scotland_early_learning' => 'Petite enfance (Nursery)',
    'uk_northern_ireland_preschool' => 'Préscolaire (Pre-school)',
    'ib_pyp' => 'PYP — Primary Years Programme (ages 3–12)',
    'ib_myp_1' => 'MYP — Middle Years Programme — year 1',
    'ib_myp_2' => 'MYP — Middle Years Programme — year 2',
    'ib_myp_3' => 'MYP — Middle Years Programme — year 3',
    'ib_myp_4' => 'MYP — Middle Years Programme — year 4',
    'ib_myp_5' => 'MYP — Middle Years Programme — year 5',
    'ib_dp_1' => 'DP — Diploma Programme — year 1',
    'ib_dp_2' => 'DP — Diploma Programme — year 2',
    'ib_cp_1' => 'CP — Career-related Programme — year 1',
    'ib_cp_2' => 'CP — Career-related Programme — year 2',
    'isced_0' => 'ISCED 0 — Éducation de la petite enfance',
    'isced_1' => 'ISCED 1 — Enseignement primaire',
    'isced_2' => 'ISCED 2 — Premier cycle du secondaire',
    'isced_3' => 'ISCED 3 — Deuxième cycle du secondaire',
    'isced_4' => 'ISCED 4 — Post-secondaire non supérieur',
    'isced_5' => 'ISCED 5 — Enseignement supérieur de cycle court',
    'isced_6' => 'ISCED 6 — Licence ou équivalent',
    'isced_7' => 'ISCED 7 — Master ou équivalent',
    'isced_8' => 'ISCED 8 — Doctorat ou équivalent',
];
for ($year = 1; $year <= 13; $year++) {
    $SCHOOL_LEVELS['uk_england_year_' . $year] = 'Year ' . $year;
    $SCHOOL_LEVELS['uk_wales_year_' . $year] = 'Year ' . $year;
}
for ($year = 1; $year <= 7; $year++) {
    $SCHOOL_LEVELS['uk_scotland_p' . $year] = 'P' . $year . ' — primaire';
    $SCHOOL_LEVELS['uk_northern_ireland_p' . $year] = 'P' . $year . ' — primaire';
}
for ($year = 1; $year <= 6; $year++) {
    $SCHOOL_LEVELS['uk_scotland_s' . $year] = 'S' . $year . ' — secondaire';
}
for ($year = 8; $year <= 14; $year++) {
    $SCHOOL_LEVELS['uk_northern_ireland_year_' . $year] = 'Year ' . $year;
}
for ($year = 1; $year <= 3; $year++) {
    $ordinal = $year === 1 ? '1re' : $year . 'e';
    $SCHOOL_LEVELS['be_fr_m' . $year] = 'M' . $year . ' — ' . $ordinal . ' maternelle';
    $SCHOOL_LEVELS['be_nl_k' . $year] = 'K' . $year . ' — ' . $year . 'e kleuterklas';
    $SCHOOL_LEVELS['be_de_k' . $year] = 'K' . $year . ' — ' . $year . '. Kindergartenjahr';
}
for ($year = 1; $year <= 6; $year++) {
    $ordinal = $year === 1 ? '1re' : $year . 'e';
    $SCHOOL_LEVELS['be_fr_p' . $year] = 'P' . $year . ' — ' . $ordinal . ' primaire';
    $SCHOOL_LEVELS['be_nl_l' . $year] = 'L' . $year . ' — ' . $year . 'e leerjaar lager onderwijs';
    $SCHOOL_LEVELS['be_de_p' . $year] = 'P' . $year . ' — ' . $year . '. Primarschuljahr';
}
for ($year = 1; $year <= 7; $year++) {
    $ordinal = $year === 1 ? '1re' : $year . 'e';
    $SCHOOL_LEVELS['be_fr_s' . $year] = 'S' . $year . ' — ' . $ordinal . ' secondaire'
        . ($year === 7 ? ' — selon la filière' : '');
    $SCHOOL_LEVELS['be_nl_s' . $year] = 'S' . $year . ' — ' . $year . 'e leerjaar secundair onderwijs';
    $SCHOOL_LEVELS['be_de_s' . $year] = 'S' . $year . ' — ' . $year . '. Sekundarschuljahr'
        . ($year === 7 ? ' (professionnel)' : '');
}
for ($year = 1; $year <= 2; $year++) {
    $SCHOOL_LEVELS['eu_school_n' . $year] = 'N' . $year . ' — cycle maternel';
}
for ($year = 1; $year <= 5; $year++) {
    $SCHOOL_LEVELS['eu_school_p' . $year] = 'P' . $year . ' — cycle primaire';
}
for ($year = 1; $year <= 7; $year++) {
    $cycle = $year <= 3
        ? 'cycle d’observation'
        : ($year <= 5 ? 'cycle de pré-orientation' : 'cycle du Baccalauréat européen');
    $SCHOOL_LEVELS['eu_school_s' . $year] = 'S' . $year . ' — ' . $cycle;
}
$EVAL_MODES     = [
    'none'          => null,
    'diagnostic'    => 'Diagnostique',
    'formative'     => 'Formative',
    'summative'     => 'Sommative',
    'certificative' => 'Certificative',
];
$AIAS_LEVELS    = [
    1 => 'Sans IA',
    2 => 'Planification avec l’IA',
    3 => 'Collaboration avec l’IA',
    4 => 'IA pleinement intégrée',
    5 => 'Exploration de l’IA',
];

$TOOLS_LABELS = [
    'moodle:workshop'          => 'Atelier',
    'moodle:database'          => 'Base de données',
    'moodle:bigbluebutton'     => 'BigBlueButton',
    'moodle:capytale'          => 'Capytale',
    'moodle:chat'              => 'Chat',
    'moodle:group-choice'      => 'Choix de groupe',
    'moodle:assignment'        => 'Devoir',
    'moodle:collaborative-doc' => 'Document collaboratif',
    'moodle:folder'            => 'Dossier',
    'moodle:etherpad'          => 'Etherpad Lite',
    'moodle:file'              => 'Fichier',
    'moodle:forum'             => 'Forum',
    'moodle:glossary'          => 'Glossaire',
    'moodle:lesson'            => 'Leçon',
    'moodle:book'              => 'Livre',
    'moodle:module'            => 'Module',
    'moodle:word-cloud'        => 'Nuage de mots',
    'moodle:page'              => 'Page',
    'moodle:file-share'        => 'Partage de fichiers',
    'moodle:feedback'          => 'Questionnaire',
    'moodle:choice'            => 'Sondage',
    'moodle:sticky-notes'      => 'Sticky Notes',
    'moodle:tableau'           => 'Tableau',
    'moodle:quiz'              => 'Test (quiz)',
    'moodle:url'               => 'URL',
    'moodle:wiki'              => 'Wiki',
    'moodle:text-media'        => 'Zone texte et média',
    'h5p:interactive-video'    => 'Vidéo interactive',
    'h5p:course-presentation'  => 'Présentation de cours',
    'h5p:branching-scenario'   => 'Scénario ramifié',
    'h5p:accordion'            => 'Accordéon',
    'h5p:advent-calendar'      => "Calendrier de l'Avent",
    'h5p:agamotto'             => 'Agamotto',
    'h5p:ar-scavenger'         => 'Chasse au trésor en RA',
    'h5p:arithmetic-quiz'      => 'Quiz arithmétique',
    'h5p:audio-recorder'       => 'Enregistreur audio',
    'h5p:chart'                => 'Graphique',
    'h5p:collage'              => 'Collage',
    'h5p:complex-fill-blanks'  => 'Texte à trous complexe',
    'h5p:cornell-notes'        => 'Notes Cornell',
    'h5p:crossword'            => 'Mots croisés',
    'h5p:dialog-cards'         => 'Cartes dialogues',
    'h5p:dictation'            => 'Dictée',
    'h5p:documentation-tool'   => 'Outil de documentation',
    'h5p:drag-and-drop'        => 'Glisser-déposer',
    'h5p:drag-the-words'       => 'Glisser les mots',
    'h5p:essay'                => 'Essai',
    'h5p:fill-in-the-blanks'   => 'Texte à trous',
    'h5p:find-multiple-hotspots'=> 'Trouver plusieurs zones',
    'h5p:find-the-hotspot'     => 'Trouver la zone',
    'h5p:find-the-words'       => 'Cherche les mots',
    'h5p:flashcards'           => 'Cartes mémoire',
    'h5p:game-map'             => 'Carte de jeu',
    'h5p:guess-the-answer'     => 'Devinez la réponse',
    'h5p:iframe-embedder'      => 'Intégrateur Iframe',
    'h5p:image-hotspots'       => 'Zones interactives sur image',
    'h5p:image-juxtaposition'  => "Juxtaposition d'images",
    'h5p:image-pairing'        => "Appariement d'images",
    'h5p:image-sequencing'     => "Séquence d'images",
    'h5p:image-slider'         => "Diaporama d'images",
    'h5p:impressive-presentation'=> 'Présentation impressionnante',
    'h5p:information-wall'     => "Mur d'informations",
    'h5p:interactive-book'     => 'Livre interactif',
    'h5p:kewar-code'           => 'Code QR (KewAr)',
    'h5p:mark-the-words'       => 'Surligner les mots',
    'h5p:memory-game'          => 'Jeu de mémoire',
    'h5p:multiple-choice'      => 'Choix multiple',
    'h5p:multimedia-choice'    => 'Choix multimédia',
    'h5p:page'                 => 'Page H5P',
    'h5p:personality-quiz'     => 'Quiz de personnalité',
    'h5p:questionnaire'        => 'Questionnaire H5P',
    'h5p:question-set'         => 'Quiz (ensemble de questions)',
    'h5p:single-choice-set'    => 'Choix unique',
    'h5p:sort-the-paragraphs'  => 'Trier les paragraphes',
    'h5p:speak-the-words'      => 'Parle les mots',
    'h5p:speak-the-words-set'  => 'Ensemble vocal',
    'h5p:structure-strip'      => 'Bande de structure',
    'h5p:summary'              => 'Résumé',
    'h5p:timeline'             => 'Frise chronologique',
    'h5p:true-false-question'  => 'Question vrai/faux',
    'h5p:virtual-tour'         => 'Visite virtuelle (360°)',
];

function safeText(mixed $value): string {
    if (is_array($value) || is_object($value)) return '';
    $s = trim((string)$value);
    if ($s === '[object Object]') return '';
    return $s;
}

function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function safeEsc(mixed $v): string {
    return esc(safeText($v));
}

/**
 * N'autorise que les schémas d'URL sûrs dans un attribut href.
 *
 * Le concepteur normalise déjà les liens d'activité en http/https côté client
 * (normalizeExternalUrl dans js/interface.js), mais save_design.php et
 * cli_publish.php enregistrent le document JSON tel quel : une requête forgée
 * ou une publication par le CLI peut donc stocker un lien « javascript: »,
 * exécuté au clic sur l'origine du site depuis cette page publique.
 * Même liste de schémas que inlineMarkdown() juste en dessous.
 */
function safeUrl(string $url): string {
    $url = trim($url);
    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return '';
    }

    return preg_match('#^(?:https?://|mailto:)#i', $url) === 1 ? $url : '';
}

function inlineMarkdown(string $text): string {
    $html = esc($text);
    $html = preg_replace('/\*\*([^*\n]+)\*\*/u', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/(^|[^*])\*([^*\n]+)\*/u', '$1<em>$2</em>', $html) ?? $html;
    $html = preg_replace(
        '/\[([^\]\n]+)\]\(((?:https?:\/\/|mailto:)[^\s)<]+)\)/ui',
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
        $html
    ) ?? $html;
    return $html;
}

function markdownHtml(string $text): string {
    $lines = preg_split('/\R/u', $text) ?: [];
    $html = [];
    $paragraph = [];
    $listType = '';

    $closeParagraph = function () use (&$html, &$paragraph): void {
        if (!$paragraph) return;
        $html[] = '<p>' . implode('<br>', array_map('inlineMarkdown', $paragraph)) . '</p>';
        $paragraph = [];
    };
    $closeList = function () use (&$html, &$listType): void {
        if ($listType === '') return;
        $html[] = '</' . $listType . '>';
        $listType = '';
    };
    $openList = function (string $type) use (&$html, &$listType, $closeParagraph, $closeList): void {
        $closeParagraph();
        if ($listType === $type) return;
        $closeList();
        $html[] = '<' . $type . '>';
        $listType = $type;
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $closeParagraph();
            $closeList();
            continue;
        }

        if (preg_match('/^##\s+(.+)$/u', $trimmed, $m)) {
            $closeParagraph();
            $closeList();
            $html[] = '<h2>' . inlineMarkdown($m[1]) . '</h2>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/u', $trimmed, $m)) {
            $openList('ul');
            $html[] = '<li>' . inlineMarkdown($m[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/u', $trimmed, $m)) {
            $openList('ol');
            $html[] = '<li>' . inlineMarkdown($m[1]) . '</li>';
            continue;
        }

        if (preg_match('/^>\s?(.+)$/u', $trimmed, $m)) {
            $closeParagraph();
            $closeList();
            $html[] = '<blockquote>' . inlineMarkdown($m[1]) . '</blockquote>';
            continue;
        }

        $closeList();
        $paragraph[] = $line;
    }

    $closeParagraph();
    $closeList();
    return implode('', $html);
}

function labelFor(array $map, string $key, string $fallback = ''): string {
    return $map[$key] ?? ($fallback !== '' ? $fallback : $key);
}

function normalizeCatalogSlug(string $value): string {
    $value = trim($value);
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($transliterated)) {
        $value = $transliterated;
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'general';
}

function toRomanNumeral(int $value): string {
    if ($value <= 0) return '';
    $map = [
        1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
        100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
        10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
    ];
    $result = '';
    foreach ($map as $amount => $symbol) {
        while ($value >= $amount) {
            $result .= $symbol;
            $value -= $amount;
        }
    }
    return $result;
}

function normalizeCompetencyToken(string $value): string {
    $value = trim($value);
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($transliterated)) {
        $value = $transliterated;
    }
    return strtolower(preg_replace('/[^a-z0-9]+/', '', $value) ?? '');
}

function loadCompetencyCatalog(): array {
    static $catalog = null;
    if (is_array($catalog)) return $catalog;

    $catalog = [];
    $tsv = app_competency_catalog_source();
    if ($tsv === '') {
        return $catalog;
    }

    $badgeByLevel = ['acquerir' => 'N1', 'approfondir' => 'N2', 'creer' => 'N3'];
    $legacyCodeByLevel = ['acquerir' => 'A', 'approfondir' => 'P', 'creer' => 'C'];
    $currentLevel = null;
    $currentLevelSections = [];
    $sectionEnByFr = [
        "Utilisation de l'iPad" => 'Using the iPad',
        'Productivité et organisation' => 'Productivity and organisation',
        'Communication et collaboration' => 'Communication and collaboration',
        'Données et programmation' => 'Data and programming',
        'Créativité et expression' => 'Creativity and expression',
        'Général' => 'General',
    ];
    $appEnByFr = [
        'Partager' => 'Sharing',
        'Écrire des emails' => 'Writing emails',
        'Excel & calcul' => 'Excel & calculations',
        'Programmation' => 'Programming',
    ];

    foreach (preg_split('/\R/', $tsv) ?: [] as $rawLine) {
        $line = str_replace("\r", '', (string)$rawLine);
        if (trim($line) === '') continue;

        if (str_starts_with($line, '# ')) {
            [$id, $labelFr, $labelEn] = array_pad(explode("\t", substr($line, 2)), 3, '');
            $currentLevel = ['id' => $id, 'labelFr' => $labelFr, 'labelEn' => $labelEn];
            $currentLevelSections = [];
            continue;
        }

        if (!is_array($currentLevel)) continue;
        [$sectionRaw, $appRaw, $numberRaw, $labelFrRaw, $descFrRaw, $labelEnRaw, $descEnRaw]
            = array_pad(explode("\t", $line, 7), 7, '');
        $section = trim($sectionRaw) !== '' ? trim($sectionRaw) : 'Général';
        $sectionIndex = array_search($section, $currentLevelSections, true);
        if ($sectionIndex === false) {
            $currentLevelSections[] = $section;
            $sectionIndex = count($currentLevelSections) - 1;
        }
        $sectionNumber = (int)$sectionIndex + 1;
        $sectionRoman = toRomanNumeral($sectionNumber);
        $competencyNumber = (int)$numberRaw;
        $labelFr = trim($labelFrRaw);
        $descFr = trim($descFrRaw);
        $labelEn = trim($labelEnRaw) !== '' ? trim($labelEnRaw) : $labelFr;
        $descEn = trim($descEnRaw) !== '' ? trim($descEnRaw) : $descFr;
        if ($competencyNumber <= 0 || $labelFr === '' || $descFr === '') continue;

        $id = 'competency:' . $currentLevel['id'] . ':' . trim($numberRaw);
        $shortCode = $currentLevel['labelFr'] . '-' . $sectionRoman . '-' . $competencyNumber;
        $shortCodeEn = $currentLevel['labelEn'] . '-' . $sectionRoman . '-' . $competencyNumber;
        $legacyShortCode = ($legacyCodeByLevel[$currentLevel['id']] ?? substr($currentLevel['id'], 0, 1)) . $competencyNumber;
        $entry = [
            'id' => $id,
            'platform' => $currentLevel['id'],
            'category' => $currentLevel['id'] . ':' . normalizeCatalogSlug($section),
            'sectionFr' => $section,
            'sectionEn' => $sectionEnByFr[$section] ?? $section,
            'appFr' => trim($appRaw),
            'appEn' => $appEnByFr[trim($appRaw)] ?? trim($appRaw),
            'levelLabelFr' => $currentLevel['labelFr'],
            'levelLabelEn' => $currentLevel['labelEn'],
            'levelBadge' => $badgeByLevel[$currentLevel['id']] ?? $currentLevel['id'],
            'number' => $competencyNumber,
            'sectionNumber' => $sectionNumber,
            'sectionRoman' => $sectionRoman,
            'shortCode' => $shortCode,
            'shortCodeEn' => $shortCodeEn,
            'legacyShortCode' => $legacyShortCode,
            'labelFr' => $labelFr,
            'labelEn' => $labelEn,
            'descFr' => $descFr,
            'descEn' => $descEn,
        ];

        foreach ([$id, $shortCode, $shortCodeEn, $legacyShortCode, $labelFr, $labelEn] as $token) {
            $normalized = normalizeCompetencyToken($token);
            if ($normalized !== '') {
                $catalog[$normalized] = $entry;
            }
        }
    }

    $frameworkDetails = [];
    $detailSources = [
        'greencomp' => app_competency_greencomp_detail_source(),
        'digcomp' => app_competency_digcomp_detail_source(),
    ];
    foreach ($detailSources as $detailFrameworkId => $detailSource) {
        foreach (preg_split('/\R/u', $detailSource) ?: [] as $detailLine) {
            [$detailCode, $detailKind, $detailOrder, $detailTextFr, $detailTextEn]
                = array_pad(explode("\t", (string)$detailLine, 5), 5, '');
            $detailCode = trim($detailCode);
            $detailKind = trim($detailKind);
            $detailTextFr = trim($detailTextFr);
            $detailTextEn = trim($detailTextEn) ?: $detailTextFr;
            if ($detailCode === '' || $detailTextFr === '') continue;
            if ($detailKind === 'description') {
                $frameworkDetails[$detailFrameworkId][$detailCode]['description'] = $detailTextFr;
                continue;
            }
            if (!in_array($detailKind, ['knowledge', 'skills', 'attitudes', 'basic', 'intermediate', 'advanced', 'highly_advanced'], true)) continue;
            $frameworkDetails[$detailFrameworkId][$detailCode]['details'][] = [
                'kind' => $detailKind,
                'order' => max(1, (int)$detailOrder),
                'textFr' => $detailTextFr,
                'textEn' => $detailTextEn,
            ];
        }
    }
    $digCompStatementLevelLabels = [
        'basic' => ['fr' => 'Niveau élémentaire', 'en' => 'Basic level'],
        'intermediate' => ['fr' => 'Niveau intermédiaire', 'en' => 'Intermediate level'],
        'advanced' => ['fr' => 'Niveau avancé', 'en' => 'Advanced level'],
        'highly_advanced' => ['fr' => 'Niveau hautement avancé', 'en' => 'Highly advanced level'],
    ];

    $framework = null;
    $group = null;
    $subgroup = null;
    foreach (preg_split('/\R/u', app_competency_framework_catalog_source()) ?: [] as $rawLine) {
        $line = str_replace("\r", '', (string)$rawLine);
        if (trim($line) === '') continue;

        if (str_starts_with($line, "# framework\t")) {
            [, $frameworkId, $frameworkLabelFr, $frameworkLabelEn]
                = array_pad(explode("\t", $line, 5), 5, '');
            $framework = [
                'id' => trim($frameworkId),
                'labelFr' => trim($frameworkLabelFr),
                'labelEn' => trim($frameworkLabelEn),
            ];
            $group = null;
            $subgroup = null;
            continue;
        }

        if (str_starts_with($line, "## group\t") && is_array($framework)) {
            [, $groupId, $groupLabelFr, $groupLabelEn]
                = array_pad(explode("\t", $line, 4), 4, '');
            $group = [
                'id' => trim($groupId),
                'labelFr' => trim($groupLabelFr),
                'labelEn' => trim($groupLabelEn),
            ];
            $subgroup = null;
            continue;
        }

        if (str_starts_with($line, "### subgroup\t") && is_array($framework) && is_array($group)) {
            [, $subgroupId, $subgroupLabelFr, $subgroupLabelEn]
                = array_pad(explode("\t", $line, 4), 4, '');
            $subgroup = [
                'id' => trim($subgroupId),
                'labelFr' => trim($subgroupLabelFr),
                'labelEn' => trim($subgroupLabelEn) !== '' ? trim($subgroupLabelEn) : trim($subgroupLabelFr),
            ];
            continue;
        }

        if (!is_array($framework) || !is_array($group)) continue;
        [$code, $labelFr, $descFr, $labelEn, $descEn]
            = array_pad(explode("\t", $line, 5), 5, '');
        $code = trim($code);
        $labelFr = trim($labelFr);
        if ($code === '' || $labelFr === '') continue;
        $labelEn = trim($labelEn) !== '' ? trim($labelEn) : $labelFr;
        $descEn = trim($descEn) !== '' ? trim($descEn) : trim($descFr);
        $id = 'competency:' . $framework['id'] . ':' . $code;
        $shortCode = $framework['labelFr'] . ' ' . $code;
        $shortCodeEn = $framework['labelEn'] . ' ' . $code;
        $entry = [
            'id' => $id,
            'platform' => $framework['id'],
            'category' => $framework['id'] . ':' . $group['id'] . (is_array($subgroup) ? ':' . $subgroup['id'] : ''),
            'sectionFr' => $subgroup['labelFr'] ?? $group['labelFr'],
            'sectionEn' => $subgroup['labelEn'] ?? $group['labelEn'],
            'appFr' => '',
            'appEn' => '',
            'levelLabelFr' => $framework['labelFr'],
            'levelLabelEn' => $framework['labelEn'],
            'levelBadge' => $framework['labelFr'],
            'number' => $code,
            'sectionNumber' => 0,
            'sectionRoman' => '',
            'shortCode' => $shortCode,
            'shortCodeEn' => $shortCodeEn,
            'legacyShortCode' => '',
            'labelFr' => $labelFr,
            'labelEn' => $labelEn,
            'descFr' => trim($descFr),
            'descEn' => $descEn,
        ];
        if (isset($frameworkDetails[$framework['id']][$code])) {
            $frameworkDetail = $frameworkDetails[$framework['id']][$code];
            if (!empty($frameworkDetail['description'])) {
                $entry['descFr'] = $frameworkDetail['description'];
            }
            $entry['details'] = $frameworkDetail['details'] ?? [];
        }

        foreach ([$id, $shortCode, $shortCodeEn, $labelFr, $labelEn] as $token) {
            $normalized = normalizeCompetencyToken($token);
            if ($normalized !== '') {
                $catalog[$normalized] = $entry;
            }
        }

        if ($framework['id'] === 'digcomp' && !empty($entry['details'])) {
            foreach ($entry['details'] as $detail) {
                $detailTextFr = trim((string)($detail['textFr'] ?? ''));
                $detailTextEn = trim((string)($detail['textEn'] ?? $detailTextFr));
                if (
                    !preg_match('/^(CS\d+\.\d+\.\d+)\s*·\s*(.+)$/u', $detailTextFr, $frMatch)
                    || !preg_match('/^(CS\d+\.\d+\.\d+)\s*·\s*(.+)$/u', $detailTextEn, $enMatch)
                    || $frMatch[1] !== $enMatch[1]
                ) {
                    continue;
                }
                $statementCode = $frMatch[1];
                $levelLabels = $digCompStatementLevelLabels[(string)($detail['kind'] ?? '')]
                    ?? ['fr' => (string)($detail['kind'] ?? ''), 'en' => (string)($detail['kind'] ?? '')];
                $statement = $entry;
                $statement['id'] = 'competency:digcomp:' . $statementCode;
                $statement['number'] = $statementCode;
                $statement['shortCode'] = $statementCode;
                $statement['shortCodeEn'] = $statementCode;
                $statement['legacyShortCode'] = $statementCode;
                $statement['labelFr'] = trim($frMatch[2]);
                $statement['labelEn'] = trim($enMatch[2]);
                $statement['descFr'] = $levelLabels['fr'] . ' · ' . $code . '. ' . $labelFr;
                $statement['descEn'] = $levelLabels['en'] . ' · ' . $code . '. ' . $labelEn;
                unset($statement['details']);
                foreach ([$statement['id'], $statementCode, $statement['labelFr'], $statement['labelEn']] as $token) {
                    $normalized = normalizeCompetencyToken((string)$token);
                    if ($normalized !== '') {
                        $catalog[$normalized] = $statement;
                    }
                }
            }
        }
    }

    return $catalog;
}

function competencyForReference(string $reference): ?array {
    $catalog = loadCompetencyCatalog();
    return $catalog[normalizeCompetencyToken($reference)] ?? null;
}

function competencyStyle(string $level, string $category = ''): array {
    $levelStyle = match ($level) {
        'approfondir' => ['#ede9fe', '#c4b5fd', '#5b21b6', '#ddd6fe'],
        'creer' => ['#dcfce7', '#86efac', '#166534', '#bbf7d0'],
        'acquerir' => ['#e0f2fe', '#7dd3fc', '#075985', '#bae6fd'],
        default => null,
    };
    if (is_array($levelStyle)) {
        return $levelStyle;
    }

    $categoryParts = explode(':', $category);
    $groupId = $categoryParts[1] ?? '';
    $domainIndexes = [
        'domaine-1' => 0, 'valeurs' => 0, 'information' => 0, 'fondements' => 0,
        'domaine-2' => 1, 'complexite' => 1, 'communication' => 1, 'usages' => 1,
        'domaine-3' => 2, 'avenirs' => 2, 'creation' => 2, 'enjeux' => 2,
        'domaine-4' => 3, 'action' => 3, 'protection' => 3,
        'domaine-5' => 4, 'problemes' => 4, 'environnement' => 4,
    ];
    $domainStyles = [
        ['#fff7ed', '#fdba74', '#9a3412', '#ffedd5'],
        ['#ecfeff', '#67e8f9', '#155e75', '#cffafe'],
        ['#f5f3ff', '#c4b5fd', '#6d28d9', '#ede9fe'],
        ['#ecfdf5', '#6ee7b7', '#047857', '#d1fae5'],
        ['#fdf2f8', '#f9a8d4', '#be185d', '#fce7f3'],
    ];
    if (isset($domainIndexes[$groupId])) {
        return $domainStyles[$domainIndexes[$groupId]];
    }

    return match ($level) {
        'greencomp' => ['#ecfdf5', '#6ee7b7', '#047857', '#d1fae5'],
        'digcomp' => ['#eff6ff', '#93c5fd', '#1d4ed8', '#dbeafe'],
        'crcn' => ['#fdf2f8', '#f9a8d4', '#be185d', '#fce7f3'],
        'pix' => ['#ecfeff', '#67e8f9', '#155e75', '#cffafe'],
        'pix-ia' => ['#fff7ed', '#fdba74', '#9a3412', '#ffedd5'],
        default => ['#e0f2fe', '#7dd3fc', '#075985', '#bae6fd'],
    };
}

function competencyTooltip(array $competency): string {
    $label = trim((string)($competency['shortCode'] ?? '') . ' ' . (string)($competency['labelFr'] ?? ''));
    $description = safeText($competency['descFr'] ?? '');
    return implode(' — ', array_filter([$label, $description], static fn(string $part): bool => $part !== ''));
}

function formatDuration(int $minutes): string {
    if ($minutes < 60) return $minutes . ' min';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m > 0 ? "{$h} h {$m} min" : "{$h} h";
}

function totalSessionDuration(array $session): int {
    $total = 0;
    foreach ($session['activities'] ?? [] as $act) {
        $total += max(1, (int)($act['duration'] ?? 1));
    }
    return $total;
}

// ── Meta info helpers ────────────────────────────────────────
$metaDesigners  = safeText($meta['designers'] ?? $meta['author'] ?? '');
$metaDescription= safeText($meta['description'] ?? '');
$metaDelivery   = safeText($meta['modeDelivery'] ?? '');
$metaSchoolSystem= safeText($meta['schoolSystem'] ?? '');
$metaSchoolLevel= safeText($meta['schoolLevel'] ?? '');
if ($metaSchoolSystem === '' && $metaSchoolLevel !== '') {
    $metaSchoolSystem = match (true) {
        str_starts_with($metaSchoolLevel, 'ch_') => 'switzerland',
        str_starts_with($metaSchoolLevel, 'us_') => 'united_states',
        str_starts_with($metaSchoolLevel, 'be_fr_') => 'belgium_french',
        str_starts_with($metaSchoolLevel, 'be_nl_') => 'belgium_flemish',
        str_starts_with($metaSchoolLevel, 'be_de_') => 'belgium_german',
        str_starts_with($metaSchoolLevel, 'uk_england_') => 'uk_england',
        str_starts_with($metaSchoolLevel, 'uk_wales_') => 'uk_wales',
        str_starts_with($metaSchoolLevel, 'uk_scotland_') => 'uk_scotland',
        str_starts_with($metaSchoolLevel, 'uk_northern_ireland_') => 'uk_northern_ireland',
        str_starts_with($metaSchoolLevel, 'eu_school_') => 'european_schools',
        str_starts_with($metaSchoolLevel, 'ib_') => 'ib',
        str_starts_with($metaSchoolLevel, 'isced_') => 'isced_2011',
        default => 'france',
    };
}
$metaClassSize  = safeText($meta['sizeClass'] ?? '');
$metaLearningDays= (int)($meta['learningDays'] ?? 0);
$metaLearningH  = (int)($meta['learningHours'] ?? 0);
$metaLearningMin= (int)($meta['learningMinutes'] ?? 0);
$metaDayHours   = max(1, (int)($meta['dayHours'] ?? 7));

$learningTimeParts = [];
if ($metaLearningDays > 0) $learningTimeParts[] = $metaLearningDays . ' j';
if ($metaLearningH > 0)    $learningTimeParts[] = $metaLearningH . ' h';
if ($metaLearningMin > 0)  $learningTimeParts[] = $metaLearningMin . ' min';
$learningTime = implode(' ', $learningTimeParts);
$learningMinutes = (($metaLearningDays * $metaDayHours + $metaLearningH) * 60) + $metaLearningMin;
$designedMinutes = max(0, (int)($meta['designedMinutes'] ?? 0));

$totalActivities = 0;
$totalMinutes    = 0;
$hasActivityContextData = false;
foreach ($sessions as $s) {
    $totalActivities += count($s['activities'] ?? []);
    $totalMinutes    += totalSessionDuration($s);
    foreach (($s['activities'] ?? []) as $act) {
        if (!is_array($act)) continue;
        $hasContext = labelFor($GROUP_MODES, (string)($act['groupMode'] ?? '')) !== ''
            || labelFor($TEACHING_MODES, (string)($act['teachingMode'] ?? '')) !== ''
            || labelFor($TRAINER_MODES, (string)($act['teacherPresence'] ?? '')) !== ''
            || labelFor($SYNC_MODES, (string)($act['syncMode'] ?? '')) !== ''
            || labelFor($LOCATION_MODES, (string)($act['locationMode'] ?? '')) !== ''
            || (($EVAL_MODES[(string)($act['evaluationMode'] ?? 'none')] ?? null) !== null);
        if (!$hasContext) {
            $tools = is_array($act['tools'] ?? null) ? array_filter($act['tools'], 'is_string') : [];
            foreach ($tools as $toolId) {
                if (competencyForReference($toolId)) {
                    $hasContext = true;
                    break;
                }
            }
        }
        if ($hasContext) {
            $hasActivityContextData = true;
            break 2;
        }
    }
}
$displayDesignedMinutes = $designedMinutes > 0 ? $designedMinutes : $totalMinutes;

?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
  <title><?= esc($title) ?> — Scenarisation</title>
  <link rel="stylesheet" href="css/interface.css?v=20260910-public-cursor">
  <link rel="stylesheet" href="css/view-footer.css?v=20260910">
  <link rel="stylesheet" href="css/view-toolbar.css?v=20260910">
  <link rel="stylesheet" href="css/view-links.css?v=20260910">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
  <style>
    :root {
      --bg: #f5f7fb;
      --surface: #ffffff;
      --surface-2: #eef2f7;
      --border: #d8dee8;
      --text-1: #1e2430;
      --text-2: #5a6474;
      --accent: #2f5bea;
      --accent-soft: #e9efff;
      --radius: 16px;
      --shadow: 0 8px 24px rgba(21,32,56,.07);
      font-family: Inter, system-ui, sans-serif;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      background: linear-gradient(180deg, #fbfcff, var(--bg));
      color: var(--text-1);
      font: 15px/1.6 Inter, system-ui, sans-serif;
    }
    .page { max-width: 1000px; margin: 0 auto; padding: 32px 20px 60px; }
    /* Header */
    .hero { margin-bottom: 32px; }
    .hero h1 {
      margin: 0 0 8px;
    }
    .hero-meta { color: var(--text-2); font-size: 13px; display: flex; flex-wrap: wrap; gap: 6px 16px; }
    .hero-meta span { white-space: nowrap; }

    /* Info cards row */
    .meta-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 24px;
    }
    .meta-card {
      display: inline-flex;
      align-items: baseline;
      gap: 6px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 5px 10px;
      min-width: 0;
    }
    .meta-card-label {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--text-2);
      white-space: nowrap;
    }
    .meta-card-value {
      font-size: 13px;
      font-weight: 700;
      white-space: nowrap;
    }

    /* Description block */
    .meta-description {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 16px 20px;
      margin-bottom: 32px;
      font-size: 14px;
      color: var(--text-1);
      line-height: 1.65;
    }
    .activity-data-toggle-btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      border: 0;
      border-radius: 8px;
      background: transparent;
      color: var(--text-2);
      padding: 6px 8px;
      font: inherit;
      font-size: 12px;
      font-weight: 700;
      line-height: 1.2;
      cursor: pointer;
    }
    .activity-data-toggle-btn:hover,
    .activity-data-toggle-btn:focus-visible {
      background: var(--accent-soft);
      color: var(--accent);
      outline: none;
    }
    body.activity-context-hidden .activity-context-chip,
    body.activity-context-hidden .chip-competency {
      display: none;
    }

    /* Sessions */
    .sessions { display: grid; gap: 20px; }
    .session-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }
    .session-head {
      padding: 16px 20px 14px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }
    .session-title {
      font-size: 17px;
      font-weight: 700;
      letter-spacing: -.02em;
    }
    .session-duration {
      font-size: 13px;
      color: var(--text-2);
      white-space: nowrap;
    }

    /* Timeline strip */
    .session-timeline {
      display: flex;
      height: 8px;
      overflow: hidden;
    }
    .session-timeline-block {
      flex-shrink: 0;
      transition: opacity .15s;
    }
    .session-timeline-block:hover { opacity: .75; }

    /* Objectives / intentions */
    .session-text {
      padding: 12px 20px;
      font-size: 13px;
      color: var(--text-2);
      border-bottom: 1px solid var(--border);
      line-height: 1.6;
    }
    .session-text > strong { color: var(--text-1); font-size: 11px; text-transform: uppercase; letter-spacing: .07em; display: block; margin-bottom: 2px; }
    .markdown-content p,
    .markdown-content blockquote,
    .markdown-content ul,
    .markdown-content ol {
      margin: 0 0 8px;
    }
    .markdown-content > :last-child {
      margin-bottom: 0;
    }
    .markdown-content h2 {
      margin: 0 0 8px;
    }
    .markdown-content ul,
    .markdown-content ol {
      padding-left: 20px;
    }
    .markdown-content blockquote {
      padding-left: 10px;
      border-left: 3px solid var(--border);
      color: var(--text-2);
    }

    /* Activities */
    .activity-list { padding: 12px 16px; display: grid; gap: 10px; }
    .activity-card {
      border: 1px solid var(--border);
      border-left: 4px solid #999;
      border-radius: 10px;
      padding: 12px 14px;
      background: #fafbfd;
    }
    .activity-head {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 6px;
    }
    .activity-type-badge {
      font-size: 12px;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 99px;
      background: #eee;
    }
    .activity-duration-badge {
      font-size: 12px;
      color: var(--text-2);
      font-weight: 600;
    }
    .activity-description {
      font-size: 14px;
      color: var(--text-1);
      margin-bottom: 8px;
      line-height: 1.6;
    }
    .activity-instructions {
      margin-bottom: 8px;
      padding: 9px 11px;
      border-radius: 8px;
      background: var(--surface);
      border: 1px solid var(--border);
      font-size: 14px;
      color: var(--text-1);
      line-height: 1.6;
    }
    .activity-text-label {
      display: block;
      margin-bottom: 3px;
      color: var(--text-2);
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .activity-chips { display: flex; flex-wrap: wrap; gap: 5px; }
    .chip {
      font-size: 11px;
      padding: 3px 8px;
      border-radius: 99px;
      border: 1px solid var(--border);
      color: var(--text-2);
      background: var(--surface);
      white-space: nowrap;
    }
    .chip-tools { background: var(--accent-soft); border-color: rgba(47,91,234,.18); color: var(--accent); }
    .chip-competency {
      background: var(--competency-bg);
      border-color: var(--competency-border);
      color: var(--competency-text);
      font-weight: 700;
      cursor: help;
      transition: background .15s ease, border-color .15s ease, transform .15s ease;
    }
    .chip-competency:hover {
      background: var(--competency-active);
      transform: translateY(-1px);
    }
    .activity-links-public {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 8px;
    }
    .activity-notes {
      margin-top: 8px;
      font-size: 13px;
      color: var(--text-2);
      font-style: italic;
      line-height: 1.5;
    }

    #app-tooltip {
      position: fixed;
      z-index: 9999;
      padding: 5px 11px 6px;
      border-radius: 7px;
      background: #1e2433;
      color: #eef2ff;
      font-size: 12px;
      font-weight: 500;
      line-height: 1.4;
      max-width: 220px;
      white-space: normal;
      text-align: center;
      pointer-events: none;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.30);
      opacity: 0;
      transform: translateY(5px) scale(0.96);
      transition: opacity 140ms ease, transform 140ms cubic-bezier(0.34, 1.4, 0.64, 1);
      will-change: opacity, transform;
    }
    #app-tooltip.tip-visible {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
    #app-tooltip::after {
      content: '';
      position: absolute;
      left: var(--tip-arrow, 50%);
      transform: translateX(-50%);
      border: 5px solid transparent;
    }
    #app-tooltip.tip-above::after {
      top: 100%;
      border-top-color: #1e2433;
    }
    #app-tooltip.tip-below::after {
      bottom: 100%;
      border-bottom-color: #1e2433;
    }

    @media print {
      body { background: #fff; }
      #app-tooltip { display: none; }
      .session-card { break-inside: avoid; }
    }
  </style>
</head>
<body class="published-design-page">
<main class="page">
  <header class="hero">
    <h1><?= esc($title) ?></h1>
    <div class="hero-meta">
      <?php if ($metaDesigners !== ''): ?>
        <span><?= esc($metaDesigners) ?></span>
      <?php endif; ?>
      <?php if ($updatedAt !== ''): ?>
        <span>Mis à jour le <?= esc($updatedAt) ?></span>
      <?php endif; ?>
    </div>
  </header>

  <?php
  $metaCards = [];
  if (count($sessions) > 0) $metaCards[] = ['Moments', count($sessions)];
  if ($totalActivities > 0) $metaCards[] = ['Activités', $totalActivities];
  if ($displayDesignedMinutes > 0) $metaCards[] = ['Durée conçue', formatDuration($displayDesignedMinutes)];
  if ($learningMinutes > 0)        $metaCards[] = ['Durée prévue', $learningTime];
  if ($metaDelivery !== '')  $metaCards[] = ['Mode', labelFor($DELIVERY_MODES, $metaDelivery, $metaDelivery)];
  if ($metaSchoolSystem !== '') {
      $metaCards[] = [
          $metaSchoolSystem === 'isced_2011' ? 'Classification' : 'Système scolaire',
          labelFor($SCHOOL_SYSTEMS, $metaSchoolSystem, $metaSchoolSystem),
      ];
  }
  if ($metaSchoolLevel !== '') $metaCards[] = ['Niveau', labelFor($SCHOOL_LEVELS, $metaSchoolLevel, $metaSchoolLevel)];
  if ($metaClassSize !== '') $metaCards[] = ['Taille du groupe', $metaClassSize];
  ?>
  <?php if ($metaCards): ?>
  <div class="meta-grid">
    <?php foreach ($metaCards as [$label, $value]): ?>
    <div class="meta-card">
      <div class="meta-card-label"><?= esc($label) ?></div>
      <div class="meta-card-value"><?= esc((string)$value) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($metaDescription !== ''): ?>
  <div class="meta-description markdown-content"><?= markdownHtml($metaDescription) ?></div>
  <?php endif; ?>

  <?php if ($hasActivityContextData): ?>
  <div class="sessions-toolbar">
    <button
      id="activity-context-toggle"
      class="activity-data-toggle-btn"
      type="button"
      aria-pressed="false"
      data-show-label="Afficher les données"
      data-hide-label="Masquer les données"
    >
      <i class="fa-solid fa-eye-slash" aria-hidden="true"></i>
      <span>Masquer les données</span>
    </button>
  </div>
  <?php endif; ?>

  <div class="sessions">
  <?php foreach ($sessions as $si => $session):
    $sTitle      = safeText($session['title'] ?? '');
    $sObjectives = safeText($session['objectives'] ?? '');
    $sNotes      = safeText($session['notes'] ?? '');
    $activities  = is_array($session['activities'] ?? null) ? $session['activities'] : [];
    $sDuration   = totalSessionDuration($session);
  ?>
  <section class="session-card">
    <div class="session-head">
      <div class="session-title"><?= esc($sTitle !== '' ? $sTitle : 'Séance ' . ($si + 1)) ?></div>
      <?php if ($sDuration > 0): ?>
        <div class="session-duration"><?= esc(formatDuration($sDuration)) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($activities): ?>
    <div class="session-timeline">
      <?php foreach ($activities as $act):
        $dur   = max(1, (int)($act['duration'] ?? 1));
        $type  = (string)($act['type'] ?? 'undefined');
        $color = $LEARNING_TYPES[$type]['color'] ?? '#d1d5db';
        $pct   = $sDuration > 0 ? round($dur / $sDuration * 100, 2) : 0;
        $aLabel = $LEARNING_TYPES[$type]['label'] ?? $type;
      ?>
      <div class="session-timeline-block" style="width:<?= esc((string)$pct) ?>%;background:<?= esc($color) ?>" title="<?= esc($aLabel) ?> – <?= esc((string)$dur) ?> min"></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($sObjectives !== ''): ?>
    <div class="session-text"><strong>Objectifs</strong><div class="markdown-content"><?= markdownHtml($sObjectives) ?></div></div>
    <?php endif; ?>

    <?php if ($activities): ?>
    <div class="activity-list">
      <?php foreach ($activities as $ai => $act):
        $aType  = (string)($act['type'] ?? 'undefined');
        $aDur   = max(1, (int)($act['duration'] ?? 1));
        $aColor = $LEARNING_TYPES[$aType]['color'] ?? '#d1d5db';
        $aLabel = $LEARNING_TYPES[$aType]['label'] ?? $aType;
        $aDesc  = safeText($act['description'] ?? '');
        $aInstructions = safeText($act['instructions'] ?? '');
        $aNotes = safeText($act['notes'] ?? '');
        $aTools = is_array($act['tools'] ?? null) ? array_filter($act['tools'], 'is_string') : [];
        $aLinks = is_array($act['links'] ?? null) ? $act['links'] : [];

        $chips = [];
        $aiasChip = null;
        $gm = labelFor($GROUP_MODES, (string)($act['groupMode'] ?? ''));
        if ($gm !== '') $chips[] = $gm;
        $tr = labelFor($TEACHING_MODES, (string)($act['teachingMode'] ?? ''));
        if ($tr === '') {
            $tr = labelFor($TRAINER_MODES, (string)($act['teacherPresence'] ?? ''));
        }
        if ($tr !== '') $chips[] = $tr;
        $sm = labelFor($SYNC_MODES, (string)($act['syncMode'] ?? ''));
        if ($sm !== '') $chips[] = $sm;
        $lm = labelFor($LOCATION_MODES, (string)($act['locationMode'] ?? ''));
        if ($lm !== '') $chips[] = $lm;
        $ev = $EVAL_MODES[(string)($act['evaluationMode'] ?? 'none')] ?? null;
        if ($ev !== null) $chips[] = $ev;
        $aias = is_array($act['aias'] ?? null) ? $act['aias'] : [];
        $aiasLevel = (int)($aias['level'] ?? 0);
        if (($aias['status'] ?? '') === 'specified' && isset($AIAS_LEVELS[$aiasLevel])) {
            $aiasChip = [
                'label' => 'AIAS ' . $aiasLevel . ' · ' . $AIAS_LEVELS[$aiasLevel],
                'class' => 'aias-level aias-level-' . $aiasLevel,
            ];
        }
      ?>
      <article class="activity-card" style="border-left-color:<?= esc($aColor) ?>">
        <div class="activity-head">
          <span class="activity-type-badge" style="background:<?= esc($aColor) ?>;color:rgba(0,0,0,.65)"><?= esc($aLabel) ?></span>
          <span class="activity-duration-badge"><?= esc(formatDuration($aDur)) ?></span>
        </div>
        <?php if ($aDesc !== ''): ?>
        <div class="activity-description"><span class="activity-text-label">Description de l'activité</span><div class="markdown-content"><?= markdownHtml($aDesc) ?></div></div>
        <?php endif; ?>
        <?php if ($aInstructions !== ''): ?>
        <div class="activity-instructions"><span class="activity-text-label">Consignes pour les élèves</span><div class="markdown-content"><?= markdownHtml($aInstructions) ?></div></div>
        <?php endif; ?>
        <?php if ($chips || $aiasChip !== null || $aTools): ?>
        <div class="activity-chips">
          <?php foreach ($chips as $chip): ?>
          <span class="chip activity-context-chip"><?= esc($chip) ?></span>
          <?php endforeach; ?>
          <?php if ($aiasChip !== null): ?>
          <span class="chip activity-context-chip <?= esc($aiasChip['class']) ?>"><?= esc($aiasChip['label']) ?></span>
          <?php endif; ?>
          <?php foreach ($aTools as $toolId):
            $competency = competencyForReference($toolId);
            if ($competency) {
                [$competencyBg, $competencyBorder, $competencyText, $competencyActive] = competencyStyle(
                    (string)$competency['platform'],
                    (string)($competency['category'] ?? '')
                );
                $toolLabel = (string)$competency['shortCode'];
                $toolTitle = competencyTooltip($competency);
            } else {
                $toolLabel = $TOOLS_LABELS[$toolId] ?? $toolId;
                $toolTitle = $toolLabel;
                $competencyBg = '';
                $competencyBorder = '';
                $competencyText = '';
                $competencyActive = '';
            }
          ?>
          <span
            class="chip <?= $competency ? 'chip-competency' : 'chip-tools' ?>"
            data-tooltip="<?= esc($toolTitle) ?>"
            <?php if ($competency): ?>style="--competency-bg:<?= esc($competencyBg) ?>;--competency-border:<?= esc($competencyBorder) ?>;--competency-text:<?= esc($competencyText) ?>;--competency-active:<?= esc($competencyActive) ?>"<?php endif; ?>
          ><?= esc($toolLabel) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($aLinks): ?>
        <div class="activity-links-public" aria-label="Liens de l'activité">
          <?php foreach ($aLinks as $link):
            if (!is_array($link)) continue;
            $linkTitle = safeText($link['title'] ?? '');
            $linkUrl = safeUrl(safeText($link['url'] ?? ''));
            if ($linkTitle === '' || $linkUrl === '') continue;
          ?>
          <a class="activity-link-public" href="<?= esc($linkUrl) ?>" target="_blank" rel="noopener noreferrer">↗ <?= esc($linkTitle) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($aNotes !== ''): ?>
        <div class="activity-notes markdown-content"><?= markdownHtml($aNotes) ?></div>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($sNotes !== ''): ?>
    <div class="session-text" style="border-top:1px solid var(--border);border-bottom:none"><strong>Notes</strong><div class="markdown-content"><?= markdownHtml($sNotes) ?></div></div>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
  </div>

  <footer class="view-footer">
    <?php if ($license): ?>
    <p class="view-license">
      <i class="fa-brands fa-creative-commons" aria-hidden="true"></i>
      <span>Cette production est mise à disposition sous
      <a href="<?= esc($license['url']) ?>" target="_blank" rel="license noopener noreferrer"><?= esc($license['label']) ?></a>.</span>
    </p>
    <?php endif; ?>
    <p>Partagé avec Scenarisation</p>
  </footer>

</main>
<script>
(() => {
  const contextToggle = document.getElementById('activity-context-toggle');
  const contextStorageKey = 'learning-designer-public-activity-context-hidden';

  function setContextVisibility(hidden) {
    document.body.classList.toggle('activity-context-hidden', hidden);
    if (!contextToggle) return;
    const label = contextToggle.querySelector('span');
    const icon = contextToggle.querySelector('i');
    contextToggle.setAttribute('aria-pressed', hidden ? 'true' : 'false');
    if (label) {
      label.textContent = hidden ? contextToggle.dataset.showLabel : contextToggle.dataset.hideLabel;
    }
    if (icon) {
      icon.classList.toggle('fa-eye', hidden);
      icon.classList.toggle('fa-eye-slash', !hidden);
    }
  }

  if (contextToggle) {
    let savedHidden = false;
    try {
      savedHidden = localStorage.getItem(contextStorageKey) === '1';
    } catch (error) {}
    setContextVisibility(savedHidden);
    contextToggle.addEventListener('click', () => {
      const hidden = !document.body.classList.contains('activity-context-hidden');
      setContextVisibility(hidden);
      try {
        localStorage.setItem(contextStorageKey, hidden ? '1' : '0');
      } catch (error) {}
    });
  }

  const tip = document.createElement('div');
  tip.id = 'app-tooltip';
  tip.setAttribute('role', 'tooltip');
  tip.setAttribute('aria-hidden', 'true');
  document.body.appendChild(tip);

  let timer = null;
  let activeTarget = null;

  function nearestTip(el) {
    let node = el;
    while (node && node !== document.body) {
      if (node.dataset && node.dataset.tooltip) return node;
      node = node.parentElement;
    }
    return null;
  }

  function formatTipText(text) {
    return String(text || '').replace(/([^:\s])\s*:\s*(?!\/\/)/g, '$1\u00a0:\u00a0');
  }

  function place(target) {
    const rect = target.getBoundingClientRect();
    const gap = 9;
    const vw = window.innerWidth;
    tip.classList.remove('tip-above', 'tip-below');

    let top;
    if (rect.top - tip.offsetHeight - gap > 6) {
      top = rect.top - tip.offsetHeight - gap;
      tip.classList.add('tip-above');
    } else {
      top = rect.bottom + gap;
      tip.classList.add('tip-below');
    }

    let left = rect.left + rect.width / 2 - tip.offsetWidth / 2;
    left = Math.max(6, Math.min(vw - tip.offsetWidth - 6, left));
    const arrowPos = Math.max(14, Math.min(tip.offsetWidth - 14, rect.left + rect.width / 2 - left));
    tip.style.setProperty('--tip-arrow', `${arrowPos}px`);
    tip.style.top = `${Math.round(top)}px`;
    tip.style.left = `${Math.round(left)}px`;
  }

  function show(target) {
    activeTarget = target;
    tip.textContent = formatTipText(target.dataset.tooltip);
    tip.setAttribute('aria-hidden', 'false');
    tip.style.left = '-9999px';
    tip.style.top = '-9999px';
    tip.classList.add('tip-visible');
    requestAnimationFrame(() => {
      if (activeTarget === target) place(target);
    });
  }

  function hide() {
    clearTimeout(timer);
    activeTarget = null;
    tip.classList.remove('tip-visible', 'tip-above', 'tip-below');
    tip.setAttribute('aria-hidden', 'true');
  }

  document.addEventListener('mouseover', (event) => {
    const target = nearestTip(event.target);
    if (!target || target === activeTarget) return;
    clearTimeout(timer);
    timer = setTimeout(() => show(target), 480);
  });
  document.addEventListener('mouseout', (event) => {
    if (!nearestTip(event.target)) return;
    hide();
  });
  document.addEventListener('click', hide, true);
  document.addEventListener('keydown', hide, true);
  document.addEventListener('scroll', () => {
    if (activeTarget) place(activeTarget);
  }, { passive: true, capture: true });
})();
</script>
<script src="js/feedback.js?v=20260906-scenarisation"></script>
</body>
</html>
