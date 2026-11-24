<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

function competency_roman(int $value): string
{
    $numerals = [
        ['M', 1000], ['CM', 900], ['D', 500], ['CD', 400],
        ['C', 100], ['XC', 90], ['L', 50], ['XL', 40],
        ['X', 10], ['IX', 9], ['V', 5], ['IV', 4], ['I', 1],
    ];
    $result = '';
    foreach ($numerals as [$symbol, $amount]) {
        while ($value >= $amount) {
            $result .= $symbol;
            $value -= $amount;
        }
    }
    return $result;
}

function parse_competency_catalog(string $source): array
{
    $items = [];
    $levels = [];
    $sectionsByLevel = [];
    $legacyCodeByLevel = ['acquerir' => 'A', 'approfondir' => 'P', 'creer' => 'C'];
    $currentLevel = null;
    $currentAppByLevel = [];
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

    foreach (preg_split('/\R/u', $source) ?: [] as $line) {
        $line = trim((string)$line, "\r");
        if (trim($line) === '') {
            continue;
        }

        if (str_starts_with($line, '# ')) {
            [$id, $labelFr, $labelEn] = array_pad(explode("\t", substr($line, 2)), 3, '');
            $currentLevel = [
                'id' => trim($id),
                'labelFr' => trim($labelFr),
                'labelEn' => trim($labelEn),
            ];
            $levels[$currentLevel['id']] = $currentLevel;
            $sectionsByLevel[$currentLevel['id']] = [];
            $currentAppByLevel[$currentLevel['id']] = '';
            continue;
        }

        if (!$currentLevel) {
            continue;
        }

        [$sectionRaw, $appRaw, $numberRaw, $labelFrRaw, $descFrRaw, $labelEnRaw, $descEnRaw]
            = array_pad(explode("\t", $line, 7), 7, '');
        $number = (int)trim($numberRaw);
        $labelFr = trim($labelFrRaw);
        $descFr = trim($descFrRaw);
        $labelEn = trim($labelEnRaw) !== '' ? trim($labelEnRaw) : $labelFr;
        $descEn = trim($descEnRaw) !== '' ? trim($descEnRaw) : $descFr;
        if ($number <= 0 || $labelFr === '' || $descFr === '') {
            continue;
        }

        $levelId = $currentLevel['id'];
        $section = trim($sectionRaw) !== '' ? trim($sectionRaw) : 'Général';
        if (!in_array($section, $sectionsByLevel[$levelId], true)) {
            $sectionsByLevel[$levelId][] = $section;
            $currentAppByLevel[$levelId] = '';
        }
        if (trim($appRaw) !== '') {
            $currentAppByLevel[$levelId] = trim($appRaw);
        }
        $sectionNumber = array_search($section, $sectionsByLevel[$levelId], true) + 1;
        $sectionRoman = competency_roman((int)$sectionNumber);

        $items[] = [
            'id' => 'competency:' . $levelId . ':' . trim($numberRaw),
            'levelId' => $levelId,
            'levelFr' => $currentLevel['labelFr'],
            'levelEn' => $currentLevel['labelEn'],
            'sectionFr' => $section,
            'sectionEn' => $sectionEnByFr[$section] ?? $section,
            'sectionRoman' => $sectionRoman,
            'app' => $currentAppByLevel[$levelId],
            'number' => $number,
            'shortCode' => $currentLevel['labelFr'] . '-' . $sectionRoman . '-' . $number,
            'shortCodeEn' => $currentLevel['labelEn'] . '-' . $sectionRoman . '-' . $number,
            'legacyCode' => ($legacyCodeByLevel[$levelId] ?? strtoupper(substr($levelId, 0, 1))) . $number,
            'labelFr' => $labelFr,
            'labelEn' => $labelEn,
            'descFr' => $descFr,
            'descEn' => $descEn,
            'appFr' => $currentAppByLevel[$levelId],
            'appEn' => $appEnByFr[$currentAppByLevel[$levelId]] ?? $currentAppByLevel[$levelId],
        ];
    }

    return ['levels' => array_values($levels), 'items' => $items];
}

