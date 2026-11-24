<?php
declare(strict_types=1);

// Collaboration bêta de l’app native : partage entre comptes, droits d’écriture et présence.
$fixture = sys_get_temp_dir() . '/scenarisation-collaboration-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700, true);
putenv('APP_DB_DSN=sqlite:' . $fixture . '/test.sqlite');
putenv('APP_BASE_URL=https://scenarisation.eu');
require_once __DIR__ . '/../lib/native-api.php';
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function expect_error(callable $operation, string $code, int $status): void {
    try { $operation(); } catch (NativeRequestError $error) {
        check($error->oauthError === $code && $error->status === $status,
            "Unexpected native error: {$error->oauthError} ({$error->status}), expected $code ($status)");
        return;
    }
    throw new RuntimeException('Expected error: ' . $code);
}
function scenario(string $uuid, string $name = 'Scénario partagé'): array {
    return ['client_uuid' => $uuid, 'title' => $name,
        'document' => json_decode('{"meta":{"name":"' . $name . '"},"sessions":[{"id":"m-1","activities":[{"id":"a-1","duration":10}]}]}')];
}
function presence(PDO $db, int $user, int $design, string $client, mixed $focus = null, bool $leave = false): array {
    $input = ['design_id' => $design, 'client_id' => $client, 'focus' => $focus];
    if ($leave) $input['leave'] = true;
    return native_api_presence($db, $user, $input)['presence'];
}
function rows(PDO $db, string $table, int $design): int {
    $query = $db->prepare("SELECT COUNT(*) FROM $table WHERE design_id = ?");
    $query->execute([$design]);
    return (int)$query->fetchColumn();
}

const CLIENT_A = '1f0e4f6a-3c2b-4d5e-8f70-112233445566';
const CLIENT_B = '2a1b3c4d-5e6f-4a7b-9c8d-0e1f2a3b4c5d';
const CLIENT_C = '3b2c4d5e-6f70-4b8c-a9d0-e1f2a3b4c5d6';

