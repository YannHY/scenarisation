<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// La navigation lit la session : elle doit démarrer avant tout HTML.
app_start_session();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260804" type="image/svg+xml" sizes="any">
    <meta name="description" content="Le Cadre conversationnel de Diana Laurillard : concepts, pratiques, cycles et six types d'apprentissage.">
    <title>Le Cadre conversationnel de Diana Laurillard | Learning Designer</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260906-highlight">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
    <link rel="stylesheet" href="css/cadre-conversationnel.css?v=20260730-toc-align">
</head>
<body class="cf-page">
<a class="skip-link" href="#main-content">Aller au contenu principal</a>
<?php render_site_nav('framework'); ?>

<main id="main-content" class="cf-shell">
    <article class="cf-article">
        <header class="cf-hero">
            <p class="cf-kicker">Documentation</p>
            <h1>Le Cadre conversationnel</h1>
        </header>

        <div class="cf-article-layout">
            <aside class="cf-toc" aria-label="Sommaire">
                <p>Sur cette page</p>
                <ol>
                    <li><a href="#introduction">Introduction</a></li>
                    <li><a href="#coeur">Concepts et pratiques</a></li>
                    <li><a href="#cycles">Des cycles reliés</a></li>
                    <li><a href="#roles">Enseignant et pairs</a></li>
                    <li><a href="#six-types">Les six types</a></li>
                    <li><a href="#synthese">Tableau de synthèse</a></li>
                    <li><a href="#conclusion">Conclusion</a></li>
                </ol>
                <div class="cf-toc-source">
                    <a href="https://mediacentral.ucl.ac.uk/Player/CG6hD928" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-circle-play" aria-hidden="true"></i> Vidéo originale</a>
                    <a href="https://ugc.futurelearn.com/uploads/files/3a/ed/3aedab5f-fcc0-44e9-8b85-d2589f9af37b/Step_1.4_CF_screencast.pdf" target="_blank" rel="noopener noreferrer"><i class="fa-regular fa-file-pdf" aria-hidden="true"></i> Schémas originaux</a>
                </div>
            </aside>

            <div class="cf-prose">
                <section class="cf-section cf-introduction" id="introduction">
                    <h2>Introduction</h2>

                    <p>Le <em>Cadre conversationnel</em> (<strong>Conversational Framework</strong>) élaboré par Diana Laurillard a été conçu pour répondre à une question apparemment simple mais fondatrice : <strong>qu'est-ce qu'apprendre exige réellement ?</strong> Il constitue une distillation des grandes théories de l'enseignement et de l'apprentissage, et propose une représentation simple du processus enseignement-apprentissage. Il permet d'évaluer la valeur pédagogique de nos scénarios d'apprentissage.</p>

                    <p>L'apprentissage y est représenté comme une série d'échanges : entre un apprenant et un enseignant d'une part, entre un apprenant et ses pairs d'autre part. Ces échanges se déploient à deux niveaux complémentaires — celui des <strong>concepts</strong> et celui des <strong>pratiques</strong>.</p>
                </section>

                <section class="cf-section" id="coeur">
                    <h2>1. Le cœur du modèle : concepts et pratiques</h2>

                    <p>Le point de départ du cadre est une manière simple de se représenter ce qui se joue chez l'apprenant. Celui-ci mobilise deux registres :</p>

                    <ul>
                        <li>des <strong>concepts</strong>, c'est-à-dire des savoirs, des idées, des connaissances ;</li>
                        <li>des <strong>pratiques</strong>, c'est-à-dire des savoir-faire, des compétences, des actions.</li>
                    </ul>

                    <p>Ces deux registres ne sont pas statiques : ils se construisent par itérations successives.</p>

                    <p>Au <strong>niveau conceptuel</strong>, l'apprenant reprend et retravaille continuellement ses idées. C'est par ces reprises qu'un concept se précise, se stabilise, puis se relie aux autres concepts déjà maîtrisés — l'apprentissage n'est jamais l'ajout d'une brique isolée, mais l'intégration d'une idée dans un réseau existant.</p>

                    <p>Au <strong>niveau pratique</strong>, un mécanisme analogue est à l'œuvre : des actions répétées et ajustées permettent de développer progressivement une compétence, puis des compétences de plus en plus complexes.</p>

                    <figure class="cf-schema cf-schema-centered">
                        <img src="assets/conversational-framework/schema/cadre-03.svg" alt="Schéma Excalidraw montrant les cycles internes des concepts et de la pratique de l'apprenant." width="1600" height="900" loading="lazy">
                        <figcaption>Les cycles internes de l'apprenant</figcaption>
                    </figure>
                </section>

                <section class="cf-section" id="cycles">
                    <h2>2. Le moteur de l'apprentissage : des cycles reliés entre eux</h2>

                    <p>L'élément décisif du modèle est que ces deux niveaux ne fonctionnent pas en parallèle sans se rencontrer. Un troisième cycle, transversal, relie les concepts et les pratiques : <strong>les concepts nourrissent la pratique, et la pratique fait évoluer les concepts</strong>, dans un va-et-vient continu.</p>

                    <p>C'est de cette circulation que dépend l'apprentissage réel. Pour qu'il ait lieu, l'enseignant doit veiller à ce que ces différentes boucles soient effectivement actives : que l'élève retravaille bien ses idées, qu'il exerce bien ses gestes, et surtout que les deux se fécondent mutuellement. Autrement dit, il ne suffit pas de « transmettre » des concepts ni de « faire faire » des exercices : c'est l'articulation entre les deux qui produit un apprentissage solide.</p>
                </section>

                <section class="cf-section" id="roles">
                    <h2>3. Le rôle de l'enseignant et celui des pairs</h2>

                    <p>L'enseignant agit sur ces cycles de deux façons complémentaires. D'une part, il engage l'élève dans une réflexion sur les concepts. D'autre part, il aménage l'environnement d'apprentissage pour que l'élève puisse mettre ces idées à l'épreuve de l'action.</p>

                    <p>Deux exemples illustrent bien cette double action. Si l'on veut que les élèves comprennent les équations, on leur fait résoudre des équations. Si l'on enseigne la démocratie, on organise une élection. Dans les deux cas, le concept ne prend consistance que parce qu'il est mis en pratique.</p>

                    <p>Les autres élèves jouent eux aussi un rôle essentiel, et non accessoire. Les pairs discutent et débattent des concepts, exercent ensemble leurs compétences, et partagent leur expérience pratique. La dimension sociale de l'apprentissage n'est donc pas un simple supplément d'ambiance : elle constitue l'une des sources d'échange qui alimentent les cycles conceptuels et pratiques.</p>

                    <p>À partir de cette structure de base — deux acteurs (enseignant, pairs), deux niveaux (concepts, pratiques), et les cycles qui les relient — Laurillard montre que l'on peut représenter l'ensemble des grands types d'apprentissage utilisés en éducation. Chaque type sollicite une partie particulière du cadre.</p>

                    <figure class="cf-schema cf-schema-wide">
                        <img src="assets/conversational-framework/schema/cadre-04.svg" alt="Schéma Excalidraw du cadre avec les concepts de l'enseignant et des pairs, l'environnement d'apprentissage, la pratique des pairs et les cycles de communication et de modélisation." width="1600" height="900" loading="lazy">
                        <figcaption>Les échanges avec l'enseignant, l'environnement et les pairs</figcaption>
                    </figure>
                </section>

                <section class="cf-section cf-types" id="six-types">
                    <h2>4. Les six types d'apprentissage</h2>

                    <section class="cf-type-section cf-type-acquisition">
                        <div class="cf-type-copy">
                            <h3>4.1 L'apprentissage par acquisition</h3>

                            <p>Dans l'<strong>acquisition</strong>, l'enseignant communique des concepts et des idées qui viennent modifier certaines représentations de l'élève ; une présentation supplémentaire en modifie d'autres, et ainsi de suite. C'est la forme la plus familière : cours magistral, lecture, vidéo, exposé...</p>

                            <p>Sa caractéristique déterminante est que l'élève n'a pas, dans ce cadre, à produire lui-même des idées. L'activité conceptuelle y est donc la plus réduite des six types. Cela ne rend pas l'acquisition inutile — elle reste indispensable pour donner accès à des savoirs constitués — mais elle appelle à être complétée par des formes d'apprentissage plus actives.</p>
                        </div>
                        <figure class="cf-schema cf-type-schema">
                            <img src="assets/conversational-framework/schema/cadre-05.svg" alt="Schéma Excalidraw de l'apprentissage par acquisition." width="1600" height="900" loading="lazy">
                            <figcaption>Apprendre par acquisition</figcaption>
                        </figure>
                    </section>

                    <section class="cf-type-section cf-type-inquiry">
                        <div class="cf-type-copy">
                            <h3>4.2 L'apprentissage par enquête</h3>

                            <p>L'<strong>enquête</strong> (ou investigation) se distingue nettement de l'acquisition, car l'élève y explore et questionne les concepts de l'enseignant, puis se sert de ce qu'il trouve pour formuler de nouvelles idées sur ce qu'il convient de chercher ensuite. On entre alors dans une véritable boucle : l'élève génère des questions, cherche les réponses dans les ressources mises à sa disposition, et repart de ce qu'il a trouvé.</p>

                            <p>Cette activité produit davantage d'élaboration conceptuelle que la simple acquisition, précisément parce que l'élève est engagé dans la production des questions et la recherche des réponses. L'« enseignant » peut d'ailleurs être ici une personne, mais aussi un livre, un site web, une base documentaire, etc. Un principe s'en dégage : plus les cycles sont nombreux, plus l'élève a d'occasions de faire évoluer ses idées.</p>
                        </div>
                        <figure class="cf-schema cf-type-schema">
                            <img src="assets/conversational-framework/schema/cadre-06.svg" alt="Schéma Excalidraw de l'apprentissage par enquête." width="1600" height="900" loading="lazy">
                            <figcaption>Apprendre par enquête</figcaption>
                        </figure>
                    </section>

                    <section class="cf-type-section cf-type-practice">
                        <div class="cf-type-copy">
                            <h3>4.3 L'apprentissage par la pratique</h3>

                            <p>Dans l'<strong>apprentissage par la pratique</strong>, l'élève utilise l'environnement d'apprentissage aménagé par l'enseignant. Le déroulement typique est le suivant : on lui demande d'atteindre un objectif ; il produit une action ; il reçoit un retour (<em>feedback</em>) ; il ajuste son action ; il reçoit un nouveau retour — et ainsi de suite.</p>

                            <p>À ce cycle de l'action peut s'ajouter une dimension plus profonde : l'élève est encouragé à laisser sa pratique modifier ses concepts, et réciproquement, afin de produire une action encore mieux ajustée. C'est pourquoi ce type d'apprentissage est particulièrement puissant : il engage simultanément le développement des concepts, celui des pratiques, et l'établissement de liens entre les deux. La qualité du <em>feedback</em> — qu'il vienne de l'enseignant ou de l'environnement lui-même — y est déterminante.</p>
                        </div>
                        <figure class="cf-schema cf-type-schema">
                            <img src="assets/conversational-framework/schema/cadre-07.svg" alt="Schéma Excalidraw de l'apprentissage par la pratique." width="1600" height="900" loading="lazy">
                            <figcaption>Apprendre par la pratique</figcaption>
                        </figure>
                    </section>

                    <section class="cf-type-section cf-type-discussion">
                        <div class="cf-type-copy">
                            <h3>4.4 L'apprentissage par la discussion</h3>

                            <p>L'<strong>apprentissage par la discussion</strong> repose sur la construction sociale des idées. En échangeant, les élèves développent et affinent leurs concepts. Le mécanisme est là encore itératif : ils formulent des questions, reçoivent des retours de leurs pairs, et répondent à leur tour à ces questions.</p>

                            <p>Ces reprises successives les engagent activement dans le développement de leurs concepts. La discussion prolonge ainsi le travail conceptuel, mais en le nourrissant de la confrontation à d'autres points de vue.</p>
                        </div>
                        <figure class="cf-schema cf-type-schema">
                            <img src="assets/conversational-framework/schema/cadre-08.svg" alt="Schéma Excalidraw de l'apprentissage par la discussion." width="1600" height="900" loading="lazy">
                            <figcaption>Apprendre par la discussion</figcaption>
                        </figure>
                    </section>

                    <section class="cf-type-section cf-type-collaboration">
                        <div class="cf-type-copy">
                            <h3>4.5 L'apprentissage par la collaboration</h3>

                            <p>L'<strong>apprentissage par la collaboration</strong> combine plusieurs registres. Chaque élève apprend par la pratique, en utilisant l'environnement d'apprentissage ; mais en même temps, il discute avec ses pairs, partage les productions issues de sa pratique, et relie ces deux dimensions.</p>

                            <p>Ce type d'apprentissage développe donc à la fois les concepts et les pratiques, avec un enrichissement supplémentaire : celui du contexte social d'apprentissage. La collaboration va plus loin que la simple discussion, car elle oblige les élèves à <strong>négocier ce qu'ils vont faire concrètement</strong> ensemble. Elle est de ce fait plus engageante que la discussion seule ou que la pratique seule. Son intérêt tient précisément à cette double source de retours : celui de l'environnement de pratique, et celui de la discussion et de la négociation avec les pairs.</p>
                        </div>
                        <figure class="cf-schema cf-type-schema">
                            <img src="assets/conversational-framework/schema/cadre-09.svg" alt="Schéma Excalidraw de l'apprentissage par la collaboration." width="1600" height="900" loading="lazy">
                            <figcaption>Apprendre par la collaboration</figcaption>
                        </figure>
                    </section>

                    <section class="cf-type-section cf-type-production">
                        <div class="cf-type-copy">
                            <h3>4.6 L'apprentissage par la production</h3>

                            <p>L'<strong>apprentissage par la production</strong>, enfin, intervient lorsque les élèves réfléchissent à ce qu'ils ont fait, expérimenté et appris, et le mettent en forme pour le communiquer à l'enseignant. Ils doivent alors relier explicitement leurs concepts et leurs pratiques, puis produire un objet abouti : une dissertation, une performance, une présentation, qui donne à voir ce qui a été appris. Le retour de l'enseignant vient ensuite renforcer et consolider cet apprentissage.</p>

                            <p>C'est cette production de l'élève que l'on mobilise dans l'<strong>évaluation</strong> : elle constitue la preuve, fournie à l'enseignant, de ce qui a été appris. Devoir produire quelque chose pour l'enseignant ou pour la classe a par ailleurs une vertu motivationnelle : c'est l'une des raisons pour lesquelles l'évaluation est utilisée comme levier pour amener les élèves à se concentrer sur leur propre processus d'apprentissage.</p>
                        </div>
                        <figure class="cf-schema cf-type-schema">
                            <img src="assets/conversational-framework/schema/cadre-10.svg" alt="Schéma Excalidraw de l'apprentissage par la production." width="1600" height="900" loading="lazy">
                            <figcaption>Apprendre par la production</figcaption>
                        </figure>
                    </section>
                </section>

                <section class="cf-section" id="synthese">
                    <h2>5. Un tableau de synthèse des six types</h2>

                    <div class="cf-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type d'apprentissage</th>
                                    <th>Ce que fait principalement l'élève</th>
                                    <th>Source du retour</th>
                                    <th>Concepts / Pratiques</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Acquisition</td>
                                    <td>Écoute, lit, regarde ; ne génère pas d'idées</td>
                                    <td>Enseignant (présentation)</td>
                                    <td>Concepts (activité faible)</td>
                                </tr>
                                <tr>
                                    <td>Enquête</td>
                                    <td>Explore et questionne, génère des questions</td>
                                    <td>Ressources, enseignant</td>
                                    <td>Concepts (activité forte)</td>
                                </tr>
                                <tr>
                                    <td>Pratique</td>
                                    <td>Agit pour atteindre un but, ajuste ses actions</td>
                                    <td>Environnement, enseignant</td>
                                    <td>Concepts + Pratiques</td>
                                </tr>
                                <tr>
                                    <td>Discussion</td>
                                    <td>Formule des questions, argumente</td>
                                    <td>Pairs</td>
                                    <td>Concepts</td>
                                </tr>
                                <tr>
                                    <td>Collaboration</td>
                                    <td>Négocie et produit avec les autres</td>
                                    <td>Environnement + pairs</td>
                                    <td>Concepts + Pratiques (contexte social)</td>
                                </tr>
                                <tr>
                                    <td>Production</td>
                                    <td>Met en forme et communique ce qu'il a appris</td>
                                    <td>Enseignant (évaluation)</td>
                                    <td>Concepts + Pratiques reliés</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </section>

                <section class="cf-section cf-conclusion" id="conclusion">
                    <h2>Conclusion : un modèle pour concevoir l'apprentissage</h2>

                    <p>Réunis, ces six types d'apprentissage constituent le Cadre conversationnel complet. Leur force tient à leur relation : ils se complètent et se renforcent mutuellement, tout en restant chacun distinct des autres. Un scénario pédagogique riche ne se contente donc pas d'un seul type — il fait circuler l'élève entre acquisition, enquête, pratique, discussion, collaboration et production.</p>

                    <p>Laurillard reconnaît volontiers qu'il s'agit d'une simplification. Mais c'est précisément ce dont on a besoin pour concevoir l'apprentissage : une représentation <em>utilisable</em> de la manière dont les élèves apprennent, suffisamment claire pour guider l'action, sans se perdre dans une complexité inexploitable. Largement diffusé aujourd'hui, ce cadre aide les enseignants à interroger leurs scénarios d'apprentissage : celui que je propose met-il réellement en jeu les cycles nécessaires pour que mes élèves apprennent ?</p>
                </section>

                <figure class="cf-schema cf-schema-wide cf-final-schema">
                    <img src="assets/conversational-framework/schema/cadre-11.svg" alt="Schéma Excalidraw complet des six types d'apprentissage dans le Cadre conversationnel." width="1600" height="900" loading="lazy">
                    <figcaption>Les six types d'apprentissage dans le Cadre conversationnel complet</figcaption>
                </figure>
            </div>
        </div>
    </article>
</main>

<?php render_site_footer(); ?>
</body>
</html>
