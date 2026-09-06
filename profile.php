<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$user = require_login_page();
$db = app_db();
$message = '';
$error = '';
$newCliToken = '';
$newCliTokenName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_same_origin_post();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'identity') {
        $username = sanitize_username((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        $emailChanged = strcasecmp($email, (string)($user['email'] ?? '')) !== 0;

        if ($username === '' || $email === '') {
            $error = 'Nom d’utilisateur et email requis.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } elseif ($emailChanged && !is_florimont_email($email)) {
            // Sans ce contrôle, un compte pouvait basculer vers n'importe quel
            // domaine après coup et contourner la restriction de signup.php.
            $error = 'Les comptes sont réservés aux adresses email @florimont.ch.';
        } else {
            try {
                if (!$emailChanged) {
                    $stmt = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->execute([$username, (int)$user['id']]);
                    $_SESSION['user']['username'] = $username;
                    $message = 'Informations mises à jour.';
                } else {
                    // La nouvelle adresse doit être confirmée avant de servir à
                    // se connecter. Tout se fait dans une transaction : si
                    // l'email de vérification ne part pas, on annule et le
                    // compte reste utilisable avec l'ancienne adresse — aucun
                    // chemin ne peut enfermer l'utilisateur dehors.
                    $db->beginTransaction();
                    $db->prepare("UPDATE users
                            SET username = ?, email = ?, email_verified_at = NULL
                            WHERE id = ?")
                       ->execute([$username, $email, (int)$user['id']]);

                    $token = create_email_verification_token($db, (int)$user['id']);
                    if (!send_email_verification_message($email, $username, $token)) {
                        $db->rollBack();
                        $error = 'L’email de confirmation n’a pas pu être envoyé : votre adresse actuelle est conservée. Réessayez plus tard.';
                    } else {
                        mark_email_verification_sent($db, (int)$user['id']);
                        $db->commit();

                        // La session en cours reste valide, mais la prochaine
                        // connexion exigera la confirmation.
                        $_SESSION['user']['username'] = $username;
                        $_SESSION['user']['email'] = $email;
                        $_SESSION['pending_verification_email'] = $email;
                        $message = 'Informations mises à jour. Un lien de confirmation vient d’être envoyé à ' . $email
                            . ' : cette adresse devra être confirmée avant votre prochaine connexion.';
                    }
                }
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Nom d’utilisateur ou email déjà utilisé.';
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Le changement d’adresse n’a pas pu être préparé : votre adresse actuelle est conservée.';
            }
        }
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $next = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $next === '' || $confirm === '') {
            $error = 'Tous les champs mot de passe sont requis.';
        } elseif (strlen($next) < 8) {
            $error = 'Le nouveau mot de passe doit contenir au moins 8 caracteres.';
        } elseif ($next !== $confirm) {
            $error = 'La confirmation ne correspond pas.';
        } else {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$user['id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($current, (string)$row['password_hash'])) {
                $error = 'Mot de passe actuel incorrect.';
            } else {
                $upd = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $upd->execute([password_hash($next, PASSWORD_DEFAULT), (int)$user['id']]);
                // Un identifiant de session capté avant le changement ne doit
                // pas rester valide après.
                session_regenerate_id(true);
                $message = 'Mot de passe mis a jour.';
            }
        }
    } elseif ($action === 'unpublish_design') {
        $designId = (int)($_POST['design_id'] ?? 0);
        if ($designId <= 0) {
            $error = 'Publication invalide.';
        } else {
            $stmt = $db->prepare("UPDATE learning_designs SET is_published = 0 WHERE id = ? AND owner_user_id = ? AND is_published = 1");
            $stmt->execute([$designId, (int)$user['id']]);
            $message = $stmt->rowCount() > 0
                ? 'Publication supprimée.'
                : 'Publication introuvable ou deja supprimée.';
        }
    } elseif ($action === 'create_cli_token') {
        $tokenName = trim((string)($_POST['cli_token_name'] ?? 'CLI'));
        if ($tokenName === '') {
            $tokenName = 'CLI';
        }
        $tokenName = mb_substr($tokenName, 0, 120, 'UTF-8');
        $newCliToken = 'ld_' . bin2hex(random_bytes(32));
        $newCliTokenName = $tokenName;
        $stmt = $db->prepare("INSERT INTO learning_cli_tokens (user_id, name, token_hash, token_prefix) VALUES (?, ?, ?, ?)");
        $stmt->execute([(int)$user['id'], $tokenName, hash('sha256', $newCliToken), substr($newCliToken, 0, 12)]);
        $message = 'Jeton CLI créé. Copiez-le maintenant : il ne sera plus affiché.';
    } elseif ($action === 'revoke_cli_token') {
        $tokenId = (int)($_POST['cli_token_id'] ?? 0);
        if ($tokenId <= 0) {
            $error = 'Jeton CLI invalide.';
        } else {
            $stmt = $db->prepare("UPDATE learning_cli_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND revoked_at IS NULL");
            $stmt->execute([$tokenId, (int)$user['id']]);
            $message = $stmt->rowCount() > 0
                ? 'Jeton CLI révoqué.'
                : 'Jeton CLI introuvable ou deja révoqué.';
        }
    } elseif ($action === 'delete_account') {
        $password = (string)($_POST['delete_current_password'] ?? '');

        if ($password === '') {
            $error = 'Veuillez confirmer avec votre mot de passe.';
        } else {
            $stmt = $db->prepare("SELECT password_hash, role FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$user['id']]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($password, (string)$row['password_hash'])) {
                $error = 'Mot de passe incorrect.';
            } elseif ((string)$row['role'] === 'admin') {
                $countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?");
                $countStmt->execute([(int)$user['id']]);
                if ((int)$countStmt->fetchColumn() === 0) {
                    $error = 'Impossible de supprimer le dernier compte administrateur.';
                }
            }

            if ($error === '') {
                $del = $db->prepare("DELETE FROM users WHERE id = ?");
                $del->execute([(int)$user['id']]);
                $_SESSION = [];
                session_destroy();
                header('Location: login.php');
                exit;
            }
        }
    }
}

$stmt = $db->prepare("SELECT username, email, role FROM users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$me = $stmt->fetch() ?: ['username' => '', 'email' => '', 'role' => 'designer'];

$countStmt = $db->prepare("SELECT COUNT(*) FROM learning_designs WHERE owner_user_id = ?");
$countStmt->execute([(int)$user['id']]);
$designCount = (int)$countStmt->fetchColumn();

$publishedStmt = $db->prepare("SELECT id, title, share_token, updated_at FROM learning_designs WHERE owner_user_id = ? AND is_published = 1 AND share_token IS NOT NULL AND share_token <> '' ORDER BY updated_at DESC");
$publishedStmt->execute([(int)$user['id']]);
$publishedDesigns = $publishedStmt->fetchAll();

$cliTokensStmt = $db->prepare("SELECT id, name, token_prefix, created_at, last_used_at FROM learning_cli_tokens WHERE user_id = ? AND revoked_at IS NULL ORDER BY created_at DESC");
$cliTokensStmt->execute([(int)$user['id']]);
$cliTokens = $cliTokensStmt->fetchAll();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title>Profil | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260906-profile-role-spacing">
</head>
<body class="profile-page">
<?php render_site_nav('profile'); ?>
<main class="account-shell with-nav profile-shell">
    <section class="account-card wide">
        <div class="account-topbar">
            <div>
                <h1 id="profile-title" class="title-with-icon"><i class="fa-solid fa-user" aria-hidden="true"></i>Profil</h1>
            </div>
        </div>
        <p class="account-copy"><span id="profile-role-label">Rôle</span>&nbsp;: <?= e((string)$me['role']) ?></p>

        <?php if ($message !== ''): ?>
            <p class="account-message success" data-profile-flash><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="account-message error" data-profile-flash><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <div class="account-grid">
            <form method="post" class="account-form panel">
                <input type="hidden" name="action" value="identity">
                <h2 id="profile-info-title" class="title-with-icon"><i class="fa-solid fa-address-card" aria-hidden="true"></i>Informations</h2>
                <div class="field">
                    <label id="profile-username-label" for="username">Nom d’utilisateur</label>
                    <input id="username" name="username" type="text" value="<?= e((string)$me['username']) ?>" required>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= e((string)$me['email']) ?>" required>
                </div>
                <button id="profile-save-identity" type="submit">Enregistrer</button>
            </form>

            <form method="post" class="account-form panel">
                <input type="hidden" name="action" value="password">
                <h2 id="profile-password-title" class="title-with-icon"><i class="fa-solid fa-key" aria-hidden="true"></i>Mot de passe</h2>
                <div class="field">
                    <label id="profile-current-password-label" for="current_password">Mot de passe actuel</label>
                    <input id="current_password" name="current_password" type="password" required>
                </div>
                <div class="field">
                    <label id="profile-new-password-label" for="new_password">Nouveau mot de passe</label>
                    <input id="new_password" name="new_password" type="password" minlength="8" required>
                </div>
                <div class="field">
                    <label id="profile-confirm-password-label" for="confirm_password">Confirmation</label>
                    <input id="confirm_password" name="confirm_password" type="password" minlength="8" required>
                </div>
                <button id="profile-update-password" type="submit">Mettre à jour</button>
            </form>
        </div>

        <section class="panel profile-productions" aria-labelledby="profile-productions-title">
            <div class="profile-section-head">
                <div>
                    <h2 id="profile-productions-title" class="title-with-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i>Mes productions</h2>
                    <p id="profile-productions-copy" class="account-copy">Suivez vos sauvegardes et les scénarios publiés avec un lien web.</p>
                </div>
            </div>

            <div class="profile-progress-row">
                <a class="profile-stat" href="my-designs.php">
                    <span class="profile-stat-value"><?= $designCount ?></span>
                    <span class="profile-stat-label" data-profile-design-count="<?= $designCount ?>">scénario<?= $designCount > 1 ? 's' : '' ?> enregistré<?= $designCount > 1 ? 's' : '' ?></span>
                </a>
                <a id="profile-export-link" class="profile-primary-link" href="export_scenarios.php"><i class="fa-solid fa-file-export" aria-hidden="true"></i>Exporter tous mes scénarios</a>
                <a id="profile-competencies-link" class="profile-primary-link" href="competencies.php"><i class="fa-solid fa-table-list" aria-hidden="true"></i>Voir mes compétences</a>
            </div>

            <h3 id="profile-publications-title" class="profile-subtitle title-with-icon"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i>Publications actives</h3>
            <?php if (!$publishedDesigns): ?>
                <p id="profile-empty-publications" class="profile-empty">Aucun scénario publié pour le moment.</p>
            <?php else: ?>
                <div class="profile-publication-list">
                    <?php foreach ($publishedDesigns as $design): ?>
                        <?php $shareUrl = app_base_url() . '/view.php?token=' . urlencode((string)$design['share_token']); ?>
                        <article class="profile-publication">
                            <div class="profile-publication-main">
                                <h4><?= e((string)$design['title']) ?></h4>
                                <a href="<?= e($shareUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($shareUrl) ?></a>
                                <p><span data-profile-updated-label>Dernière mise à jour</span> : <?= e((string)$design['updated_at']) ?></p>
                            </div>
                            <form method="post" class="profile-publication-actions" data-confirm-fr="Supprimer cette publication et désactiver son lien public ?" data-confirm-en="Delete this publication and disable its public link?" onsubmit="return window.confirm(this.dataset.confirm || this.dataset.confirmFr);">
                                <input type="hidden" name="action" value="unpublish_design">
                                <input type="hidden" name="design_id" value="<?= (int)$design['id'] ?>">
                                <button class="btn-icon-danger" type="submit" title="Supprimer la publication" aria-label="Supprimer la publication" data-profile-delete-publication-btn>
                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                </button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel profile-cli" aria-labelledby="profile-cli-title">
            <div class="profile-section-head">
                <div>
                    <h2 id="profile-cli-title" class="title-with-icon"><i class="fa-solid fa-terminal" aria-hidden="true"></i>Publication depuis le CLI</h2>
                    <p id="profile-cli-copy" class="account-copy">Créez un jeton personnel pour publier depuis la commande <code>scenarisation publish</code>.</p>
                </div>
            </div>

            <?php if ($newCliToken !== ''): ?>
                <div class="account-message success" data-profile-flash>
                    <strong>Jeton <?= e($newCliTokenName) ?> :</strong>
                    <code><?= e($newCliToken) ?></code>
                    <br>
                    <span>Commande :</span>
                    <code>scenarisation login --site <?= e(app_base_url()) ?></code>
                </div>
            <?php endif; ?>

            <form method="post" class="account-form">
                <input type="hidden" name="action" value="create_cli_token">
                <div class="field">
                    <label id="profile-cli-token-name-label" for="cli_token_name">Nom du jeton</label>
                    <input id="cli_token_name" name="cli_token_name" type="text" placeholder="Donnez un nom à votre jeton">
                </div>
                <button id="profile-cli-create-button" type="submit">Créer un jeton CLI</button>
            </form>

            <h3 id="profile-cli-active-title" class="profile-subtitle title-with-icon"><i class="fa-solid fa-key" aria-hidden="true"></i>Jetons actifs</h3>
            <?php if (!$cliTokens): ?>
                <p id="profile-cli-empty" class="profile-empty">Aucun jeton CLI actif.</p>
            <?php else: ?>
                <div class="profile-publication-list">
                    <?php foreach ($cliTokens as $token): ?>
                        <article class="profile-publication">
                            <div class="profile-publication-main">
                                <h4><?= e((string)$token['name']) ?></h4>
                                <p>Préfixe : <code><?= e((string)$token['token_prefix']) ?>…</code></p>
                                <p>Créé : <?= e((string)$token['created_at']) ?></p>
                                <p>Dernière utilisation : <?= e((string)($token['last_used_at'] ?? 'Jamais')) ?></p>
                            </div>
                            <form method="post" class="profile-publication-actions" data-confirm-fr="Révoquer ce jeton CLI ?" data-confirm-en="Revoke this CLI token?" onsubmit="return window.confirm(this.dataset.confirm || this.dataset.confirmFr);">
                                <input type="hidden" name="action" value="revoke_cli_token">
                                <input type="hidden" name="cli_token_id" value="<?= (int)$token['id'] ?>">
                                <button class="btn-icon-danger" type="submit" title="Révoquer le jeton" aria-label="Révoquer le jeton">
                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                </button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <form method="post" class="account-form panel danger-panel" data-confirm-fr="Supprimer définitivement votre compte ?" data-confirm-en="Permanently delete your account?" onsubmit="return window.confirm(this.dataset.confirm || this.dataset.confirmFr);">
            <input type="hidden" name="action" value="delete_account">
            <h2 id="profile-delete-title" class="title-with-icon"><i class="fa-solid fa-trash-can" aria-hidden="true"></i>Supprimer mon compte</h2>
            <p id="profile-delete-copy" class="account-copy">Toutes vos productions seront supprimées avec votre compte.</p>
            <div class="field">
                <label id="profile-delete-password-label" for="delete_current_password">Mot de passe actuel</label>
                <input id="delete_current_password" name="delete_current_password" type="password" required>
            </div>
            <button id="profile-delete-button" type="submit" class="danger-button">Supprimer mon compte</button>
        </form>
    </section>
</main>
<?php render_site_footer(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var translations = {
        'profile-title': 'Profile',
        'profile-role-label': 'Role',
        'profile-info-title': 'Information',
        'profile-username-label': 'Username',
        'profile-save-identity': 'Save',
        'profile-password-title': 'Password',
        'profile-current-password-label': 'Current password',
        'profile-new-password-label': 'New password',
        'profile-confirm-password-label': 'Confirmation',
        'profile-update-password': 'Update',
        'profile-productions-title': 'My scenarios',
        'profile-productions-copy': 'Track your saved scenarios and scenarios published with a web link.',
        'profile-export-link': 'Export all my scenarios',
        'profile-competencies-link': 'View my competencies',
        'profile-publications-title': 'Active publications',
        'profile-empty-publications': 'No published scenario yet.',
        'profile-cli-title': 'CLI publishing',
        'profile-cli-copy': 'Create a personal token to publish with the `scenarisation publish` command.',
        'profile-cli-token-name-label': 'Token name',
        'profile-cli-create-button': 'Create CLI token',
        'profile-cli-active-title': 'Active tokens',
        'profile-cli-empty': 'No active CLI token.',
        'profile-delete-title': 'Delete my account',
        'profile-delete-copy': 'All your scenarios will be deleted with your account.',
        'profile-delete-password-label': 'Current password',
        'profile-delete-button': 'Delete my account'
    };

    var flashTranslations = {
        'Nom d’utilisateur et email requis.': 'Username and email are required.',
        'Adresse email invalide.': 'Invalid email address.',
        'Informations mises a jour.': 'Information updated.',
        'Nom d’utilisateur ou email deja utilise.': 'Username or email already in use.',
        'Tous les champs mot de passe sont requis.': 'All password fields are required.',
        'Le nouveau mot de passe doit contenir au moins 8 caracteres.': 'The new password must be at least 8 characters long.',
        'La confirmation ne correspond pas.': 'The confirmation does not match.',
        'Mot de passe actuel incorrect.': 'Current password is incorrect.',
        'Mot de passe mis a jour.': 'Password updated.',
        'Publication invalide.': 'Invalid publication.',
        'Publication supprimée.': 'Publication deleted.',
        'Publication introuvable ou deja supprimée.': 'Publication not found or already deleted.',
        'Jeton CLI créé. Copiez-le maintenant : il ne sera plus affiché.': 'CLI token created. Copy it now: it will not be shown again.',
        'Jeton CLI invalide.': 'Invalid CLI token.',
        'Jeton CLI révoqué.': 'CLI token revoked.',
        'Jeton CLI introuvable ou deja révoqué.': 'CLI token not found or already revoked.',
        'Veuillez confirmer avec votre mot de passe.': 'Please confirm with your password.',
        'Mot de passe incorrect.': 'Incorrect password.',
        'Impossible de supprimer le dernier compte administrateur.': 'The last administrator account cannot be deleted.'
    };

    function setIconText(id, text) {
        var el = document.getElementById(id);
        if (!el) return;
        var icon = el.querySelector('i');
        el.textContent = '';
        if (icon) {
            el.appendChild(icon);
            el.appendChild(document.createTextNode(' '));
            el.appendChild(document.createTextNode(text));
        } else {
            el.textContent = text;
        }
    }

    Object.keys(translations).forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.dataset.profileFr = el.textContent.trim();
        }
    });

    document.querySelectorAll('[data-profile-flash]').forEach(function (el) {
        el.dataset.profileFr = el.textContent.trim();
    });

    function applyProfileLanguage(lang) {
        document.documentElement.lang = lang === 'en' ? 'en' : 'fr';
        document.title = lang === 'en' ? 'Profile | Scenarisation' : 'Profil | Scenarisation';
        document.getElementById('cli_token_name').placeholder = lang === 'en'
            ? 'Give your token a name'
            : 'Donnez un nom à votre jeton';

        Object.keys(translations).forEach(function (id) {
            var el = document.getElementById(id);
            var text = lang === 'en' ? translations[id] : (el?.dataset.profileFr || '');
            if (text) setIconText(id, text);
        });

        document.querySelectorAll('[data-profile-design-count]').forEach(function (el) {
            var count = Number(el.getAttribute('data-profile-design-count') || '0');
            if (lang === 'en') {
                el.textContent = count === 1 ? 'saved scenario' : 'saved scenarios';
            } else {
                el.textContent = 'scénario' + (count > 1 ? 's' : '') + ' enregistré' + (count > 1 ? 's' : '');
            }
        });

        document.querySelectorAll('[data-profile-updated-label]').forEach(function (el) {
            el.textContent = lang === 'en' ? 'Last updated' : 'Dernière mise à jour';
        });

        document.querySelectorAll('[data-profile-delete-publication-btn]').forEach(function (button) {
            var label = lang === 'en' ? 'Delete publication' : 'Supprimer la publication';
            button.title = label;
            button.setAttribute('aria-label', label);
        });

        document.querySelectorAll('[data-confirm-fr]').forEach(function (form) {
            form.dataset.confirm = lang === 'en' ? form.dataset.confirmEn : form.dataset.confirmFr;
        });

        document.querySelectorAll('[data-profile-flash]').forEach(function (el) {
            var text = el.dataset.profileFr || el.textContent.trim();
            el.textContent = lang === 'en' && flashTranslations[text] ? flashTranslations[text] : text;
        });
    }

    var lang = 'fr';
    try {
        lang = localStorage.getItem('learningDesignerLang') || 'fr';
    } catch (error) {
        lang = 'fr';
    }
    applyProfileLanguage(lang);

    var langSelect = document.getElementById('lang-select');
    if (langSelect) {
        langSelect.addEventListener('change', function () {
            applyProfileLanguage(langSelect.value);
        });
    }
});
</script>
</body>
</html>