try {
    $db = app_db();
    $db->exec("INSERT INTO users (id, username, email, password_hash, role, status, email_verified_at) VALUES
        (1,'Owner','owner@example.test','test','designer','active',CURRENT_TIMESTAMP),
        (2,'Colleague','Colleague@Example.test','test','designer','active',CURRENT_TIMESTAMP),
        (3,'Unverified','unverified@example.test','test','designer','active',NULL),
        (4,'Disabled','disabled@example.test','test','designer','disabled',CURRENT_TIMESTAMP),
        (5,'Outsider','outsider@example.test','test','designer','active',CURRENT_TIMESTAMP),
        (6,'Béatrice','beatrice@example.test','test','designer','active',CURRENT_TIMESTAMP)");

    // Schéma 11, migration additive et idempotente.
    check((int)$db->query('SELECT schema_version FROM app_schema_meta')->fetchColumn() === 11, 'Migration reaches schema 11');
    ensure_native_collaboration_schema($db); ensure_native_app_schema($db); ensure_app_schema($db);
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    check(in_array('design_collaborators', $tables, true) && in_array('design_presence', $tables, true), 'Collaboration tables exist');
    check((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 6, 'Repeated migrations preserve accounts');

    $id = native_api_save($db, 1, scenario('a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d'))['design']['id'];
    $owned = native_api_list($db, 1)['items'][0];
    check($owned['role'] === 'owner' && $owned['owner'] === 'Owner' && $owned['shared'] === false, 'Private scenarios are listed as owned and unshared');
    check(native_design_access($db, 1, $id) === ['owner_user_id' => 1, 'role' => 'owner', 'owner_name' => 'Owner', 'revision' => 1],
        'Access helper identifies the owner');
    check(native_design_access($db, 2, $id) === null && native_design_access($db, 1, 999) === null, 'Non-members and missing scenarios have no access');

    // Invitations.
    $sharing = native_api_share($db, 1, ['design_id' => $id, 'email' => '  colleague@EXAMPLE.test '])['sharing'];
    check($sharing['designId'] === $id && $sharing['canManage'] === true, 'Owner manages the sharing');
    check(array_column($sharing['members'], 'username') === ['Owner', 'Colleague']
        && array_column($sharing['members'], 'role') === ['owner', 'editor'], 'Owner is listed first, then invited teachers');
    check($sharing['members'][1]['email'] === 'Colleague@Example.test', 'Owner sees member addresses');
    check(count(native_api_share($db, 1, ['design_id' => $id, 'email' => 'colleague@example.test'])['sharing']['members']) === 2,
        'Repeated invitations are idempotent');
    check(native_design_access($db, 2, $id)['role'] === 'editor', 'Invited teachers become editors');
    foreach (['nobody@example.test', 'unverified@example.test', 'disabled@example.test'] as $email) {
        expect_error(fn() => native_api_share($db, 1, ['design_id' => $id, 'email' => $email]), 'unknown_user', 422);
    }
    expect_error(fn() => native_api_share($db, 1, ['design_id' => $id, 'email' => 'pas-une-adresse']), 'invalid_request', 422);
    expect_error(fn() => native_api_share($db, 1, ['design_id' => $id, 'email' => 'OWNER@example.test']), 'invalid_request', 422);
    expect_error(fn() => native_api_share($db, 1, ['design_id' => (string)$id, 'email' => 'outsider@example.test']), 'invalid_request', 422);
    expect_error(fn() => native_api_share($db, 2, ['design_id' => $id, 'email' => 'outsider@example.test']), 'forbidden', 403);
    expect_error(fn() => native_api_share($db, 5, ['design_id' => $id, 'email' => 'outsider@example.test']), 'not_found', 404);
    $refusal = null;
    try { native_api_share($db, 1, ['design_id' => $id, 'email' => 'nobody@example.test']); }
    catch (NativeRequestError $error) { $refusal = $error->getMessage(); }
    check($refusal === 'Aucun compte vérifié de Scénarisation n’utilise cette adresse.', 'Unknown addresses get a readable message');

    // Listes et lecture.
    $colleagueItems = native_api_list($db, 2)['items'];
    check(count($colleagueItems) === 1 && $colleagueItems[0]['id'] === $id && $colleagueItems[0]['role'] === 'editor'
        && $colleagueItems[0]['owner'] === 'Owner' && $colleagueItems[0]['shared'] === true, 'Shared scenarios appear in the collaborator list');
    check(native_api_list($db, 1)['items'][0]['shared'] === true, 'Owner sees the scenario as shared');
    check(native_api_list($db, 5)['items'] === [], 'Other accounts do not see the scenario');
    check(native_api_list($db, 2)['deleted'] === [], 'Collaborators receive no owner tombstones');
    $remote = native_api_get($db, 2, $id)['design'];
    check($remote['role'] === 'editor' && $remote['owner'] === 'Owner' && $remote['shared'] === true
        && $remote['document']->meta->name === 'Scénario partagé', 'Collaborators download the shared scenario');
    check(native_api_get($db, 1, $id)['design']['role'] === 'owner', 'Owner downloads keep the owner role');
    expect_error(fn() => native_api_get($db, 5, $id), 'not_found', 404);
    $seen = native_api_collaborators($db, 2, $id)['sharing'];
    check($seen['canManage'] === false && !array_key_exists('email', $seen['members'][0]) && !array_key_exists('email', $seen['members'][1]),
        'Collaborators see members without their addresses');
    expect_error(fn() => native_api_collaborators($db, 5, $id), 'not_found', 404);

    // Écritures d’un enseignant invité.
    $edit = scenario('d5a85cd6-2636-4f00-860d-71dcb61ce479', 'Version de la collègue') + ['design_id' => $id, 'expected_revision' => 1];
    $saved = native_api_save($db, 2, $edit)['design'];
    check($saved['id'] === $id && $saved['revision'] === 2 && $saved['title'] === 'Version de la collègue', 'Collaborators save with conditional revisions');
    check((int)$db->query("SELECT owner_user_id FROM learning_designs WHERE id = $id")->fetchColumn() === 1, 'Saving never transfers ownership');
    expect_error(fn() => native_api_save($db, 2, $edit), 'conflict', 409);
    expect_error(fn() => native_api_save($db, 5, ['expected_revision' => 2] + $edit), 'not_found', 404);
    $own = native_api_save($db, 2, scenario('6f1c2c7e-58a4-4c3b-9d2e-1a0b6c9f4e21', 'Brouillon de la collègue'))['design'];
    $roles = array_column(native_api_list($db, 2)['items'], 'role', 'id');
    check($roles === [$own['id'] => 'owner', $id => 'editor'], 'Collaborators keep their own scenarios next to shared ones');
    check((int)$db->query("SELECT owner_user_id FROM learning_designs WHERE id = {$own['id']}")->fetchColumn() === 2, 'Creations stay owned by their author');
    expect_error(fn() => native_api_delete($db, 2, ['design_id' => $id, 'expected_revision' => 2]), 'forbidden', 403);
    expect_error(fn() => native_api_delete($db, 5, ['design_id' => $id, 'expected_revision' => 2]), 'not_found', 404);
    check(native_api_get($db, 1, $id)['design']['revision'] === 2, 'Refused deletions keep the scenario');

    // Consultation seule.
    $members = native_api_share($db, 1, ['design_id' => $id, 'email' => 'beatrice@example.test', 'role' => 'viewer'])['sharing']['members'];
    check(array_column($members, 'role', 'id') == [1 => 'owner', 2 => 'editor', 6 => 'viewer'], 'Teachers can be invited to view only');
    check(native_design_access($db, 6, $id)['role'] === 'viewer', 'Viewers have read access');
    check(array_column(native_api_list($db, 6)['items'], 'role', 'id') === [$id => 'viewer'], 'Viewers see their role in the list');
    check(native_api_get($db, 6, $id)['design']['role'] === 'viewer', 'Viewers download the scenario with their role');
    expect_error(fn() => native_api_save($db, 6, ['expected_revision' => 2] + $edit), 'forbidden', 403);
    expect_error(fn() => native_api_delete($db, 6, ['design_id' => $id, 'expected_revision' => 2]), 'forbidden', 403);
    check(native_api_get($db, 1, $id)['design']['revision'] === 2, 'Viewers change nothing');
    check(presence($db, 6, $id, CLIENT_C)['shared'] === true, 'Viewers signal their presence');
    native_api_share($db, 1, ['design_id' => $id, 'email' => 'beatrice@example.test']);
    check(native_design_access($db, 6, $id)['role'] === 'viewer', 'A repeated invitation without a role keeps the rights');
    expect_error(fn() => native_api_share($db, 1, ['design_id' => $id, 'email' => 'beatrice@example.test', 'role' => 'owner']), 'invalid_request', 422);
    $changed = native_api_collaborator_role($db, 1, ['design_id' => $id, 'user_id' => 6, 'role' => 'editor'])['sharing'];
    check(array_column($changed['members'], 'role', 'id')[6] === 'editor' && native_design_access($db, 6, $id)['role'] === 'editor',
        'Owner lets a viewer edit');
    native_api_share($db, 1, ['design_id' => $id, 'email' => 'beatrice@example.test', 'role' => 'viewer']);
    check(native_design_access($db, 6, $id)['role'] === 'viewer', 'A repeated invitation with a role changes the rights');
    expect_error(fn() => native_api_collaborator_role($db, 2, ['design_id' => $id, 'user_id' => 6, 'role' => 'editor']), 'forbidden', 403);
    expect_error(fn() => native_api_collaborator_role($db, 6, ['design_id' => $id, 'user_id' => 6, 'role' => 'editor']), 'forbidden', 403);
    expect_error(fn() => native_api_collaborator_role($db, 5, ['design_id' => $id, 'user_id' => 6, 'role' => 'editor']), 'not_found', 404);
    expect_error(fn() => native_api_collaborator_role($db, 1, ['design_id' => $id, 'user_id' => 5, 'role' => 'editor']), 'invalid_request', 422);
    expect_error(fn() => native_api_collaborator_role($db, 1, ['design_id' => $id, 'user_id' => 1, 'role' => 'viewer']), 'invalid_request', 422);
    expect_error(fn() => native_api_collaborator_role($db, 1, ['design_id' => $id, 'user_id' => 6]), 'invalid_request', 422);
    expect_error(fn() => native_api_collaborator_role($db, 1, ['design_id' => $id, 'user_id' => 6, 'role' => 'admin']), 'invalid_request', 422);
    native_api_unshare($db, 1, ['design_id' => $id, 'user_id' => 6]);
    check(rows($db, 'design_presence', $id) === 0, 'Removing a viewer clears their presence');

    // Présence.
    $first = presence($db, 2, $id, CLIENT_A, 'activity:a-1:instructions');
    check($first['revision'] === 2 && $first['shared'] === true && $first['participants'] === [], 'A window never sees itself');
    $owner = presence($db, 1, $id, CLIENT_B);
    check(count($owner['participants']) === 1 && $owner['participants'][0]['userId'] === 2
        && $owner['participants'][0]['username'] === 'Colleague' && $owner['participants'][0]['clientId'] === CLIENT_A
        && $owner['participants'][0]['focus'] === 'activity:a-1:instructions' && is_int($owner['participants'][0]['updatedAt']),
        'Participants show who works where');
    $second = presence($db, 2, $id, strtoupper(CLIENT_C), 'moment:m-1:objectives');
    check(array_column($second['participants'], 'clientId') === [CLIENT_A, CLIENT_B], 'Other windows of the same account are listed');
    check((int)$db->query("SELECT COUNT(*) FROM design_presence WHERE client_id = '" . CLIENT_C . "'")->fetchColumn() === 1,
        'Window identifiers are stored in lower case');
    presence($db, 2, $id, CLIENT_A, 'activity:a-1:description');
    check(rows($db, 'design_presence', $id) === 3, 'Repeated signals update the same row');
    check(presence($db, 1, $id, CLIENT_B)['participants'][0]['focus'] === 'activity:a-1:description', 'Signals update the work location');
    presence($db, 2, $id, CLIENT_A, "act\u{0007}ivity:a-1:\nnotes\u{007F}");
    check($db->query("SELECT focus FROM design_presence WHERE client_id = '" . CLIENT_A . "'")->fetchColumn() === 'activity:a-1:notes',
        'Control characters are removed from work locations');
    presence($db, 2, $id, CLIENT_A, str_repeat('é', 200));
    $stored = (string)$db->query("SELECT focus FROM design_presence WHERE client_id = '" . CLIENT_A . "'")->fetchColumn();
    check(strlen($stored) === 300 && mb_check_encoding($stored, 'UTF-8'), 'Long work locations are cut without breaking UTF-8');
    presence($db, 2, $id, CLIENT_A, '   ');
    check($db->query("SELECT focus FROM design_presence WHERE client_id = '" . CLIENT_A . "'")->fetchColumn() === null, 'Blank locations become null');
    expect_error(fn() => presence($db, 2, $id, 'bad'), 'invalid_request', 422);
    expect_error(fn() => presence($db, 2, $id, CLIENT_A, ['activity']), 'invalid_request', 422);
    expect_error(fn() => presence($db, 5, $id, CLIENT_A), 'not_found', 404);
    expect_error(fn() => presence($db, 2, 999, CLIENT_A), 'not_found', 404);
    $db->exec('UPDATE design_presence SET updated_at = ' . (time() - 60) . " WHERE client_id = '" . CLIENT_A . "'");
    check(array_column(presence($db, 1, $id, CLIENT_B)['participants'], 'clientId') === [CLIENT_C], 'Quiet windows leave the participant list');
    check(rows($db, 'design_presence', $id) === 3, 'Recent quiet windows are kept until the retention delay');
    $db->exec('UPDATE design_presence SET updated_at = ' . (time() - 200) . " WHERE client_id = '" . CLIENT_A . "'");
    presence($db, 1, $id, CLIENT_B);
    check(rows($db, 'design_presence', $id) === 2, 'Old signals are cleaned up');
    presence($db, 2, $id, CLIENT_C, null, true);
    check(presence($db, 1, $id, CLIENT_B)['participants'] === [] && rows($db, 'design_presence', $id) === 1, 'Closing a window removes its presence');
    $unshared = native_api_save($db, 1, scenario('7a8b9c0d-1e2f-4a3b-8c4d-5e6f7a8b9c0d', 'Solo'))['design']['id'];
    check(presence($db, 1, $unshared, CLIENT_B)['shared'] === false, 'Unshared scenarios report no sharing');

    // Retraits.
    native_api_share($db, 1, ['design_id' => $id, 'email' => 'beatrice@example.test']);
    presence($db, 6, $id, CLIENT_C);
    expect_error(fn() => native_api_unshare($db, 2, ['design_id' => $id, 'user_id' => 6]), 'forbidden', 403);
    expect_error(fn() => native_api_unshare($db, 2, ['design_id' => $id, 'user_id' => 1]), 'forbidden', 403);
    expect_error(fn() => native_api_unshare($db, 1, ['design_id' => $id, 'user_id' => 1]), 'invalid_request', 422);
    expect_error(fn() => native_api_unshare($db, 1, ['design_id' => $id, 'user_id' => '6']), 'invalid_request', 422);
    expect_error(fn() => native_api_unshare($db, 5, ['design_id' => $id, 'user_id' => 5]), 'not_found', 404);
    $removed = native_api_unshare($db, 1, ['design_id' => $id, 'user_id' => 6]);
    check(array_column($removed['sharing']['members'], 'id') === [1, 2], 'Owner removes an invited teacher');
    check((int)$db->query("SELECT COUNT(*) FROM design_presence WHERE user_id = 6")->fetchColumn() === 0, 'Removal clears the teacher’s presence');
    expect_error(fn() => native_api_get($db, 6, $id), 'not_found', 404);
    check(count(native_api_unshare($db, 1, ['design_id' => $id, 'user_id' => 5])['sharing']['members']) === 2, 'Removing a non-member is idempotent');
    presence($db, 2, $id, CLIENT_A);
    check(native_api_unshare($db, 2, ['design_id' => $id, 'user_id' => 2]) === ['success' => true, 'sharing' => null], 'A teacher can leave the sharing');
    expect_error(fn() => native_api_get($db, 2, $id), 'not_found', 404);
    check(!in_array($id, array_column(native_api_list($db, 2)['items'], 'id'), true), 'A left scenario disappears from the list');
    check((int)$db->query("SELECT COUNT(*) FROM design_presence WHERE user_id = 2")->fetchColumn() === 0, 'Leaving clears presence');
    check(native_api_list($db, 1)['items'][array_search($id, array_column(native_api_list($db, 1)['items'], 'id'), true)]['shared'] === false,
        'A scenario without invited teachers is no longer shared');

    // Comptes suspendus et limite d’invitations.
    native_api_share($db, 1, ['design_id' => $id, 'email' => 'beatrice@example.test']);
    $db->exec("UPDATE users SET status = 'disabled' WHERE id = 6");
    check(native_design_access($db, 6, $id) === null, 'Suspended accounts lose access');
    check(count(native_api_collaborators($db, 1, $id)['sharing']['members']) === 1, 'Suspended accounts are hidden from members');
    $db->exec("UPDATE users SET status = 'active' WHERE id = 6");
    check(native_design_access($db, 6, $id)['role'] === 'editor', 'Reactivated accounts recover their invitation');
    native_api_unshare($db, 1, ['design_id' => $id, 'user_id' => 6]);
    $insert = $db->prepare("INSERT INTO users (id, username, email, password_hash, status, email_verified_at)
        VALUES (?, ?, ?, 'test', 'active', CURRENT_TIMESTAMP)");
    for ($i = 0; $i < NATIVE_MAX_COLLABORATORS; $i++) {
        $insert->execute([100 + $i, "Teacher $i", "teacher$i@example.test"]);
        native_api_share($db, 1, ['design_id' => $unshared, 'email' => "teacher$i@example.test"]);
    }
    check(count(native_api_collaborators($db, 1, $unshared)['sharing']['members']) === NATIVE_MAX_COLLABORATORS + 1, 'Twenty teachers can be invited');
    expect_error(fn() => native_api_share($db, 1, ['design_id' => $unshared, 'email' => 'outsider@example.test']), 'invalid_request', 422);

    // Suppressions et cascades.
    native_api_share($db, 1, ['design_id' => $id, 'email' => 'colleague@example.test']);
    presence($db, 2, $id, CLIENT_A);
    $revision = native_api_get($db, 1, $id)['design']['revision'];
    native_api_delete($db, 1, ['design_id' => $id, 'expected_revision' => $revision]);
    check(rows($db, 'design_collaborators', $id) === 0 && rows($db, 'design_presence', $id) === 0, 'Native deletion removes sharing and presence');
    expect_error(fn() => native_api_get($db, 2, $id), 'not_found', 404);
    $web = native_api_save($db, 1, scenario('8b9c0d1e-2f3a-4b4c-9d5e-6f7a8b9c0d1e', 'Web'))['design']['id'];
    native_api_share($db, 1, ['design_id' => $web, 'email' => 'colleague@example.test']);
    presence($db, 2, $web, CLIENT_A);
    check(app_delete_design_document($db, 2, $web) === false && rows($db, 'design_collaborators', $web) === 1,
        'A collaborator cannot delete through the website');
    check(app_delete_design_document($db, 1, $web) && rows($db, 'design_collaborators', $web) === 0 && rows($db, 'design_presence', $web) === 0,
        'Website deletion removes sharing and presence');
    $kept = native_api_save($db, 1, scenario('9c0d1e2f-3a4b-4c5d-8e6f-7a8b9c0d1e2f', 'Gardé'))['design']['id'];
    native_api_share($db, 1, ['design_id' => $kept, 'email' => 'outsider@example.test']);
    presence($db, 5, $kept, CLIENT_C);
    $db->exec('DELETE FROM users WHERE id = 5');
    check(rows($db, 'design_collaborators', $kept) === 0 && rows($db, 'design_presence', $kept) === 0, 'Deleting an invited account removes its sharing');
    native_api_share($db, 1, ['design_id' => $kept, 'email' => 'colleague@example.test']);
    $db->exec('DELETE FROM users WHERE id = 1');
    check((int)$db->query('SELECT COUNT(*) FROM design_collaborators')->fetchColumn() === 0, 'Deleting an owner removes the sharing of their scenarios');
    check(native_api_list($db, 2)['items'] === [['id' => $own['id'], 'title' => 'Brouillon de la collègue', 'revision' => 1,
        'updatedAt' => native_api_list($db, 2)['items'][0]['updatedAt'], 'role' => 'owner', 'owner' => 'Colleague', 'shared' => false]],
        'Collaborators keep only their own scenarios');
    check(!$db->inTransaction(), 'Collaboration operations leave no open transaction');
    echo "$checks native collaboration checks passed\n";
} finally {
    $db = null;
    foreach (glob($fixture . '/*') as $path) unlink($path);
    rmdir($fixture);
}
