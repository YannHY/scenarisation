<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$db = app_db();
$user = current_user();

$pageSize = 24;
$itemCount = (int)$db->query("SELECT COUNT(*)
    FROM learning_designs
    WHERE is_published = 1 AND is_listed = 1 AND share_token IS NOT NULL")->fetchColumn();
$pageCount = max(1, (int)ceil($itemCount / $pageSize));
$currentPage = min($pageCount, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($currentPage - 1) * $pageSize;

$stmt = $db->prepare("SELECT d.title, d.document_json, d.share_token, d.license_code, d.updated_at, d.listed_at, u.username
    FROM learning_designs d
    JOIN users u ON u.id = d.owner_user_id
    WHERE d.is_published = 1 AND d.is_listed = 1 AND d.share_token IS NOT NULL
    ORDER BY d.listed_at DESC, d.id DESC
    LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = [];

foreach ($stmt->fetchAll() as $row) {
    $document = json_decode((string)$row['document_json'], true);
    if (!is_array($document)) {
        $document = ['sessions' => [], 'meta' => []];
    }

    $meta = is_array($document['meta'] ?? null) ? $document['meta'] : [];
    $sessions = is_array($document['sessions'] ?? null) ? $document['sessions'] : [];
    $activityCount = 0;
    $duration = 0;
    foreach ($sessions as $session) {
        $activities = is_array($session['activities'] ?? null) ? $session['activities'] : [];
        $activityCount += count($activities);
        foreach ($activities as $activity) {
            $duration += max(0, (int)($activity['duration'] ?? 0));
        }
    }

    $title = trim((string)($meta['name'] ?? ''));
    $titleIsDefault = false;
    if ($title === '') {
        $title = trim((string)$row['title']);
    }
    if ($title === '') {
        $title = 'Production sans titre';
        $titleIsDefault = true;
    }

    $description = trim((string)($meta['description'] ?? ''));
    if ($description === '') {
        $description = trim((string)($meta['personas'] ?? ''));
    }
    if (mb_strlen($description, 'UTF-8') > 220) {
        $description = mb_substr($description, 0, 217, 'UTF-8') . '...';
    }

    $mode = trim((string)($meta['modeDelivery'] ?? ''));
    $designedDuration = share_designed_minutes($meta);
    $license = creative_commons_license((string)($row['license_code'] ?? ''));
    $items[] = [
        'title' => $title,
        'title_is_default' => $titleIsDefault,
        'description' => $description,
        'author' => (string)$row['username'],
        'token' => (string)$row['share_token'],
        'updated_at' => (string)$row['updated_at'],
        'session_count' => count($sessions),
        'activity_count' => $activityCount,
        'duration' => $designedDuration > 0 ? $designedDuration : $duration,
        'mode' => $mode,
        'license' => $license,
    ];
}

function share_designed_minutes(array $meta): int
{
    return max(0, (int)($meta['designedMinutes'] ?? 0));
}

function share_format_minutes(int $minutes, string $language = 'fr'): string
{
    if ($minutes <= 0) {
        return $language === 'en' ? 'Duration not specified' : 'Durée non précisée';
    }
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    if ($hours > 0 && $rest > 0) {
        return $hours . ' h ' . $rest . ' min';
    }
    if ($hours > 0) {
        return $hours . ' h';
    }
    return $rest . ' min';
}

function share_mode_label(string $mode, string $language = 'fr'): string
{
    if ($language === 'en') {
        return match ($mode) {
            'online' => 'Online',
            'hybrid' => 'Hybrid',
            'onsite' => 'Onsite',
            default => 'Mode not specified',
        };
    }

    return match ($mode) {
        'online' => 'Distanciel',
        'hybrid' => 'Hybride',
        'onsite' => 'Présentiel',
        default => 'Mode non précisé',
    };
}

function share_count_label(int $count, string $singular, ?string $plural = null): string
{
    return $count . ' ' . ($count === 1 ? $singular : ($plural ?? $singular . 's'));
}
?>
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any" />
    <title data-site-i18n-en="Shared designs | Scenarisation" data-site-i18n-fr="Designs partagés | Scenarisation">Designs partagés | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus" />
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight" />
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm" />
    <style>
      .shared-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 26px;
        margin-bottom: var(--page-section-gap);
      }

      .shared-shell {
        width: min(var(--content-shell-width, 1180px), calc(100vw - var(--content-shell-gutter, 36px)));
        padding-bottom: 56px;
      }

      .shared-subtitle {
        max-width: 760px;
        margin: 12px 0 0;
        color: var(--muted);
        line-height: var(--content-leading);
      }

      .shared-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: var(--page-section-gap);
      }

      .shared-card {
        display: flex;
        min-height: 260px;
        flex-direction: column;
        gap: 22px;
        padding: var(--card-padding);
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--panel-2);
      }

      .shared-card-title {
        margin: 0;
        line-height: 1.35;
      }

      .shared-card-copy {
        margin: 0;
        color: var(--muted);
        line-height: 1.68;
      }

      .shared-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 9px;
        margin-top: auto;
      }

      .shared-pill {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        gap: 5px;
        padding: 6px 7px;
        border: 1px solid var(--line);
        border-radius: 999px;
        color: var(--muted);
        font-size: 12px;
        white-space: nowrap;
        background: #fff;
      }

      .shared-meta a.shared-pill {
        text-decoration: none;
      }

      .shared-meta a.shared-pill:hover,
      .shared-meta a.shared-pill:focus {
        border-color: var(--accent);
        color: var(--accent);
      }

      .shared-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
      }

      .shared-actions form {
        margin: 0;
      }

      .shared-actions .shared-action-btn {
        width: 108px;
      }

      .shared-actions a.btn,
      .shared-actions a.btn:hover,
      .shared-actions a.btn:focus {
        text-decoration: none;
      }

      .shared-empty {
        margin: 0;
        padding: var(--card-padding);
        border: 1px dashed var(--line);
        border-radius: 8px;
        color: var(--muted);
        background: var(--surface-light);
      }

      .shared-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin-top: 32px;
      }

      .shared-pagination-status {
        color: var(--muted);
        font-size: 13px;
      }

      [data-theme="dark"] .shared-subtitle,
      [data-theme="dark"] .shared-card-copy,
      [data-theme="dark"] .shared-pill,
      [data-theme="dark"] .shared-empty {
        color: var(--text-body);
      }

      [data-theme="dark"] .shared-card,
      [data-theme="dark"] .shared-empty {
        border-color: var(--line);
        background: var(--panel-2);
      }

      [data-theme="dark"] .shared-pill {
        border-color: var(--line);
        background: var(--surface-light);
      }

      @media (max-width: 1140px) {
        .shared-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }

      @media (max-width: 760px) {
        .shared-header {
          align-items: stretch;
          flex-direction: column;
        }

        .shared-grid {
          grid-template-columns: 1fr;
        }

        .shared-shell {
          padding-bottom: 40px;
        }
      }

      @media (max-width: 580px) {
        .shared-meta {
          flex-wrap: wrap;
        }
      }
    </style>
  </head>
  <body class="shared-page">
    <?php render_site_nav('share'); ?>
    <main class="shared-shell">
      <div class="shared-header">
        <div>
          <h1 class="shared-title" data-site-i18n-en="Shared designs" data-site-i18n-fr="Designs partagés">Designs partagés</h1>
          <p class="shared-subtitle" data-site-i18n-en="Explore shared designs and import the ones you want to adapt." data-site-i18n-fr="Explorez les designs partagés et importez ceux que vous souhaitez adapter.">Explorez les designs partagés et importez ceux que vous souhaitez adapter.</p>
        </div>
      </div>

      <?php if (!$items): ?>
        <p class="shared-empty" data-site-i18n-en="No designs are currently visible in the shared designs catalog." data-site-i18n-fr="Aucun design n’est encore visible dans la page de partage.">Aucun design n’est encore visible dans la page de partage.</p>
      <?php else: ?>
        <section class="shared-grid" aria-label="Designs publiés dans le catalogue" data-site-i18n-attr="aria-label" data-site-i18n-en="Designs published in the catalog" data-site-i18n-fr="Designs publiés dans le catalogue">
          <?php foreach ($items as $item): ?>
            <article class="shared-card">
              <div>
                <?php if ($item['title_is_default']): ?>
                  <h2 class="shared-card-title" data-site-i18n-en="Untitled design" data-site-i18n-fr="Production sans titre">Production sans titre</h2>
                <?php else: ?>
                  <h2 class="shared-card-title"><?= h($item['title']) ?></h2>
                <?php endif; ?>
                <p class="shared-card-copy"><span data-site-i18n-en="By" data-site-i18n-fr="Par">Par</span> <?= h($item['author']) ?></p>
              </div>
              <?php if ($item['description'] !== ''): ?>
                <p class="shared-card-copy"><?= h($item['description']) ?></p>
              <?php endif; ?>
              <div class="shared-meta" aria-label="Résumé du design" data-site-i18n-attr="aria-label" data-site-i18n-en="Design summary" data-site-i18n-fr="Résumé du design">
                <span class="shared-pill"><i class="fa-regular fa-folder" aria-hidden="true"></i><span data-site-i18n-en="<?= h(share_count_label((int)$item['session_count'], 'session')) ?>" data-site-i18n-fr="<?= h(share_count_label((int)$item['session_count'], 'séance')) ?>"><?= h(share_count_label((int)$item['session_count'], 'séance')) ?></span></span>
                <span class="shared-pill"><i class="fa-solid fa-list-check" aria-hidden="true"></i><span data-site-i18n-en="<?= h(share_count_label((int)$item['activity_count'], 'activity', 'activities')) ?>" data-site-i18n-fr="<?= h(share_count_label((int)$item['activity_count'], 'activité')) ?>"><?= h(share_count_label((int)$item['activity_count'], 'activité')) ?></span></span>
                <span class="shared-pill"><i class="fa-regular fa-clock" aria-hidden="true"></i><span data-site-i18n-en="<?= h(share_format_minutes((int)$item['duration'], 'en')) ?>" data-site-i18n-fr="<?= h(share_format_minutes((int)$item['duration'])) ?>"><?= h(share_format_minutes((int)$item['duration'])) ?></span></span>
                <span class="shared-pill"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span data-site-i18n-en="<?= h(share_mode_label($item['mode'], 'en')) ?>" data-site-i18n-fr="<?= h(share_mode_label($item['mode'])) ?>"><?= h(share_mode_label($item['mode'])) ?></span></span>
                <?php if ($item['license']): ?>
                  <a class="shared-pill" href="<?= h($item['license']['url']) ?>" target="_blank" rel="license noopener noreferrer"><i class="fa-brands fa-creative-commons" aria-hidden="true"></i><?= h($item['license']['label']) ?></a>
                <?php endif; ?>
              </div>
              <div class="shared-actions">
                <a class="btn btn-light shared-action-btn" href="view.php?token=<?= urlencode($item['token']) ?>">
                  <span class="btn-label"><i class="fa-regular fa-eye btn-icon-inline" aria-hidden="true"></i><span data-site-i18n-en="View" data-site-i18n-fr="Voir">Voir</span></span>
                </a>
                <?php if ($user): ?>
                  <form method="post" action="import_shared_design.php">
                    <input type="hidden" name="token" value="<?= h($item['token']) ?>" />
                    <button class="btn btn-primary shared-action-btn" type="submit">
                      <span class="btn-label"><i class="fa-solid fa-file-import btn-icon-inline" aria-hidden="true"></i><span data-site-i18n-en="Reuse" data-site-i18n-fr="Réutiliser">Réutiliser</span></span>
                    </button>
                  </form>
                <?php else: ?>
                  <a class="btn btn-primary" href="login.php">
                    <span class="btn-label"><i class="fa-regular fa-user btn-icon-inline" aria-hidden="true"></i><span data-site-i18n-en="Sign in to import" data-site-i18n-fr="Se connecter pour importer">Se connecter pour importer</span></span>
                  </a>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
        <?php if ($pageCount > 1): ?>
          <nav class="shared-pagination" aria-label="Pagination du catalogue" data-site-i18n-attr="aria-label" data-site-i18n-en="Catalog pagination" data-site-i18n-fr="Pagination du catalogue">
            <?php if ($currentPage > 1): ?>
              <a class="btn btn-light" href="share.php?page=<?= $currentPage - 1 ?>" data-site-i18n-en="Previous" data-site-i18n-fr="Précédent">Précédent</a>
            <?php endif; ?>
            <span class="shared-pagination-status" data-site-i18n-en="Page <?= $currentPage ?> of <?= $pageCount ?>" data-site-i18n-fr="Page <?= $currentPage ?> sur <?= $pageCount ?>">Page <?= $currentPage ?> sur <?= $pageCount ?></span>
            <?php if ($currentPage < $pageCount): ?>
              <a class="btn btn-light" href="share.php?page=<?= $currentPage + 1 ?>" data-site-i18n-en="Next" data-site-i18n-fr="Suivant">Suivant</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </main>
    <?php render_site_footer(); ?>
  </body>
</html>