function parse_additional_competency_frameworks(string $source): array
{
    $frameworks = [];
    $items = [];
    $framework = null;
    $group = null;
    $subgroup = null;

    foreach (preg_split('/\R/u', $source) ?: [] as $rawLine) {
        $line = trim((string)$rawLine, "\r");
        if (trim($line) === '') {
            continue;
        }
        if (str_starts_with($line, "# framework\t")) {
            [, $id, $labelFr, $labelEn, $sourceUrl] = array_pad(explode("\t", $line), 5, '');
            $framework = [
                'id' => trim($id),
                'labelFr' => trim($labelFr),
                'labelEn' => trim($labelEn) ?: trim($labelFr),
                'sourceUrl' => trim($sourceUrl),
                'groups' => [],
            ];
            $frameworks[$framework['id']] = $framework;
            $group = null;
            $subgroup = null;
            continue;
        }
        if (str_starts_with($line, "## group\t") && is_array($framework)) {
            [, $id, $labelFr, $labelEn] = array_pad(explode("\t", $line), 4, '');
            $group = [
                'id' => trim($id),
                'labelFr' => trim($labelFr),
                'labelEn' => trim($labelEn) ?: trim($labelFr),
            ];
            $frameworks[$framework['id']]['groups'][$group['id']] = $group;
            $subgroup = null;
            continue;
        }
        if (str_starts_with($line, "### subgroup\t") && is_array($framework) && is_array($group)) {
            [, $id, $labelFr, $labelEn] = array_pad(explode("\t", $line), 4, '');
            $subgroup = [
                'id' => trim($id),
                'labelFr' => trim($labelFr),
                'labelEn' => trim($labelEn) ?: trim($labelFr),
            ];
            continue;
        }
        if (!is_array($framework) || !is_array($group)) {
            continue;
        }
        [$code, $labelFr, $descFr, $labelEn, $descEn, $sourceUrl] = array_pad(explode("\t", $line, 6), 6, '');
        $code = trim($code);
        $labelFr = trim($labelFr);
        if ($code === '' || $labelFr === '') {
            continue;
        }
        $items[] = [
            'id' => 'competency:' . $framework['id'] . ':' . $code,
            'levelId' => $framework['id'],
            'levelFr' => $framework['labelFr'],
            'levelEn' => $framework['labelEn'],
            'themeId' => $framework['id'],
            'sectionFr' => $subgroup['labelFr'] ?? $group['labelFr'],
            'sectionEn' => $subgroup['labelEn'] ?? $group['labelEn'],
            'sectionRoman' => '',
            'groupId' => $group['id'] . (is_array($subgroup) ? ':' . $subgroup['id'] : ''),
            'app' => '',
            'number' => $code,
            'shortCode' => $framework['labelFr'] . ' ' . $code,
            'shortCodeEn' => $framework['labelEn'] . ' ' . $code,
            'legacyCode' => $code,
            'sourceUrl' => trim($sourceUrl),
            'labelFr' => $labelFr,
            'labelEn' => trim($labelEn) ?: $labelFr,
            'descFr' => trim($descFr),
            'descEn' => trim($descEn) ?: trim($descFr),
            'appFr' => '',
            'appEn' => '',
        ];
    }

    return ['frameworks' => array_values($frameworks), 'items' => $items];
}

function attach_framework_details(array &$items, string $frameworkId, string $source): void
{
    $indexesByCode = [];
    foreach ($items as $index => $item) {
        if (($item['levelId'] ?? '') === $frameworkId) {
            $indexesByCode[(string)$item['legacyCode']] = $index;
        }
    }

    foreach (preg_split('/\R/u', $source) ?: [] as $line) {
        [$code, $kind, $orderRaw, $textFr, $textEn] = array_pad(explode("\t", (string)$line, 5), 5, '');
        $code = trim($code);
        $kind = trim($kind);
        $textFr = trim($textFr);
        $textEn = trim($textEn) ?: $textFr;
        if (!isset($indexesByCode[$code]) || $textFr === '') {
            continue;
        }
        $index = $indexesByCode[$code];
        if ($kind === 'description') {
            $items[$index]['descFr'] = $textFr;
            $items[$index]['descEn'] = $textEn;
            continue;
        }
        if (!in_array($kind, ['knowledge', 'skills', 'attitudes', 'basic', 'intermediate', 'advanced', 'highly_advanced'], true)) {
            continue;
        }
        $items[$index]['details'][] = [
            'kind' => $kind,
            'order' => max(1, (int)$orderRaw),
            'textFr' => $textFr,
            'textEn' => $textEn,
        ];
    }
}

function expand_greencomp_indicators(array $items): array
{
    $typeDefinitions = [
        'knowledge' => ['K', 'Connaissance', 'Knowledge'],
        'skills' => ['S', 'Aptitude', 'Skill'],
        'attitudes' => ['A', 'Attitude', 'Attitude'],
    ];
    $expandedItems = [];

    foreach ($items as $item) {
        if (($item['levelId'] ?? '') !== 'greencomp' || empty($item['details'])) {
            $expandedItems[] = $item;
            continue;
        }

        foreach ($item['details'] as $detail) {
            if (!isset($typeDefinitions[$detail['kind']])) {
                continue;
            }
            [$typeCode, $typeFr, $typeEn] = $typeDefinitions[$detail['kind']];
            $indicatorCode = sprintf(
                'GC%s.%s%02d',
                (string)$item['number'],
                $typeCode,
                max(1, (int)($detail['order'] ?? 1))
            );
            $indicator = $item;
            $indicator['id'] = 'competency:greencomp:' . $indicatorCode;
            $indicator['number'] = $indicatorCode;
            $indicator['shortCode'] = $indicatorCode;
            $indicator['shortCodeEn'] = $indicatorCode;
            $indicator['legacyCode'] = $indicatorCode;
            $indicator['descFr'] = trim((string)($detail['textFr'] ?? ''));
            $indicator['descEn'] = trim((string)($detail['textEn'] ?? $indicator['descFr'])) ?: $indicator['descFr'];
            $indicator['details'] = [];
            $indicator['isCompetencyIndicator'] = true;
            $indicator['parentNumber'] = $item['number'];
            $indicator['parentDescFr'] = $item['descFr'];
            $indicator['parentDescEn'] = $item['descEn'];
            $indicator['proficiencyFr'] = $typeFr;
            $indicator['proficiencyEn'] = $typeEn;
            $expandedItems[] = $indicator;
        }
    }

    return $expandedItems;
}

