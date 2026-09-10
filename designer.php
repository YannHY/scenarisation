<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

app_start_session();
?>
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any" />
    <title>Interface de conception | Scenarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260910-bloom-on-demand" />
  </head>
  <body class="designer-page">
    <a id="skip-link" class="skip-link" href="#board">Aller au contenu principal</a>
    <p id="sr-status" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></p>
    <?php render_site_nav('designer'); ?>
    <section id="top-panel" class="top-panel collapsed" role="region" aria-labelledby="app-title">
      <div class="top-brand">
        <div class="brand-left">
          <h1 id="app-title" class="brand-title">Interface de conception d’apprentissage</h1>
        </div>
        <div class="top-brand-actions">
          <div class="top-tabs" role="tablist" aria-label="Vues du panneau supérieur">
            <span class="top-tab-slider" aria-hidden="true"></span>
            <button id="top-tab-settings" class="top-tab" type="button" role="tab" aria-selected="false" aria-controls="timeline-view">Paramètres</button>
            <button id="top-tab-analysis" class="top-tab active" type="button" role="tab" aria-selected="true" aria-controls="analysis-view">Analyse</button>
            <button id="top-tab-chronology" class="top-tab" type="button" role="tab" aria-selected="false" aria-controls="chronology-view">Chronologie</button>
          </div>
          <button id="top-panel-toggle-btn" class="top-panel-toggle" type="button" aria-expanded="false" aria-controls="top-panel-body" aria-label="Déplier le panneau" title="Déplier le panneau">
            <svg class="chevron" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
          </button>
        </div>
      </div>
      <div id="top-panel-body" class="top-panel-body" aria-hidden="true" inert>
        <div id="timeline-view" class="panel-grid" role="tabpanel" aria-labelledby="top-tab-settings">
          <div class="panel-column panel-column-left">
            <div class="form-row">
              <label id="label-meta-name" for="meta-name" data-tooltip-i18n="metaNameTooltip">Titre</label>
              <input id="meta-name" class="panel-input" type="text" />
            </div>
            <div class="form-row">
              <label id="label-meta-learning" data-tooltip-i18n="metaLearningTooltip">Durée prévue</label>
              <div class="time-inline">
                <input id="meta-learning-days" class="panel-input" type="number" min="0" />
                <span id="unit-learning-days" class="time-unit">jours</span>
                <input id="meta-learning-hours" class="panel-input" type="number" min="0" />
                <span id="unit-learning-hours" class="time-unit">heures</span>
                <input id="meta-learning-minutes" class="panel-input" type="number" min="0" max="59" />
                <span id="unit-learning-minutes" class="time-unit">minutes</span>
              </div>
            </div>
            <div class="form-row">
              <label id="label-meta-designed" data-tooltip-i18n="metaDesignedTooltip">Durée conçue</label>
              <div class="time-inline">
                <input id="meta-designed-days" class="panel-input" type="number" readonly />
                <span id="unit-designed-days" class="time-unit">jours</span>
                <input id="meta-designed-hours" class="panel-input" type="number" readonly />
                <span id="unit-designed-hours" class="time-unit">heures</span>
                <input id="meta-designed-minutes" class="panel-input" type="number" readonly />
                <span id="unit-designed-minutes" class="time-unit">minutes</span>
              </div>
            </div>
            <div class="form-row">
              <label id="label-meta-day-hours" for="meta-day-hours" data-tooltip-i18n="metaDayTooltip">1 jour =</label>
              <div class="time-inline">
                <input id="meta-day-hours" class="panel-input" type="number" min="1" />
                <span id="unit-day-hours" class="time-unit">heures</span>
              </div>
            </div>
            <div class="form-row">
              <label id="label-meta-description" for="meta-description" data-tooltip-i18n="metaDescriptionTooltip">Description</label>
              <div class="expandable-field">
                <textarea id="meta-description" class="panel-textarea"></textarea>
                <button class="expand-btn" type="button" aria-label="Plein écran">⤢</button>
              </div>
            </div>
            <div class="form-row">
              <label id="label-meta-command" for="meta-command" data-tooltip-i18n="metaCommandTooltip">Commande institutionnelle</label>
              <div class="expandable-field">
                <textarea id="meta-command" class="panel-textarea" placeholder="Collez ici la commande institutionnelle déjà définie..."></textarea>
                <button class="expand-btn" type="button" aria-label="Plein écran">⤢</button>
              </div>
            </div>
          </div>
          <div class="panel-column panel-column-right">
            <div class="form-row">
              <label id="label-meta-delivery" for="meta-delivery" data-tooltip-i18n="metaDeliveryTooltip">Mode</label>
              <select id="meta-delivery" class="panel-select">
                <option id="opt-meta-delivery-empty" value=""></option>
                <option id="opt-meta-delivery-onsite" value="onsite">Présentiel</option>
                <option id="opt-meta-delivery-online" value="online">Distanciel</option>
                <option id="opt-meta-delivery-hybrid" value="hybrid">Hybride</option>
              </select>
            </div>
            <div class="form-row">
              <label id="label-meta-school-system" for="meta-school-system" data-tooltip-i18n="metaSchoolSystemTooltip">Système / classification</label>
              <select id="meta-school-system" class="panel-select">
                <option id="opt-meta-school-system-empty" value=""></option>
                <optgroup id="optgroup-meta-school-systems-national" label="Systèmes nationaux">
                  <option id="opt-meta-school-system-france" value="france">France</option>
                  <option id="opt-meta-school-system-switzerland" value="switzerland">Suisse (HarmoS)</option>
                  <option id="opt-meta-school-system-us" value="united_states">États-Unis (K–12)</option>
                  <option id="opt-meta-school-system-belgium-french" value="belgium_french">Belgique — Fédération Wallonie-Bruxelles</option>
                  <option id="opt-meta-school-system-belgium-flemish" value="belgium_flemish">Belgique — Communauté flamande</option>
                  <option id="opt-meta-school-system-belgium-german" value="belgium_german">Belgique — Communauté germanophone</option>
                  <option id="opt-meta-school-system-uk-england" value="uk_england">Royaume-Uni — Angleterre</option>
                  <option id="opt-meta-school-system-uk-wales" value="uk_wales">Royaume-Uni — Pays de Galles</option>
                  <option id="opt-meta-school-system-uk-scotland" value="uk_scotland">Royaume-Uni — Écosse</option>
                  <option id="opt-meta-school-system-uk-northern-ireland" value="uk_northern_ireland">Royaume-Uni — Irlande du Nord</option>
                </optgroup>
                <optgroup id="optgroup-meta-school-systems-transnational" label="Systèmes transnationaux">
                  <option id="opt-meta-school-system-european-schools" value="european_schools">Système des Écoles européennes</option>
                  <option id="opt-meta-school-system-ib" value="ib">International Baccalaureate (IB)</option>
                </optgroup>
                <optgroup id="optgroup-meta-school-systems-international" label="Classification internationale">
                  <option id="opt-meta-school-system-isced" value="isced_2011">International — ISCED 2011 (CITE)</option>
                </optgroup>
              </select>
            </div>
            <div class="form-row">
              <label id="label-meta-level" for="meta-level" data-tooltip-i18n="metaLevelTooltip">Niveau</label>
              <select id="meta-level" class="panel-select" disabled>
                <option value="">Choisissez d’abord un système ou une classification</option>
              </select>
            </div>
            <div class="form-row">
              <label id="label-meta-size-class" for="meta-size-class" data-tooltip-i18n="metaSizeTooltip">Taille du groupe</label>
              <input id="meta-size-class" class="panel-input" type="number" min="1" />
            </div>
            <div class="form-row">
              <label id="label-meta-designers" for="meta-designers" data-tooltip-i18n="metaDesignersTooltip">Concepteur(s)</label>
              <input id="meta-designers" class="panel-input" type="text" />
            </div>
            <div class="form-row">
              <label id="label-meta-trainers" for="meta-trainers" data-tooltip-i18n="metaTrainersTooltip">Enseignant(s)</label>
              <input id="meta-trainers" class="panel-input" type="text" />
            </div>
            <div class="form-row">
              <label id="label-meta-personas" for="meta-personas" data-tooltip-i18n="metaPersonasTooltip">Objectifs</label>
              <div class="expandable-field">
                <textarea id="meta-personas" class="panel-textarea" placeholder="Décrivez les objectifs de la formation..."></textarea>
                <button class="expand-btn" type="button" aria-label="Plein écran">⤢</button>
              </div>
            </div>
            <div class="form-row outcomes-row">
              <div class="outcomes-header">
                <button id="add-outcome-btn" type="button" class="outcomes-add-btn" aria-label="Ajouter un acquis d’apprentissage" aria-haspopup="dialog" aria-controls="bloom-modal-backdrop"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                <label id="label-meta-outcomes" data-tooltip-i18n="outcomesTooltip">Acquis d'apprentissage</label>
              </div>
              <div id="outcomes-list" class="outcomes-list"></div>
            </div>
          </div>
        </div>
        <div id="analysis-view" class="analysis-view hidden" role="tabpanel" aria-labelledby="top-tab-analysis">
          <h2 id="analysis-title" class="analysis-title" data-tooltip-i18n="analysisTooltip">Expérience d’apprentissage</h2>
          <div id="analysis-alerts" class="analysis-alerts hidden" role="status" aria-live="polite" aria-atomic="false"></div>
          <div class="analysis-main-grid">
            <div class="analysis-block analysis-learning-block">
              <h3 id="analysis-learning-title" class="analysis-chart-title" data-tooltip-i18n="analysisLearningTooltip">Type d’apprentissage</h3>
              <div id="analysis-learning-pie-wrap" class="pie-with-labels pie-large">
                <div id="analysis-learning-pie" class="analysis-big-pie"></div>
                <div id="analysis-learning-labels" class="pie-outer-labels" aria-hidden="true"></div>
                <div id="analysis-learning-tooltip" class="pie-tooltip hidden" role="tooltip" aria-hidden="true"></div>
              </div>
              <div id="analysis-learning-legend" class="analysis-legend"></div>
            </div>
            <div class="analysis-block">
              <h3 id="analysis-delivery-title" class="analysis-chart-title" data-tooltip-i18n="analysisDeliveryTooltip">Modalité</h3>
              <div id="analysis-delivery-pie" class="analysis-small-pie"></div>
              <div id="analysis-delivery-legend" class="analysis-legend"></div>
            </div>
            <div class="analysis-block">
              <h3 id="analysis-teacher-title" class="analysis-chart-title" data-tooltip-i18n="analysisTeachingTooltip">Enseignement</h3>
              <div id="analysis-teacher-pie" class="analysis-small-pie"></div>
              <div id="analysis-teacher-legend" class="analysis-legend"></div>
            </div>
            <div class="analysis-block">
              <h3 id="analysis-sync-title" class="analysis-chart-title" data-tooltip-i18n="analysisPacingTooltip">Rythme</h3>
              <div id="analysis-sync-pie" class="analysis-small-pie"></div>
              <div id="analysis-sync-legend" class="analysis-legend"></div>
            </div>
            <div class="analysis-block">
              <h3 id="analysis-eval-title" class="analysis-chart-title" data-tooltip-i18n="analysisEvaluationTooltip">Évaluation</h3>
              <div id="analysis-eval-pie" class="analysis-small-pie"></div>
              <div id="analysis-eval-legend" class="analysis-legend"></div>
            </div>
          </div>
          <div class="analysis-group-section">
            <h3 id="analysis-group-title" class="analysis-chart-title" data-tooltip-i18n="analysisGroupTooltip">Groupe</h3>
            <div id="analysis-group-bar" class="analysis-group-bar"></div>
            <div id="analysis-group-legend" class="analysis-legend"></div>
          </div>
          <section class="analysis-aias-section" aria-labelledby="analysis-aias-title">
            <h3 id="analysis-aias-title" class="analysis-section-title" data-tooltip-i18n="analysisAiasTooltip">AIAS · Répartition pondérée par la durée</h3>
            <div id="analysis-aias-bar" class="analysis-aias-bar"></div>
            <div id="analysis-aias-legend" class="analysis-legend"></div>
          </section>
        </div>
        <div id="chronology-view" class="analysis-view hidden" role="tabpanel" aria-labelledby="top-tab-chronology">
          <h2 id="chronology-title" class="analysis-title" data-tooltip-i18n="chronologyTooltip">Chronologie des activités</h2>
          <div id="chronology-container">
            <!-- Partition sessions will be rendered here -->
          </div>
        </div>
      </div>
    </section>

    <header class="toolbar" role="region" aria-label="Actions du scénario">
      <div class="toolbar-left">
        <div class="toolbar-cluster">
          <button id="toggle-intentions-btn" class="btn btn-light" type="button"><span class="btn-label"><i class="fa-solid fa-eye btn-icon-inline" aria-hidden="true"></i>Intentions</span></button>
          <div id="board-layout-toggle" class="layout-toggle" role="group" aria-label="Mode d'affichage des séances">
          <button
            id="board-layout-list-btn"
            class="layout-toggle-btn"
            type="button"
            aria-pressed="false"
            title="Liste"
            aria-label="Liste"
          >
            <svg class="material-icon-svg" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M4 10.5c.83 0 1.5-.67 1.5-1.5S4.83 7.5 4 7.5 2.5 8.17 2.5 9 3.17 10.5 4 10.5zm0 6c.83 0 1.5-.67 1.5-1.5S4.83 13.5 4 13.5 2.5 14.17 2.5 15 3.17 16.5 4 16.5zM7 16h14v-2H7v2zm0-6h14V8H7v2z"></path>
            </svg>
            <span id="board-layout-list-text" class="sr-only">Liste</span>
          </button>
          <button
            id="board-layout-columns-btn"
            class="layout-toggle-btn"
            type="button"
            aria-pressed="true"
            title="Colonnes"
            aria-label="Colonnes"
          >
            <svg class="material-icon-svg" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 8v-8h8v8h-8z"></path>
            </svg>
            <span id="board-layout-columns-text" class="sr-only">Colonnes</span>
          </button>
          <button
            id="board-layout-grid-btn"
            class="layout-toggle-btn"
            type="button"
            aria-pressed="false"
            title="Grille"
            aria-label="Grille"
          >
            <svg class="material-icon-svg" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M20 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h15c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 2v3H5V5h15zm-8 14H5v-4h7v4zm0-6H5v-4h7v4zm8 6h-6v-4h6v4zm0-6h-6v-4h6v4z"/>
            </svg>
            <span id="board-layout-grid-text" class="sr-only">Grille</span>
          </button>
          </div>
        </div>
      </div>
      <div class="toolbar-right">
        <div class="toolbar-cluster">
          <button id="new-design-btn" class="btn" type="button"><span class="btn-label"><i class="fa-regular fa-file btn-icon-inline" aria-hidden="true"></i>Nouveau</span></button>
          <button id="import-design-btn" class="btn" type="button"><span class="btn-label"><i class="fa-solid fa-file-arrow-up btn-icon-inline" aria-hidden="true"></i>Importer</span></button>
        </div>
        <span class="toolbar-sep" aria-hidden="true"></span>
        <div class="toolbar-cluster">
          <button id="save-btn" class="btn btn-light" type="button" hidden aria-live="polite" aria-atomic="true"><span class="btn-label"><i class="fa-regular fa-floppy-disk btn-icon-inline" aria-hidden="true"></i>Enregistrer</span></button>
          <button id="publish-btn" class="btn btn-light" type="button" hidden><span class="btn-label"><i class="fa-solid fa-share-nodes btn-icon-inline" aria-hidden="true"></i>Partager</span></button>
          <button id="export-design-btn" class="btn" type="button"><span class="btn-label"><i class="fa-solid fa-file-export btn-icon-inline" aria-hidden="true"></i>Exporter</span></button>
        </div>
      </div>
      <input id="import-file-input" type="file" accept=".json,.ldj,.csv,.xlsx,.md,.markdown,application/json,text/csv,text/markdown" hidden />
      <button id="info-btn" hidden></button>
    </header>

    <main id="main-content">
      <section id="board" class="board" aria-label="Séquences de séances"></section>
    </main>

    <?php render_site_footer(); ?>


    <template id="session-template">
      <article class="session-card">
        <header class="session-header">
          <div class="session-strip"></div>
          <button class="icon-btn delete-btn delete-session-btn" title="Supprimer la séance" aria-label="Supprimer la séance">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 6l12 12"></path>
              <path d="M18 6l-12 12"></path>
            </svg>
          </button>
        </header>
        <div class="session-title-wrap">
          <textarea class="session-title" rows="2"></textarea>
          <div class="expandable-field session-objectives-wrap">
            <textarea class="session-objectives" rows="2" placeholder="Objectifs du moment..."></textarea>
            <button class="expand-btn" type="button" aria-label="Plein écran">⤢</button>
          </div>
          <div class="expandable-field session-intentions-wrap">
            <textarea class="session-intentions" rows="2" placeholder="Choix pédagogiques (ex. : pourquoi cet ordre d'activités ? quelle alternance de modalités ? quel rythme ?)"></textarea>
            <button class="expand-btn" type="button" aria-label="Plein écran">⤢</button>
          </div>
        </div>
        <div class="activities"></div>
        <footer class="session-footer">
          <div class="session-footer-actions">
            <button class="btn btn-light add-activity-btn" type="button">+ Activité</button>
            <button class="btn btn-light toggle-session-notes-btn" type="button">Notes</button>
          </div>
          <div class="session-metas">
            <span class="duration-pill"><span class="total-duration">0</span> <span class="duration-unit">min</span></span>
            <button class="icon-btn duplicate-session-btn" type="button" title="Dupliquer le moment" aria-label="Dupliquer le moment">
              <i class="fa-regular fa-copy" aria-hidden="true"></i>
            </button>
          </div>
        </footer>
        <div class="session-notes hidden">
          <div class="expandable-field">
            <textarea class="session-notes-input" rows="3" placeholder="Notes de la séance..."></textarea>
            <button class="expand-btn" type="button" aria-label="Plein écran">⤢</button>
          </div>
        </div>
      </article>
    </template>

    <template id="activity-template">
      <div class="activity-card">
        <div class="activity-toolbar">
          <button class="choice-btn activity-type-btn" type="button"></button>
          <span class="mini-label">
            <span class="sr-only">Durée en minutes</span>
            <span class="duration-symbol" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>
            <input class="activity-duration" type="number" min="1" aria-label="Durée en minutes" />
          </span>
          <button class="choice-btn activity-group-mode-btn" type="button"></button>
          <button class="choice-btn activity-teaching-mode-btn" type="button"></button>
          <button class="choice-btn activity-sync-mode-btn" type="button"></button>
          <button class="choice-btn activity-location-mode-btn" type="button"></button>
          <button class="choice-btn activity-evaluation-mode-btn" type="button"></button>
          <div class="activity-actions">
            <button class="icon-btn activity-aias-btn" type="button" title="AIAS" aria-label="AIAS" aria-haspopup="dialog" aria-expanded="false">
              <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
            </button>
            <button class="icon-btn select-tools-btn" type="button" aria-haspopup="dialog" aria-expanded="false">
              <svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16">
                <path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/>
              </svg>
            </button>
            <button class="icon-btn duplicate-activity-btn" type="button" title="Dupliquer l’activité" aria-label="Dupliquer l’activité">
              <i class="fa-regular fa-copy" aria-hidden="true"></i>
            </button>
            <button class="icon-btn delete-btn delete-activity-btn" title="Supprimer" aria-label="Supprimer">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 6l12 12"></path>
                <path d="M18 6l-12 12"></path>
              </svg>
            </button>
          </div>
        </div>
        <div class="activity-tools hidden" role="list"></div>
        <div class="activity-text-fields">
          <div class="activity-field-group">
            <div class="activity-field-label activity-description-label">Description de l'activité</div>
            <div class="expandable-field">
              <textarea class="activity-description" rows="3"></textarea>
              <button class="expand-btn" type="button" aria-label="Plein écran">⤢</button>
            </div>
          </div>
          <div class="activity-field-group">
            <div class="activity-field-label activity-instructions-label">Consignes pour les élèves</div>
            <div class="expandable-field">
              <textarea class="activity-instructions" rows="3"></textarea>
              <button class="expand-btn" type="button" aria-label="Plein écran">⤢</button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div id="info-modal-backdrop" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="info-modal-title">
      <div class="modal">
        <h2 id="info-modal-title" class="modal-title">À propos</h2>
        <p id="info-modal-p1">Cette application web monopage s’inspire de l’UCL Learning Designer :</p>
        <p><a href="https://www.ucl.ac.uk/learning-designer/" target="_blank" rel="noopener noreferrer">https://www.ucl.ac.uk/learning-designer/</a></p>
        <p id="info-modal-p2">(UCL Knowledge Lab, UCL Institute of Education, 2013-2026).</p>
        <p id="info-modal-p3">Traitement local : toutes les données restent dans votre navigateur ; aucune donnée n’est transmise en ligne.</p>
        <p id="info-modal-p4">François Jourde (2026) • CC BY-SA<br />Code source : <a href="https://github.com/YannHY/scenarisation" target="_blank" rel="noopener noreferrer">https://github.com/YannHY/scenarisation</a> (basé sur <a href="https://github.com/jourde" target="_blank" rel="noopener noreferrer">github.com/jourde</a>)</p>
        <p id="info-modal-p5"></p>
        <div class="modal-actions">
          <button id="info-modal-close-btn" class="btn btn-primary" type="button">Fermer</button>
        </div>
      </div>
    </div>

    <!-- Import modal: template library or local file -->
    <div id="import-modal-backdrop" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="import-modal-title">
      <div class="modal import-modal">
        <h2 id="import-modal-title" class="modal-title">Importer un scénario</h2>
        <p id="import-modal-desc" class="import-modal-desc">Partez d’un modèle prérempli, ou chargez un fichier exporté depuis cette application. Le scénario actuel sera remplacé.</p>

        <section class="import-source" aria-labelledby="import-file-section-title">
          <h3 id="import-file-section-title" class="import-section-title">Depuis mon ordinateur</h3>
          <div id="import-drop-zone" class="import-drop-zone">
            <span class="import-drop-icon" aria-hidden="true"><i class="fa-solid fa-file-arrow-down"></i></span>
            <div class="import-drop-copy">
              <p id="import-drop-title" class="import-drop-title" aria-live="polite">Glissez-déposez un fichier ici</p>
              <p id="import-drop-hint" class="import-drop-hint">ou choisissez-le sur votre ordinateur</p>
            </div>
            <button id="import-file-btn" class="btn btn-light" type="button"><span class="btn-label"><i class="fa-solid fa-folder-open btn-icon-inline" aria-hidden="true"></i>Choisir un fichier…</span></button>
          </div>
          <p id="import-file-formats" class="import-file-formats">LDJ, JSON, CSV, Excel, Markdown</p>
        </section>

        <section class="import-source import-models-section" aria-labelledby="import-models-section-title">
          <div class="import-models-head">
            <h3 id="import-models-section-title" class="import-section-title">Bibliothèque de modèles</h3>
            <a id="import-models-link" class="import-models-link" href="models.php" target="_blank" rel="noopener noreferrer">Voir la bibliothèque</a>
          </div>
          <div class="import-models-filters">
            <label id="import-models-search-label" class="sr-only" for="import-models-search">Rechercher un modèle</label>
            <input id="import-models-search" class="panel-input" type="search" autocomplete="off" placeholder="Rechercher un modèle…" />
            <label id="import-models-family-label" class="sr-only" for="import-models-family">Famille de modèles</label>
            <select id="import-models-family" class="panel-select"></select>
          </div>
          <p id="import-models-status" class="import-models-status" role="status" aria-live="polite">Chargement des modèles…</p>
          <div id="import-models-list" class="import-models-list" role="list"></div>
        </section>

        <section id="import-model-preview" class="import-model-preview hidden" aria-labelledby="import-model-preview-title">
          <header class="import-model-preview-header">
            <p id="import-model-preview-eyebrow" class="import-model-preview-eyebrow">Aperçu du scénario</p>
            <h2 id="import-model-preview-title" class="modal-title"></h2>
            <p id="import-model-preview-summary" class="import-model-preview-summary"></p>
            <div id="import-model-preview-chips" class="import-model-preview-chips"></div>
          </header>
          <p id="import-model-preview-status" class="import-model-preview-status" role="status" aria-live="polite"></p>
          <div id="import-model-preview-content" class="import-model-preview-content"></div>
          <div class="modal-actions import-model-preview-actions">
            <button id="import-model-preview-back-btn" class="btn btn-light" type="button"><span class="btn-label"><i class="fa-solid fa-arrow-left btn-icon-inline" aria-hidden="true"></i>Retour aux modèles</span></button>
            <button id="import-model-preview-use-btn" class="btn btn-primary import-model-action" type="button"><span class="btn-label"><i class="fa-solid fa-file-import btn-icon-inline" aria-hidden="true"></i>Importer</span></button>
          </div>
        </section>

        <div class="modal-actions">
          <button id="import-modal-cancel-btn" class="btn btn-light" type="button">Fermer</button>
        </div>
      </div>
    </div>

    <div id="export-modal-backdrop" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="export-modal-title">
      <div class="modal export-modal">
        <h2 id="export-modal-title" class="modal-title">Exporter le scénario</h2>
        <fieldset class="export-scope-fieldset">
          <legend id="export-scope-label">Contenu à exporter</legend>
          <div class="export-scope-options">
            <label class="export-scope-option">
              <input id="export-scope-full-input" type="radio" name="export-scope" value="full" checked />
              <span class="export-scope-option-copy">
                <span class="export-scope-option-title">Export enseignant</span>
                <span id="export-scope-full-description" class="export-scope-option-description">Toutes les informations du scénario pédagogique.</span>
              </span>
            </label>
            <label class="export-scope-option">
              <input id="export-scope-students-input" type="radio" name="export-scope" value="students" />
              <span class="export-scope-option-copy">
                <span class="export-scope-option-title">Export élève</span>
                <span id="export-scope-students-description" class="export-scope-option-description">Les séances, les activités et les consignes adressées aux élèves.</span>
              </span>
            </label>
          </div>
        </fieldset>
        <details id="export-moments-details" class="export-moments-details">
          <summary>
            <span id="export-moments-label">Moments à exporter</span>
            <span class="export-moments-summary-trailing">
              <span id="export-moments-summary" class="export-moments-summary" aria-live="polite"></span>
              <span class="export-moments-chevron" aria-hidden="true"></span>
            </span>
          </summary>
          <div class="export-moments-content" role="group" aria-labelledby="export-moments-label">
            <div class="export-moments-controls">
              <label class="export-moments-all-option">
                <input id="export-moments-all-input" type="checkbox" checked />
                <span id="export-moments-all-label">Tous les moments</span>
              </label>
            </div>
            <div id="export-moments-list" class="export-moments-list"></div>
            <p id="export-moments-empty" class="export-moments-empty hidden">Aucun moment à exporter.</p>
          </div>
        </details>
        <label for="export-format-select">Format</label>
        <select id="export-format-select" class="panel-select">
          <option value="markdown">Markdown</option>
          <option value="html">HTML</option>
          <option value="json">JSON</option>
          <option value="excel">Excel</option>
          <option value="word">Word</option>
        </select>
        <label for="export-filename-input">Nom du fichier</label>
        <input id="export-filename-input" class="panel-input" type="text" maxlength="160" autocomplete="off" />
        <p id="export-result-modal-copy">Le contenu exporté est lisible ci-dessous. Vous pouvez le copier ou télécharger le fichier.</p>
        <details id="export-preview-details" class="export-preview-details">
          <summary>
            <span id="export-preview-label">Prévisualisation</span>
            <span class="export-preview-chevron" aria-hidden="true"></span>
          </summary>
          <textarea id="export-result-text" class="panel-textarea" rows="14" readonly></textarea>
        </details>
        <div class="modal-actions">
          <button id="export-result-copy-btn" class="btn btn-light" type="button">Copier</button>
          <button id="export-modal-cancel-btn" class="btn btn-light" type="button">Fermer</button>
          <button id="export-modal-confirm-btn" class="btn btn-primary" type="button">Télécharger</button>
        </div>
      </div>
    </div>

    <!-- New scenario confirmation modal -->
    <div id="new-design-modal-backdrop" class="modal-backdrop hidden"
         role="dialog" aria-modal="true" aria-hidden="true"
         aria-labelledby="new-design-modal-title">
      <div class="modal">
        <h2 id="new-design-modal-title" class="modal-title">Nouveau scénario</h2>
        <p id="new-design-modal-msg" class="new-design-modal-msg">Vous allez créer un nouveau scénario vierge. Si vous n'avez pas enregistré le scénario actuel, il sera perdu.</p>
        <div class="modal-actions">
          <button id="new-design-cancel-btn" class="btn btn-light" type="button">Annuler</button>
          <button id="new-design-confirm-btn" class="btn btn-primary" type="button">Créer un nouveau scénario</button>
        </div>
      </div>
    </div>

    <!-- Bloom taxonomy modal -->
    <div id="bloom-modal-backdrop" class="modal-backdrop hidden"
         role="dialog" aria-modal="true" aria-hidden="true"
         aria-labelledby="bloom-modal-title">
      <div class="modal bloom-modal">
        <h2 id="bloom-modal-title" class="modal-title">Acquis d’apprentissage</h2>
        <p id="outcomes-description" class="outcomes-description"></p>
        <textarea id="meta-outcomes" class="panel-textarea" rows="4" aria-labelledby="bloom-modal-title" aria-describedby="outcomes-description"></textarea>
        <div id="bloom-selected-tag" class="bloom-selected-tag hidden"></div>
        <button id="bloom-toggle-btn" type="button" class="btn btn-light outcomes-taxonomy-btn" aria-expanded="false" aria-controls="bloom-picker">Taxonomie de Bloom</button>
        <div id="bloom-picker" class="bloom-picker hidden" hidden>
          <p id="bloom-modal-subtitle" class="bloom-modal-subtitle">Sélectionnez une catégorie ou un verbe d'action</p>
          <div id="bloom-category-list" class="bloom-category-list"></div>
        </div>
        <div class="modal-actions">
          <button id="bloom-cancel-btn" class="btn btn-light" type="button">Annuler</button>
          <button id="bloom-add-btn" class="btn btn-primary" type="button">Ajouter</button>
        </div>
      </div>
    </div>

    <!-- Partition config modal -->
    <div id="partition-config-modal-backdrop" class="modal-backdrop hidden"
         role="dialog" aria-modal="true" aria-hidden="true"
         aria-labelledby="partition-config-modal-title">
      <div class="modal partition-config-modal">
        <h2 id="partition-config-modal-title" class="modal-title">Configurer les lignes</h2>
        <p id="partition-config-modal-desc" class="partition-config-modal-desc">
          Choisissez les lignes à afficher et leur ordre dans la partition.
        </p>
        <div id="partition-config-list" class="partition-config-list"></div>
        <div class="partition-add-section">
          <p id="partition-add-section-label" class="partition-add-section-label">
            Ajouter une ligne
          </p>
          <div class="partition-add-controls">
            <select id="partition-add-type" class="panel-select partition-add-select"></select>
            <select id="partition-add-value" class="panel-select partition-add-select"></select>
            <button id="partition-add-line-btn" class="btn btn-light partition-add-button" type="button">+ Ajouter</button>
          </div>
        </div>
        <div class="modal-actions">
          <button id="partition-config-cancel-btn" class="btn btn-light" type="button">Annuler</button>
          <button id="partition-config-save-btn" class="btn btn-primary" type="button">Valider</button>
        </div>
      </div>
    </div>

    <div id="aias-modal-backdrop" class="modal-backdrop hidden"
         role="dialog" aria-modal="true" aria-hidden="true"
         aria-labelledby="aias-modal-title" aria-describedby="aias-modal-intro">
      <div class="modal aias-modal">
        <h2 id="aias-modal-title" class="modal-title">AIAS 2.1</h2>
        <p id="aias-modal-intro" class="aias-modal-intro"></p>
        <div id="aias-modal-status-options" class="aias-status-options"></div>
        <div id="aias-modal-levels" class="aias-levels" role="radiogroup"></div>
        <p class="aias-attribution">
          <span id="aias-modal-attribution-prefix">Basé sur</span>
          <a href="https://aiassessmentscale.com/" target="_blank" rel="noopener noreferrer">AI Assessment Scale (AIAS) v2.1</a>
          <span>© 2026 Learning Innovation Practice Ltd. ·</span>
          <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/" target="_blank" rel="noopener noreferrer">CC BY-NC-SA 4.0</a>
        </p>
        <div class="modal-actions">
          <button id="aias-modal-close-btn" class="btn btn-light" type="button">Fermer</button>
        </div>
      </div>
    </div>
    <!-- Les sources précèdent les modules ; interface.js les assemble avant account-ui.js. -->
    <script src="js/competency-catalog.js?v=20260831-framework-i18n"></script>
    <script src="js/competency-greencomp-details.js?v=20260831-framework-i18n"></script>
    <script src="js/competency-digcomp-details.js?v=20260830-digcomp-statements"></script>
    <script src="js/editor/config.js?v=20260910-bloom-on-demand"></script>
    <script src="js/editor/competencies.js?v=20260905-modules-v1"></script>
    <script src="js/editor/exports.js?v=20260910-scenario-wording"></script>
    <script src="js/editor/imports.js?v=20260906-ib-en"></script>
    <script src="js/editor/analysis.js?v=20260905-modules-v1"></script>
    <script src="js/editor/fields.js?v=20260905-modules-v1"></script>
    <script src="js/interface.js?v=20260910-bloom-on-demand"></script>
    <script src="js/account-ui.js?v=20260910-scenario-wording"></script>
  </body>
</html>
