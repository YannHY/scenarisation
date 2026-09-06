<?php
declare(strict_types=1);

/** Null owner exports all accounts; callers must enforce administrator access. */
function app_export_scenarios(PDO $db, ?int $ownerId, string $path): void
{
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de créer l’archive.');
    }
    try {
        if ($ownerId === null) {
            $stmt = $db->query('SELECT d.id, d.title, d.document_json, d.owner_user_id, u.username
                FROM learning_designs d LEFT JOIN users u ON u.id = d.owner_user_id
                ORDER BY d.owner_user_id, d.id');
        } else {
            $stmt = $db->prepare('SELECT id, title, document_json FROM learning_designs WHERE owner_user_id = ? ORDER BY id');
            $stmt->execute([$ownerId]);
        }
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Prefix with the database ID to preserve scenarios with identical titles.
            $title = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', (string)$row['title']);
            $title = mb_substr(trim((string)$title, '-'), 0, 80) ?: 'scenario';
            $folder = '';
            if ($ownerId === null) {
                $username = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', (string)($row['username'] ?? ''));
                $username = mb_substr(trim((string)$username, '-'), 0, 80) ?: 'utilisateur';
                $folder = (int)$row['owner_user_id'] . '-' . $username . '/';
            }
            if (!$zip->addFromString($folder . $row['id'] . '-' . $title . '.json', (string)$row['document_json'])) {
                throw new RuntimeException('Impossible d’ajouter un scénario à l’archive.');
            }
            $count++;
        }
        if ($count === 0) {
            if (!$zip->addFromString('README.txt', "Aucun scénario enregistré.\nNo saved scenarios.\n")) {
                throw new RuntimeException('Impossible de créer l’archive vide.');
            }
        }
    } finally {
        if (!$zip->close()) {
            throw new RuntimeException('Impossible de finaliser l’archive.');
        }
    }
}