function expand_digcomp_statements(array $items): array
{
    $levelLabels = [
        'basic' => ['Niveau élémentaire', 'Basic level'],
        'intermediate' => ['Niveau intermédiaire', 'Intermediate level'],
        'advanced' => ['Niveau avancé', 'Advanced level'],
        'highly_advanced' => ['Niveau hautement avancé', 'Highly advanced level'],
    ];
    $expandedItems = [];

    foreach ($items as $item) {
        if (($item['levelId'] ?? '') !== 'digcomp' || empty($item['details'])) {
            $expandedItems[] = $item;
            continue;
        }

        foreach ($item['details'] as $detail) {
            $textFr = trim((string)($detail['textFr'] ?? ''));
            $textEn = trim((string)($detail['textEn'] ?? $textFr));
            if (
                !preg_match('/^(CS\d+\.\d+\.\d+)\s*·\s*(.+)$/u', $textFr, $matchFr)
                || !preg_match('/^(CS\d+\.\d+\.\d+)\s*·\s*(.+)$/u', $textEn, $matchEn)
                || $matchFr[1] !== $matchEn[1]
            ) {
                continue;
            }

            $statementCode = $matchFr[1];
            [$levelFr, $levelEn] = $levelLabels[$detail['kind']] ?? [$detail['kind'], $detail['kind']];
            $statement = $item;
            $statement['id'] = 'competency:digcomp:' . $statementCode;
            $statement['number'] = $statementCode;
            $statement['shortCode'] = $statementCode;
            $statement['shortCodeEn'] = $statementCode;
            $statement['legacyCode'] = $statementCode;
            $statement['descFr'] = $matchFr[2];
            $statement['descEn'] = $matchEn[2];
            $statement['details'] = [];
            $statement['isCompetencyStatement'] = true;
            $statement['parentNumber'] = $item['number'];
            $statement['parentDescFr'] = $item['descFr'];
            $statement['parentDescEn'] = $item['descEn'];
            $statement['proficiencyFr'] = $levelFr;
            $statement['proficiencyEn'] = $levelEn;
            $expandedItems[] = $statement;
        }
    }

    return $expandedItems;
}

function competency_search_text(array $item): string
{
    $values = [];
    array_walk_recursive($item, static function (mixed $value) use (&$values): void {
        if (is_scalar($value)) {
            $values[] = (string)$value;
        }
    });
    return mb_strtolower(implode(' ', $values), 'UTF-8');
}

function competency_domain_style(string $levelId, string $groupId, string $themeId): array
{
    $domainStyles = [
        ['bg' => '#fff7ed', 'border' => '#fdba74', 'text' => '#9a3412', 'active' => '#ffedd5', 'row' => '#fffdfa', 'alt' => '#fff7ed'],
        ['bg' => '#ecfeff', 'border' => '#67e8f9', 'text' => '#155e75', 'active' => '#cffafe', 'row' => '#fbfeff', 'alt' => '#ecfeff'],
        ['bg' => '#f5f3ff', 'border' => '#c4b5fd', 'text' => '#6d28d9', 'active' => '#ede9fe', 'row' => '#fdfcff', 'alt' => '#f5f3ff'],
        ['bg' => '#ecfdf5', 'border' => '#6ee7b7', 'text' => '#047857', 'active' => '#d1fae5', 'row' => '#fbfffd', 'alt' => '#ecfdf5'],
        ['bg' => '#fdf2f8', 'border' => '#f9a8d4', 'text' => '#be185d', 'active' => '#fce7f3', 'row' => '#fffafd', 'alt' => '#fdf2f8'],
    ];
    $domainIndexes = [
        'domaine-1' => 0, 'valeurs' => 0, 'information' => 0, 'fondements' => 0,
        'domaine-2' => 1, 'complexite' => 1, 'communication' => 1, 'usages' => 1,
        'domaine-3' => 2, 'avenirs' => 2, 'creation' => 2, 'enjeux' => 2,
        'domaine-4' => 3, 'action' => 3, 'protection' => 3,
        'domaine-5' => 4, 'problemes' => 4, 'environnement' => 4,
    ];
    $primaryGroupId = explode(':', $groupId, 2)[0];
    if ($levelId !== 'florimont' && isset($domainIndexes[$primaryGroupId])) {
        return $domainStyles[$domainIndexes[$primaryGroupId]];
    }

    return match ($themeId) {
        'approfondir' => ['bg' => '#ede9fe', 'border' => '#c4b5fd', 'text' => '#5b21b6', 'active' => '#ddd6fe', 'row' => '#fcfbff', 'alt' => '#f5f1ff'],
        'creer' => ['bg' => '#dcfce7', 'border' => '#86efac', 'text' => '#166534', 'active' => '#bbf7d0', 'row' => '#f8fff9', 'alt' => '#effcf3'],
        default => ['bg' => '#e0f2fe', 'border' => '#7dd3fc', 'text' => '#075985', 'active' => '#bae6fd', 'row' => '#f8fcff', 'alt' => '#eef9ff'],
    };
}

function competency_style_css(array $style): string
{
    return '--competency-bg:' . $style['bg']
        . ';--competency-border:' . $style['border']
        . ';--competency-text:' . $style['text']
        . ';--competency-active:' . $style['active']
        . ';--domain-row:' . $style['row']
        . ';--domain-alt:' . $style['alt'];
}

