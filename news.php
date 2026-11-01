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
                data-site-i18n-en="Richer, traceable scenarios that are easier to use"
                data-site-i18n-fr="Des scénarios plus riches, traçables et faciles à exploiter">Des scénarios plus riches, traçables et faciles à exploiter</h2>
            <p class="updates-summary"
               data-site-i18n-en="This update enriches scenario design with source tracking, analysis exports, LaTeX formulas and teacher materials. It also makes scenarios easier to find and reuse with new import filters."
               data-site-i18n-fr="Cette mise à jour enrichit la conception des scénarios avec le suivi de leur origine, l’export des analyses, les formules LaTeX et le matériel enseignant. Elle facilite aussi leur recherche et leur réutilisation grâce à de nouveaux filtres d’import.">Cette mise à jour enrichit la conception des scénarios avec le suivi de leur origine, l’export des analyses, les formules LaTeX et le matériel enseignant. Elle facilite aussi leur recherche et leur réutilisation grâce à de nouveaux filtres d’import.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-code-branch"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Source and author tracking" data-site-i18n-fr="Suivi de l’origine et de l’auteur">Suivi de l’origine et de l’auteur</h3>
                        <p data-site-i18n-en="Scénarisation now preserves the source, author, licence and date of every adaptation. This information remains visible in the application and its exports. All scenarios can be adapted privately; a NoDerivatives adaptation cannot be published, while a ShareAlike adaptation retains the source licence."
                           data-site-i18n-fr="Scénarisation conserve désormais la source, l’auteur, la licence et la date de chaque adaptation. Ces informations restent visibles dans l’application et les exports. Tous les scénarios peuvent être adaptés en privé&nbsp;; une adaptation NoDerivatives ne peut pas être publiée, tandis qu’une adaptation ShareAlike conserve la licence d’origine.">Scénarisation conserve désormais la source, l’auteur, la licence et la date de chaque adaptation. Ces informations restent visibles dans l’application et les exports. Tous les scénarios peuvent être adaptés en privé&nbsp;; une adaptation NoDerivatives ne peut pas être publiée, tandis qu’une adaptation ShareAlike conserve la licence d’origine.</p>
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
                        <h3 data-site-i18n-en="New import filters" data-site-i18n-fr="Nouveaux filtres d’import">Nouveaux filtres d’import</h3>
                        <p data-site-i18n-en="The template and shared-library import windows can now be filtered by duration, number of stages, delivery mode, learning type, school system, level and competency framework."
                           data-site-i18n-fr="Les fenêtres d’import des modèles et de la bibliothèque partagée peuvent désormais être filtrées par durée, nombre de moments, modalité, type d’apprentissage, système scolaire, niveau et référentiel de compétences.">Les fenêtres d’import des modèles et de la bibliothèque partagée peuvent désormais être filtrées par durée, nombre de moments, modalité, type d’apprentissage, système scolaire, niveau et référentiel de compétences.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-square-root-variable"></i></span>
                    <div>
                        <h3 data-site-i18n-en="LaTeX formula editor" data-site-i18n-fr="Éditeur de formules LaTeX">Éditeur de formules LaTeX</h3>
                        <p data-site-i18n-en="A guided assistant now makes it easy to insert inline or centred mathematical formulas into Markdown fields. Formulas are rendered in the editor, shared pages, HTML exports and Word equations; their LaTeX source remains editable in Markdown, JSON and Excel and can also be supplied through the CLI."
                           data-site-i18n-fr="Un assistant guidé permet désormais d’insérer facilement des formules mathématiques dans le texte ou centrées dans les champs Markdown. Les formules sont rendues dans l’éditeur, les pages partagées, les exports HTML et les équations Word&nbsp;; leur source LaTeX reste modifiable dans les formats Markdown, JSON et Excel et peut aussi être fournie par le CLI.">Un assistant guidé permet désormais d’insérer facilement des formules mathématiques dans le texte ou centrées dans les champs Markdown. Les formules sont rendues dans l’éditeur, les pages partagées, les exports HTML et les équations Word&nbsp;; leur source LaTeX reste modifiable dans les formats Markdown, JSON et Excel et peut aussi être fournie par le CLI.</p>
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
               data-site-i18n-fr="Une nouvelle page de démarrage rassemble les différentes façons de commencer&nbsp;: partir d’une page blanche, adapter un modèle ou importer un scénario partagé.">Une nouvelle page de démarrage rassemble les différentes façons de commencer&nbsp;: partir d’une page blanche, adapter un modèle ou importer un scénario partagé.</p>

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

    <section class="updates-entry" aria-labelledby="updates-2026-09-06">
        <div class="updates-date">
            <time datetime="2026-09-06" data-site-i18n-en="6 September 2026" data-site-i18n-fr="6 septembre 2026">6 septembre 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-09-06"
                data-site-i18n-en="Search, saved scenarios and more school systems"
                data-site-i18n-fr="Recherche, scénarios sauvegardés et systèmes scolaires élargis">Recherche, scénarios sauvegardés et systèmes scolaires élargis</h2>
            <p class="updates-summary"
               data-site-i18n-en="Scénarisation becomes easier to explore and adapts to a wider range of educational contexts, while giving users more control over their saved work."
               data-site-i18n-fr="Scénarisation devient plus facile à explorer et s’adapte à davantage de contextes éducatifs, tout en donnant plus de contrôle sur les travaux sauvegardés.">Scénarisation devient plus facile à explorer et s’adapte à davantage de contextes éducatifs, tout en donnant plus de contrôle sur les travaux sauvegardés.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Bilingual site search" data-site-i18n-fr="Recherche bilingue dans le site">Recherche bilingue dans le site</h3>
                        <p data-site-i18n-en="A French and English search engine now helps users find features, documentation and reference pages, with search terms highlighted on arrival."
                           data-site-i18n-fr="Un moteur de recherche en français et en anglais permet désormais de retrouver les fonctionnalités, la documentation et les pages de référence, avec mise en évidence des termes recherchés à l’arrivée.">Un moteur de recherche en français et en anglais permet désormais de retrouver les fonctionnalités, la documentation et les pages de référence, avec mise en évidence des termes recherchés à l’arrivée.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-school"></i></span>
                    <div>
                        <h3 data-site-i18n-en="International programmes and portable backups" data-site-i18n-fr="Programmes internationaux et sauvegardes transportables">Programmes internationaux et sauvegardes transportables</h3>
                        <p data-site-i18n-en="International Baccalaureate programmes join the supported school systems. Users can also export their saved scenarios from their profile for safekeeping or reuse."
                           data-site-i18n-fr="Les programmes du Baccalauréat International rejoignent les systèmes scolaires pris en charge. Les utilisateurs peuvent aussi exporter leurs scénarios sauvegardés depuis leur profil afin de les conserver ou de les réutiliser.">Les programmes du Baccalauréat International rejoignent les systèmes scolaires pris en charge. Les utilisateurs peuvent aussi exporter leurs scénarios sauvegardés depuis leur profil afin de les conserver ou de les réutiliser.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-09-05">
        <div class="updates-date">
            <time datetime="2026-09-05" data-site-i18n-en="5 September 2026" data-site-i18n-fr="5 septembre 2026">5 septembre 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-09-05"
                data-site-i18n-en="A more reliable and supportive editor"
                data-site-i18n-fr="Un éditeur plus fiable et plus accompagnant">Un éditeur plus fiable et plus accompagnant</h2>
            <p class="updates-summary"
               data-site-i18n-en="The scenario editor better explains its pedagogical choices and protects work more effectively when several changes or saves happen close together."
               data-site-i18n-fr="L’éditeur de scénarios explicite mieux les choix pédagogiques et protège davantage le travail lorsque plusieurs modifications ou sauvegardes se succèdent.">L’éditeur de scénarios explicite mieux les choix pédagogiques et protège davantage le travail lorsque plusieurs modifications ou sauvegardes se succèdent.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-circle-question"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Help where it is needed" data-site-i18n-fr="De l’aide au bon endroit">De l’aide au bon endroit</h3>
                        <p data-site-i18n-en="Contextual explanations now accompany the main fields, while activity forms guide users through the essential learning, teaching and assessment choices."
                           data-site-i18n-fr="Des explications contextuelles accompagnent désormais les principaux champs, tandis que les formulaires d’activité guident les choix essentiels liés à l’apprentissage, à l’enseignement et à l’évaluation.">Des explications contextuelles accompagnent désormais les principaux champs, tandis que les formulaires d’activité guident les choix essentiels liés à l’apprentissage, à l’enseignement et à l’évaluation.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Safer automatic saving" data-site-i18n-fr="Sauvegarde automatique sécurisée">Sauvegarde automatique sécurisée</h3>
                        <p data-site-i18n-en="The editor detects conflicting versions and preserves unsaved drafts, reducing the risk of losing recent changes when a scenario is opened or edited in several places."
                           data-site-i18n-fr="L’éditeur détecte les versions en conflit et préserve les brouillons non synchronisés, ce qui réduit le risque de perdre des changements récents lorsqu’un scénario est ouvert ou modifié à plusieurs endroits.">L’éditeur détecte les versions en conflit et préserve les brouillons non synchronisés, ce qui réduit le risque de perdre des changements récents lorsqu’un scénario est ouvert ou modifié à plusieurs endroits.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-08-30">
        <div class="updates-date">
            <time datetime="2026-08-30" data-site-i18n-en="30 August 2026" data-site-i18n-fr="30 août 2026">30 août 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-08-30"
                data-site-i18n-en="Richer and more precise competency frameworks"
                data-site-i18n-fr="Des référentiels enrichis et plus précis">Des référentiels enrichis et plus précis</h2>
            <p class="updates-summary"
               data-site-i18n-en="More detailed educational references make it easier to identify and assign the right competencies to each activity."
               data-site-i18n-fr="Des références pédagogiques plus détaillées facilitent l’identification et l’attribution des compétences adaptées à chaque activité.">Des références pédagogiques plus détaillées facilitent l’identification et l’attribution des compétences adaptées à chaque activité.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-sitemap"></i></span>
                    <div>
                        <h3 data-site-i18n-en="More precise competency selection" data-site-i18n-fr="Sélection plus précise des compétences">Sélection plus précise des compétences</h3>
                        <p data-site-i18n-en="Competencies can now be selected at a finer level. The DigComp and GreenComp catalogues provide expanded descriptions that make the frameworks easier to understand and apply."
                           data-site-i18n-fr="Les compétences peuvent désormais être sélectionnées à un niveau plus fin. Les catalogues DigComp et GreenComp proposent des descriptions enrichies qui facilitent la compréhension et l’utilisation des référentiels.">Les compétences peuvent désormais être sélectionnées à un niveau plus fin. Les catalogues DigComp et GreenComp proposent des descriptions enrichies qui facilitent la compréhension et l’utilisation des référentiels.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-08-23">
        <div class="updates-date">
            <time datetime="2026-08-23" data-site-i18n-en="23 August 2026" data-site-i18n-fr="23 août 2026">23 août 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-08-23"
                data-site-i18n-en="Models and more complete teaching plans"
                data-site-i18n-fr="Des modèles et une planification plus complète">Des modèles et une planification plus complète</h2>
            <p class="updates-summary"
               data-site-i18n-en="New starting points and richer planning options help turn an idea into a complete, reusable teaching scenario."
               data-site-i18n-fr="De nouveaux points de départ et des options de planification enrichies aident à transformer une idée en scénario complet et réutilisable.">De nouveaux points de départ et des options de planification enrichies aident à transformer une idée en scénario complet et réutilisable.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Reusable models and fuller planning" data-site-i18n-fr="Modèles réutilisables et planification plus complète">Modèles réutilisables et planification plus complète</h3>
                        <p data-site-i18n-en="A model library offers ready-made structures to adapt. Activities also gain richer planning options, teaching modes, export choices and student-facing materials."
                           data-site-i18n-fr="Une bibliothèque propose des structures prêtes à adapter. Les activités gagnent également des options de planification, des modes d’enseignement, des exports et des supports destinés aux élèves.">Une bibliothèque propose des structures prêtes à adapter. Les activités gagnent également des options de planification, des modes d’enseignement, des exports et des supports destinés aux élèves.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-07-28">
        <div class="updates-date">
            <time datetime="2026-07-28" data-site-i18n-en="28 July 2026" data-site-i18n-fr="28 juillet 2026">28 juillet 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-07-28"
                data-site-i18n-en="Bilingual resources for teaching design"
                data-site-i18n-fr="Des ressources bilingues pour la conception pédagogique">Des ressources bilingues pour la conception pédagogique</h2>
            <p class="updates-summary"
               data-site-i18n-en="New French and English resources support teachers throughout the scenario design process."
               data-site-i18n-fr="De nouvelles ressources en français et en anglais accompagnent les enseignants tout au long de la conception d’un scénario.">De nouvelles ressources en français et en anglais accompagnent les enseignants tout au long de la conception d’un scénario.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-comments"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Bilingual prompts and complete help" data-site-i18n-fr="Prompts bilingues et aide complète">Prompts bilingues et aide complète</h3>
                        <p data-site-i18n-en="A French and English prompt library provides practical starting points for working with an AI assistant, alongside a redesigned help page covering the whole application."
                           data-site-i18n-fr="Une bibliothèque de prompts en français et en anglais offre des points de départ concrets pour travailler avec un assistant IA, aux côtés d’une page d’aide repensée couvrant toute l’application.">Une bibliothèque de prompts en français et en anglais offre des points de départ concrets pour travailler avec un assistant IA, aux côtés d’une page d’aide repensée couvrant toute l’application.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-07-21">
        <div class="updates-date">
            <time datetime="2026-07-21" data-site-i18n-en="21 July 2026" data-site-i18n-fr="21 juillet 2026">21 juillet 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-07-21"
                data-site-i18n-en="More flexible imports and exports"
                data-site-i18n-fr="Des imports et exports plus souples">Des imports et exports plus souples</h2>
            <p class="updates-summary"
               data-site-i18n-en="Scenarios become easier to move between tools and to export in the format needed for each use."
               data-site-i18n-fr="Les scénarios circulent plus facilement entre les outils et s’exportent dans le format adapté à chaque usage.">Les scénarios circulent plus facilement entre les outils et s’exportent dans le format adapté à chaque usage.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-file-import"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Markdown import and improved exports" data-site-i18n-fr="Import Markdown et exports améliorés">Import Markdown et exports améliorés</h3>
                        <p data-site-i18n-en="Scénarisation can import a structured Markdown scenario. Document exports are clearer, offer custom filenames and guide users through the available formats."
                           data-site-i18n-fr="Scénarisation peut importer un scénario structuré en Markdown. Les exports de documents sont plus clairs, proposent un nom de fichier personnalisé et guident le choix du format.">Scénarisation peut importer un scénario structuré en Markdown. Les exports de documents sont plus clairs, proposent un nom de fichier personnalisé et guident le choix du format.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-06-13">
        <div class="updates-date">
            <time datetime="2026-06-13" data-site-i18n-en="13 June 2026" data-site-i18n-fr="13 juin 2026">13 juin 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-06-13"
                data-site-i18n-en="Smoother sharing and adaptation"
                data-site-i18n-fr="Un partage et une adaptation plus fluides">Un partage et une adaptation plus fluides</h2>
            <p class="updates-summary"
               data-site-i18n-en="Publishing, importing and adapting shared scenarios become more consistent across the site and the command-line workflow."
               data-site-i18n-fr="La publication, l’import et l’adaptation des scénarios partagés deviennent plus cohérents dans le site comme dans le parcours en ligne de commande.">La publication, l’import et l’adaptation des scénarios partagés deviennent plus cohérents dans le site comme dans le parcours en ligne de commande.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-share-nodes"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Shared scenarios easier to reuse" data-site-i18n-fr="Des scénarios partagés plus faciles à réutiliser">Des scénarios partagés plus faciles à réutiliser</h3>
                        <p data-site-i18n-en="Shared scenarios are easier to publish, browse and import, providing a clearer path from discovering an existing design to adapting it for a new context."
                           data-site-i18n-fr="Les scénarios partagés sont plus simples à publier, parcourir et importer, avec un chemin plus clair entre la découverte d’une conception existante et son adaptation à un nouveau contexte.">Les scénarios partagés sont plus simples à publier, parcourir et importer, avec un chemin plus clair entre la découverte d’une conception existante et son adaptation à un nouveau contexte.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-05-20">
        <div class="updates-date">
            <time datetime="2026-05-20" data-site-i18n-en="20 May 2026" data-site-i18n-fr="20 mai 2026">20 mai 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-05-20"
                data-site-i18n-en="The CLI and AI assistance take shape"
                data-site-i18n-fr="Le CLI et l’assistance IA prennent forme">Le CLI et l’assistance IA prennent forme</h2>
            <p class="updates-summary"
               data-site-i18n-en="Scénarisation expands beyond the graphical editor with a command-line workflow and reusable instructions for AI assistants."
               data-site-i18n-fr="Scénarisation dépasse l’éditeur graphique avec un parcours en ligne de commande et des instructions réutilisables pour les assistants IA.">Scénarisation dépasse l’éditeur graphique avec un parcours en ligne de commande et des instructions réutilisables pour les assistants IA.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-terminal"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Create and publish from the terminal" data-site-i18n-fr="Créer et publier depuis le terminal">Créer et publier depuis le terminal</h3>
                        <p data-site-i18n-en="An installable command-line tool can create, validate and publish teaching scenarios. Dedicated documentation explains the workflow and its publication token."
                           data-site-i18n-fr="Un outil installable en ligne de commande permet de créer, valider et publier des scénarios pédagogiques. Une documentation dédiée explique le parcours et son jeton de publication.">Un outil installable en ligne de commande permet de créer, valider et publier des scénarios pédagogiques. Une documentation dédiée explique le parcours et son jeton de publication.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-robot"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Reusable AI guidance" data-site-i18n-fr="Accompagnement IA réutilisable">Accompagnement IA réutilisable</h3>
                        <p data-site-i18n-en="A reusable skill guides AI assistants through learning-design tasks and helps produce scenarios that can be continued in the application."
                           data-site-i18n-fr="Une skill réutilisable guide les assistants IA dans les tâches de scénarisation pédagogique et aide à produire des scénarios qui peuvent être poursuivis dans l’application.">Une skill réutilisable guide les assistants IA dans les tâches de scénarisation pédagogique et aide à produire des scénarios qui peuvent être poursuivis dans l’application.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="updates-entry" aria-labelledby="updates-2026-05-02">
        <div class="updates-date">
            <time datetime="2026-05-02" data-site-i18n-en="2 May 2026" data-site-i18n-fr="2 mai 2026">2 mai 2026</time>
        </div>
        <div class="updates-content">
            <h2 id="updates-2026-05-02"
                data-site-i18n-en="The foundations of Scénarisation"
                data-site-i18n-fr="Les fondations de Scénarisation">Les fondations de Scénarisation</h2>
            <p class="updates-summary"
               data-site-i18n-en="The project takes shape around a visual teaching-scenario editor and the first personal workspaces."
               data-site-i18n-fr="Le projet prend forme autour d’un éditeur visuel de scénarios pédagogiques et des premiers espaces de travail personnels.">Le projet prend forme autour d’un éditeur visuel de scénarios pédagogiques et des premiers espaces de travail personnels.</p>

            <div class="updates-list">
                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-pen-ruler"></i></span>
                    <div>
                        <h3 data-site-i18n-en="The first scenario editor" data-site-i18n-fr="Le premier éditeur de scénarios">Le premier éditeur de scénarios</h3>
                        <p data-site-i18n-en="An initial graphical interface provides the essential structure for designing learning activities and organising a teaching scenario."
                           data-site-i18n-fr="Une première interface graphique fournit la structure essentielle pour concevoir des activités d’apprentissage et organiser un scénario pédagogique.">Une première interface graphique fournit la structure essentielle pour concevoir des activités d’apprentissage et organiser un scénario pédagogique.</p>
                    </div>
                </article>

                <article class="updates-item">
                    <span class="updates-icon" aria-hidden="true"><i class="fa-solid fa-user-lock"></i></span>
                    <div>
                        <h3 data-site-i18n-en="Accounts and saved work" data-site-i18n-fr="Comptes et travaux sauvegardés">Comptes et travaux sauvegardés</h3>
                        <p data-site-i18n-en="Users can create an account, sign in and keep their scenarios in a personal space, with the ability to reopen or delete saved work."
                           data-site-i18n-fr="Les utilisateurs peuvent créer un compte, se connecter et conserver leurs scénarios dans un espace personnel, avec la possibilité de rouvrir ou de supprimer les travaux sauvegardés.">Les utilisateurs peuvent créer un compte, se connecter et conserver leurs scénarios dans un espace personnel, avec la possibilité de rouvrir ou de supprimer les travaux sauvegardés.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>
<?php render_site_footer(); ?>
</body>
</html>
