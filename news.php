<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

app_start_session();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Découvrez les dernières améliorations et nouvelles fonctionnalités de Scénarisation.">
    <link rel="icon" href="assets/favicon.svg?v=20260906-scenarisation" type="image/svg+xml" sizes="any">
    <title>Nouveautés | Scénarisation</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260912-breadcrumb-alignment">
    <link rel="stylesheet" href="css/nouveautes.css?v=<?= hash_file('sha256', __DIR__ . '/css/nouveautes.css') ?>">
</head>
<body class="updates-page">
<?php render_site_nav('updates'); ?>
<main class="updates-shell">
    <header class="updates-hero">
        <p class="updates-kicker" data-site-i18n-en="Product updates" data-site-i18n-fr="Évolutions du site">Évolutions du site</p>
        <h1 data-site-i18n-en="What’s new" data-site-i18n-fr="Nouveautés">Nouveautés</h1>
        <p class="updates-intro"
           data-site-i18n-en="Follow the new features, improvements and fixes added to Scénarisation."
           data-site-i18n-fr="Suivez les nouvelles fonctionnalités, les améliorations et les corrections apportées à Scénarisation.">Suivez les nouvelles fonctionnalités, les améliorations et les corrections apportées à Scénarisation.</p>
    </header>

    <section class="updates-entry" aria-labelledby="updates-2026-11-01">
        <div class="updates-date">
            <time datetime="2026-11-01" data-site-i18n-en="1 November 2026" data-site-i18n-fr="1er novembre 2026">1er novembre 2026</time>
            <span data-site-i18n-en="Version 1.0.0" data-site-i18n-fr="Version 1.0.0">Version 1.0.0</span>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-11-01"
                data-site-i18n-en="Source tracking, richer analyses and smoother imports"
                data-site-i18n-fr="Suivi de l’origine, analyses enrichies et imports plus fluides">Suivi de l’origine, analyses enrichies et imports plus fluides</h2>
            <p class="updates-summary"
               data-site-i18n-en="Scénarisation 1.0 is the first stable public release. It makes the origin and authorship of adapted scenarios traceable, while making analyses easier to share and improving the information and filters available during design and import."
               data-site-i18n-fr="Scénarisation 1.0 est la première version publique stable. Elle rend traçables l’origine et la paternité des scénarios adaptés, tout en facilitant le partage des analyses et en améliorant les informations et les filtres disponibles lors de la conception ou de l’import.">Scénarisation 1.0 est la première version publique stable. Elle rend traçables l’origine et la paternité des scénarios adaptés, tout en facilitant le partage des analyses et en améliorant les informations et les filtres disponibles lors de la conception ou de l’import.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-code-branch"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Source and author tracking" data-site-i18n-fr="Suivi de l’origine et de l’auteur">Suivi de l’origine et de l’auteur</h3>
                        <p data-site-i18n-en="Scénarisation now preserves the source, author, licence and date of every adaptation. This information remains visible in the application and its exports. All scenarios can be adapted privately; a NoDerivatives adaptation cannot be published, while a ShareAlike adaptation retains the source licence."
                           data-site-i18n-fr="Scénarisation conserve désormais la source, l’auteur, la licence et la date de chaque adaptation. Ces informations restent visibles dans l’application et les exports. Tous les scénarios peuvent être adaptés en privé ; une adaptation NoDerivatives ne peut pas être publiée, tandis qu’une adaptation ShareAlike conserve la licence d’origine.">Scénarisation conserve désormais la source, l’auteur, la licence et la date de chaque adaptation. Ces informations restent visibles dans l’application et les exports. Tous les scénarios peuvent être adaptés en privé ; une adaptation NoDerivatives ne peut pas être publiée, tandis qu’une adaptation ShareAlike conserve la licence d’origine.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Analysis exports" data-site-i18n-fr="Exports des analyses">Exports des analyses</h3>
                        <p data-site-i18n-en="The visual analysis of a scenario can now be exported as text, structured data, a document or an image, including TXT, Markdown, HTML, CSV, Excel, Word, PDF, PNG, JPEG and SVG formats."
                           data-site-i18n-fr="L’analyse visuelle d’un scénario peut désormais être exportée sous forme de texte, de données structurées, de document ou d’image, notamment aux formats TXT, Markdown, HTML, CSV, Excel, Word, PDF, PNG, JPEG et SVG.">L’analyse visuelle d’un scénario peut désormais être exportée sous forme de texte, de données structurées, de document ou d’image, notamment aux formats TXT, Markdown, HTML, CSV, Excel, Word, PDF, PNG, JPEG et SVG.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-toolbox"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Teacher materials" data-site-i18n-fr="Matériel enseignant">Matériel enseignant</h3>
                        <p data-site-i18n-en="A new field records the physical tools, equipment, software and online services teachers need. This information is preserved when scenarios are imported and exported."
                           data-site-i18n-fr="Un nouveau champ permet d’indiquer les outils physiques, les équipements, les logiciels et les services numériques nécessaires à l’enseignant. Cette information est conservée lors des imports et des exports.">Un nouveau champ permet d’indiquer les outils physiques, les équipements, les logiciels et les services numériques nécessaires à l’enseignant. Cette information est conservée lors des imports et des exports.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-filter"></i></span>
                    <div>
                        <h3 data-site-i18n-en="More precise import filters" data-site-i18n-fr="Filtres d’import plus précis">Filtres d’import plus précis</h3>
                        <p data-site-i18n-en="The template and shared-library import windows now offer clearer filters for duration, number of stages, delivery mode, learning type, school system, level and competency framework."
                           data-site-i18n-fr="Les fenêtres d’import des modèles et de la bibliothèque partagée proposent désormais des filtres plus clairs par durée, nombre de moments, modalité, type d’apprentissage, système scolaire, niveau et référentiel de compétences.">Les fenêtres d’import des modèles et de la bibliothèque partagée proposent désormais des filtres plus clairs par durée, nombre de moments, modalité, type d’apprentissage, système scolaire, niveau et référentiel de compétences.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-graduation-cap"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Reliable school frameworks" data-site-i18n-fr="Référentiels scolaires fiabilisés">Référentiels scolaires fiabilisés</h3>
                        <p data-site-i18n-en="School cycles, levels and competency frameworks now remain consistent across countries and education systems, making shared scenarios easier to find."
                           data-site-i18n-fr="Les cycles, les niveaux et les référentiels de compétences restent maintenant cohérents entre les différents pays et systèmes éducatifs, ce qui facilite la recherche de scénarios partagés.">Les cycles, les niveaux et les référentiels de compétences restent maintenant cohérents entre les différents pays et systèmes éducatifs, ce qui facilite la recherche de scénarios partagés.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-09-27">
        <div class="updates-date">
            <time datetime="2026-09-27" data-site-i18n-en="27 September 2026" data-site-i18n-fr="27 septembre 2026">27 septembre 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-09-27"
                data-site-i18n-en="A clearer introduction to the command-line tool"
                data-site-i18n-fr="Une présentation plus concrète de l’outil en ligne de commande">Une présentation plus concrète de l’outil en ligne de commande</h2>
            <p class="updates-summary"
               data-site-i18n-en="The help page now shows the Scénarisation CLI as it actually appears in a terminal, making its purpose and main commands easier to understand."
               data-site-i18n-fr="La page d’aide montre désormais le CLI Scénarisation tel qu’il apparaît réellement dans un terminal, afin de rendre son rôle et ses principales commandes plus faciles à comprendre.">La page d’aide montre désormais le CLI Scénarisation tel qu’il apparaît réellement dans un terminal, afin de rendre son rôle et ses principales commandes plus faciles à comprendre.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-terminal"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Terminal preview in the help page" data-site-i18n-fr="Aperçu du terminal dans l’aide">Aperçu du terminal dans l’aide</h3>
                        <p data-site-i18n-en="A complete, bilingual terminal illustration presents the CLI banner, its options and the commands used to create, validate and publish a teaching scenario."
                           data-site-i18n-fr="Une illustration complète et bilingue du terminal présente la bannière du CLI, ses options et les commandes permettant de créer, valider et publier un scénario pédagogique.">Une illustration complète et bilingue du terminal présente la bannière du CLI, ses options et les commandes permettant de créer, valider et publier un scénario pédagogique.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-09-26">
        <div class="updates-date">
            <time datetime="2026-09-26" data-site-i18n-en="26 September 2026" data-site-i18n-fr="26 septembre 2026">26 septembre 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-09-26"
                data-site-i18n-en="A more capable CLI and more flexible account management"
                data-site-i18n-fr="Un CLI plus complet et une gestion des comptes plus souple">Un CLI plus complet et une gestion des comptes plus souple</h2>
            <p class="updates-summary"
               data-site-i18n-en="This release strengthens scenario creation from the terminal, opens access to more users and gives administrators finer control over account permissions."
               data-site-i18n-fr="Cette version renforce la création de scénarios depuis le terminal, ouvre l’accès à davantage d’utilisateurs et donne aux administrateurs un contrôle plus précis sur les comptes.">Cette version renforce la création de scénarios depuis le terminal, ouvre l’accès à davantage d’utilisateurs et donne aux administrateurs un contrôle plus précis sur les comptes.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Competency frameworks in the CLI" data-site-i18n-fr="Référentiels de compétences dans le CLI">Référentiels de compétences dans le CLI</h3>
                        <p data-site-i18n-en="The CLI can list and search competency frameworks, attach them to a scenario and validate the competencies assigned to each activity. Stable identifiers and French or English labels are supported."
                           data-site-i18n-fr="Le CLI peut lister et rechercher les référentiels de compétences, les associer à un scénario et valider les compétences affectées à chaque activité. Les identifiants stables et les libellés français ou anglais sont pris en charge.">Le CLI peut lister et rechercher les référentiels de compétences, les associer à un scénario et valider les compétences affectées à chaque activité. Les identifiants stables et les libellés français ou anglais sont pris en charge.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                    <div>
                        <h3 data-site-i18n-en="A more reliable and better documented CLI" data-site-i18n-fr="Un CLI plus fiable et mieux documenté">Un CLI plus fiable et mieux documenté</h3>
                        <p data-site-i18n-en="Installation, updates and the bilingual help were improved. The redesigned banner makes the tool easier to identify, while the documentation clarifies when the publication token is actually required."
                           data-site-i18n-fr="L’installation, les mises à jour et l’aide bilingue ont été améliorées. La nouvelle bannière rend l’outil plus identifiable, tandis que la documentation précise à quel moment le jeton de publication est réellement nécessaire.">L’installation, les mises à jour et l’aide bilingue ont été améliorées. La nouvelle bannière rend l’outil plus identifiable, tandis que la documentation précise à quel moment le jeton de publication est réellement nécessaire.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Configurable manager permissions" data-site-i18n-fr="Autorisations configurables pour les gestionnaires">Autorisations configurables pour les gestionnaires</h3>
                        <p data-site-i18n-en="Administrators can assign the manager role and separately allow account management, feedback deletion or access to the security log, without granting full administration rights."
                           data-site-i18n-fr="Les administrateurs peuvent attribuer le rôle de gestionnaire et autoriser séparément la gestion des comptes, la suppression des avis ou la consultation du journal de sécurité, sans accorder tous les droits d’administration.">Les administrateurs peuvent attribuer le rôle de gestionnaire et autoriser séparément la gestion des comptes, la suppression des avis ou la consultation du journal de sécurité, sans accorder tous les droits d’administration.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-user-plus"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Open registration and consolidated legal information" data-site-i18n-fr="Inscription ouverte et informations juridiques regroupées">Inscription ouverte et informations juridiques regroupées</h3>
                        <p data-site-i18n-en="Registration is no longer limited to one email domain. The sign-in experience was refined, and the terms of use and reuse licences were consolidated into a clearer legal page."
                           data-site-i18n-fr="L’inscription n’est plus limitée à un domaine de messagerie. L’expérience de connexion a été affinée, et les conditions d’utilisation ainsi que les licences de réutilisation ont été regroupées dans une page juridique plus claire.">L’inscription n’est plus limitée à un domaine de messagerie. L’expérience de connexion a été affinée, et les conditions d’utilisation ainsi que les licences de réutilisation ont été regroupées dans une page juridique plus claire.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-09-15">
        <div class="updates-date">
            <time datetime="2026-09-15" data-site-i18n-en="15 September 2026" data-site-i18n-fr="15 septembre 2026">15 septembre 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-09-15"
                data-site-i18n-en="Richer competency frameworks that are easier to use"
                data-site-i18n-fr="Des référentiels de compétences enrichis et plus simples à utiliser">Des référentiels de compétences enrichis et plus simples à utiliser</h2>
            <p class="updates-summary"
               data-site-i18n-en="The PER Digital Education and CRCN frameworks now provide more detailed, source-linked competencies, while a streamlined picker makes them easier to add to a scenario."
               data-site-i18n-fr="Les référentiels PER Éducation numérique et CRCN proposent désormais des compétences plus détaillées et reliées à leurs sources, tandis qu’un sélecteur simplifié facilite leur ajout à un scénario.">Les référentiels PER Éducation numérique et CRCN proposent désormais des compétences plus détaillées et reliées à leurs sources, tandis qu’un sélecteur simplifié facilite leur ajout à un scénario.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
                    <div>
                        <h3 data-site-i18n-en="More detailed official frameworks" data-site-i18n-fr="Des référentiels officiels plus détaillés">Des référentiels officiels plus détaillés</h3>
                        <p data-site-i18n-en="The PER Digital Education catalogue now includes learning progressions and fundamental expectations. The CRCN descriptions have also been expanded, with direct links to the reference sources."
                           data-site-i18n-fr="Le catalogue PER Éducation numérique intègre désormais les progressions des apprentissages et les attentes fondamentales. Les descriptions du CRCN ont également été enrichies, avec des liens directs vers les sources de référence.">Le catalogue PER Éducation numérique intègre désormais les progressions des apprentissages et les attentes fondamentales. Les descriptions du CRCN ont également été enrichies, avec des liens directs vers les sources de référence.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-check-double"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Simpler competency selection" data-site-i18n-fr="Sélection des compétences simplifiée">Sélection des compétences simplifiée</h3>
                        <p data-site-i18n-en="Frameworks can now be enabled from a clearer list before selecting the competencies needed for each learning activity."
                           data-site-i18n-fr="Les référentiels peuvent maintenant être activés depuis une liste plus claire avant de sélectionner les compétences nécessaires à chaque activité d’apprentissage.">Les référentiels peuvent maintenant être activés depuis une liste plus claire avant de sélectionner les compétences nécessaires à chaque activité d’apprentissage.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-09-11">
        <div class="updates-date">
            <time datetime="2026-09-11" data-site-i18n-en="11 September 2026" data-site-i18n-fr="11 septembre 2026">11 septembre 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-09-11"
                data-site-i18n-en="A clearer starting point for every new scenario"
                data-site-i18n-fr="Un point de départ plus clair pour chaque nouveau scénario">Un point de départ plus clair pour chaque nouveau scénario</h2>
            <p class="updates-summary"
               data-site-i18n-en="A new entry page brings together the different ways to begin: start from scratch, adapt a template or import a shared scenario."
               data-site-i18n-fr="Une nouvelle page de démarrage rassemble les différentes façons de commencer : partir d’une page blanche, adapter un modèle ou importer un scénario partagé.">Une nouvelle page de démarrage rassemble les différentes façons de commencer : partir d’une page blanche, adapter un modèle ou importer un scénario partagé.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-signs-post"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Three clearly identified paths" data-site-i18n-fr="Trois parcours clairement identifiés">Trois parcours clairement identifiés</h3>
                        <p data-site-i18n-en="The new scenario page now guides users directly to a blank scenario, the template library or the shared scenario library."
                           data-site-i18n-fr="La page Nouveau scénario oriente désormais directement vers un scénario vierge, la bibliothèque de modèles ou la bibliothèque des scénarios partagés.">La page Nouveau scénario oriente désormais directement vers un scénario vierge, la bibliothèque de modèles ou la bibliothèque des scénarios partagés.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-file-arrow-down"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Imports without leaving the page" data-site-i18n-fr="Imports sans quitter la page">Imports sans quitter la page</h3>
                        <p data-site-i18n-en="Dedicated dialogs let users search and preview templates, browse shared scenarios or drop a local LDJ, JSON, CSV, Excel or Markdown file before opening the editor."
                           data-site-i18n-fr="Des fenêtres dédiées permettent de rechercher et prévisualiser les modèles, de parcourir les scénarios partagés ou de déposer un fichier local LDJ, JSON, CSV, Excel ou Markdown avant d’ouvrir l’éditeur.">Des fenêtres dédiées permettent de rechercher et prévisualiser les modèles, de parcourir les scénarios partagés ou de déposer un fichier local LDJ, JSON, CSV, Excel ou Markdown avant d’ouvrir l’éditeur.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>
<?php render_site_footer(); ?>
</body>
</html>