function collect_competency_references(mixed $value, array &$references): void
{
    if (!is_array($value)) {
        return;
    }

    foreach (['tools', 'activity_competencies', 'activity_tools'] as $key) {
        if (!isset($value[$key])) {
            continue;
        }
        $rawReferences = is_array($value[$key])
            ? $value[$key]
            : preg_split('/[;,]/', (string)$value[$key]);
        foreach ($rawReferences ?: [] as $reference) {
            if (is_string($reference) && trim($reference) !== '') {
                $references[$reference] = true;
            }
        }
    }

    foreach ($value as $child) {
        collect_competency_references($child, $references);
    }
}

$florimontCatalog = parse_competency_catalog(app_competency_catalog_source());
$additionalCatalog = parse_additional_competency_frameworks(app_competency_framework_catalog_source());
attach_framework_details($additionalCatalog['items'], 'greencomp', app_competency_greencomp_detail_source());
attach_framework_details($additionalCatalog['items'], 'digcomp', app_competency_digcomp_detail_source());
$additionalCatalog['items'] = expand_greencomp_indicators($additionalCatalog['items']);
$additionalCatalog['items'] = expand_digcomp_statements($additionalCatalog['items']);
$levels = [[
    'id' => 'florimont',
    'labelFr' => 'Florimont',
    'labelEn' => 'Florimont',
    'sourceUrl' => '',
]];
foreach ($additionalCatalog['frameworks'] as $framework) {
    $levels[] = $framework;
}
$items = [];
foreach ($florimontCatalog['items'] as $item) {
    $originalLevelId = $item['levelId'];
    $item['themeId'] = $originalLevelId;
    $item['groupId'] = $originalLevelId . ':' . $item['sectionRoman'] . ':' . $item['sectionFr'];
    $item['sectionFr'] = $item['levelFr'] . ' · ' . $item['sectionRoman'] . ' - ' . $item['sectionFr'];
    $item['sectionEn'] = $item['levelEn'] . ' · ' . $item['sectionRoman'] . ' - ' . $item['sectionEn'];
    $item['sectionRoman'] = '';
    $item['levelId'] = 'florimont';
    $item['levelFr'] = 'Florimont';
    $item['levelEn'] = 'Florimont';
    $items[] = $item;
}
array_push($items, ...$additionalCatalog['items']);
$referenceMap = [];
foreach ($items as $item) {
    foreach ([$item['id'], $item['shortCode'], $item['shortCodeEn'], $item['legacyCode'], $item['labelFr'], $item['labelEn']] as $reference) {
        $reference = trim((string)$reference);
        if ($reference !== '') {
            $referenceMap[mb_strtolower($reference, 'UTF-8')] = $item['id'];
        }
    }
}
$usedCompetencyIds = [];
$currentUser = current_user();
if ($currentUser) {
    $stmt = app_db()->prepare('SELECT document_json FROM learning_designs WHERE owner_user_id = ?');
    $stmt->execute([(int)$currentUser['id']]);
    foreach ($stmt->fetchAll() as $designRow) {
        $document = json_decode((string)($designRow['document_json'] ?? ''), true);
        if (!is_array($document)) {
            continue;
        }
        $references = [];
        collect_competency_references($document, $references);
        foreach (array_keys($references) as $reference) {
            $normalized = mb_strtolower(trim((string)$reference), 'UTF-8');
            if (isset($referenceMap[$normalized])) {
                $usedCompetencyIds[$referenceMap[$normalized]] = true;
            }
        }
    }
}
$levelCounts = [];
foreach ($items as $item) {
    $levelCounts[$item['levelId']] = ($levelCounts[$item['levelId']] ?? 0) + 1;
}
$sectionGroups = [];
foreach ($items as $item) {
    $sectionKey = $item['levelId'] . ':' . $item['groupId'];
    if (!isset($sectionGroups[$sectionKey])) {
        $sectionGroups[$sectionKey] = [
            'levelId' => $item['levelId'],
            'levelFr' => $item['levelFr'],
            'levelEn' => $item['levelEn'],
            'sectionFr' => $item['sectionFr'],
            'sectionEn' => $item['sectionEn'],
            'sectionRoman' => $item['sectionRoman'],
            'themeId' => $item['themeId'],
            'groupId' => $item['groupId'],
            'items' => [],
        ];
    }
    $sectionGroups[$sectionKey]['items'][] = $item;
}
$levelGroups = [];
foreach ($levels as $level) {
    $levelGroups[$level['id']] = [
        'levelId' => $level['id'],
        'levelFr' => $level['labelFr'],
        'levelEn' => $level['labelEn'],
        'sourceUrl' => $level['sourceUrl'] ?? '',
        'sections' => [],
        'count' => $levelCounts[$level['id']] ?? 0,
    ];
}
foreach ($sectionGroups as $sectionKey => $group) {
    $levelGroups[$group['levelId']]['sections'][$sectionKey] = $group;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title>Référentiels de compétences | Scénarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20261124-rem">
    <link rel="stylesheet" href="css/account-ui.css?v=20261124-rem">
    <link rel="stylesheet" href="css/account-pages.css?v=20261124-rem">
    <link rel="stylesheet" href="css/competencies.css?v=<?= hash_file('sha256', __DIR__ . '/css/competencies.css') ?>">
<?php render_refined_styles(); ?>
</head>
<body class="competencies-page">
<?php render_site_nav(); ?>
<main class="competencies-shell with-nav">
    <header class="competencies-header">
        <p class="competencies-kicker" data-i18n-fr="Documentation" data-i18n-en="Documentation">Documentation</p>
        <h1 class="competencies-title" data-i18n-fr="Référentiels de compétences" data-i18n-en="Competency frameworks">Référentiels de compétences</h1>
        <p class="competencies-subtitle" data-i18n-fr="Catalogue complet des huit cadres proposés dans le sélecteur de Scénarisation. Chaque cadre est organisé par domaines et renvoie à sa source de référence." data-i18n-en="Complete catalogue of the eight frameworks available in Scénarisation’s picker. Each framework is organised by domain and links to its reference source.">Catalogue complet des huit cadres proposés dans le sélecteur de Scénarisation. Chaque cadre est organisé par domaines et renvoie à sa source de référence.</p>
    </header>

    <section class="competencies-controls" aria-label="Filtres" data-i18n-attr="aria-label" data-i18n-fr="Filtres" data-i18n-en="Filters">
        <label class="sr-only" for="competency-search" data-i18n-fr="Rechercher une compétence" data-i18n-en="Search competencies">Rechercher une compétence</label>
        <input id="competency-search" class="competencies-search" type="search" placeholder="Rechercher…" data-i18n-attr="placeholder" data-i18n-fr="Rechercher…" data-i18n-en="Search…">
        <div class="competencies-filters" role="group" aria-label="Filtrer par cadre" data-i18n-attr="aria-label" data-i18n-fr="Filtrer par cadre" data-i18n-en="Filter by framework">
            <?php foreach ($levels as $level): ?>
                <?php $filterLabelEn = $level['id'] === 'crcn' ? 'CRCN' : $level['labelEn']; ?>
                <button class="competencies-filter-btn" type="button" data-level-filter="<?= h($level['id']) ?>" aria-pressed="false">
                    <span class="competencies-dot competencies-dot-<?= h($level['id']) ?>" aria-hidden="true"></span>
                    <span data-level-fr="<?= h($level['labelFr']) ?>" data-level-en="<?= h($filterLabelEn) ?>"><?= h($level['labelFr']) ?></span>
                    <span class="competencies-filter-count"><?= h((string)($levelCounts[$level['id']] ?? 0)) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="competencies-table-wrap">
        <table class="competencies-table">
            <colgroup>
                <col class="competencies-col-used">
                <col class="competencies-col-code">
                <col class="competencies-col-label">
                <col class="competencies-col-description">
            </colgroup>
            <tbody id="competencies-table-body">
                <?php foreach ($levelGroups as $levelGroup): ?>
                    <tr class="competencies-level-row" data-level-row="<?= h($levelGroup['levelId']) ?>" data-level="<?= h($levelGroup['levelId']) ?>">
                        <td colspan="4">
                            <div class="competencies-level-heading-row" id="framework-<?= h($levelGroup['levelId']) ?>">
                                <button class="competencies-level-toggle" type="button" aria-expanded="true" data-level-toggle="<?= h($levelGroup['levelId']) ?>">
                                    <span class="competencies-level-heading">
                                        <span class="competencies-dot competencies-dot-<?= h($levelGroup['levelId']) ?>" aria-hidden="true"></span>
                                        <span class="competencies-level-title" data-level-fr="<?= h($levelGroup['levelFr']) ?>" data-level-en="<?= h($levelGroup['levelEn']) ?>"><?= h($levelGroup['levelFr']) ?></span>
                                    </span>
                                    <span class="competencies-section-meta">
                                        <i class="fa-solid fa-chevron-down competencies-chevron" aria-hidden="true"></i>
                                    </span>
                                </button>
                                <?php if ($levelGroup['sourceUrl'] !== ''): ?>
                                    <?php
                                    $sourceUrlEn = $levelGroup['levelId'] === 'greencomp'
                                        ? str_replace(['/fr/publication-detail/', '/language-fr'], ['/en/publication-detail/', '/language-en'], $levelGroup['sourceUrl'])
                                        : $levelGroup['sourceUrl'];
                                    ?>
                                    <a class="competencies-framework-source" href="<?= h($levelGroup['sourceUrl']) ?>" target="_blank" rel="noopener noreferrer" data-i18n-attr="href" data-i18n-fr="<?= h($levelGroup['sourceUrl']) ?>" data-i18n-en="<?= h($sourceUrlEn) ?>"><span data-i18n-fr="Consulter la source" data-i18n-en="View source">Consulter la source</span> <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php foreach ($levelGroup['sections'] as $sectionKey => $group): ?>
                        <?php $sectionDomainStyle = competency_domain_style($group['levelId'], $group['groupId'], $group['themeId']); ?>
                        <tr class="competencies-section-row domain-colored" style="<?= h(competency_style_css($sectionDomainStyle)) ?>" data-section-key="<?= h($sectionKey) ?>" data-level="<?= h($group['levelId']) ?>">
                            <td colspan="4">
                                <button class="competencies-section-toggle" type="button" aria-expanded="true" data-section-toggle="<?= h($sectionKey) ?>">
                                    <span class="competencies-section-heading">
                                        <span class="competencies-section-title" data-i18n-fr="<?= h($group['sectionFr']) ?>" data-i18n-en="<?= h($group['sectionEn']) ?>"><?= h($group['sectionFr']) ?></span>
                                    </span>
                                    <span class="competencies-section-meta">
                                        <i class="fa-solid fa-chevron-down competencies-chevron" aria-hidden="true"></i>
                                    </span>
                                </button>
                            </td>
                        </tr>
                        <tr class="competencies-column-row" data-section="<?= h($sectionKey) ?>" data-level="<?= h($group['levelId']) ?>">
                            <th scope="col" data-i18n-fr="Utilisée" data-i18n-en="Used">Utilisée</th>
                            <th scope="col" data-i18n-fr="Code" data-i18n-en="Code">Code</th>
                            <th scope="col" data-i18n-fr="Compétence" data-i18n-en="Competency">Compétence</th>
                            <th scope="col" data-i18n-fr="Repères observables" data-i18n-en="Observable indicators">Repères observables</th>
                        </tr>
                        <?php $previousFrameworkParent = null; ?>
                        <?php foreach ($group['items'] as $index => $item): ?>
                            <?php $itemDomainStyle = competency_domain_style($item['levelId'], $item['groupId'], $item['themeId']); ?>
                            <?php
                            $isGranularCompetency = !empty($item['isCompetencyStatement']) || !empty($item['isCompetencyIndicator']);
                            $competencyLabelFr = $isGranularCompetency
                                ? $item['proficiencyFr']
                                : (string)$item['number'] . '. ' . $item['labelFr'];
                            $competencyLabelEn = $isGranularCompetency
                                ? $item['proficiencyEn']
                                : (string)$item['number'] . '. ' . $item['labelEn'];
                            $frameworkParentKey = $isGranularCompetency
                                ? $sectionKey . ':' . $item['parentNumber']
                                : '';
                            $descriptionFr = str_replace(' | ', ' · ', $item['descFr']);
                            $descriptionEn = str_replace(' | ', ' · ', $item['descEn']);
                            $pixThemesFr = '';
                            $pixThemesEn = '';
                            if ($item['levelId'] === 'pix' && str_contains($descriptionFr, ' Thématiques associées : ')) {
                                [$descriptionFr, $pixThemesFr] = explode(' Thématiques associées : ', $descriptionFr, 2);
                            }
                            if ($item['levelId'] === 'pix' && str_contains($descriptionEn, ' Associated topics: ')) {
                                [$descriptionEn, $pixThemesEn] = explode(' Associated topics: ', $descriptionEn, 2);
                            }
                            ?>
                            <?php if ($frameworkParentKey !== '' && $previousFrameworkParent !== $frameworkParentKey): ?>
                                <tr class="competencies-parent-row domain-colored" style="<?= h(competency_style_css($itemDomainStyle)) ?>" data-parent-key="<?= h($frameworkParentKey) ?>" data-section="<?= h($sectionKey) ?>" data-level="<?= h($item['levelId']) ?>">
                                    <td colspan="4">
                                        <span class="competencies-parent-title">
                                            <span class="competencies-parent-code"><?= h((string)$item['parentNumber']) ?>.</span><span data-i18n-fr="<?= h($item['labelFr']) ?>" data-i18n-en="<?= h($item['labelEn']) ?>"><?= h($item['labelFr']) ?></span>
                                        </span>
                                    </td>
                                </tr>
                                <?php $previousFrameworkParent = $frameworkParentKey; ?>
                            <?php endif; ?>
                            <tr class="competencies-item-row domain-colored" style="<?= h(competency_style_css($itemDomainStyle)) ?>" data-row-shade="<?= $index % 2 === 1 ? 'alt' : 'base' ?>" data-section="<?= h($sectionKey) ?>" data-level="<?= h($item['levelId']) ?>" data-parent-group="<?= h($frameworkParentKey) ?>" data-search="<?= h(competency_search_text($item)) ?>">
                                <td class="competencies-used-cell">
                                    <?php if ($currentUser): ?>
                                        <input class="competencies-used-check" type="checkbox" disabled <?= isset($usedCompetencyIds[$item['id']]) ? 'checked' : '' ?> aria-label="<?= h('Compétence utilisée : ' . $item['legacyCode']) ?>" data-i18n-attr="aria-label" data-i18n-fr="<?= h('Compétence utilisée : ' . $item['legacyCode']) ?>" data-i18n-en="<?= h('Used competency: ' . $item['legacyCode']) ?>">
                                    <?php else: ?>
                                        <span class="competencies-used-empty" aria-label="Connexion requise" data-i18n-attr="aria-label" data-i18n-fr="Connexion requise" data-i18n-en="Sign-in required">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="competency-code" title="<?= h($item['shortCode']) ?>" data-i18n-attr="title" data-i18n-fr="<?= h($item['shortCode']) ?>" data-i18n-en="<?= h($item['shortCodeEn']) ?>"><?= h($item['legacyCode']) ?></span></td>
                                <td class="competencies-label">
                                    <?php if ($isGranularCompetency): ?>
                                        <span class="competency-proficiency" data-i18n-fr="<?= h($competencyLabelFr) ?>" data-i18n-en="<?= h($competencyLabelEn) ?>"><?= h($competencyLabelFr) ?></span>
                                    <?php else: ?>
                                        <span data-i18n-fr="<?= h($competencyLabelFr) ?>" data-i18n-en="<?= h($competencyLabelEn) ?>"><?= h($competencyLabelFr) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="competencies-description">
                                    <span data-i18n-fr="<?= h($descriptionFr) ?>" data-i18n-en="<?= h($descriptionEn) ?>"><?= h($descriptionFr) ?></span>
                                    <?php if (in_array($item['levelId'], ['per-romand', 'crcn'], true) && !empty($item['sourceUrl'])): ?>
                                        <a class="competencies-framework-source" href="<?= h($item['sourceUrl']) ?>" target="_blank" rel="noopener noreferrer">Source <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                                    <?php endif; ?>
                                    <?php if ($pixThemesFr !== '' || $pixThemesEn !== ''): ?>
                                        <span class="competencies-associated-themes">
                                            <strong data-i18n-fr="Thématiques associées :" data-i18n-en="Associated topics:">Thématiques associées :</strong>
                                            <span data-i18n-fr="<?= h($pixThemesFr) ?>" data-i18n-en="<?= h($pixThemesEn) ?>"><?= h($pixThemesFr) ?></span>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['details'])): ?>
                                        <?php
                                        $detailsByKind = ['knowledge' => [], 'skills' => [], 'attitudes' => [], 'basic' => [], 'intermediate' => [], 'advanced' => [], 'highly_advanced' => []];
                                        foreach ($item['details'] as $detail) {
                                            $detailsByKind[$detail['kind']][] = $detail;
                                        }
                                        $detailKindLabels = $item['levelId'] === 'greencomp'
                                            ? [
                                                'knowledge' => ['Connaissances', 'Knowledge'],
                                                'skills' => ['Aptitudes', 'Skills'],
                                                'attitudes' => ['Attitudes', 'Attitudes'],
                                            ]
                                            : ($item['levelId'] === 'digcomp' ? [
                                                'basic' => ['Niveau élémentaire', 'Basic level'],
                                                'intermediate' => ['Niveau intermédiaire', 'Intermediate level'],
                                                'advanced' => ['Niveau avancé', 'Advanced level'],
                                                'highly_advanced' => ['Niveau hautement avancé', 'Highly advanced level'],
                                            ] : [
                                                'knowledge' => ['Savoirs', 'Knowledge'],
                                                'skills' => ['Savoir-faire', 'Skills'],
                                                'attitudes' => ['Attitudes', 'Attitudes'],
                                            ]);
                                        ?>
                                        <div class="competencies-details">
                                            <?php foreach ($detailKindLabels as $kind => [$kindFr, $kindEn]): ?>
                                                <?php if ($detailsByKind[$kind]): ?>
                                                    <div class="competencies-details-group">
                                                        <strong data-i18n-fr="<?= h($kindFr) ?>" data-i18n-en="<?= h($kindEn) ?>"><?= h($kindFr) ?></strong>
                                                        <ul>
                                                            <?php foreach ($detailsByKind[$kind] as $detail): ?>
                                                                <li data-i18n-fr="<?= h($detail['textFr']) ?>" data-i18n-en="<?= h($detail['textEn']) ?>"><?= h($detail['textFr']) ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p id="competencies-empty" class="competencies-empty" data-i18n-fr="Aucune compétence ne correspond à ce filtre." data-i18n-en="No competency matches this filter.">Aucune compétence ne correspond à ce filtre.</p>
