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
            ? 'Scenario deleted.'
            : 'Scenario not found.';
        $flashKind = $stmt->rowCount() > 0 ? 'success' : 'warning';
    } elseif ($action === 'unlist' && $designId > 0) {
        $stmt = $db->prepare('UPDATE learning_designs
            SET is_listed = 0, listed_at = NULL
            WHERE id = ? AND owner_user_id = ? AND is_published = 1 AND is_listed = 1');
        $stmt->execute([$designId, (int)$user['id']]);
        $flashMessage = $stmt->rowCount() > 0
            ? 'Scénario retiré de la page des partages. Son lien reste actif.'
            : 'Publication introuvable ou déjà retirée des partages.';
        $flashMessageEn = $stmt->rowCount() > 0
            ? 'Scenario removed from the shared catalog. Its link remains active.'
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
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any" />
    <title data-site-i18n-en="My scenarios | Scenarisation" data-site-i18n-fr="Mes scénarios | Scenarisation">Mes scénarios | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus" />
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight" />
    <link rel="stylesheet" href="css/account-pages.css?v=20260910-saved-table" />
    <link rel="stylesheet" href="css/my-designs.css?v=20260910-no-heading-divider" />
  </head>
  <body class="designs-page">
    <?php render_site_nav('saves'); ?>
    <main class="saved-shell">
      <div class="saved-header">
        <div>
          <h1 class="saved-title" data-site-i18n-en="My scenarios" data-site-i18n-fr="Mes scénarios">Mes scénarios</h1>
          <p class="saved-subtitle" data-site-i18n-en="Find, open, or delete your saved scenarios." data-site-i18n-fr="Retrouvez, ouvrez ou supprimez vos scénarios enregistrés.">Retrouvez, ouvrez ou supprimez vos scénarios enregistrés.</p>
        </div>
      </div>

      <?php if ($flashMessage !== ''): ?>
        <p class="saved-flash saved-flash-<?= e($flashKind) ?>" data-site-i18n-en="<?= e($flashMessageEn) ?>" data-site-i18n-fr="<?= e($flashMessage) ?>"><?= e($flashMessage) ?></p>
      <?php endif; ?>

      <?php if (!$items): ?>
        <p class="saved-empty"
          data-site-i18n-en="No saved scenarios yet. Return to the editor and use the Save button."
          data-site-i18n-fr="Aucune sauvegarde pour le moment. Revenez dans l’éditeur puis utilisez le bouton Enregistrer.">Aucune sauvegarde pour le moment. Revenez dans l’éditeur puis utilisez le bouton Enregistrer.</p>
      <?php else: ?>
        <section class="saved-grid" aria-label="Liste des productions sauvegardées"
          data-site-i18n-attr="aria-label" data-site-i18n-en="Saved scenarios list" data-site-i18n-fr="Liste des productions sauvegardées">
          <div class="saved-table-scroll">
          <table class="profile-publications-table saved-table" aria-label="Scénarios enregistrés" data-site-i18n-attr="aria-label" data-site-i18n-en="Saved scenarios" data-site-i18n-fr="Scénarios enregistrés">
            <thead><tr>
              <th scope="col" data-site-i18n-en="Scenario" data-site-i18n-fr="Scénario">Scénario</th>
              <th scope="col" data-site-i18n-en="Last updated" data-site-i18n-fr="Dernière mise à jour">Dernière mise à jour</th>
              <th scope="col" data-site-i18n-en="Created on" data-site-i18n-fr="Créée le">Créée le</th>
              <th scope="col" data-site-i18n-en="Visibility" data-site-i18n-fr="Visibilité">Visibilité</th>
              <th scope="col" data-site-i18n-en="Actions" data-site-i18n-fr="Actions">Actions</th>
            </tr></thead>
            <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <h2 class="saved-card-title"><?= e((string)$item['title']) ?></h2>
              </td>
              <td class="saved-card-meta"><?= e((string)$item['updated_at']) ?></td>
              <td class="saved-card-meta"><?= e((string)$item['created_at']) ?></td>
              <td>
                <?php
                  $isPublished = (bool)$item['is_published'];
                  $isListed = (bool)$item['is_listed'];
                  $statusTextFr = $isListed ? 'Visible dans les partages' : ($isPublished ? 'Publié par lien' : 'Privé');
                  $statusTextEn = $isListed ? 'Listed in the shared catalog' : ($isPublished ? 'Shared by link' : 'Private');
                  $statusIcon = $isListed ? 'fa-solid fa-share-nodes' : ($isPublished ? 'fa-regular fa-eye' : 'fa-solid fa-lock');
                ?>
                <span class="saved-status"><i class="<?= e($statusIcon) ?>" aria-hidden="true"></i><span data-site-i18n-en="<?= e($statusTextEn) ?>" data-site-i18n-fr="<?= e($statusTextFr) ?>"><?= e($statusTextFr) ?></span></span>
              </td>
              <td><div class="saved-card-actions">
                <a class="btn btn-primary saved-action-btn" href="designer.php?remote_design_id=<?= (int)$item['id'] ?>"
                  aria-label="Ouvrir le scénario" title="Ouvrir le scénario"
                  data-site-i18n-attr="aria-label,title" data-site-i18n-en="Open scenario" data-site-i18n-fr="Ouvrir le scénario">
                  <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                </a>
                <?php if ($isPublished && trim((string)$item['share_token']) !== ''): ?>
                  <a class="btn btn-light saved-action-btn" href="view.php?token=<?= urlencode((string)$item['share_token']) ?>" target="_blank" rel="noopener noreferrer"
                    aria-label="Voir le scénario partagé" title="Voir le scénario partagé"
                    data-site-i18n-attr="aria-label,title" data-site-i18n-en="View shared scenario" data-site-i18n-fr="Voir le scénario partagé">
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                  </a>
                <?php endif; ?>
                <?php if ($isListed): ?>
                  <form class="saved-action-confirm" method="post" action="my-designs.php"
                    data-confirm-fr="Retirer ce scénario de la page des partages ? Son lien de consultation restera actif."
                    data-confirm-en="Remove this scenario from the shared catalog? Its view link will remain active.">
                    <input type="hidden" name="action" value="unlist" />
                    <input type="hidden" name="design_id" value="<?= (int)$item['id'] ?>" />
                    <button class="btn btn-light saved-action-btn saved-action-unlist" type="submit"
                      aria-label="Retirer le scénario des partages" title="Retirer le scénario des partages"
                      data-site-i18n-attr="aria-label,title" data-site-i18n-en="Remove scenario from shared catalog" data-site-i18n-fr="Retirer le scénario des partages">
                      <i class="fa-regular fa-eye-slash" aria-hidden="true"></i>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($isPublished): ?>
                  <form class="saved-action-confirm" method="post" action="my-designs.php"
                    data-confirm-fr="Révoquer ce lien de partage ? Il cessera immédiatement de fonctionner et le scénario sera retiré des partages."
                    data-confirm-en="Revoke this share link? It will stop working immediately and the scenario will be removed from the shared catalog.">
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
                  data-confirm-fr="Supprimer définitivement ce scénario ? Cette action est irréversible."
                  data-confirm-en="Permanently delete this scenario? This action cannot be undone.">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="design_id" value="<?= (int)$item['id'] ?>" />
                  <button class="btn btn-light saved-action-btn saved-action-delete" type="submit"
                    aria-label="Supprimer le scénario" title="Supprimer le scénario"
                    data-site-i18n-attr="aria-label,title" data-site-i18n-en="Delete scenario" data-site-i18n-fr="Supprimer le scénario">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                  </button>
                </form>
              </div></td>
            </tr>
          <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </section>
      <?php endif; ?>
    </main>
    <?php render_site_footer(); ?>
    <dialog id="saved-action-dialog" class="modal account-confirm-dialog"
      aria-labelledby="saved-action-title" aria-describedby="saved-action-message">
      <h2 id="saved-action-title" class="modal-title"></h2>
      <p id="saved-action-message" class="account-confirm-message"></p>
      <div class="modal-actions">
        <button class="btn btn-light" type="button" data-saved-cancel autofocus></button>
        <button class="btn btn-primary" type="button" data-saved-confirm></button>
      </div>
    </dialog>
    <script>
      (function () {
        var dialog = document.getElementById('saved-action-dialog');
        var cancel = dialog.querySelector('[data-saved-cancel]');
        var confirm = dialog.querySelector('[data-saved-confirm]');
        var pendingForm = null;
        var pendingSubmitter = null;
        var previousFocus = null;
        var approvedForm = null;

        document.querySelectorAll('.saved-action-confirm').forEach(function (form) {
          form.addEventListener('submit', function (event) {
            if (approvedForm === form) return;
            event.preventDefault();
            if (pendingForm) return;
            var english = document.documentElement.lang === 'en';
            var deleting = form.elements.action.value === 'delete';
            dialog.querySelector('h2').textContent = deleting
              ? (english ? 'Delete scenario' : 'Supprimer le scénario')
              : (english ? 'Revoke share link' : 'Révoquer le lien de partage');
            dialog.querySelector('p').textContent = english ? form.dataset.confirmEn : form.dataset.confirmFr;
            cancel.textContent = english ? 'Cancel' : 'Annuler';
            confirm.textContent = deleting
              ? (english ? 'Delete' : 'Supprimer')
              : (english ? 'Revoke link' : 'Révoquer le lien');
            pendingForm = form;
            pendingSubmitter = event.submitter;
            previousFocus = event.submitter || document.activeElement;
            dialog.returnValue = '';
            dialog.showModal();
            cancel.focus();
          });
        });

        cancel.addEventListener('click', function () { dialog.close('cancel'); });
        confirm.addEventListener('click', function () { dialog.close('confirm'); });
        dialog.addEventListener('cancel', function (event) {
          event.preventDefault();
          dialog.close('cancel');
        });
        dialog.addEventListener('close', function () {
          var form = pendingForm;
          var submitter = pendingSubmitter;
          pendingForm = null;
          pendingSubmitter = null;
          if (previousFocus && previousFocus.isConnected) previousFocus.focus();
          if (dialog.returnValue === 'confirm' && form && form.isConnected) {
            approvedForm = form;
            try {
              form.requestSubmit(submitter || undefined);
            } finally {
              approvedForm = null;
            }
          }
        });
      }());
    </script>
  </body>
</html>
