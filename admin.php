<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/admin-security.php';
require_once __DIR__ . '/lib/admin-i18n.php';

$admin = require_admin_page();
$db = app_db();
ensure_admin_security_table($db);
$_SESSION['admin_security_csrf'] ??= bin2hex(random_bytes(32));
$db->prepare('DELETE FROM app_feedback WHERE created_at_epoch < ?')->execute([time() - 63072000]);
$db->prepare("UPDATE app_feedback SET visitor_hash = '' WHERE visitor_hash <> '' AND created_at_epoch < ?")
    ->execute([time() - 86400]);
$message = '';
$error = '';
$activeAdminTab = (string)($_GET['tab'] ?? $_POST['admin_tab'] ?? 'accounts');
if (!in_array($activeAdminTab, ['accounts', 'feedback', 'statistics', 'security'], true)) {
    $activeAdminTab = 'accounts';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_same_origin_post();
    $adminAction = (string)($_POST['admin_action'] ?? 'create_account');
    if ($adminAction === 'moderate_user') {
        $activeAdminTab = 'security';
        if (!hash_equals($_SESSION['admin_security_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
            $error = 'Formulaire expiré. Rechargez la page puis réessayez.';
        } else {
            try {
                admin_moderate_user($db, (int)$admin['id'], (int)($_POST['target_user_id'] ?? 0),
                    (string)($_POST['security_action'] ?? ''), (string)($_POST['reason'] ?? ''),
                    (string)($_POST['confirmation'] ?? ''));
                $_SESSION['admin_security_message'] = 'Intervention enregistrée avec succès.';
                header('Location: admin.php?tab=security');
                exit;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                error_log('Admin moderation failed: ' . $e->getMessage());
                $error = 'L’intervention a échoué. Aucune modification n’a été conservée.';
            }
        }
    } elseif ($adminAction === 'delete_feedback') {
        $activeAdminTab = 'feedback';
        $feedbackId = filter_var($_POST['feedback_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($feedbackId === false) {
            $error = 'Le retour à supprimer est invalide.';
        } else {
            $deleteFeedbackStmt = $db->prepare('DELETE FROM app_feedback WHERE id = ?');
            $deleteFeedbackStmt->execute([$feedbackId]);
            $message = $deleteFeedbackStmt->rowCount() === 1
                ? 'Le retour a été supprimé.'
                : 'Ce retour avait déjà été supprimé.';
        }
    } elseif ($adminAction === 'create_account') {
        $username = sanitize_username((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'designer');
        if (!in_array($role, ['admin', 'designer'], true)) {
            $role = 'designer';
        }

        if ($username === '' || $email === '' || $password === '') {
            $error = 'Nom d’utilisateur, email et mot de passe requis.';
        } elseif (strlen($password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, status, email_verified_at) VALUES (?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)");
                $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                $message = 'Compte créé avec succès.';
            } catch (PDOException $e) {
                $error = 'Impossible de créer ce compte (email ou nom déjà utilisé ?).';
            }
        }
    }
}

if (isset($_SESSION['admin_security_message'])) {
    $message = (string)$_SESSION['admin_security_message'];
    unset($_SESSION['admin_security_message']);
}
$securityLog = $db->query('SELECT * FROM admin_security_log ORDER BY id DESC LIMIT 100')->fetchAll();
$securityLabels = ['suspend' => 'Suspension', 'reactivate' => 'Réactivation', 'delete' => 'Suppression'];

$usersStmt = $db->query("SELECT
    u.id,
    u.username,
    u.email,
    u.role,
    u.status,
    u.email_verified_at,
    u.created_at,
    u.last_login_at,
    COUNT(d.id) AS design_count
FROM users u
LEFT JOIN learning_designs d ON d.owner_user_id = u.id
GROUP BY u.id, u.username, u.email, u.role, u.status, u.email_verified_at, u.created_at, u.last_login_at
ORDER BY u.created_at DESC");
$users = $usersStmt->fetchAll();

$statisticsSince = gmdate('Y-m-d H:i:s', time() - 2592000);
$designStatisticsStmt = $db->prepare("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN is_published = 1 AND share_token IS NOT NULL AND share_token <> '' THEN 1 ELSE 0 END) AS shared,
    SUM(CASE WHEN is_published = 1 AND is_listed = 1 AND share_token IS NOT NULL AND share_token <> '' THEN 1 ELSE 0 END) AS listed,
    SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS recent
FROM learning_designs");
$designStatisticsStmt->execute([$statisticsSince]);
$designStatistics = $designStatisticsStmt->fetch() ?: [];

$statistics = [
    'users' => count($users),
    'active_users' => 0,
    'verified_users' => 0,
    'creators' => 0,
    'recent_users' => 0,
    'designs' => (int)($designStatistics['total'] ?? 0),
    'shared_designs' => (int)($designStatistics['shared'] ?? 0),
    'listed_designs' => (int)($designStatistics['listed'] ?? 0),
    'recent_designs' => (int)($designStatistics['recent'] ?? 0),
];
foreach ($users as $user) {
    $statistics['active_users'] += (string)$user['status'] === 'active' ? 1 : 0;
    $statistics['verified_users'] += !empty($user['email_verified_at']) ? 1 : 0;
    $statistics['creators'] += (int)$user['design_count'] > 0 ? 1 : 0;
    $statistics['recent_users'] += (string)$user['created_at'] >= $statisticsSince ? 1 : 0;
}

$topCreators = array_values(array_filter($users, static fn(array $user): bool => (int)$user['design_count'] > 0));
usort($topCreators, static function (array $left, array $right): int {
    $byDesignCount = (int)$right['design_count'] <=> (int)$left['design_count'];
    return $byDesignCount !== 0 ? $byDesignCount : strcmp((string)$left['username'], (string)$right['username']);
});
$topCreators = array_slice($topCreators, 0, 5);

$feedbackCounts = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
$feedbackCountStmt = $db->query("SELECT rating, COUNT(*) AS total FROM app_feedback GROUP BY rating");
foreach ($feedbackCountStmt->fetchAll() as $row) {
    $rating = (string)($row['rating'] ?? '');
    if (array_key_exists($rating, $feedbackCounts)) {
        $feedbackCounts[$rating] = (int)$row['total'];
    }
}
$feedbackTotal = array_sum($feedbackCounts);
$feedbackStmt = $db->query("SELECT id, rating, comment, page_path, locale, created_at
    FROM app_feedback
    ORDER BY created_at_epoch DESC, id DESC
    LIMIT 200");
$feedbackRows = $feedbackStmt->fetchAll();
$feedbackLabels = [
    'positive' => ['label' => 'Satisfait', 'icon' => 'fa-face-smile'],
    'neutral' => ['label' => 'Mitigé', 'icon' => 'fa-face-meh'],
    'negative' => ['label' => 'Insatisfait', 'icon' => 'fa-face-frown'],
];

function feedback_display_page_path(string $pagePath): string
{
    $basePath = app_script_base_path();
    if ($basePath !== '' && ($pagePath === $basePath || str_starts_with($pagePath, $basePath . '/'))) {
        $pagePath = substr($pagePath, strlen($basePath));
    }
    return $pagePath === '' ? '/' : $pagePath;
}

function admin_stat_percentage(int $value, int $total): int
{
    return $total > 0 ? (int)round(($value / $total) * 100) : 0;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title>Administration | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260906-admin-backup-section">
</head>
<body class="admin-page">
<?php render_site_nav('admin'); ?>
<main class="account-shell with-nav profile-shell">
    <section class="account-card wide">
        <div class="account-topbar">
            <div>
                <p class="account-kicker">Administration</p>
                <h1 <?= admin_i18n_attributes('Vue d’ensemble') ?>>Vue d’ensemble</h1>
            </div>
        </div>

        <div class="admin-tabs" role="tablist" aria-label="Sections de l’administration" <?= admin_i18n_attributes('Sections de l’administration') ?> data-site-i18n-attr="aria-label">
            <button id="admin-tab-accounts" class="admin-tab<?= $activeAdminTab === 'accounts' ? ' is-active' : '' ?>" type="button" role="tab" aria-selected="<?= $activeAdminTab === 'accounts' ? 'true' : 'false' ?>" aria-controls="admin-panel-accounts" data-admin-tab="accounts">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <?= admin_i18n('Comptes') ?>
            </button>
            <button id="admin-tab-feedback" class="admin-tab<?= $activeAdminTab === 'feedback' ? ' is-active' : '' ?>" type="button" role="tab" aria-selected="<?= $activeAdminTab === 'feedback' ? 'true' : 'false' ?>" aria-controls="admin-panel-feedback" data-admin-tab="feedback">
                <i class="fa-regular fa-message" aria-hidden="true"></i>
                Feedback
                <span><?= $feedbackTotal ?></span>
            </button>
            <button id="admin-tab-statistics" class="admin-tab<?= $activeAdminTab === 'statistics' ? ' is-active' : '' ?>" type="button" role="tab" aria-selected="<?= $activeAdminTab === 'statistics' ? 'true' : 'false' ?>" aria-controls="admin-panel-statistics" data-admin-tab="statistics">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                <?= admin_i18n('Statistiques') ?>
            </button>
            <button id="admin-tab-security" class="admin-tab<?= $activeAdminTab === 'security' ? ' is-active' : '' ?>" type="button" role="tab" aria-selected="<?= $activeAdminTab === 'security' ? 'true' : 'false' ?>" aria-controls="admin-panel-security" data-admin-tab="security">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> <?= admin_i18n('Sécurité') ?>
            </button>
        </div>

        <?php if ($message !== ''): ?>
            <p class="account-message success"><?= admin_i18n($message) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="account-message error"><?= admin_i18n($error) ?></p>
        <?php endif; ?>

        <div id="admin-panel-security" class="admin-tab-panel" role="tabpanel" aria-labelledby="admin-tab-security"<?= $activeAdminTab === 'security' ? '' : ' hidden' ?>>
            <form method="post" action="admin.php?tab=security" class="account-form panel">
                <h2 <?= admin_i18n_attributes('Modérer un compte') ?>>Modérer un compte</h2>
                <p class="account-copy" <?= admin_i18n_attributes('La suspension bloque l’accès au compte, révoque ses jetons CLI et retire ses scénarios du catalogue et du partage. La réactivation ne republie pas les scénarios et ne restaure pas les jetons.') ?>>La suspension bloque l’accès au compte, révoque ses jetons CLI et retire ses scénarios du catalogue et du partage. La réactivation ne republie pas les scénarios et ne restaure pas les jetons.</p>
                <input type="hidden" name="admin_action" value="moderate_user">
                <input type="hidden" name="admin_tab" value="security">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['admin_security_csrf']) ?>">
                <div>
                    <label for="security-user" <?= admin_i18n_attributes('Compte concerné') ?>>Compte concerné</label>
                    <select id="security-user" name="target_user_id" required>
                        <option value="" <?= admin_i18n_attributes('Choisir un utilisateur') ?>>Choisir un utilisateur</option>
                        <?php foreach ($users as $u): ?>
                            <?php if ((int)$u['id'] === (int)$admin['id']) continue; ?>
                            <option <?= admin_i18n_attributes($u['username'] . ' — ' . $u['email'] . ' · ' . ($u['status'] === 'active' ? 'Actif' : 'Suspendu') . ' · ' . $u['role'] . ' · ' . $u['design_count'] . ' scénario(s)', $u['username'] . ' — ' . $u['email'] . ' · ' . ($u['status'] === 'active' ? 'Active' : 'Suspended') . ' · ' . $u['role'] . ' · ' . $u['design_count'] . ' scenario(s)') ?> value="<?= (int)$u['id'] ?>"<?= (int)($_POST['target_user_id'] ?? 0) === (int)$u['id'] ? ' selected' : '' ?>><?= h($u['username'] . ' — ' . $u['email'] . ' · ' . ($u['status'] === 'active' ? 'Actif' : 'Suspendu') . ' · ' . $u['role'] . ' · ' . $u['design_count'] . ' scénario(s)') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="security-action" <?= admin_i18n_attributes('Intervention') ?>>Intervention</label>
                    <select id="security-action" name="security_action" required>
                        <?php foreach ($securityLabels as $value => $label): ?>
                            <option <?= admin_i18n_attributes($label) ?> value="<?= h($value) ?>"<?= ($_POST['security_action'] ?? '') === $value ? ' selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="security-reason" <?= admin_i18n_attributes('Motif de l’intervention') ?>>Motif de l’intervention</label>
                    <input id="security-reason" name="reason" required maxlength="500" value="<?= h((string)($_POST['reason'] ?? '')) ?>" placeholder="Ex. : publications abusives répétées" <?= admin_i18n_attributes('Ex. : publications abusives répétées') ?> data-site-i18n-attr="placeholder">
                </div>
                <div id="security-delete-confirmation">
                    <p class="account-message error" id="security-delete-warning" <?= admin_i18n_attributes('La suppression est définitive : le compte, tous ses scénarios et ses jetons CLI seront supprimés. L’historique des interventions sera conservé.') ?>>La suppression est définitive : le compte, tous ses scénarios et ses jetons CLI seront supprimés. L’historique des interventions sera conservé.</p>
                    <label for="security-confirmation" <?= admin_i18n_attributes('Pour supprimer, recopiez exactement le nom d’utilisateur') ?>>Pour supprimer, recopiez exactement le nom d’utilisateur</label>
                    <input id="security-confirmation" name="confirmation" autocomplete="off" aria-describedby="security-delete-warning">
                </div>
                <p class="account-copy" <?= admin_i18n_attributes('Votre propre compte et le dernier administrateur actif sont protégés.') ?>>Votre propre compte et le dernier administrateur actif sont protégés.</p>
                <button type="submit" <?= admin_i18n_attributes('Appliquer l’intervention') ?>>Appliquer l’intervention</button>
            </form>
            <section class="panel">
                <h2 <?= admin_i18n_attributes('Historique des interventions') ?>>Historique des interventions</h2>
                <p class="account-copy" <?= admin_i18n_attributes('Les 100 interventions les plus récentes. Dates en UTC.') ?>>Les 100 interventions les plus récentes. Dates en UTC.</p>
                <?php if ($securityLog === []): ?>
                    <p <?= admin_i18n_attributes('Aucune intervention pour le moment.') ?>>Aucune intervention pour le moment.</p>
                <?php else: ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Date</th><th <?= admin_i18n_attributes('Administrateur') ?>>Administrateur</th><th <?= admin_i18n_attributes('Compte concerné') ?>>Compte concerné</th><th>Action</th><th <?= admin_i18n_attributes('Motif') ?>>Motif</th></tr></thead>
                        <tbody><?php foreach ($securityLog as $entry): ?>
                            <tr><td><?= h($entry['created_at']) ?></td><td><?= h($entry['actor_name']) ?> (#<?= (int)$entry['actor_id'] ?>)</td><td><?= h($entry['target_name']) ?> (#<?= (int)$entry['target_id'] ?>)</td><td><?= admin_i18n($securityLabels[$entry['action']] ?? $entry['action']) ?></td><td class="admin-feedback-comment"><?= h($entry['reason']) ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                <?php endif; ?>
            </section>
        </div>

        <div id="admin-panel-statistics" class="admin-tab-panel" role="tabpanel" aria-labelledby="admin-tab-statistics"<?= $activeAdminTab === 'statistics' ? '' : ' hidden' ?>>
        <section class="admin-statistics-panel">
            <div class="admin-section-head">
                <div>
                    <h2 <?= admin_i18n_attributes('Activité de la plateforme') ?>>Activité de la plateforme</h2>
                    <p <?= admin_i18n_attributes('Indicateurs calculés en temps réel à partir des comptes et des scénarios enregistrés.') ?>>Indicateurs calculés en temps réel à partir des comptes et des scénarios enregistrés.</p>
                </div>
                <span class="admin-statistics-period"><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= admin_i18n('30 derniers jours') ?></span>
            </div>

            <div class="admin-statistics-cards" aria-label="Indicateurs principaux" <?= admin_i18n_attributes('Indicateurs principaux') ?> data-site-i18n-attr="aria-label">
                <article class="admin-stat-card admin-stat-card-users">
                    <div class="admin-stat-card-head">
                        <span <?= admin_i18n_attributes('Utilisateurs') ?>>Utilisateurs</span>
                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                    </div>
                    <strong><?= $statistics['users'] ?></strong>
                    <p><b>+<?= $statistics['recent_users'] ?></b> <?= admin_i18n('sur les 30 derniers jours') ?></p>
                </article>
                <article class="admin-stat-card admin-stat-card-designs">
                    <div class="admin-stat-card-head">
                        <span <?= admin_i18n_attributes('Scénarios créés') ?>>Scénarios créés</span>
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                    </div>
                    <strong><?= $statistics['designs'] ?></strong>
                    <p><b>+<?= $statistics['recent_designs'] ?></b> <?= admin_i18n('sur les 30 derniers jours') ?></p>
                </article>
                <article class="admin-stat-card admin-stat-card-shared">
                    <div class="admin-stat-card-head">
                        <span <?= admin_i18n_attributes('Scénarios partagés') ?>>Scénarios partagés</span>
                        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                    </div>
                    <strong><?= $statistics['shared_designs'] ?></strong>
                    <p><b><?= admin_stat_percentage($statistics['shared_designs'], $statistics['designs']) ?> %</b> <?= admin_i18n('des scénarios ont un lien actif') ?></p>
                </article>
                <article class="admin-stat-card admin-stat-card-listed">
                    <div class="admin-stat-card-head">
                        <span <?= admin_i18n_attributes('Dans le catalogue') ?>>Dans le catalogue</span>
                        <i class="fa-regular fa-compass" aria-hidden="true"></i>
                    </div>
                    <strong><?= $statistics['listed_designs'] ?></strong>
                    <p><b><?= admin_stat_percentage($statistics['listed_designs'], $statistics['shared_designs']) ?> %</b> <?= admin_i18n('des scénarios partagés') ?></p>
                </article>
            </div>

            <div class="admin-statistics-details">
                <section class="admin-statistics-block" aria-labelledby="admin-stat-users-title">
                    <h3 id="admin-stat-users-title">Adoption</h3>
                    <div class="admin-stat-progress-list">
                        <?php
                        $adoptionRows = [
                            ['label' => 'Comptes actifs', 'value' => $statistics['active_users'], 'total' => $statistics['users']],
                            ['label' => 'Emails vérifiés', 'value' => $statistics['verified_users'], 'total' => $statistics['users']],
                            ['label' => 'Utilisateurs ayant créé un scénario', 'value' => $statistics['creators'], 'total' => $statistics['users']],
                        ];
                        ?>
                        <?php foreach ($adoptionRows as $row): ?>
                            <?php $percentage = admin_stat_percentage($row['value'], $row['total']); ?>
                            <div class="admin-stat-progress-row">
                                <div><span><?= admin_i18n($row['label']) ?></span><strong><?= $row['value'] ?> <small>/ <?= $row['total'] ?></small></strong></div>
                                <div class="admin-stat-progress" role="progressbar" <?= admin_i18n_attributes($row['label']) ?> data-site-i18n-attr="aria-label" aria-label="<?= h($row['label']) ?>" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                    <span style="width: <?= $percentage ?>%"></span>
                                </div>
                                <small><?= $percentage ?> %</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="admin-statistics-block" aria-labelledby="admin-stat-sharing-title">
                    <h3 id="admin-stat-sharing-title" <?= admin_i18n_attributes('Diffusion des scénarios') ?>>Diffusion des scénarios</h3>
                    <div class="admin-stat-progress-list">
                        <?php
                        $sharingRows = [
                            ['label' => 'Enregistrés', 'value' => $statistics['designs']],
                            ['label' => 'Partagés par lien', 'value' => $statistics['shared_designs']],
                            ['label' => 'Visibles dans le catalogue', 'value' => $statistics['listed_designs']],
                        ];
                        ?>
                        <?php foreach ($sharingRows as $row): ?>
                            <?php $percentage = admin_stat_percentage($row['value'], $statistics['designs']); ?>
                            <div class="admin-stat-progress-row">
                                <div><span><?= admin_i18n($row['label']) ?></span><strong><?= $row['value'] ?></strong></div>
                                <div class="admin-stat-progress" role="progressbar" <?= admin_i18n_attributes($row['label']) ?> data-site-i18n-attr="aria-label" aria-label="<?= h($row['label']) ?>" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                    <span style="width: <?= $percentage ?>%"></span>
                                </div>
                                <small><?= $percentage ?> %</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <section class="admin-statistics-ranking" aria-labelledby="admin-stat-ranking-title">
                <div class="admin-statistics-ranking-head">
                    <div>
                        <h3 id="admin-stat-ranking-title" <?= admin_i18n_attributes('Créateurs les plus actifs') ?>>Créateurs les plus actifs</h3>
                        <p><?= admin_i18n($statistics['creators'] . ($statistics['creators'] !== 1 ? ' utilisateurs ont' : ' utilisateur a') . ' créé au moins un scénario.', $statistics['creators'] . ($statistics['creators'] !== 1 ? ' users have' : ' user has') . ' created at least one scenario.') ?></p>
                    </div>
                    <?php if ($statistics['creators'] > 0): ?>
                        <span><?= admin_i18n(number_format($statistics['designs'] / $statistics['creators'], 1, ',', ' ') . ' scénario' . ($statistics['designs'] / $statistics['creators'] >= 2 ? 's' : '') . ' par créateur', number_format($statistics['designs'] / $statistics['creators'], 1, '.', ',') . ' scenario' . ($statistics['designs'] / $statistics['creators'] != 1 ? 's' : '') . ' per creator') ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($topCreators === []): ?>
                    <p class="admin-statistics-empty" <?= admin_i18n_attributes('Aucun scénario n’a encore été créé.') ?>>Aucun scénario n’a encore été créé.</p>
                <?php else: ?>
                    <ol class="admin-statistics-top-list">
                        <?php foreach ($topCreators as $index => $creator): ?>
                            <li>
                                <span class="admin-statistics-rank"><?= $index + 1 ?></span>
                                <span class="admin-statistics-creator"><strong><?= h((string)$creator['username']) ?></strong><small><?= h((string)$creator['email']) ?></small></span>
                                <strong class="admin-statistics-design-count"><?= (int)$creator['design_count'] ?> <small>scénario<?= (int)$creator['design_count'] !== 1 ? 's' : '' ?></small></strong>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </section>
        </div>

        <div id="admin-panel-feedback" class="admin-tab-panel" role="tabpanel" aria-labelledby="admin-tab-feedback"<?= $activeAdminTab === 'feedback' ? '' : ' hidden' ?>>
        <section class="panel admin-feedback-panel">
            <div class="admin-section-head">
                <div>
                    <h2 <?= admin_i18n_attributes('Retours utilisateurs') ?>>Retours utilisateurs</h2>
                    <p <?= admin_i18n_attributes('Les 200 réponses les plus récentes.') ?>>Les 200 réponses les plus récentes.</p>
                </div>
                <span class="admin-feedback-total"><?= admin_i18n($feedbackTotal . ($feedbackTotal > 1 ? ' réponses' : ' réponse'), $feedbackTotal . ($feedbackTotal === 1 ? ' response' : ' responses')) ?></span>
            </div>

            <div class="admin-feedback-stats" aria-label="Répartition des appréciations" <?= admin_i18n_attributes('Répartition des appréciations') ?> data-site-i18n-attr="aria-label">
                <?php foreach ($feedbackCounts as $rating => $count): ?>
                    <?php $ratingMeta = $feedbackLabels[$rating]; ?>
                    <div class="admin-feedback-stat admin-feedback-stat-<?= h($rating) ?>">
                        <i class="fa-regular <?= h($ratingMeta['icon']) ?>" aria-hidden="true"></i>
                        <strong><?= $count ?></strong>
                        <span><?= admin_i18n($ratingMeta['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($feedbackRows === []): ?>
                <p class="admin-feedback-empty" <?= admin_i18n_attributes('Aucun retour pour le moment.') ?>>Aucun retour pour le moment.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="admin-feedback-table">
                        <thead>
                        <tr>
                            <th <?= admin_i18n_attributes('Appréciation') ?>>Appréciation</th>
                            <th <?= admin_i18n_attributes('Commentaire') ?>>Commentaire</th>
                            <th>Page</th>
                            <th <?= admin_i18n_attributes('Langue') ?>>Langue</th>
                            <th <?= admin_i18n_attributes('Reçu le') ?>>Reçu le</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($feedbackRows as $feedback): ?>
                            <?php
                            $rating = (string)($feedback['rating'] ?? 'neutral');
                            $ratingMeta = $feedbackLabels[$rating] ?? $feedbackLabels['neutral'];
                            ?>
                            <tr>
                                <td>
                                    <span class="admin-feedback-badge admin-feedback-badge-<?= h($rating) ?>">
                                        <i class="fa-regular <?= h($ratingMeta['icon']) ?>" aria-hidden="true"></i>
                                        <?= admin_i18n($ratingMeta['label']) ?>
                                    </span>
                                </td>
                                <td class="admin-feedback-comment"><?= h((string)($feedback['comment'] ?: '—')) ?></td>
                                <td><code><?= h(feedback_display_page_path((string)$feedback['page_path'])) ?></code></td>
                                <td><?= h(strtoupper((string)$feedback['locale'])) ?></td>
                                <td><?= h((string)$feedback['created_at']) ?></td>
                                <td class="admin-feedback-actions">
                                    <form method="post" class="admin-feedback-delete-form" data-feedback-delete-form>
                                        <input type="hidden" name="admin_tab" value="feedback">
                                        <input type="hidden" name="admin_action" value="delete_feedback">
                                        <input type="hidden" name="feedback_id" value="<?= (int)$feedback['id'] ?>">
                                        <button class="btn-icon-danger" type="submit" title="Supprimer ce retour" aria-label="Supprimer ce retour" <?= admin_i18n_attributes('Supprimer ce retour') ?> data-site-i18n-attr="title,aria-label">
                                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        </div>

        <div id="admin-panel-accounts" class="admin-tab-panel" role="tabpanel" aria-labelledby="admin-tab-accounts"<?= $activeAdminTab === 'accounts' ? '' : ' hidden' ?>>
        <form method="post" class="account-form panel">
            <input type="hidden" name="admin_tab" value="accounts">
            <h2 <?= admin_i18n_attributes('Créer un compte') ?>>Créer un compte</h2>
            <div class="inline-grid">
                <div>
                    <label for="username" <?= admin_i18n_attributes('Nom d’utilisateur') ?>>Nom d’utilisateur</label>
                    <input id="username" name="username" type="text" required autocomplete="off">
                </div>
                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required autocomplete="off">
                </div>
                <div>
                    <label for="password" <?= admin_i18n_attributes('Mot de passe') ?>>Mot de passe</label>
                    <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
                </div>
                <div>
                    <label for="role" <?= admin_i18n_attributes('Rôle') ?>>Rôle</label>
                    <select id="role" name="role">
                        <option value="designer">Designer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <button type="submit" <?= admin_i18n_attributes('Créer le compte') ?>>Créer le compte</button>
        </form>

        <section class="panel">
            <h2 <?= admin_i18n_attributes('Comptes existants') ?>>Comptes existants</h2>
            <div class="admin-account-filters" aria-label="Filtrer les comptes" <?= admin_i18n_attributes('Filtrer les comptes') ?> data-site-i18n-attr="aria-label">
                <label class="admin-filter-search" for="admin-account-search">
                    <span <?= admin_i18n_attributes('Rechercher') ?>>Rechercher</span>
                    <span class="admin-filter-field">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input id="admin-account-search" type="search" placeholder="Nom ou email" <?= admin_i18n_attributes('Nom ou email') ?> data-site-i18n-attr="placeholder" autocomplete="off">
                    </span>
                </label>
                <label for="admin-account-role">
                    <span <?= admin_i18n_attributes('Rôle') ?>>Rôle</span>
                    <select id="admin-account-role">
                        <option value="" <?= admin_i18n_attributes('Tous') ?>>Tous</option>
                        <option value="admin">Admin</option>
                        <option value="designer">Designer</option>
                    </select>
                </label>
                <label for="admin-account-status">
                    <span <?= admin_i18n_attributes('Statut') ?>>Statut</span>
                    <select id="admin-account-status">
                        <option value="" <?= admin_i18n_attributes('Tous') ?>>Tous</option>
                        <option value="active" <?= admin_i18n_attributes('Actif') ?>>Actif</option>
                        <option value="disabled" <?= admin_i18n_attributes('Désactivé') ?>>Désactivé</option>
                    </select>
                </label>
                <label for="admin-account-verification">
                    <span>Email</span>
                    <select id="admin-account-verification">
                        <option value="" <?= admin_i18n_attributes('Tous') ?>>Tous</option>
                        <option value="verified" <?= admin_i18n_attributes('Vérifié') ?>>Vérifié</option>
                        <option value="pending" <?= admin_i18n_attributes('En attente') ?>>En attente</option>
                    </select>
                </label>
                <p class="admin-filter-result" id="admin-account-filter-result" role="status" aria-live="polite"></p>
            </div>
            <div class="table-wrap">
                <table class="admin-accounts-table">
                    <thead>
                    <tr>
                        <th <?= admin_i18n_attributes('Nom') ?>>Nom</th>
                        <th>Email</th>
                        <th <?= admin_i18n_attributes('Rôle') ?>>Rôle</th>
                        <th <?= admin_i18n_attributes('Productions') ?>>Productions</th>
                        <th <?= admin_i18n_attributes('Statut') ?>>Statut</th>
                        <th <?= admin_i18n_attributes('Créé le') ?>>Créé le</th>
                        <th <?= admin_i18n_attributes('Dernière connexion') ?>>Dernière connexion</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr data-account-row data-search="<?= h(strtolower((string)$u['username'] . ' ' . (string)$u['email'])) ?>" data-role="<?= h((string)$u['role']) ?>" data-status="<?= h((string)$u['status']) ?>" data-verification="<?= empty($u['email_verified_at']) ? 'pending' : 'verified' ?>">
                            <td><?= htmlspecialchars((string)$u['username'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$u['role'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)$u['design_count'] ?></td>
                            <td><?= admin_i18n($u['status'] === 'active' ? 'Actif' : 'Désactivé') ?><?= empty($u['email_verified_at']) ? admin_i18n(' · email en attente') : '' ?></td>
                            <td><?= htmlspecialchars((string)$u['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= !empty($u['last_login_at']) ? h((string)$u['last_login_at']) : admin_i18n('Jamais') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
            <section class="panel admin-backup-panel" aria-labelledby="admin-backup-title">
                <h2 id="admin-backup-title" <?= admin_i18n_attributes('Sauvegarde des scénarios') ?>>Sauvegarde des scénarios</h2>
                <p class="account-copy" <?= admin_i18n_attributes('Téléchargez tous les scénarios dans une archive ZIP, avec un dossier par utilisateur et un fichier JSON par scénario, réimportable dans l’éditeur après extraction.') ?>>Téléchargez tous les scénarios dans une archive ZIP, avec un dossier par utilisateur et un fichier JSON par scénario, réimportable dans l’éditeur après extraction.</p>
                <a class="account-secondary-button" href="export_scenarios.php?scope=all">
                    <i class="fa-solid fa-file-export" aria-hidden="true"></i><?= admin_i18n('Sauvegarder tous les scénarios') ?>
                </a>
            </section>
        </div>
    </section>
</main>
<?php render_site_footer(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tabs = Array.from(document.querySelectorAll('[data-admin-tab]'));
    var tabList = document.querySelector('.admin-tabs');
    var panels = {
        accounts: document.getElementById('admin-panel-accounts'),
        feedback: document.getElementById('admin-panel-feedback'),
        security: document.getElementById('admin-panel-security'),
        statistics: document.getElementById('admin-panel-statistics')
    };

    function activateTab(name, updateUrl) {
        if (!panels[name]) return;
        tabs.forEach(function (tab) {
            var active = tab.dataset.adminTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.setAttribute('tabindex', active ? '0' : '-1');
        });
        Object.keys(panels).forEach(function (key) {
            panels[key].hidden = key !== name;
        });
        var activeTab = tabs.find(function (tab) {
            return tab.dataset.adminTab === name;
        });
        if (activeTab && tabList && tabList.scrollWidth > tabList.clientWidth) {
            var activeBounds = activeTab.getBoundingClientRect();
            var listBounds = tabList.getBoundingClientRect();
            var left = tabList.scrollLeft + activeBounds.left - listBounds.left
                - (tabList.clientWidth - activeBounds.width) / 2;
            tabList.scrollTo({ left: Math.max(0, left), behavior: updateUrl ? 'smooth' : 'auto' });
        }
        if (updateUrl && window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', name);
            window.history.replaceState({}, '', url);
        }
    }

    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () {
            activateTab(tab.dataset.adminTab, true);
        });
        tab.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            var direction = event.key === 'ArrowRight' ? 1 : -1;
            var next = tabs[(index + direction + tabs.length) % tabs.length];
            activateTab(next.dataset.adminTab, true);
            next.focus();
        });
    });

    var securityAction = document.getElementById('security-action');
    function updateSecurityConfirmation() {
        var deleting = securityAction.value === 'delete';
        document.getElementById('security-delete-confirmation').hidden = !deleting;
        document.getElementById('security-confirmation').required = deleting;
    }
    securityAction.addEventListener('change', updateSecurityConfirmation);
    updateSecurityConfirmation();

    var search = document.getElementById('admin-account-search');
    var role = document.getElementById('admin-account-role');
    var status = document.getElementById('admin-account-status');
    var verification = document.getElementById('admin-account-verification');
    var result = document.getElementById('admin-account-filter-result');
    var rows = Array.from(document.querySelectorAll('[data-account-row]'));

    function normalize(value) {
        return String(value || '').toLocaleLowerCase('fr').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function filterAccounts() {
        var query = normalize(search.value.trim());
        var visible = 0;
        rows.forEach(function (row) {
            var matches = (!query || normalize(row.dataset.search).includes(query))
                && (!role.value || row.dataset.role === role.value)
                && (!status.value || row.dataset.status === status.value)
                && (!verification.value || row.dataset.verification === verification.value);
            row.hidden = !matches;
            row.classList.toggle('is-even-visible', matches && visible % 2 === 1);
            if (matches) visible += 1;
        });
        result.textContent = document.documentElement.lang === 'en'
            ? visible + ' account' + (visible !== 1 ? 's' : '') + ' displayed'
            : visible + ' compte' + (visible !== 1 ? 's' : '') + ' affiché' + (visible !== 1 ? 's' : '');
    }

    [search, role, status, verification].forEach(function (control) {
        control.addEventListener(control === search ? 'input' : 'change', filterAccounts);
    });
    document.querySelectorAll('[data-feedback-delete-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(document.documentElement.lang === 'en' ? 'Permanently delete this feedback?' : 'Supprimer définitivement ce retour ?')) event.preventDefault();
        });
    });
    activateTab('<?= h($activeAdminTab) ?>', false);
    filterAccounts();
    document.getElementById('lang-select').addEventListener('change', filterAccounts);
});
</script>
</body>
</html>
