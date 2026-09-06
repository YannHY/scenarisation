<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/scenario-export.php';

$exportAll = ($_GET['scope'] ?? '') === 'all';
$user = $exportAll ? require_admin_page() : require_login_page();
header('Cache-Control: no-store');
$path = false;
try {
    $path = tempnam(sys_get_temp_dir(), 'scenarios-');
    if ($path === false) {
        throw new RuntimeException('Impossible de créer le fichier temporaire.');
    }
    app_export_scenarios(app_db(), $exportAll ? null : (int)$user['id'], $path);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . ($exportAll ? 'tous-les-scenarios-' : 'scenarios-') . date('Y-m-d') . '.zip"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($path));
    readfile($path);
} catch (Throwable $error) {
    error_log('Scenario export: ' . $error->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Impossible d’exporter les scénarios. Veuillez réessayer plus tard.';
} finally {
    if ($path !== false && is_file($path)) {
        unlink($path);
    }
}
