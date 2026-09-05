<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$user = require_login_page();
$db = app_db();
$flashMessage = '';
$flashMessageEn = '';
$flashKind = 'info';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_same_origin_post(false);
    $designId = (int)($_POST['design_id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'delete' && $designId > 0) {
        $stmt = $db->prepare('DELETE FROM learning_designs WHERE id = ? AND owner_user_id = ?');
        $stmt->execute([$designId, (int)$user['id']]);
        $flashMessage = $stmt->rowCount() > 0
            ? 'Production supprimée.'
            : 'Production introuvable.';
        $flashMessageEn = $stmt->rowCount() > 0
            ? 'Design deleted.'
            : 'Design not found.';
        $flashKind = $stmt->rowCount() > 0 ? 'success' : 'warning';
    } elseif ($action === 'unlist' && $designId > 0) {
        $stmt = $db->prepare('UPDATE learning_designs
            SET is_listed = 0, listed_at = NULL
            WHERE id = ? AND owner_user_id = ? AND is_published = 1 AND is_listed = 1');
        $stmt->execute([$designId, (int)$user['id']]);
        $flashMessage = $stmt->rowCount() > 0
            ? 'Design retiré de la page des partages. Son lien reste actif.'
            : 'Publication introuvable ou déjà retirée des partages.';
        $flashMessageEn = $stmt->rowCount() > 0
            ? 'Design removed from the shared catalog. Its link remains active.'
            : 'Publication not found or already removed from the shared catalog.';
        $flashKind = $stmt->rowCount() > 0 ? 'success' : 'warning';
    } elseif ($action === 'revoke_share' && $designId > 0) {
        $stmt = $db->prepare('UPDATE learning_designs
            SET share_token = NULL, is_published = 0, is_listed = 0, listed_at = NULL
            WHERE id = ? AND owner_user_id = ? AND is_published = 1');
        $stmt->execute([$designId, (int)$user['id']]);
        $flashMessage = $stmt->rowCount() > 0
            ? 'Lien de partage révoqué.'
            : 'Publication introuvable ou lien déjà révoqué.';
        $flashMessageEn = $stmt->rowCount() > 0
            ? 'Share link revoked.'
            : 'Publication not found or link already revoked.';
        $flashKind = $stmt->rowCount() > 0 ? 'success' : 'warning';
    }
}

$stmt = $db->prepare('SELECT id, title, share_token, is_published, is_listed, updated_at, created_at FROM learning_designs WHERE owner_user_id = ? ORDER BY updated_at DESC');
$stmt->execute([(int)$user['id']]);
$items = $stmt->fetchAll();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="assets/favicon.svg?v=20260804" type="image/svg+xml" sizes="any" />
    <title data-site-i18n-en="My designs | Learning Designer" data-site-i18n-fr="Mes designs | Learning Designer">Mes designs | Learning Designer</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus" />
    <link rel="stylesheet" href="css/account-ui.css?v=20260903-pagefind-dark" />
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm" />
    <style>
      .saved-shell {
        border: 0;
        border-radius: 0;
      }

      .saved-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: var(--page-section-gap);
      }

      .saved-subtitle {
        margin: 10px 0 0;
        color: var(--muted);
        line-height: var(--content-leading);
      }

      .saved-flash {
        margin: 0 0 20px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid var(--line);
        background: var(--surface-light);
        color: var(--text-strong);
      }

      .saved-flash-success {
        border-color: rgba(20, 140, 80, 0.22);
      }

      .saved-flash-warning {
        border-color: rgba(209, 140, 19, 0.22);
      }

      .saved-grid {
        display: grid;
        gap: var(--page-section-gap);
      }

      .saved-empty {
        margin: 0;
        padding: var(--card-padding);
        border: 1px dashed var(--line);
        border-radius: 18px;
        color: var(--muted);
        background: var(--surface-light);
      }

      .saved-card {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 28px;
        padding: var(--card-padding);
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--panel-2);
      }

      .saved-card-title {
        margin: 0 0 10px;
      }

      .saved-card-meta {
        margin: 0;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.68;
      }

      .saved-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 14px;
        padding: 6px 9px;
        border: 1px solid var(--line);
        border-radius: 999px;
        color: var(--muted);
        font-size: 13px;
        background: var(--surface-light);
      }

      .saved-card-actions {
        display: inline-flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
      }

      .saved-card-actions form {
        margin: 0;
      }

      .saved-card-actions a.btn,
      .saved-card-actions a.btn:hover,
      .saved-card-actions a.btn:focus {
        text-decoration: none;
      }

      .saved-action-btn {
        width: 40px;
        min-width: 40px;
        height: 40px;
        padding: 0;
      }

      .saved-action-delete:hover,
      .saved-action-delete:focus-visible,
      .saved-action-revoke:hover,
      .saved-action-revoke:focus-visible {
        border-color: rgba(184, 54, 69, 0.28);
        color: var(--danger);
        background: rgba(184, 54, 69, 0.08);
      }

      .saved-action-unlist:hover,
      .saved-action-unlist:focus-visible {
        border-color: rgba(209, 140, 19, 0.32);
        color: #a45f00;
        background: rgba(209, 140, 19, 0.1);
      }

      [data-theme="dark"] .saved-subtitle,
      [data-theme="dark"] .saved-card-meta,
      [data-theme="dark"] .saved-status,
      [data-theme="dark"] .saved-empty {
        color: var(--text-body);
      }

      [data-theme="dark"] .saved-flash {
        border-color: var(--line);
        background: var(--surface-light);
        color: #e8edf5;
      }

      [data-theme="dark"] .saved-flash-success {
        border-color: rgba(106, 176, 255, 0.28);
      }

      [data-theme="dark"] .saved-flash-warning {
        border-color: rgba(251, 191, 36, 0.24);
        color: #fde68a;
      }

      [data-theme="dark"] .saved-empty {
        border-color: var(--line);
        background: var(--surface-light);
      }

      [data-theme="dark"] .saved-card {
        border-color: var(--line);
        background: var(--panel-2);
      }

      [data-theme="dark"] .saved-status {
        border-color: var(--line);
        background: var(--surface-light);
      }

      @media (max-width: 760px) {
        .saved-header,
        .saved-card {
          flex-direction: column;
          align-items: stretch;
        }
      }
    </style>
  </head>
  <body class="designs-page">
    <?php render_site_nav('saves'); ?>
    <main class="saved-shell">
      <div class="saved-header">
        <div>
          <h1 class="saved-title" data-site-i18n-en="My designs" data-site-i18n-fr="Mes designs">Mes designs</h1>
          <p class="saved-subtitle" data-site-i18n-en="Find, open, or delete your saved designs." data-site-i18n-fr="Retrouvez, ouvrez ou supprimez vos designs enregistrés.">Retrouvez, ouvrez ou supprimez vos designs enregistrés.</p>
        </div>
      </div>

      <?php if ($flashMessage !== ''): ?>
        <p class="saved-flash saved-flash-<?= e($flashKind) ?>" data-site-i18n-en="<?= e($flashMessageEn) ?>" data-site-i18n-fr="<?= e($flashMessage) ?>"><?= e($flashMessage) ?></p>
      <?php endif; ?>

      <?php if (!$items): ?>
        <p class="saved-empty"
          data-site-i18n-en="No saved designs yet. Return to the editor and use the Save button."
          data-site-i18n-fr="Aucune sauvegarde pour le moment. Revenez dans l’éditeur puis utilisez le bouton Enregistrer.">Aucune sauvegarde pour le moment. Revenez dans l’éditeur puis utilisez le bouton Enregistrer.</p>
      <?php else: ?>
        <section class="saved-grid" aria-label="Liste des productions sauvegardées"
          data-site-i18n-attr="aria-label" data-site-i18n-en="Saved designs list" data-site-i18n-fr="Liste des productions sauvegardées">
          <?php foreach ($items as $item): ?>
            <article class="saved-card">
              <div>
                <h2 class="saved-card-title"><?= e((string)$item['title']) ?></h2>
                <p class="saved-card-meta">
                  <span data-site-i18n-en="Last updated:" data-site-i18n-fr="Dernière mise à jour :">Dernière mise à jour :</span>
                  <?= e((string)$item['updated_at']) ?><br />
                  <span data-site-i18n-en="Created:" data-site-i18n-fr="Créée le :">Créée le :</span>
                  <?= e((string)$item['created_at']) ?>
                </p>
                <?php
                  $isPublished = (bool)$item['is_published'];
                  $isListed = (bool)$item['is_listed'];
                  $statusTextFr = $isListed ? 'Visible dans les partages' : ($isPublished ? 'Publié par lien' : 'Privé');
                  $statusTextEn = $isListed ? 'Listed in the shared catalog' : ($isPublished ? 'Shared by link' : 'Private');
                  $statusIcon = $isListed ? 'fa-solid fa-share-nodes' : ($isPublished ? 'fa-regular fa-eye' : 'fa-solid fa-lock');
                ?>
                <span class="saved-status"><i class="<?= e($statusIcon) ?>" aria-hidden="true"></i><span data-site-i18n-en="<?= e($statusTextEn) ?>" data-site-i18n-fr="<?= e($statusTextFr) ?>"><?= e($statusTextFr) ?></span></span>
              </div>
              <div class="saved-card-actions">
                <a class="btn btn-primary saved-action-btn" href="designer.php?remote_design_id=<?= (int)$item['id'] ?>"
                  aria-label="Ouvrir le design" title="Ouvrir le design"
                  data-site-i18n-attr="aria-label,title" data-site-i18n-en="Open design" data-site-i18n-fr="Ouvrir le design">
                  <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                </a>
                <?php if ($isPublished && trim((string)$item['share_token']) !== ''): ?>
                  <a class="btn btn-light saved-action-btn" href="view.php?token=<?= urlencode((string)$item['share_token']) ?>" target="_blank" rel="noopener noreferrer"
                    aria-label="Voir le design partagé" title="Voir le design partagé"
                    data-site-i18n-attr="aria-label,title" data-site-i18n-en="View shared design" data-site-i18n-fr="Voir le design partagé">
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                  </a>
                <?php endif; ?>
                <?php if ($isListed): ?>
                  <form class="saved-action-confirm" method="post" action="my-designs.php"
                    data-confirm-fr="Retirer ce design de la page des partages ? Son lien de consultation restera actif."
                    data-confirm-en="Remove this design from the shared catalog? Its view link will remain active.">
                    <input type="hidden" name="action" value="unlist" />
                    <input type="hidden" name="design_id" value="<?= (int)$item['id'] ?>" />
                    <button class="btn btn-light saved-action-btn saved-action-unlist" type="submit"
                      aria-label="Retirer le design des partages" title="Retirer le design des partages"
                      data-site-i18n-attr="aria-label,title" data-site-i18n-en="Remove design from shared catalog" data-site-i18n-fr="Retirer le design des partages">
                      <i class="fa-regular fa-eye-slash" aria-hidden="true"></i>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($isPublished): ?>
                  <form class="saved-action-confirm" method="post" action="my-designs.php"
                    data-confirm-fr="Révoquer ce lien de partage ? Il cessera immédiatement de fonctionner et le design sera retiré des partages."
                    data-confirm-en="Revoke this share link? It will stop working immediately and the design will be removed from the shared catalog.">
                    <input type="hidden" name="action" value="revoke_share" />
                    <input type="hidden" name="design_id" value="<?= (int)$item['id'] ?>" />
                    <button class="btn btn-light saved-action-btn saved-action-revoke" type="submit"
                      aria-label="Révoquer le lien de partage" title="Révoquer le lien de partage"
                      data-site-i18n-attr="aria-label,title" data-site-i18n-en="Revoke share link" data-site-i18n-fr="Révoquer le lien de partage">
                      <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
                    </button>
                  </form>
                <?php endif; ?>
                <form class="saved-action-confirm" method="post" action="my-designs.php"
                  data-confirm-fr="Supprimer définitivement ce design ? Cette action est irréversible."
                  data-confirm-en="Permanently delete this design? This action cannot be undone.">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="design_id" value="<?= (int)$item['id'] ?>" />
                  <button class="btn btn-light saved-action-btn saved-action-delete" type="submit"
                    aria-label="Supprimer le design" title="Supprimer le design"
                    data-site-i18n-attr="aria-label,title" data-site-i18n-en="Delete design" data-site-i18n-fr="Supprimer le design">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                  </button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </main>
    <?php render_site_footer(); ?>
    <script>
      document.querySelectorAll('.saved-action-confirm').forEach(function (form) {
        form.addEventListener('submit', function (event) {
          var lang = document.documentElement.lang === 'en' ? 'en' : 'fr';
          var message = lang === 'en' ? form.dataset.confirmEn : form.dataset.confirmFr;
          if (message && !window.confirm(message)) {
            event.preventDefault();
          }
        });
      });
    </script>
  </body>
</html>