</main>
<?php render_site_footer(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var activeLevel = 'all';
    var searchInput = document.getElementById('competency-search');
    var itemRows = Array.prototype.slice.call(document.querySelectorAll('.competencies-item-row'));
    var sectionRows = Array.prototype.slice.call(document.querySelectorAll('.competencies-section-row'));
    var parentRows = Array.prototype.slice.call(document.querySelectorAll('.competencies-parent-row'));
    var levelRows = Array.prototype.slice.call(document.querySelectorAll('.competencies-level-row'));
    var columnRows = Array.prototype.slice.call(document.querySelectorAll('.competencies-column-row'));
    var empty = document.getElementById('competencies-empty');
    var filterButtons = Array.prototype.slice.call(document.querySelectorAll('[data-level-filter]'));
    var sectionToggles = Array.prototype.slice.call(document.querySelectorAll('[data-section-toggle]'));
    var levelToggles = Array.prototype.slice.call(document.querySelectorAll('[data-level-toggle]'));
    var collapsedSections = new Set();
    var collapsedLevels = new Set();

    function levelFromHash() {
        var match = window.location.hash.match(/^#framework-([a-z0-9-]+)$/i);
        if (!match) return 'all';
        return filterButtons.some(function (button) {
            return button.dataset.levelFilter === match[1];
        }) ? match[1] : 'all';
    }

    function syncFilterButtons() {
        filterButtons.forEach(function (button) {
            var isActive = button.dataset.levelFilter === activeLevel;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function syncFrameworkHash() {
        var hash = activeLevel === 'all' ? '' : '#framework-' + activeLevel;
        var url = window.location.pathname + window.location.search + hash;
        window.history.replaceState(null, '', url);
    }

    function normalize(value) {
        return String(value || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function applyLanguageTypography(value, lang) {
        var text = String(value || '').replace(/,\s*(…|\.{3})/g, '$1');
        return text.replace(/[ \u00a0\u202f]*([:;]|[!?]+)(?!\/\/)/g, function (match, punctuation, offset, source) {
            var previous = source.charAt(offset - 1);
            var next = source.charAt(offset + match.length);
            if (punctuation === ':' && /\d/.test(previous) && /\d/.test(next)) {
                return punctuation;
            }
            if (lang === 'en') {
                return punctuation;
            }
            return (punctuation === ':' ? '\u00a0' : '\u202f') + punctuation;
        });
    }

    function filterRows() {
        var query = normalize(searchInput ? searchInput.value : '');
        var visibleCount = 0;
        var sectionMatches = {};
        var parentMatches = {};
        var levelMatches = {};

        itemRows.forEach(function (row) {
            var matchesLevel = activeLevel === 'all' || row.dataset.level === activeLevel;
            var matchesSearch = !query || normalize(row.dataset.search).indexOf(query) !== -1;
            var matches = matchesLevel && matchesSearch;
            row.dataset.matchesFilter = matches ? 'true' : 'false';
            if (matches) {
                visibleCount += 1;
                sectionMatches[row.dataset.section] = (sectionMatches[row.dataset.section] || 0) + 1;
                if (row.dataset.parentGroup) {
                    parentMatches[row.dataset.parentGroup] = (parentMatches[row.dataset.parentGroup] || 0) + 1;
                }
                levelMatches[row.dataset.level] = (levelMatches[row.dataset.level] || 0) + 1;
            }
        });

        levelRows.forEach(function (row) {
            var count = levelMatches[row.dataset.levelRow] || 0;
            row.hidden = count === 0;
        });

        sectionRows.forEach(function (row) {
            var count = sectionMatches[row.dataset.sectionKey] || 0;
            row.hidden = count === 0 || collapsedLevels.has(row.dataset.level);
        });

        parentRows.forEach(function (row) {
            var count = parentMatches[row.dataset.parentKey] || 0;
            row.hidden = count === 0 || collapsedLevels.has(row.dataset.level) || collapsedSections.has(row.dataset.section);
        });

        columnRows.forEach(function (row) {
            var count = sectionMatches[row.dataset.section] || 0;
            row.hidden = count === 0 || collapsedLevels.has(row.dataset.level) || collapsedSections.has(row.dataset.section);
        });

        itemRows.forEach(function (row) {
            var matches = row.dataset.matchesFilter === 'true';
            row.hidden = !matches || collapsedLevels.has(row.dataset.level) || collapsedSections.has(row.dataset.section);
        });
        if (empty) {
            empty.classList.toggle('is-visible', visibleCount === 0);
        }
    }

    levelToggles.forEach(function (button) {
        button.addEventListener('click', function () {
            var level = button.dataset.levelToggle;
            var isCollapsed = collapsedLevels.has(level);
            if (isCollapsed) {
                collapsedLevels.delete(level);
            } else {
                collapsedLevels.add(level);
            }
            button.setAttribute('aria-expanded', isCollapsed ? 'true' : 'false');
            filterRows();
        });
    });

    sectionToggles.forEach(function (button) {
        button.addEventListener('click', function () {
            var section = button.dataset.sectionToggle;
            var isCollapsed = collapsedSections.has(section);
            if (isCollapsed) {
                collapsedSections.delete(section);
            } else {
                collapsedSections.add(section);
            }
            button.setAttribute('aria-expanded', isCollapsed ? 'true' : 'false');
            filterRows();
        });
    });

    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var selectedLevel = button.dataset.levelFilter || 'all';
            activeLevel = activeLevel === selectedLevel ? 'all' : selectedLevel;
            syncFilterButtons();
            syncFrameworkHash();
            filterRows();
        });
    });

    window.addEventListener('hashchange', function () {
        activeLevel = levelFromHash();
        syncFilterButtons();
        filterRows();
    });

    if (searchInput) {
        searchInput.addEventListener('input', filterRows);
    }

    function applyPageLanguage(lang) {
        document.documentElement.lang = lang === 'en' ? 'en' : 'fr';
        document.title = lang === 'en' ? 'Competency frameworks | Scénarisation' : 'Référentiels de compétences | Scénarisation';
        document.querySelectorAll('[data-i18n-fr]').forEach(function (el) {
            var value = lang === 'en' ? el.dataset.i18nEn : el.dataset.i18nFr;
            if (!value) return;
            value = applyLanguageTypography(value, lang);
            var attrs = (el.dataset.i18nAttr || '').split(',').map(function (attr) {
                return attr.trim();
            }).filter(Boolean);
            if (attrs.length) {
                attrs.forEach(function (attr) {
                    el.setAttribute(attr, value);
                });
            } else {
                el.textContent = value;
            }
        });
        document.querySelectorAll('[data-level-fr]').forEach(function (el) {
            var value = lang === 'en' ? el.dataset.levelEn : el.dataset.levelFr;
            el.textContent = applyLanguageTypography(value, lang);
        });
    }

    var lang = 'fr';
    try {
        lang = localStorage.getItem('learningDesignerLang') || 'fr';
    } catch (error) {
        lang = 'fr';
    }
    applyPageLanguage(lang);

    activeLevel = levelFromHash();
    syncFilterButtons();
    filterRows();

    var langSelect = document.getElementById('lang-select');
    if (langSelect) {
        langSelect.addEventListener('change', function () {
            applyPageLanguage(langSelect.value);
        });
    }
});
</script>
</body>
</html>
