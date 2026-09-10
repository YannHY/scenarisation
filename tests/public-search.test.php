<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/public-search.php';

function check_search(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE learning_designs (id INTEGER PRIMARY KEY, title TEXT, document_json TEXT, share_token TEXT, is_published INTEGER, is_listed INTEGER, listed_at TEXT)');
$insert = $db->prepare('INSERT INTO learning_designs VALUES (?, ?, ?, ?, ?, ?, ?)');
$document = json_encode(['meta' => ['name' => 'Énergie solaire'], 'sessions' => [['activities' => [['instructions' => 'Comparer les panneaux photovoltaïques <script>alert(1)</script> & discuter']]]]], JSON_UNESCAPED_UNICODE);
foreach ([[1,1,1,'public'], [2,1,0,'link-only'], [3,0,1,'private'], [4,1,1,null], [5,1,1,'']] as [$id,$published,$listed,$token]) {
    $insert->execute([$id, 'Test', $document, $token, $published, $listed, '2026-09-10']);
}
$result = search_public_designs($db, 'energie');
check_search($result['count'] === 1, 'Only listed, published scenarios with a token are searchable');
check_search($result['results'][0]['url'] === 'view.php?token=public', 'Public result links to its scenario');
check_search(search_public_designs($db, 'PHOTOVOLTAIQUES')['count'] === 1, 'Activity instructions match without case or accents');
check_search(search_public_designs($db, 'energie impossible')['count'] === 0, 'All query terms must match');
check_search(search_public_designs($db, 'e')['count'] === 0, 'Short queries are ignored');
check_search(!str_contains($result['results'][0]['excerpt'], '<script>'), 'Excerpts contain no executable HTML');
check_search(str_contains($result['results'][0]['excerpt'], '&amp;'), 'Excerpts are HTML escaped');
for ($id = 10; $id < 25; $id++) $insert->execute([$id, 'Test', $document, 'public-' . $id, 1, 1, '2026-09-10']);
$first = search_public_designs($db, 'energie');
$second = search_public_designs($db, 'energie', 12);
check_search($first['count'] === 16 && count($first['results']) === 12 && count($second['results']) === 4, 'Pagination preserves the full count');
check_search(!array_intersect(array_column($first['results'], 'url'), array_column($second['results'], 'url')), 'Pages have no duplicate results');
$db->exec('UPDATE learning_designs SET is_listed = 0');
check_search(search_public_designs($db, 'energie')['count'] === 0, 'Removing a scenario from the catalogue immediately removes it from search');
echo "Public search checks passed\n";
