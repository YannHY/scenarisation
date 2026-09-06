<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/scenario-export.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)');
$db->exec("INSERT INTO users VALUES (10, '../Équipe / A'), (20, '../Équipe / A')");
$db->exec('CREATE TABLE learning_designs (id INTEGER PRIMARY KEY, owner_user_id INTEGER, title TEXT, document_json TEXT, is_published INTEGER)');
$insert = $db->prepare('INSERT INTO learning_designs VALUES (?, ?, ?, ?, ?)');
$document = '{"meta":{"name":"Énergie"},"sessions":[{"id":"s1","activities":[]}]}';
$insert->execute([1, 10, '../Énergie / séance', $document, 0]);
$insert->execute([2, 10, '../Énergie / séance', $document, 1]);
$insert->execute([3, 20, 'Private', '{"secret":true}', 1]);
$path = tempnam(sys_get_temp_dir(), 'scenario-export-test-');
function check_export(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
try {
    app_export_scenarios($db, 10, $path);
    $zip = new ZipArchive();
    check_export($zip->open($path) === true, 'Export is a readable ZIP');
    check_export($zip->numFiles === 2, 'Both published and unpublished scenarios, and only the owner’s scenarios, are included');
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        check_export(!str_contains($name, '/') && str_ends_with($name, '.json'), 'Safe JSON filename');
        check_export($zip->getFromIndex($i) === $document, 'Full document preserved for reimport');
        $names[] = $name;
    }
    check_export(count(array_unique($names)) === 2, 'Duplicate titles do not overwrite each other');
    $zip->close();
    app_export_scenarios($db, null, $path);
    check_export($zip->open($path) === true, 'Administrator export is a readable ZIP');
    check_export($zip->numFiles === 3, 'Administrator export includes all scenarios');
    check_export($zip->getFromName('10-Équipe-A/1-Énergie-séance.json') === $document, 'First user folder preserves full document');
    check_export($zip->getFromName('10-Équipe-A/2-Énergie-séance.json') === $document, 'Duplicate titles remain distinct in user folder');
    check_export($zip->getFromName('20-Équipe-A/3-Private.json') === '{"secret":true}', 'Duplicate usernames have separate safe folders');
    $zip->close();
    app_export_scenarios($db, 30, $path);
    check_export($zip->open($path) === true, 'Empty account produces a valid archive');
    check_export($zip->numFiles === 1 && $zip->getNameIndex(0) === 'README.txt', 'Empty account contains an explanation');
    $zip->close();
    echo "Scenario export checks passed\n";
} finally {
    unlink($path);
}
