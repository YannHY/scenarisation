<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// La navigation lit la session : elle doit démarrer avant tout HTML.
app_start_session();

$aiPrompt = <<<'PROMPT'
Tu dois créer un design pédagogique Learning Designer en utilisant le CLI `learning`.

Important :
Tu es peut-être dans un sandbox isolé. Sélectionne le CLI selon ses capacités, pas seulement selon l’existence d’une commande.

Teste dans cet ordre :
1. `./bin/learning` si tu travailles dans le dépôt Learning Designer ;
2. la commande globale `learning` ;
3. `./.tools/bin/learning` si cette copie locale existe déjà.

Le CLI retenu doit prendre en charge `list school-systems`, `list activity-options`, les options `--school-system` et `--school-level` de `init`, les choix pédagogiques explicites de `add-activity` et `validate --strict-pedagogy`.

Si la commande globale existe mais n’est pas compatible, indique-moi simplement qu’elle doit être mise à jour et demande-moi l’autorisation de lancer `learning upgrade`. Après mon accord explicite, effectue la mise à jour et recommence les vérifications.

Si aucune copie compatible n’est disponible, installe une copie locale dans ton environnement :

mkdir -p .tools/bin
curl -fsSL https://raw.githubusercontent.com/YannHY/learning-designer/main/bin/learning -o .tools/bin/learning
chmod +x .tools/bin/learning
./.tools/bin/learning --help

Si raw.githubusercontent.com est bloqué, utilise web_fetch ou une autre méthode disponible pour récupérer :
https://github.com/YannHY/learning-designer/blob/main/bin/learning

Puis écris le fichier dans `.tools/bin/learning`, rends-le exécutable et vérifie ses capacités.

Mémorise la commande compatible retenue sous la variable `LEARNING` : `./bin/learning`, `learning` ou `./.tools/bin/learning`. Utilise ensuite `$LEARNING` dans toutes les commandes ci-dessous. Ne suppose pas qu’une variable shell définie dans une commande précédente persistera : si nécessaire, remplace `$LEARNING` par la commande retenue.

Une fois `.tools/bin/learning` créé, ne dépends plus du réseau.

Mission :
Créer un fichier `design.json` complet, structuré, détaillé et importable dans Learning Designer.

Utilise le CLI autant que possible. N’écris pas le JSON à la main sauf si le CLI est impossible à utiliser après plusieurs tentatives documentées.

Commence par me poser les questions nécessaires en français, sans me surcharger.

Questions indispensables :
- sujet ou thème de la séance/séquence ;
- système scolaire ou classification, puis niveau et public cible ;
- durée totale ;
- modalité : présentiel, distanciel ou hybride ;
- taille du groupe ;
- objectifs d’enseignement : ce que je veux faire travailler, transmettre ou entraîner ;
- acquis d’apprentissage attendus : ce que les élèves devront être capables de faire à la fin ;
- contraintes matérielles, pédagogiques ou institutionnelles ;
- niveau de détail souhaité.

Questions complémentaires à poser seulement si c’est utile :
- niveau Bloom souhaité pour chaque acquis, si je le connais ;
- compétences numériques à mobiliser, si pertinent ;
- supports, œuvres, ressources ou outils déjà imposés.

Distingue bien :
- les objectifs d’enseignement, qui décrivent mon intention pédagogique ;
- les acquis d’apprentissage, qui décrivent ce que les élèves sauront faire à la fin.

Si je donne seulement des objectifs d’enseignement, transforme-les en acquis d’apprentissage observables, formulés avec des verbes d’action et reliés à la taxonomie de Bloom.

Si certaines informations manquent, fais des hypothèses raisonnables au lieu de bloquer, sauf si l’hypothèse serait risquée.

Pour la Belgique, distingue les communautés française, flamande et germanophone. Pour le Royaume-Uni, distingue l’Angleterre, le pays de Galles, l’Écosse et l’Irlande du Nord. Considère les Écoles européennes comme un système transnational et ISCED 2011 comme une classification internationale. Si le choix est ambigu, demande la précision nécessaire. N’invente jamais un identifiant : consulte les catalogues du CLI.

Cas particulier de la durée :
- si la durée est donnée en jours, demande ou propose explicitement une durée par séance avant de générer le design ;
- par défaut, pour le collège, interprète 1 jour comme 1 séance de 55 minutes, sauf indication contraire ;
- annonce clairement l’hypothèse retenue.

Avant d’exécuter les commandes de création complète, reformule brièvement :
- le sujet ;
- le système scolaire ou la classification, le niveau et le public ;
- la durée totale convertie en minutes ;
- le nombre de moments prévu ;
- les objectifs d’enseignement ;
- les acquis Bloom proposés ;
- les principales compétences numériques, si elles sont mobilisées.

Ensuite utilise le CLI, pas une écriture manuelle du JSON.

Avant de créer toutes les activités, vérifie les commandes utiles :
- $LEARNING --help
- $LEARNING init --help
- $LEARNING add-moment --help
- $LEARNING add-activity --help
- $LEARNING outcome --help
- $LEARNING list types
- $LEARNING list bloom
- $LEARNING list competencies
- $LEARNING list activity-options
- $LEARNING list school-systems
- $LEARNING list school-levels --system IDENTIFIANT

Pour `init` et `add-activity`, utilise uniquement les valeurs contrôlées acceptées par le CLI.

Valeurs sûres :
- `--school-system` : un identifiant renvoyé par `list school-systems`
- `--school-level` : un identifiant renvoyé par `list school-levels --system IDENTIFIANT`
- `--type` : `read`, `investigate`, `practice`, `produce`, `discuss`, `collaborate`
- `--group` : `individual`, `subgroups`, `whole`
- `--teaching` : `directed`, `guided`, `supported`, `independent`
- `--pacing` : `sync`, `async`
- `--mode` : `onsite`, `location-based`, `online`, `blended`, `other`
- `--evaluation` : `diagnostic`, `formative`, `summative`, `certificative`, `none`
- `--aias` : `1`, `2`, `3`, `4`, `5` ou `not-applicable`

Pour chaque activité, détermine et transmets explicitement `--group`, `--teaching`, `--pacing`, `--mode`, `--evaluation` et `--aias`. Ne t’appuie pas sur des valeurs par défaut. Choisis-les comme un ensemble cohérent à partir de l’objectif, de l’autonomie des élèves, des interactions nécessaires, des contraintes de formation et des traces d’apprentissage attendues. AIAS 1 signifie sans IA ; AIAS 2 réserve l’IA à l’exploration, la recherche ou la planification ; AIAS 3 en fait une collaboratrice dont l’élève évalue et transforme les productions ; AIAS 4 l’intègre pleinement sous la direction critique de l’élève ; AIAS 5 correspond à l’exploration et à la co-conception de nouveaux usages. Utilise `not-applicable` seulement si le cadre AIAS ne s’applique réellement pas, et ne laisse jamais AIAS indécis dans un design généré.

Pour `init`, transmets le système et le niveau lorsqu’ils sont connus, par exemple : `$LEARNING init design.json --school-system france --school-level quatrieme`.

Utilise les valeurs canoniques ci-dessus pour `--pacing` et `--mode` ; le CLI accepte également leurs principaux équivalents français ou anglais.

Ne mets jamais de phrases longues dans les champs contrôlés comme `--school-system`, `--school-level`, `--group`, `--teaching`, `--evaluation`, `--type` ou `--pacing`.

Utilise `--description` pour décrire l’activité du point de vue pédagogique et `--instructions` pour les consignes directement adressées aux élèves. Place les critères, supports, rôle de l’enseignant, modalités de différenciation et autres détails dans `--notes`, `--objectives` ou `--intentions` selon leur portée.

Commandes à utiliser obligatoirement autant que possible :
- $LEARNING init
- $LEARNING add-moment
- $LEARNING add-activity
- $LEARNING outcome
- $LEARNING validate design.json --strict-pedagogy
- $LEARNING prompt design.json

Procédure recommandée :
1. Crée `design.json` avec `init`.
2. Ajoute les acquis Bloom avec `outcome`.
3. Ajoute un premier moment et une première activité complète pour tester les valeurs CLI.
4. Si la commande passe, ajoute le reste des moments et activités.
5. Si une commande échoue, explique pourquoi, corrige la valeur fautive, puis recommence.
6. Valide systématiquement avec `validate --strict-pedagogy`.
7. Exécute `prompt design.json`.

Le design doit inclure :
- des moments clairement titrés ;
- des intentions pédagogiques explicites ;
- des activités variées ;
- des durées réalistes ;
- des modalités de groupe adaptées ;
- des évaluations diagnostiques, formatives ou sommatives selon les étapes ;
- des acquis Bloom reliés aux activités ;
- des compétences numériques quand c’est pertinent ;
- des descriptions suffisamment détaillées pour être exploitables par un enseignant.

Si je demande d’intégrer le numérique, propose des usages pédagogiquement utiles, par exemple :
- recherche documentaire guidée ;
- vérification ou sélection de sources ;
- carte collaborative ;
- rédaction numérique ;
- sauvegarde et organisation des fichiers ;
- relecture, correction et amélioration d’un texte ;
- production ou partage contrôlé.

Utilise les identifiants de compétences numériques acceptés par le CLI, par exemple :
- A1, A2
- P1, P6
- C14, C15

À la fin, restitue-moi :
- le chemin du fichier `design.json` ;
- le résultat de la validation CLI ;
- le système scolaire ou la classification et le niveau ;
- le nombre de moments ;
- le nombre d’activités ;
- les objectifs d’enseignement pris en compte ;
- les acquis Bloom créés ;
- les compétences numériques mobilisées ;
- la répartition des durées ;
- les hypothèses retenues ;
- le contenu ou le fichier `design.json`.

Publication :
Ne publie pas directement depuis ton sandbox sauf si je te donne explicitement un jeton CLI.
Pour publier depuis mon Mac, indique-moi simplement, si le fichier est sur le Bureau :

learning publish ~/Desktop/design.json

Ou, si le fichier reste dans le dossier courant :

learning publish design.json

Règles importantes :
- Travaille progressivement.
- Pose d’abord les questions nécessaires.
- N’écris pas le JSON à la main sauf si le CLI est impossible à utiliser après plusieurs tentatives.
- Vérifie les valeurs acceptées avant de générer beaucoup d’activités.
- Teste une première activité avant de produire toute la séquence.
- Si une commande échoue, explique pourquoi, corrige-la et recommence.
- Une fois `.tools/bin/learning` créé, ne dépends plus du réseau.
- Ne publie jamais sans autorisation explicite.
PROMPT;

$designReviewPrompt = <<<'PROMPT'
Tu es expert en learning design, en conception universelle de l’apprentissage (CUA/UDL) ainsi qu’en différenciation pédagogique.

L’enseignant va te soumettre la description d’une séquence pédagogique (ou un export du Learning Designer de l’UCL). Tu dois l’aider à l’analyser en lui posant des questions réflexives et en lui proposant des pistes d’amélioration concrètes.

Tu structures ton analyse autour des 11 axes suivants :

1. INCLUSIVITÉ GÉNÉRALE — Le design permet-il à tous les élèves de participer, quels que soient leur niveau ou leur profil ? Les consignes sont-elles claires et accessibles ? Y a-t-il des alternatives pour les élèves qui n’auraient pas suivi une séance précédente ?

2. ÉLÈVES À BESOINS PARTICULIERS — Le design prend-il en compte les élèves DYS ? Des aménagements sont-ils prévus pour les élèves TDAH ? Les élèves HPI disposent-ils de tâches d’approfondissement ou d’enrichissement ?

3. DIFFÉRENCIATION PÉDAGOGIQUE — Le design propose-t-il des niveaux de difficulté différents ? Y a-t-il une différenciation de contenu, de processus ou de production ? Les élèves fragiles bénéficient-ils d’un étayage explicite ?

4. MULTIMODALITÉ — Le design varie-t-il les canaux d’apprentissage (texte, audio, vidéo, manipulation) ? Un même contenu est-il proposé sous plusieurs formes ?

5. AUTONOMIE ET MÉTACOGNITION — Les élèves savent-ils ce qu’on attend d’eux et pourquoi ? Y a-t-il des moments où l’élève réfléchit à ses propres apprentissages ? Le design favorise-t-il la prise d’initiative ?

6. COLLABORATION ET INTERACTION — Le design prévoit-il des moments de travail en binôme ou en groupe ? Les élèves ont-ils l’occasion d’apprendre les uns des autres ?

7. FEEDBACK ET ÉVALUATION FORMATIVE — Les élèves reçoivent-ils des feedbacks réguliers ? Y a-t-il des moments d’auto-évaluation ou de co-évaluation ? Une remédiation est-elle prévue en cas de difficulté ?

8. MOTIVATION ET ENGAGEMENT — Les activités ont-elles du sens aux yeux des élèves ? Y a-t-il des éléments déclencheurs (accroche, défi, énigme) ? La variété des formats maintient-elle l’attention ?

9. CHARGE COGNITIVE — Les activités sont-elles progressives ? Le design évite-t-il de surcharger les élèves ? Les temps sont-ils adaptés à la complexité des tâches ?

10. PLACE DU NUMÉRIQUE — Les outils numériques apportent-ils une réelle plus-value ? Le design serait-il accessible sans équipement numérique ? L’usage du numérique favorise-t-il l’activité de l’élève ?

11. ÉQUILIBRE DU LEARNING DESIGN — Le design s’appuie-t-il sur les 6 types d’apprentissage du Conversational Framework de Diana Laurillard (Acquisition, Investigation, Discussion, Pratique, Collaboration, Production) ? L’un de ces types est-il sur- ou sous-représenté ? La répartition entre travail individuel, en groupe et en classe entière est-elle cohérente avec les objectifs ? Les durées prévues sont-elles réalistes au regard de la complexité des tâches ? Les objectifs d’apprentissage (Knowledge, Comprehension, Application, Production) sont-ils bien alignés avec les activités proposées ?

Pour chaque axe :
- porte un diagnostic honnête à partir de ce que l’enseignant t’a soumis ;
- identifie 1 ou 2 points forts si possible ;
- propose 2 à 3 pistes concrètes et réalistes d’amélioration ;
- termine par un conseil clé en une phrase.

Adopte un ton bienveillant, professionnel et encourageant. Évite le jargon inutile. Tes suggestions doivent être directement applicables en classe.
PROMPT;

$aiPromptEn = <<<'PROMPT'
You must create a Learning Designer teaching design using the `learning` CLI.

Important:
You may be working in an isolated sandbox. Select the CLI by capability, not merely by whether a command exists.

Test these candidates in order:
1. `./bin/learning` when working in the Learning Designer repository;
2. the global `learning` command;
3. `./.tools/bin/learning` when that local copy already exists.

The selected CLI must support `list school-systems`, `list activity-options`, the `init` options `--school-system` and `--school-level`, explicit pedagogical choices in `add-activity`, and `validate --strict-pedagogy`.

If the global command exists but is incompatible, simply tell me that it must be updated and ask for permission to run `learning upgrade`. After my explicit approval, perform the update and repeat the checks.

If no compatible copy is available, install a local copy in your environment:

mkdir -p .tools/bin
curl -fsSL https://raw.githubusercontent.com/YannHY/learning-designer/main/bin/learning -o .tools/bin/learning
chmod +x .tools/bin/learning
./.tools/bin/learning --help

If raw.githubusercontent.com is blocked, use web_fetch or another available method to retrieve:
https://github.com/YannHY/learning-designer/blob/main/bin/learning

Then write the file to `.tools/bin/learning`, make it executable, and check its capabilities.

Remember the selected compatible command as `LEARNING`: `./bin/learning`, `learning`, or `./.tools/bin/learning`. Use `$LEARNING` in all commands below. Do not assume that a shell variable set in an earlier command will persist: replace `$LEARNING` with the selected command when necessary.

Once `.tools/bin/learning` has been created, do not rely on the network again.

Mission:
Create a complete, structured, detailed `design.json` file that can be imported into Learning Designer.

Use the CLI whenever possible. Do not write the JSON manually unless using the CLI remains impossible after several documented attempts.

Start by asking me the necessary questions in English without overwhelming me.

Essential questions:
- topic or subject of the lesson or sequence;
- school system or classification, then level and target learners;
- total duration;
- delivery mode: in person, online, or hybrid;
- group size;
- teaching objectives: what I want learners to work on, understand, or practise;
- expected learning outcomes: what learners should be able to do at the end;
- material, pedagogical, or institutional constraints;
- desired level of detail.

Ask these additional questions only when useful:
- the desired Bloom level for each outcome, if I know it;
- digital competencies to be developed, when relevant;
- any required resources, works, materials, or tools.

Clearly distinguish between:
- teaching objectives, which describe my pedagogical intention;
- learning outcomes, which describe what learners will be able to do at the end.

If I provide only teaching objectives, turn them into observable learning outcomes using action verbs linked to Bloom’s taxonomy.

If information is missing, make reasonable assumptions instead of blocking, unless an assumption would be risky.

For Belgium, distinguish the French, Flemish, and German-speaking Communities. For the United Kingdom, distinguish England, Wales, Scotland, and Northern Ireland. Treat the European Schools as a transnational system and ISCED 2011 as an international classification. If the choice is ambiguous, ask for the necessary clarification. Never invent an identifier: consult the CLI catalogs.

Duration rules:
- if the duration is given in days, ask for or explicitly suggest a duration per session before generating the design;
- by default, for lower secondary education, interpret one day as one 55-minute session unless stated otherwise;
- clearly state the assumption you use.

Before running all creation commands, briefly restate:
- the topic;
- the school system or classification, level, and audience;
- the total duration converted to minutes;
- the planned number of moments;
- the teaching objectives;
- the proposed Bloom outcomes;
- the main digital competencies, when relevant.

Then use the CLI rather than writing the JSON manually.

Before creating all activities, inspect the useful commands:
- $LEARNING --help
- $LEARNING init --help
- $LEARNING add-moment --help
- $LEARNING add-activity --help
- $LEARNING outcome --help
- $LEARNING list types
- $LEARNING list bloom
- $LEARNING list competencies
- $LEARNING list activity-options
- $LEARNING list school-systems
- $LEARNING list school-levels --system SYSTEM_ID

For `init` and `add-activity`, use only controlled values accepted by the CLI.

Safe values:
- `--school-system`: an id returned by `list school-systems`
- `--school-level`: an id returned by `list school-levels --system SYSTEM_ID`
- `--type`: `read`, `investigate`, `practice`, `produce`, `discuss`, `collaborate`
- `--group`: `individual`, `subgroups`, `whole`
- `--teaching`: `directed`, `guided`, `supported`, `independent`
- `--pacing`: `sync`, `async`
- `--mode`: `onsite`, `location-based`, `online`, `blended`, `other`
- `--evaluation`: `diagnostic`, `formative`, `summative`, `certificative`, `none`
- `--aias`: `1`, `2`, `3`, `4`, `5`, or `not-applicable`

For every activity, explicitly determine and pass `--group`, `--teaching`, `--pacing`, `--mode`, `--evaluation`, and `--aias`. Do not rely on defaults. Choose them as a coherent set based on the objective, learner autonomy, required interactions, delivery constraints, and expected evidence of learning. AIAS 1 means no AI; AIAS 2 limits AI to exploration, research, or planning; AIAS 3 makes AI a collaborator whose output the learner evaluates and transforms; AIAS 4 fully integrates AI under the learner's critical direction; AIAS 5 covers exploring and co-designing new AI uses. Use `not-applicable` only when the AIAS framework genuinely does not apply, and never leave AIAS undecided in a generated design.

For `init`, pass the system and level whenever they are known, for example: `$LEARNING init design.json --school-system france --school-level quatrieme`.

Use the canonical values above for `--pacing` and `--mode`; the CLI also accepts their main French and English equivalents.

Never put long sentences in controlled fields such as `--school-system`, `--school-level`, `--group`, `--teaching`, `--evaluation`, `--type`, or `--pacing`.

Use `--description` for the pedagogical description of the activity and `--instructions` for directions addressed directly to students. Put criteria, resources, the teacher’s role, differentiation, and other details in `--notes`, `--objectives`, or `--intentions` according to their scope.

Use these commands whenever possible:
- $LEARNING init
- $LEARNING add-moment
- $LEARNING add-activity
- $LEARNING outcome
- $LEARNING validate design.json --strict-pedagogy
- $LEARNING prompt design.json

Recommended process:
1. Create `design.json` with `init`.
2. Add Bloom outcomes with `outcome`.
3. Add one moment and one complete activity to test the CLI values.
4. If the command succeeds, add the remaining moments and activities.
5. If a command fails, explain why, correct the invalid value, and try again.
6. Always run `validate --strict-pedagogy`.
7. Run `prompt design.json`.

The design must include:
- clearly titled moments;
- explicit pedagogical intentions;
- varied activities;
- realistic durations;
- suitable group arrangements;
- diagnostic, formative, or summative assessment where appropriate;
- Bloom outcomes linked to activities;
- digital competencies when relevant;
- descriptions detailed enough for a teacher to use.

If I ask you to integrate digital technology, suggest pedagogically useful applications such as guided research, source checking, collaborative mapping, digital writing, file organisation, revision, and controlled sharing.

Use digital competency identifiers accepted by the CLI, for example:
- A1, A2
- P1, P6
- C14, C15

At the end, give me:
- the path to `design.json`;
- the CLI validation result;
- the school system or classification and level;
- the number of moments and activities;
- the teaching objectives used;
- the Bloom outcomes created;
- the digital competencies developed;
- the duration breakdown;
- the assumptions made;
- the content or file `design.json`.

Publishing:
Do not publish directly from your sandbox unless I explicitly give you a CLI token.
To publish from my Mac, tell me to use `learning publish ~/Desktop/design.json` if the file is on the Desktop, or `learning publish design.json` if it remains in the current folder.

Important rules:
- Work progressively and ask the necessary questions first.
- Do not write JSON manually unless the CLI remains impossible to use after several attempts.
- Check accepted values before generating many activities.
- Test one activity before producing the whole sequence.
- If a command fails, explain why, correct it, and try again.
- Once `.tools/bin/learning` has been created, do not rely on the network.
- Never publish without explicit permission.
PROMPT;

$designReviewPromptEn = <<<'PROMPT'
You are an expert in learning design, Universal Design for Learning (UDL), and differentiated instruction.

The teacher will submit a description of a teaching sequence or an export from UCL Learning Designer. Help them analyse it by asking reflective questions and suggesting concrete improvements.

Structure your analysis around these 11 areas:

1. OVERALL INCLUSIVENESS — Can all learners participate, regardless of level or profile? Are instructions clear and accessible? Are alternatives available for learners who missed an earlier session?

2. LEARNERS WITH ADDITIONAL NEEDS — Does the design support learners with dyslexia or related learning differences? Are accommodations planned for learners with ADHD? Do highly able learners have extension or enrichment tasks?

3. DIFFERENTIATED INSTRUCTION — Does the design offer different levels of difficulty? Is content, process, or output differentiated? Do learners who need support receive explicit scaffolding?

4. MULTIMODALITY — Does the design vary learning channels such as text, audio, video, and hands-on work? Is the same content available in more than one form?

5. AUTONOMY AND METACOGNITION — Do learners know what is expected and why? Are there moments when they reflect on their own learning? Does the design encourage initiative?

6. COLLABORATION AND INTERACTION — Does the design include pair or group work? Can learners learn from one another?

7. FEEDBACK AND FORMATIVE ASSESSMENT — Do learners receive regular feedback? Are self-assessment and peer assessment included? Is remediation planned when difficulties arise?

8. MOTIVATION AND ENGAGEMENT — Are the activities meaningful to learners? Is there a hook, challenge, or puzzle? Does variety help sustain attention?

9. COGNITIVE LOAD — Do activities progress gradually? Does the design avoid overloading learners? Are timings appropriate for task complexity?

10. ROLE OF DIGITAL TECHNOLOGY — Do digital tools add genuine value? Would the design remain accessible without digital equipment? Does technology support active learning?

11. LEARNING DESIGN BALANCE — Does the design use the six learning types from Diana Laurillard’s Conversational Framework: Acquisition, Investigation, Discussion, Practice, Collaboration, and Production? Is any type over- or under-represented? Is the balance between individual, group, and whole-class work consistent with the objectives? Are planned durations realistic for the complexity of the tasks? Are the learning objectives (Knowledge, Comprehension, Application, Production) aligned with the proposed activities?

For each area:
- give an honest diagnosis based on the submitted material;
- identify one or two strengths where possible;
- suggest two or three concrete, realistic improvements;
- finish with one key recommendation in a single sentence.

Use a supportive, professional, and encouraging tone. Avoid unnecessary jargon. Your suggestions must be directly applicable in the classroom.
PROMPT;

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.svg?v=20260804" type="image/svg+xml" sizes="any">
    <title>Aide | Learning Designer</title>
    <?php render_theme_boot_script(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/interface.css?v=20260905-subtle-focus">
    <link rel="stylesheet" href="css/account-ui.css?v=20260903-pagefind-dark">
    <link rel="stylesheet" href="css/account-pages.css?v=20260904-content-rhythm">
</head>
<body class="help-page">
<?php render_site_nav('help'); ?>
<main class="help-shell" id="main-content">
    <header class="help-hero">
        <h1 class="help-title">Concevoir, analyser et partager un design</h1>
        <p class="help-lead">Ce guide explique comment concevoir, importer, exporter et publier un design, avec ou sans IA.</p>
        <div class="help-quick-links" aria-label="Accès rapides">
            <a class="help-quick-link" href="#premiers-pas"><i class="fa-solid fa-rocket" aria-hidden="true"></i>Commencer</a>
            <a class="help-quick-link" href="#import-export"><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>Importer et exporter</a>
            <a class="help-quick-link" href="#markdown"><i class="fa-brands fa-markdown" aria-hidden="true"></i>Format Markdown</a>
            <a class="help-quick-link" href="#cli"><i class="fa-solid fa-terminal" aria-hidden="true"></i>IA et CLI</a>
        </div>
    </header>

    <button class="help-mobile-menu" id="help-menu-toggle" type="button" aria-expanded="false" aria-controls="help-sidebar">
        <span><i class="fa-solid fa-list" aria-hidden="true"></i> Sur cette page</span>
        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
    </button>

    <div class="help-layout">
        <aside class="help-sidebar" id="help-sidebar" aria-label="Sommaire de l’aide">
            <p class="help-sidebar-title">
                <span>Sur cette page</span>
                <span class="help-progress" aria-hidden="true"><span class="help-progress-bar" id="help-progress-bar"></span></span>
            </p>
            <nav class="help-toc" id="help-toc"></nav>
        </aside>

        <div class="help-content">
            <article class="help-section" id="premiers-pas">
                <h2>Comprendre le learning design</h2>
                <p>Le <em>learning design</em> consiste à organiser les activités que les apprenants vont faire pour atteindre les acquis visés.</p>

                <h3 id="learning-designer-scenario-visible">Learning Designer : rendre le scénario visible</h3>
                <p>Le <a href="https://www.ucl.ac.uk/learning-designer/" title="Learning Designer">Learning Designer</a> original a été développé à l’University College London par l’équipe de Diana Laurillard pour aider les enseignants à concevoir des activités pédagogiques, à analyser leur équilibre et à partager leurs scénarisations. L’application présentée ici s’inscrit dans cette filiation : elle transforme un scénario pédagogique en une structure lisible, analysable et réutilisable.</p>
                <p>L’outil rend vos choix explicites afin que vous puissiez les interroger&nbsp;:&nbsp;le temps prévu correspond-il au temps effectivement scénarisé&nbsp;? Quelle place est accordée à chaque type d’apprentissage&nbsp;? Les élèves pratiquent-ils, discutent-ils et produisent-ils, ou restent-ils surtout en situation d’acquisition&nbsp;? Les modalités de groupe, le rythme, le mode d’enseignement et les évaluations sont-ils cohérents avec les acquis visés&nbsp;?</p>
                <div class="help-grid">
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i></span>Un outil de conception</strong>
                        <span>Il documente le contexte, les objectifs d’enseignement, les acquis attendus, les moments du parcours et les activités d’enseignement et d’apprentissage (<em>Teaching and Learning Activities</em>, ou TLA).</span>
                    </div>
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></span>Un outil d’analyse</strong>
                        <span>Il calcule les durées et visualise la proportion des types d’apprentissage ainsi que les principales dimensions du scénario.</span>
                    </div>
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span>Un outil d’ajustement</strong>
                        <span>Il aide à repérer un déséquilibre ou un oubli afin que le concepteur décide des modifications pédagogiques pertinentes.</span>
                    </div>
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i></span>Un outil de partage</strong>
                        <span>Il permet d’enregistrer, d’exporter, de publier et de réutiliser un design pour le discuter ou l’adapter avec d’autres enseignants.</span>
                    </div>
                </div>

                <h3 id="creer-premier-design">Créer un premier design, étape par étape</h3>
                <ol>
                    <li><strong>Décrire le contexte.</strong> Renseignez le titre, la description, la commande institutionnelle, les objectifs d’enseignement, les concepteurs, les enseignants, la taille du groupe, la modalité et le temps d’apprentissage prévu. Pour le niveau, choisissez d’abord un système ou une classification, puis la classe correspondante. Le catalogue couvre la France, la Suisse, les États-Unis, les trois communautés belges, les quatre systèmes du Royaume-Uni et le système transnational des Écoles européennes. ISCED 2011 est proposé séparément comme classification internationale de comparaison. La durée peut être exprimée en jours, heures et minutes ; le nombre d’heures correspondant à une journée est configurable.</li>
                    <li><strong>Formuler les acquis attendus.</strong> Indiquez ce que les apprenants devront être capables de faire à la fin. Reliez si nécessaire chaque acquis à un niveau de la taxonomie révisée de Bloom et choisissez un verbe d’action observable.</li>
                    <li><strong>Structurer le parcours en moments.</strong> Un moment correspond à une phase cohérente de la séance ou de la séquence : lancement, exploration, entraînement, mise en commun, production ou évaluation.</li>
                    <li><strong>Ajouter les activités.</strong> Pour chacune, précisez le type d’apprentissage, la durée, l’organisation du groupe, le mode d’enseignement, le rythme, le mode de formation, l’évaluation, les consignes et les ressources.</li>
                    <li><strong>Visualiser puis ajuster.</strong> Comparez le temps d’apprentissage prévu avec la somme des activités et observez les graphiques de répartition. Ces indicateurs éclairent votre décision ; ils ne remplacent pas votre jugement pédagogique.</li>
                </ol>
                <div class="help-callout">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <p><strong>Objectifs et acquis ne désignent pas la même chose.</strong> Les objectifs expriment ce que l’enseignant veut faire travailler, transmettre ou entraîner. Les acquis décrivent ce que les apprenants seront capables de faire à la fin, idéalement avec un verbe d’action observable.</p>
                </div>
                <p>Pour une présentation plus développée du cadre pédagogique, consultez également <a href="learning-design.php">Comprendre le learning design</a>.</p>
            </article>

            <article class="help-section" id="moments-activites">
                <h2>Organiser les moments et les activités</h2>
                <p>Chaque moment correspond à une phase cohérente de la séance ou de la séquence : lancement, exploration, mise en commun, entraînement, production, évaluation, etc. Un moment possède un titre, des objectifs, des choix pédagogiques et des notes. Les moments et les activités peuvent être réordonnés ou dupliqués.</p>
                <h3 id="dupliquer-moment-activite">Dupliquer un moment ou une activité</h3>
                <p>La duplication permet de reprendre rapidement une structure existante avant d’en adapter seulement les éléments qui changent.</p>
                <div class="help-grid">
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-regular fa-copy" aria-hidden="true"></i></span>Dupliquer un moment</strong>
                        <span>Dans les vues Liste et Colonnes, utilisez l’icône de copie placée en bas du moment, juste à côté de sa durée. Le moment complet — titre, objectifs, choix pédagogiques, notes et activités — est inséré juste après l’original. Son titre reçoit automatiquement la mention « copie ».</span>
                    </div>
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-regular fa-copy" aria-hidden="true"></i></span>Dupliquer une activité</strong>
                        <span>Utilisez l’icône de copie dans la barre d’outils de l’activité, juste avant la croix de suppression. Tous ses réglages et contenus — durée, modalités, AIAS, compétences, description, consignes et notes — sont repris dans une nouvelle activité placée juste après l’originale.</span>
                    </div>
                </div>
                <div class="help-callout">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <p><strong>En vue Grille,</strong> les mêmes icônes sont disponibles dans la colonne d’actions. La copie apparaît brièvement en surbrillance afin de la repérer et peut être modifiée immédiatement.</p>
                </div>
                <h3>Paramètres disponibles pour une activité</h3>
                <div class="help-table-wrap">
                    <table class="help-table">
                        <thead><tr><th>Champ</th><th>Possibilités</th><th>Usage</th></tr></thead>
                        <tbody>
                            <tr><td>Type d’apprentissage</td><td>Lire, investiguer, pratiquer, produire, discuter, collaborer</td><td>Détermine la catégorie utilisée dans les graphiques d’analyse.</td></tr>
                            <tr><td>Durée</td><td>Nombre de minutes</td><td>Alimente le temps conçu, la chronologie et toutes les répartitions.</td></tr>
                            <tr><td>Groupe</td><td>Individuel, sous-groupes, groupe entier</td><td>Précise l’organisation sociale de l’activité.</td></tr>
                            <tr><td>Enseignement</td><td>Dirigé, guidé, accompagné ou en autonomie</td><td>Précise qui détermine la méthode, le déroulement et le rythme de l’activité.</td></tr>
                            <tr><td>Rythme</td><td>Synchrone ou asynchrone</td><td>Permet de distinguer les activités simultanées des activités réalisées au rythme de chacun.</td></tr>
                            <tr><td>Mode de formation</td><td>En classe, sur site, en ligne, hybride ou autre</td><td>Décrit le lieu et le mode de participation.</td></tr>
                            <tr><td>Évaluation</td><td>Aucune, diagnostique, formative, sommative, certificative</td><td>Rend explicite la fonction évaluative de l’activité.</td></tr>
                            <tr><td>AIAS</td><td>À définir, non pertinent ou niveaux 1 à 5</td><td>Décrit le rôle de l’IA dans la tâche, indépendamment de sa fonction évaluative.</td></tr>
                            <tr><td>Description de l’activité</td><td>Texte libre</td><td>Décrit l’intention pédagogique, le déroulement et l’organisation de l’activité du point de vue de l’enseignant.</td></tr>
                            <tr><td>Consignes pour les élèves</td><td>Texte libre</td><td>Formule les actions attendues dans un langage directement adressé aux élèves : étapes, production attendue et critères utiles.</td></tr>
                            <tr><td>Ressources</td><td>Liens Markdown dans les textes</td><td>Associe les supports, documents, vidéos ou outils avec la syntaxe <code>[titre](URL)</code>.</td></tr>
                            <tr><td>Compétences</td><td>Une ou plusieurs compétences issues de sept cadres</td><td>Relie l’activité à Florimont, au Socle commun, à GreenComp, à DigComp, au CRCN, à Pix ou à Pix IA.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>Le champ AIAS s’appuie sur <a href="https://aiassessmentscale.com/" target="_blank" rel="noopener noreferrer">The AI Assessment Scale 2.1</a>. Dans une activité, l’icône avec des étincelles signale ce réglage lié à l’intelligence artificielle. Après sélection, le bouton affiche directement le niveau choisi, de 1 à 5. Ces cinq niveaux décrivent des conceptions différentes de la tâche et ne constituent pas une progression.</p>
                <p>Les descriptions, les consignes et les notes acceptent une mise en forme <a href="https://github.com/YannHY/cours/blob/master/Tech/Markdown/Apprendre%20le%20Markdown.md" title="Markdown">Markdown</a> légère. Une barre d’outils et un aperçu permettent de structurer plus facilement le texte. Ajoutez les liens directement avec la syntaxe <code>[titre](URL)</code>.</p>
                <h3>Raccourcis clavier Markdown</h3>
                <p>Lorsque le curseur se trouve dans un champ compatible Markdown, utilisez <code>Cmd</code> sur macOS ou <code>Ctrl</code> sur Windows et Linux.</p>
                <div class="help-table-wrap">
                    <table class="help-table">
                        <thead><tr><th>Action</th><th>Raccourci</th></tr></thead>
                        <tbody>
                            <tr><td>Gras</td><td><code>Cmd/Ctrl + B</code></td></tr>
                            <tr><td>Italique</td><td><code>Cmd/Ctrl + I</code></td></tr>
                            <tr><td>Lien</td><td><code>Cmd/Ctrl + K</code></td></tr>
                            <tr><td>Titre</td><td><code>Cmd/Ctrl + Shift + H</code></td></tr>
                            <tr><td>Liste à puces</td><td><code>Cmd/Ctrl + Shift + L</code></td></tr>
                            <tr><td>Liste numérotée</td><td><code>Cmd/Ctrl + Shift + 7</code></td></tr>
                            <tr><td>Continuer une liste</td><td><code>Entrée</code></td></tr>
                            <tr><td>Saut de ligne sans nouvelle puce</td><td><code>Shift + Entrée</code></td></tr>
                            <tr><td>Citation</td><td><code>Cmd/Ctrl + Shift + Q</code></td></tr>
                        </tbody>
                    </table>
                </div>
                <p>Pour créer un lien, sélectionnez son libellé, utilisez <code>Cmd/Ctrl + K</code>, puis remplacez <code>https://</code> par l’adresse voulue.</p>
                <p>Dans une liste à puces ou numérotée, <code>Entrée</code> crée automatiquement la puce ou le numéro suivant. Appuyez sur <code>Entrée</code> lorsque l’élément est vide pour terminer la liste, ou sur <code>Shift + Entrée</code> pour insérer un simple saut de ligne.</p>
            </article>

            <article class="help-section" id="types-apprentissage">
                <h2>Les six types d’apprentissage</h2>
                <p>Le Learning Designer s’appuie sur les six types d’apprentissage associés au <a href="cadre-conversationnel.php">Cadre conversationnel de Diana Laurillard</a>. Une séquence n’a pas besoin de les utiliser à parts égales, mais leur combinaison aide à varier l’expérience de l’apprenant.</p>
                <div class="help-types">
                    <div class="help-type help-type-read"><span class="help-type-dot"></span><div><strong>Lire / Regarder / Écouter</strong><span>Acquérir des informations par un exposé, un texte, une vidéo ou un enregistrement.</span></div></div>
                    <div class="help-type help-type-investigate"><span class="help-type-dot"></span><div><strong>Investiguer</strong><span>Rechercher, sélectionner, comparer et évaluer des informations ou des données.</span></div></div>
                    <div class="help-type help-type-practice"><span class="help-type-dot"></span><div><strong>Pratiquer</strong><span>Essayer, recevoir un retour, corriger son approche et recommencer.</span></div></div>
                    <div class="help-type help-type-produce"><span class="help-type-dot"></span><div><strong>Produire</strong><span>Créer un texte, une présentation, un modèle ou toute autre production évaluée.</span></div></div>
                    <div class="help-type help-type-discuss"><span class="help-type-dot"></span><div><strong>Discuter</strong><span>Formuler un point de vue, tenir compte des autres et défendre une position.</span></div></div>
                    <div class="help-type help-type-collaborate"><span class="help-type-dot"></span><div><strong>Collaborer</strong><span>Construire ensemble une réponse ou une production avec une responsabilité partagée.</span></div></div>
                </div>
                <p class="help-spaced">Le panneau d’analyse calcule la part de temps consacrée à chaque type. Cette visualisation sert d’indicateur de conception : elle aide à repérer une séquence très transmissive, un manque de pratique ou l’absence d’une production finale, sans imposer de répartition idéale.</p>
            </article>

            <article class="help-section" id="acquis-competences">
                <h2>Formuler les acquis et suivre les compétences</h2>
                <p>Formulez des résultats observables avec Bloom et reliez les activités aux compétences numériques.</p>
                <h3 id="taxonomie-bloom">Taxonomie révisée de Bloom</h3>
                <p>Les acquis d’apprentissage peuvent être formulés à partir de la taxonomie révisée d’Anderson et Krathwohl. Choisissez un niveau cognitif, un verbe d’action, puis précisez le résultat attendu.</p>
                <div class="help-grid three">
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-brain" aria-hidden="true"></i></span>Se souvenir</strong><span>Citer, définir, identifier, lister, reconnaître.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i></span>Comprendre</strong><span>Expliquer, illustrer, interpréter, résumer.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-pencil" aria-hidden="true"></i></span>Appliquer</strong><span>Démontrer, exécuter, résoudre, utiliser.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>Analyser</strong><span>Comparer, décomposer, différencier, examiner.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></span>Évaluer</strong><span>Critiquer, défendre, justifier, recommander.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-hammer" aria-hidden="true"></i></span>Créer</strong><span>Concevoir, élaborer, formuler, produire.</span></div>
                </div>
                <p class="help-spaced">La page <a href="bloom.php">Taxonomie de Bloom</a> donne accès au tableau complet des verbes. Dans l’éditeur, chaque acquis reste modifiable et peut être supprimé.</p>
                <h3 id="competences-numeriques">Cadres de compétences</h3>
                <p>Dans chaque activité, le bouton <strong>Sélectionner des compétences</strong> permet d’abord de choisir un cadre, puis un domaine et une ou plusieurs compétences. Sept cadres sont disponibles : Florimont, le Socle commun, GreenComp, DigComp 3.0, le CRCN, Pix et Pix IA.</p>
                <div class="help-grid three">
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-school" aria-hidden="true"></i></span>Florimont</strong><span>95 compétences numériques progressives réparties entre Acquérir, Approfondir et Créer.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-landmark" aria-hidden="true"></i></span>Socle commun</strong><span>135 compétences détaillées, réparties en cinq domaines et dix-huit sous-domaines.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-leaf" aria-hidden="true"></i></span>GreenComp</strong><span>12 compétences européennes enrichies de 169 repères de connaissances, aptitudes et attitudes.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-globe" aria-hidden="true"></i></span>DigComp 3.0</strong><span>362 énoncés bilingues sélectionnables individuellement, regroupés en 21 compétences et quatre niveaux de maîtrise.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>CRCN</strong><span>16 compétences du cadre de référence français des compétences numériques.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-certificate" aria-hidden="true"></i></span>Pix</strong><span>16 compétences numériques transversales utilisées par Pix pour l’évaluation et la formation.</span></div>
                    <div class="help-card"><strong><span class="help-card-icon"><i class="fa-solid fa-robot" aria-hidden="true"></i></span>Pix IA</strong><span>16 compétences complémentaires sur les fondements, les usages et les enjeux de l’intelligence artificielle.</span></div>
                </div>
                <p class="help-spaced">Les 169 repères GreenComp précisent les compétences de ce cadre sans être comptés comme des compétences supplémentaires. Dans DigComp 3.0, chacun des 362 énoncés codés CS peut en revanche être sélectionné individuellement, comme une compétence Florimont. Le texte anglais officiel est conservé et une traduction française propre à l’application est proposée ; la Commission européenne n’est pas responsable de cette traduction. Pix et Pix IA sont deux référentiels complémentaires : le second ne remplace pas le référentiel Pix général. La page <a href="competencies.php">Référentiels de compétences</a> permet de consulter les sept catalogues complets, de les filtrer, de les rechercher et d’ouvrir leur source de référence.</p>
                <p>Dans les énoncés DigComp 3.0, <strong>[AI-E]</strong> indique que l’intelligence artificielle est explicitement mentionnée et <strong>[AI-I]</strong> qu’elle est implicitement pertinente.</p>
            </article>

            <article class="help-section" id="vues-analyses">
                <h2>Afficher et analyser le scénario</h2>
                <p>Utilisez les vues d’analyse et de chronologie pour repérer les équilibres, les écarts de durée et les données manquantes.</p>
                <h3>Analyser l’expérience d’apprentissage</h3>
                <p>Dans le panneau supérieur de l’éditeur, juste sous la barre de navigation, cliquez sur l’onglet <strong>Analyse</strong>, à côté de <strong>Paramètres</strong> et <strong>Chronologie</strong>. Si le panneau est replié, ce clic le déploie. La vue <strong>Expérience d’apprentissage</strong> synthétise alors le scénario sous forme de graphiques. Elle compare notamment :</p>
                <ul>
                    <li>les six types d’apprentissage ;</li>
                    <li>le travail individuel, en sous-groupes et en groupe entier ;</li>
                    <li>les quatre modes d’enseignement ;</li>
                    <li>le rythme synchrone ou asynchrone ;</li>
                    <li>les modalités présentielle, distancielle et hybride ;</li>
                    <li>les formes d’évaluation.</li>
                </ul>
                <p>Les avertissements affichés dans cette vue signalent les données manquantes ou incohérentes — par exemple une activité sans durée ou sans type — qui pourraient fausser les graphiques.</p>
                <h3>Consulter la chronologie des activités</h3>
                <p>Dans ce même panneau supérieur, cliquez sur l’onglet <strong>Chronologie</strong>. Chaque activité y est positionnée selon sa durée et son ordre afin de donner une autre lecture de la séquence.</p>
                <h3>Choisir l’affichage du scénario</h3>
                <p>Plus bas dans l’éditeur, la barre d’outils située au-dessus du scénario permet de choisir <strong>Liste</strong> pour une lecture linéaire, <strong>Colonnes</strong> pour comparer les moments ou <strong>Grille</strong> pour obtenir une vue plus compacte. Ces trois boutons modifient uniquement la présentation des moments et des activités.</p>
            </article>

            <article class="help-section" id="sauvegarde-partage">
                <h2>Sauvegarder, publier et réutiliser</h2>
                <p>Comprenez ce qui reste local, ce qui est enregistré et les options de publication ou de réutilisation.</p>
                <h3 id="sauvegarde-sans-compte">Sans compte</h3>
                <p>Vous pouvez concevoir et exporter un design sans vous connecter. Les modifications restent disponibles dans la page tant qu’elle est ouverte. Aucune donnée n’est envoyée au serveur sans action explicite de votre part.</p>
                <h3 id="sauvegarde-avec-compte">Avec un compte</h3>
                <p>Le bouton <strong>Enregistrer</strong> associe le design à votre compte. Vous pouvez ensuite le retrouver, le rouvrir, le renommer ou le supprimer depuis la page de vos designs.</p>
                <h3 id="publier-lien">Publier un lien consultable</h3>
                <p>Un design enregistré peut être publié pour générer une page de lecture partageable. Cette page présente les paramètres, les moments, les activités, les durées, les liens et les compétences numériques, mais ne permet pas aux visiteurs de modifier l’original.</p>
                <ul>
                    <li>Le lien seul reste non répertorié : seules les personnes qui le possèdent peuvent consulter le design.</li>
                    <li>Pour rendre le design visible dans la galerie publique, choisissez l’une des six licences Creative Commons 4.0. La fenêtre de partage donne accès au sélecteur officiel et à un comparatif pour vous aider.</li>
                    <li>Vous pouvez révoquer le lien de publication.</li>
                    <li>Vous pouvez choisir de rendre le design visible dans la galerie publique des designs partagés.</li>
                    <li>Une personne connectée peut importer un design partagé dans son propre compte afin de l’adapter.</li>
                </ul>
                <div class="help-callout">
                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                    <p><strong>Partager ne signifie pas forcément envoyer du Markdown.</strong> Vous pouvez transmettre un lien de publication, partager un fichier Word ou Excel, fournir une page HTML, ou échanger le fichier JSON natif selon le besoin du destinataire.</p>
                </div>
                <h3 id="donner-avis">Donner son avis sur l’application</h3>
                <p>Le bouton rond placé dans l’angle inférieur droit ouvre un formulaire très court. Choisissez l’une des trois icônes colorées — satisfait, mitigé ou insatisfait — puis ajoutez, si vous le souhaitez, un commentaire expliquant votre choix.</p>
                <div class="help-callout">
                    <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                    <p>Le retour est anonyme et n’est pas rattaché à votre compte. La page consultée et la langue de l’interface sont enregistrées pour aider à comprendre le contexte. N’indiquez aucune donnée personnelle ou confidentielle dans le commentaire.</p>
                </div>
            </article>

            <article class="help-section" id="import-export">
                <h2>Importer et exporter dans plusieurs formats</h2>
                <p>Choisissez le bon format pour relire, partager, modifier ou réimporter votre design.</p>
                <h3 id="formats-export">Formats d’export</h3>
                <div class="help-table-wrap">
                    <table class="help-table">
                        <thead><tr><th>Format</th><th>À choisir pour…</th><th>À savoir</th></tr></thead>
                        <tbody>
                            <tr><td><span class="help-format-badge">Markdown</span></td><td>Éditer un document lisible en texte brut, le versionner ou le transmettre à une IA.</td><td>Peut être réimporté si la structure et les libellés sont conservés.</td></tr>
                            <tr><td><span class="help-format-badge">HTML</span></td><td>Partager une page autonome lisible dans un navigateur.</td><td>Pratique pour consultation ou archivage web.</td></tr>
                            <tr><td><span class="help-format-badge">JSON</span></td><td>Conserver toutes les données structurées ou travailler avec le CLI.</td><td>C’est le format le plus fidèle pour l’échange technique et la réimportation.</td></tr>
                            <tr><td><span class="help-format-badge">Excel</span></td><td>Analyser, filtrer ou retravailler les activités dans un tableur.</td><td>Le fichier téléchargé utilise le format <code>.xlsx</code>.</td></tr>
                            <tr><td><span class="help-format-badge">Word</span></td><td>Relire, annoter, imprimer ou diffuser un document bureautique.</td><td>Le fichier téléchargé utilise le format <code>.docx</code>.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>Pour exporter, cliquez sur <strong>Exporter</strong>, choisissez le format et le nom du fichier, puis prévisualisez le contenu lorsqu’il s’agit d’un format textuel. Vous pouvez le copier ou le télécharger.</p>
                <h3 id="modeles">Partir d’un modèle</h3>
                <p class="help-spaced">Le bouton <strong>Importer</strong> ouvre une fenêtre qui propose d’abord une bibliothèque de scénarios génériques et préremplis : introduire un chapitre, étude de documents, débat argumenté, exposé, tâche complexe, séance avec IA encadrée… Les moments, les durées, les types d’apprentissage, les modalités, les acquis Bloom et les niveaux AIAS sont déjà posés ; il reste à remplacer les jalons entre crochets, comme <code>[MATIÈRE]</code> ou <code>[NOTION 1]</code>, par le contenu de votre discipline. La page <a href="models.php">Modèles de scénarios</a> présente l’ensemble de la bibliothèque et permet de télécharger chaque modèle au format JSON.</p>
                <h3 id="formats-import">Formats d’import</h3>
                <p>La même fenêtre permet de charger un fichier depuis votre ordinateur, dans les formats suivants :</p>
                <ul>
                    <li><strong>LDJ</strong>, le format du Learning Designer de l’University College London (UCL) ;</li>
                    <li><strong>JSON</strong>, pour restaurer les données structurées ;</li>
                    <li><strong>CSV</strong>, pour importer des données tabulaires ;</li>
                    <li><strong>Excel</strong> au format <code>.xlsx</code> ;</li>
                    <li><strong>Markdown</strong> au format <code>.md</code> ou <code>.markdown</code>.</li>
                </ul>
                <div class="help-callout warning">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <p>L’import remplace le design en cours. Enregistrez-le sur votre compte ou exportez-le avant d’importer un autre fichier si vous souhaitez le conserver.</p>
                </div>
            </article>

            <article class="help-section" id="markdown">
                <h2>Importer un design en Markdown</h2>
                <p>Le plus sûr est de partir d’un fichier Markdown exporté depuis Learning Designer, puis de le modifier sans changer sa structure. Le fichier doit contenir les sections <code>## Paramètres</code> et <code>## Séances</code>, qui permettent à l’application de reconnaître le document.</p>
                <h3 id="markdown-structure">Structure attendue</h3>
                <div class="help-code-wrap">
                    <button class="help-copy-btn" type="button" aria-label="Copier l’exemple Markdown" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    <pre class="help-code"># Titre du design

## Paramètres

- Mode: Hybride
- Système scolaire: France
- Niveau: 4e
- Taille du groupe: 24
- Concepteur(s): Nom
- Enseignant(s): Nom
- Temps d'apprentissage: 1 j 2 h 30 min
- Temps conçu: 0 j 1 h 30 min
- 1 jour = 7 heures

### Description
Description générale du design.

### Commande institutionnelle
Contexte ou demande de départ.

### Objectifs
Objectifs généraux de la formation.

### Acquis d'apprentissage
- Comparer : comparer deux solutions

## Séances

## 1. Première séance
&gt; Objectifs:
&gt; Objectifs de la séance
&gt; Choix pédagogiques:
&gt; Intentions pédagogiques
&gt; Notes:
&gt; Notes de la séance

### 1.1 Investiguer
- Durée: 30 min
- Groupe: Sous-groupes
- Enseignement: Enseignement guidé
- Rythme: Synchrone
- Mode de formation: En classe
- Évaluation: Formative
- AIAS: AIAS 3 · Collaboration avec l’IA
- Description: Consultez [cet exemple](https://example.com), puis comparez les propositions.
- Consignes pour les élèves: Lisez les documents puis justifiez votre réponse.
- Compétences: A6 - Exemple de compétence</pre>
                </div>
                <h3 id="markdown-numerotation">Règles de numérotation</h3>
                <p>Chaque moment doit commencer par un titre de niveau 2 numéroté, et chaque activité par un titre de niveau 3 :</p>
                <div class="help-code-wrap">
                    <button class="help-copy-btn" type="button" aria-label="Copier la règle de numérotation" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    <pre class="help-code">## 1. Titre du moment
### 1.1 Type d'activité</pre>
                </div>
                <h3 id="markdown-valeurs">Valeurs reconnues</h3>
                <div class="help-table-wrap">
                    <table class="help-table">
                        <thead><tr><th>Champ</th><th>Valeurs conseillées</th></tr></thead>
                        <tbody>
                            <tr><td>Type</td><td><code>Non défini</code>, <code>Lire / Regarder / Écouter</code>, <code>Investiguer</code>, <code>Pratiquer</code>, <code>Produire</code>, <code>Discuter</code>, <code>Collaborer</code></td></tr>
                            <tr><td>Groupe</td><td><code>Groupe entier</code>, <code>Sous-groupes</code>, <code>Individuel</code></td></tr>
                            <tr><td>Enseignement</td><td><code>Enseignement dirigé</code>, <code>Enseignement guidé</code>, <code>Enseignement accompagné</code>, <code>Enseignement en autonomie</code></td></tr>
                            <tr><td>Rythme</td><td><code>Synchrone</code>, <code>Asynchrone</code></td></tr>
                            <tr><td>Mode de formation</td><td><code>En classe</code>, <code>Sur site</code>, <code>En ligne</code>, <code>Hybride</code>, <code>Autre</code></td></tr>
                            <tr><td>Évaluation</td><td><code>Aucune</code>, <code>Diagnostique</code>, <code>Formative</code>, <code>Sommative</code>, <code>Certificative</code></td></tr>
                            <tr><td>AIAS</td><td><code>À définir</code>, <code>Non pertinent</code>, <code>AIAS 1</code> à <code>AIAS 5</code></td></tr>
                        </tbody>
                    </table>
                </div>
                <h3 id="markdown-modifications">Ce que vous pouvez modifier</h3>
                <p>Vous pouvez modifier le titre, les paramètres, la description, la commande institutionnelle, les objectifs, les acquis d’apprentissage, les titres et contenus des moments, ainsi que les activités et tous leurs champs.</p>
                <h3 id="markdown-libelles">Libellés à conserver</h3>
                <p>Évitez de changer les libellés fixes <code>## Paramètres</code>, <code>## Séances</code>, <code>- Système scolaire:</code>, <code>- Niveau:</code>, <code>- Durée:</code>, <code>- Groupe:</code>, <code>- Enseignement:</code>, <code>- Rythme:</code>, <code>- Mode de formation:</code>, <code>- Évaluation:</code>, <code>- AIAS:</code>, <code>- Description:</code> et <code>- Consignes pour les élèves:</code>. S’ils changent trop, certaines informations risquent de ne plus être reconnues.</p>
                <h3 id="markdown-import">Procédure d’import</h3>
                <ol>
                    <li>Ouvrez Learning Designer.</li>
                    <li>Cliquez sur <strong>Importer</strong>.</li>
                    <li>Choisissez <strong>Markdown</strong>, puis un fichier <code>.md</code> ou <code>.markdown</code>.</li>
                    <li>Vérifiez les paramètres, les moments, les activités et les durées dans l’interface.</li>
                </ol>
                <p>Si l’import échoue, exportez un design simple en Markdown depuis l’application et comparez sa structure avec votre fichier.</p>
            </article>

            <article class="help-section" id="cli">
                <h2>Créer avec l’IA</h2>
                <p>Choisissez entre un prompt prêt à copier, une skill réutilisable ou le CLI selon votre besoin.</p>

                <h3 id="ia-guide">Guide</h3>
                <div class="help-grid">
                    <div class="help-card">
                        <strong>1. L’IA crée et publie</strong>
                        <span>Copiez le prompt ci-dessous, répondez aux questions et validez les propositions de l’IA.</span>
                        <span>Pour publier, donnez-lui explicitement l’autorisation et un jeton CLI créé dans votre profil. Vous n’avez rien à installer vous-même.</span>
                    </div>
                    <div class="help-card">
                        <strong>2. Vous publiez vous-même</strong>
                        <span>L’IA vous remet le fichier <code>design.json</code>. Installez ensuite le CLI sur votre ordinateur, connectez-le avec <code>learning login</code>, puis publiez avec <code>learning publish</code>.</span>
                        <span>Le jeton reste alors sur votre ordinateur.</span>
                    </div>
                </div>

                <h4>Prompt à donner à Claude Code ou Codex</h4>
                <p>Ce prompt suffit pour un usage ponctuel. L’IA sélectionne un CLI compatible et utilise une copie locale si son environnement est isolé.</p>
                <div class="help-prompt-wrap">
                    <button class="help-copy-btn" type="button" aria-label="Copier le prompt" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    <textarea class="help-prompt" data-help-prompt="ai" readonly><?= h($aiPrompt) ?></textarea>
                </div>

                <h3 id="skill-claude">Utiliser la skill</h3>
                <p>La skill est adaptée à un usage régulier dans Claude Code ou Codex. Elle pose les questions pédagogiques, crée le fichier <code>design.json</code> avec le CLI et le valide.</p>

                <h4>Installer ou actualiser</h4>
                <p>Depuis la racine de votre projet, cette commande installe ou actualise la skill pour Claude Code et Codex ainsi que le CLI, puis vérifie leur compatibilité.</p>
                <div class="help-code-wrap">
                    <button class="help-copy-btn" type="button" aria-label="Copier la commande" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    <pre class="help-code">curl -fsSL https://raw.githubusercontent.com/YannHY/learning-designer/main/install-skill.sh | sh</pre>
                </div>
                <p>Relancez l’outil si nécessaire. Dans Claude Code, utilisez <code>/learning-design</code>. Dans Codex, utilisez <code>$learning-designer</code>.</p>

                <h3 id="cli-detaille">Utiliser le CLI</h3>
                <p>Le CLI permet de créer, valider et publier un design directement depuis votre terminal.</p>

                <h4>Installer ou actualiser</h4>
                <p>Utilisez cet installateur si vous souhaitez travailler vous-même avec la commande <code>learning</code>. Il installe la dernière version ou remplace la version existante.</p>
                <div class="help-code-wrap">
                    <button class="help-copy-btn" type="button" aria-label="Copier la commande" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    <pre class="help-code">curl -fsSL https://raw.githubusercontent.com/YannHY/learning-designer/main/install.sh | sh
learning status</pre>
                </div>

                <h4>Créer un design</h4>
                <div class="help-details-grid">
                    <div>
                        <strong>1. Initialiser le fichier</strong>
                        <p><code>init</code> crée le fichier JSON de départ avec le titre, la langue, la durée, la modalité, le système ou la classification et le niveau. Consultez d’abord les catalogues du CLI : ils fournissent les identifiants exacts et empêchent d’associer un niveau au mauvais système.</p>
                        <div class="help-code-wrap">
                            <button class="help-copy-btn" type="button" aria-label="Copier la commande" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                            <pre class="help-code">learning list school-systems
learning list school-levels --system france
learning init design.json --title "Atelier IA" --lang fr --duration 120 --mode hybride --school-system france --school-level quatrieme --group-size 24</pre>
                        </div>
                    </div>
                    <div>
                        <strong>2. Ajouter des moments</strong>
                        <p>Un moment correspond à une grande phase de la séance ou de la séquence.</p>
                        <div class="help-code-wrap">
                            <button class="help-copy-btn" type="button" aria-label="Copier la commande" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                            <pre class="help-code">learning add-moment design.json --title "Explorer" --objectives "Identifier les usages possibles"</pre>
                        </div>
                    </div>
                    <div>
                        <strong>3. Ajouter des activités</strong>
                        <p>Une activité précise le type d’apprentissage, ses paramètres, sa description pédagogique et les consignes directement adressées aux élèves.</p>
                        <div class="help-code-wrap">
                            <button class="help-copy-btn" type="button" aria-label="Copier la commande" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                            <pre class="help-code">learning add-activity design.json --type investigate --duration 30 --group subgroups --teaching guided --pacing sync --mode onsite --evaluation formative --aias 3 --competencies A6,P34 --description "Comparer trois exemples d'usages de l'IA." --instructions "Relevez deux points communs et une différence."</pre>
                        </div>
                    </div>
                    <div>
                        <strong>4. Ajouter des acquis Bloom</strong>
                        <p><code>outcome</code> ajoute un acquis d’apprentissage relié à la taxonomie de Bloom.</p>
                        <div class="help-code-wrap">
                            <button class="help-copy-btn" type="button" aria-label="Copier la commande" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                            <pre class="help-code">learning outcome design.json --bloom analyser --verb "Comparer" --text "Comparer des réponses générées par IA selon leur fiabilité."</pre>
                        </div>
                    </div>
                    <div>
                        <strong>5. Valider et préparer le relais</strong>
                        <p><code>validate</code> vérifie le fichier. <code>prompt</code> produit un prompt de relais utile pour demander à Claude Code ou Codex de continuer le travail.</p>
                        <div class="help-code-wrap">
                            <button class="help-copy-btn" type="button" aria-label="Copier la commande" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                            <pre class="help-code">learning validate design.json --strict-pedagogy
learning prompt design.json</pre>
                        </div>
                    </div>
                </div>

                <h4>Publier</h4>
                <p>Pour publier depuis votre ordinateur, créez d’abord un jeton dans votre profil, section <strong>Publication depuis le CLI</strong>. Ensuite, connectez le CLI et publiez le fichier.</p>
                <div class="help-code-wrap">
                    <button class="help-copy-btn" type="button" aria-label="Copier la commande" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    <pre class="help-code">learning login
learning publish design.json</pre>
                </div>
                <p>Pour mettre à jour une publication existante, gardez l’identifiant renvoyé lors de la première publication.</p>
                <div class="help-code-wrap">
                    <button class="help-copy-btn" type="button" aria-label="Copier la commande" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    <pre class="help-code">learning publish design.json --design-id 123</pre>
                </div>
                <div class="help-callout warning">
                    <i class="fa-solid fa-key" aria-hidden="true"></i>
                    <p>Le jeton CLI est personnel. Il permet de publier sur votre compte : ne le transmettez à l’IA que si vous voulez explicitement qu’elle publie à votre place.</p>
                </div>

                <h4>Commandes utiles</h4>
                <div class="help-code-wrap">
                    <button class="help-copy-btn" type="button" aria-label="Copier la commande" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    <pre class="help-code">learning --help
learning list types
learning list bloom
learning list competencies
learning list school-systems
learning list school-levels --system france
learning status
learning upgrade</pre>
                </div>
            </article>
            <article class="help-section" id="enrichir-design-ia">
                <h2>Interroger et enrichir son design avec l’IA</h2>
                <p>Utilisez l’IA pour questionner un scénario, prioriser des améliorations et produire les ressources nécessaires.</p>
                <h3 id="analyser-design-ia">Analyser son design</h3>
                <p>Une IA peut vous aider à questionner votre séquence, repérer ses points forts et envisager des améliorations directement applicables en classe. Son analyse nourrit votre réflexion : vous restez maître des choix pédagogiques et de leur adaptation à vos élèves.</p>

                <div class="help-grid three">
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-solid fa-file-export" aria-hidden="true"></i></span>1. Préparer le design</strong>
                        <span>Exportez votre design au format JSON depuis Learning Designer, ou préparez une description précise de votre séquence.</span>
                    </div>
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-solid fa-robot" aria-hidden="true"></i></span>2. Configurer l’IA</strong>
                        <span>Créez un Gem dans Gemini ou un projet dans ChatGPT ou Claude, puis copiez le prompt ci-dessous dans ses instructions.</span>
                    </div>
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-solid fa-comments" aria-hidden="true"></i></span>3. Engager le dialogue</strong>
                        <span>Joignez l’export ou collez votre description. Répondez aux questions réflexives, puis retenez et adaptez les pistes pertinentes.</span>
                    </div>
                </div>

                <h4>Prompt d’analyse du design</h4>
                <p>Copiez ce prompt dans les instructions de votre Gem ou de votre projet. Vous pourrez ensuite lui soumettre autant de designs que vous le souhaitez.</p>
                <div class="help-prompt-wrap">
                    <button class="help-copy-btn" type="button" aria-label="Copier le prompt d’analyse du design" title="Copier"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                    <textarea class="help-prompt" data-help-prompt="review" readonly><?= h($designReviewPrompt) ?></textarea>
                </div>

                <div class="help-callout">
                    <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                    <p><strong>Pour aller plus loin :</strong> demandez à l’IA de prioriser trois améliorations réalistes pour votre prochaine séance, puis de vous aider à reformuler concrètement les activités concernées.</p>
                </div>

                <h3 id="prompts-prets-emploi">Utiliser des prompts prêts à l’emploi</h3>
                <div class="help-callout">
                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                    <p><strong>Une bibliothèque de prompts pédagogiques est en préparation.</strong> Vous pourrez y choisir des prompts conçus pour interroger, enrichir ou compléter votre scénario. Les premiers sont consacrés à la conception universelle de l’apprentissage, la différenciation pédagogique et au modèle SAMR.<br><br><a href="prompts.php">Découvrir la page des prompts</a>.</p>
                </div>

                <h3 id="creer-contenus-ia">Créer les contenus nécessaires au scénario</h3>
                <p>Le design décrit les activités à mener ; l’IA peut ensuite vous aider à fabriquer les ressources dont vous avez besoin pour les mettre en œuvre : consignes, fiches élèves, textes adaptés, études de cas, exercices, quiz, corrigés, grilles d’évaluation, supports de présentation ou variantes différenciées.</p>

                <div class="help-grid three">
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-solid fa-crosshairs" aria-hidden="true"></i></span>1. Cibler une activité</strong>
                        <span>Partagez le scénario complet ou l’activité concernée afin que l’IA comprenne le public, les acquis visés, la durée et le contexte.</span>
                    </div>
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i></span>2. Définir le livrable</strong>
                        <span>Précisez le contenu attendu, son format, son niveau de détail et ses contraintes. L’IA peut aussi vous aider à choisir le support pertinent.</span>
                    </div>
                    <div class="help-card">
                        <strong><span class="help-card-icon"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></span>3. Relire et adapter</strong>
                        <span>Vérifiez les informations, les sources, le niveau de difficulté et l’accessibilité, puis ajustez la proposition à votre classe.</span>
                    </div>
                </div>

                <div class="help-callout warning">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <p><strong>Avant d’utiliser un contenu en classe :</strong> relisez-le, contrôlez les faits et les sources, vérifiez les droits d’usage et ne transmettez aucune donnée personnelle ou sensible concernant vos élèves.</p>
                </div>
            </article>
        </div>
    </div>
</main>
<?php render_site_footer(); ?>
<script>
window.helpPromptTranslations = <?= json_encode([
    'en' => [
        'ai' => $aiPromptEn,
        'review' => $designReviewPromptEn,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="js/help-i18n.js?v=20260905-claude-installer-v1"></script>
<script>
var initialHelpLanguage = 'fr';
try {
    initialHelpLanguage = localStorage.getItem('learningDesignerLang') || 'fr';
} catch (error) {
    initialHelpLanguage = 'fr';
}
if (window.HelpPageI18n) {
    window.HelpPageI18n.apply(initialHelpLanguage, window.helpPromptTranslations);
}

document.addEventListener('DOMContentLoaded', function () {
    var isEnglish = initialHelpLanguage === 'en';
    var sections = Array.from(document.querySelectorAll('.help-section[id]'));
    var toc = document.getElementById('help-toc');
    var sidebar = document.getElementById('help-sidebar');
    var toggle = document.getElementById('help-menu-toggle');
    var progressBar = document.getElementById('help-progress-bar');

    function closeMobileSidebar() {
        if (!window.matchMedia('(max-width: 1000px)').matches) return;
        sidebar.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    function navigateTo(target) {
        closeMobileSidebar();
        expandSectionForTarget(target);
        if (window.history && window.history.pushState) {
            window.history.pushState(null, '', '#' + target.id);
        }
        window.requestAnimationFrame(function () {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    function setSubmenuExpanded(group, expanded) {
        var button = group.querySelector('.help-toc-toggle');
        var submenu = group.querySelector('.help-toc-submenu');
        if (!button || !submenu) return;
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        submenu.hidden = !expanded;
    }

    sections.forEach(function (section, index) {
        var sectionNumber = String(index + 1).padStart(2, '0');
        var sectionHeading = section.querySelector(':scope > h2');
        var sectionTitle = sectionHeading ? sectionHeading.textContent.trim() : '';
        if (sectionHeading) {
            var headingIndex = document.createElement('span');
            headingIndex.className = 'help-heading-index';
            headingIndex.setAttribute('aria-hidden', 'true');
            headingIndex.textContent = sectionNumber + '.';
            sectionHeading.prepend(headingIndex);
        }

        var allSubsections = Array.from(section.querySelectorAll(':scope > h3'));
        allSubsections.forEach(function (subsection, subsectionIndex) {
            var subheadingIndex = document.createElement('span');
            subheadingIndex.className = 'help-subheading-index';
            subheadingIndex.setAttribute('aria-hidden', 'true');
            subheadingIndex.textContent = sectionNumber + '.' + (subsectionIndex + 1) + ' ';
            subsection.prepend(subheadingIndex);
        });

        var group = document.createElement('div');
        group.className = 'help-toc-group';
        group.dataset.sectionId = section.id;

        var link = document.createElement('a');
        link.className = 'help-toc-link';
        link.href = '#' + section.id;
        link.dataset.sectionId = section.id;
        link.innerHTML = '<span class="help-toc-index">' + sectionNumber + '</span><span>' + sectionTitle + '</span>';
        link.addEventListener('click', function (event) {
            event.preventDefault();
            navigateTo(section);
        });

        var subsections = allSubsections.filter(function (subsection) {
            return subsection.id;
        });
        if (subsections.length > 1) {
            var row = document.createElement('div');
            row.className = 'help-toc-row';
            row.appendChild(link);

            var submenuId = 'help-toc-submenu-' + section.id;
            var submenuToggle = document.createElement('button');
            submenuToggle.className = 'help-toc-toggle';
            submenuToggle.type = 'button';
            submenuToggle.setAttribute('aria-expanded', 'false');
            submenuToggle.setAttribute('aria-controls', submenuId);
            submenuToggle.setAttribute('aria-label', (isEnglish ? 'Show subsections for ' : 'Afficher les sous-sections de ') + sectionTitle);
            submenuToggle.innerHTML = '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
            submenuToggle.addEventListener('click', function () {
                setSubmenuExpanded(group, submenuToggle.getAttribute('aria-expanded') !== 'true');
            });
            row.appendChild(submenuToggle);
            group.appendChild(row);

            var submenu = document.createElement('div');
            submenu.className = 'help-toc-submenu';
            submenu.id = submenuId;
            submenu.hidden = true;
            subsections.forEach(function (subsection) {
                var sublink = document.createElement('a');
                sublink.className = 'help-toc-sublink';
                sublink.href = '#' + subsection.id;
                sublink.dataset.sectionId = section.id;
                sublink.dataset.subsectionId = subsection.id;
                sublink.textContent = subsection.textContent.trim();
                sublink.addEventListener('click', function (event) {
                    event.preventDefault();
                    navigateTo(subsection);
                });
                submenu.appendChild(sublink);
            });
            group.appendChild(submenu);
        } else {
            group.appendChild(link);
        }
        toc.appendChild(group);

        var sectionIntro = sectionHeading ? sectionHeading.nextElementSibling : null;
        var sectionBodyStart = sectionHeading;
        if (sectionIntro && sectionIntro.tagName === 'P') {
            sectionBodyStart = sectionIntro;
        }

        if (sectionBodyStart) {
            var sectionBody = document.createElement('div');
            sectionBody.className = 'help-section-body';
            sectionBody.id = section.id + '-content';

            var nextNode = sectionBodyStart.nextSibling;
            while (nextNode) {
                var followingNode = nextNode.nextSibling;
                sectionBody.appendChild(nextNode);
                nextNode = followingNode;
            }

            var chapterToggle = document.createElement('button');
            chapterToggle.className = 'help-section-toggle';
            chapterToggle.type = 'button';
            chapterToggle.setAttribute('aria-expanded', 'false');
            chapterToggle.setAttribute('aria-controls', sectionBody.id);
            chapterToggle.setAttribute(
                'aria-label',
                (isEnglish ? 'Open chapter: ' : 'Ouvrir le chapitre : ') + sectionTitle
            );
            chapterToggle.innerHTML =
                '<span class="sr-only">' + (isEnglish ? 'View chapter' : 'Voir le chapitre') + '</span>' +
                '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
            chapterToggle.addEventListener('click', function () {
                setSectionExpanded(section, chapterToggle.getAttribute('aria-expanded') !== 'true');
            });

            sectionBody.hidden = true;
            section.appendChild(chapterToggle);
            section.appendChild(sectionBody);
        }
    });

    function setSectionExpanded(section, expanded) {
        if (!section) return;
        var button = section.querySelector(':scope > .help-section-toggle');
        var body = section.querySelector(':scope > .help-section-body');
        if (!button || !body) return;

        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        button.setAttribute(
            'aria-label',
            (expanded
                ? (isEnglish ? 'Close chapter: ' : 'Réduire le chapitre : ')
                : (isEnglish ? 'Open chapter: ' : 'Ouvrir le chapitre : ')) +
            (section.querySelector(':scope > h2')?.textContent.replace(/^\d+\.\s*/, '').trim() || '')
        );
        button.querySelector('span').textContent = expanded
            ? (isEnglish ? 'Hide chapter' : 'Masquer le chapitre')
            : (isEnglish ? 'View chapter' : 'Voir le chapitre');
        body.hidden = !expanded;
        section.classList.toggle('is-expanded', expanded);
    }

    function expandSectionForTarget(target) {
        if (!target) return;
        var section = target.classList.contains('help-section')
            ? target
            : target.closest('.help-section');
        setSectionExpanded(section, true);
    }

    var tocLinks = Array.from(toc.querySelectorAll('.help-toc-link'));
    var tocSublinks = Array.from(toc.querySelectorAll('.help-toc-sublink'));
    function setActiveSection(id, subsectionId) {
        tocLinks.forEach(function (link) {
            var active = link.dataset.sectionId === id;
            link.classList.toggle('is-active', active);
            if (active && !subsectionId) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
        tocSublinks.forEach(function (link) {
            var active = link.dataset.subsectionId === subsectionId;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
        var index = sections.findIndex(function (section) { return section.id === id; });
        if (index >= 0) progressBar.style.width = (((index + 1) / sections.length) * 100) + '%';
    }

    var scrollUpdatePending = false;
    function updateActiveFromScroll() {
        var current = sections[0];
        sections.forEach(function (section) {
            if (section.getBoundingClientRect().top <= 145) current = section;
        });
        var currentSubsection = null;
        var currentBody = current.querySelector(':scope > .help-section-body');
        if (currentBody && !currentBody.hidden) {
            Array.from(currentBody.querySelectorAll('h3[id]')).forEach(function (subsection) {
                if (subsection.getBoundingClientRect().top <= 145) currentSubsection = subsection;
            });
        }
        setActiveSection(current.id, currentSubsection ? currentSubsection.id : '');
        scrollUpdatePending = false;
    }
    function requestScrollUpdate() {
        if (scrollUpdatePending) return;
        scrollUpdatePending = true;
        window.requestAnimationFrame(updateActiveFromScroll);
    }
    window.addEventListener('scroll', requestScrollUpdate, { passive: true });
    window.addEventListener('resize', requestScrollUpdate);

    function getHashTarget() {
        var targetId = window.location.hash.slice(1);
        if (!targetId) return null;
        try {
            targetId = decodeURIComponent(targetId);
        } catch (error) {
            return null;
        }
        if (targetId === 'bonnes-pratiques') {
            targetId = 'enrichir-design-ia';
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#enrichir-design-ia');
            }
        }
        return document.getElementById(targetId);
    }

    function expandSubmenuForHash() {
        var target = getHashTarget();
        if (!target || target.tagName !== 'H3') return;
        var section = target.closest('.help-section');
        var group = section ? toc.querySelector('.help-toc-group[data-section-id="' + section.id + '"]') : null;
        if (group) setSubmenuExpanded(group, true);
    }

    function alignAndActivateHashTarget() {
        var target = getHashTarget();
        if (!target) return;
        target.scrollIntoView({ behavior: 'instant', block: 'start' });

        var section = target.classList.contains('help-section') ? target : target.closest('.help-section');
        if (!section) return;
        setActiveSection(section.id, target.tagName === 'H3' ? target.id : '');
    }

    function syncHashNavigation() {
        expandSectionForTarget(getHashTarget());
        expandSubmenuForHash();
        window.requestAnimationFrame(alignAndActivateHashTarget);
    }

    window.addEventListener('popstate', syncHashNavigation);
    window.addEventListener('hashchange', syncHashNavigation);
    window.addEventListener('load', syncHashNavigation);
    expandSectionForTarget(getHashTarget());
    expandSubmenuForHash();
    updateActiveFromScroll();

    toggle.addEventListener('click', function () {
        var open = sidebar.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    async function copyText(text, button) {
        try {
            await navigator.clipboard.writeText(text);
        } catch (error) {
            var temporary = document.createElement('textarea');
            temporary.value = text;
            temporary.setAttribute('readonly', '');
            temporary.style.position = 'fixed';
            temporary.style.top = '-1000px';
            document.body.appendChild(temporary);
            temporary.select();
            document.execCommand('copy');
            temporary.remove();
        }
        var original = button.innerHTML;
        button.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
        button.setAttribute('title', isEnglish ? 'Copied' : 'Copié');
        window.setTimeout(function () {
            button.innerHTML = original;
            button.setAttribute('title', isEnglish ? 'Copy' : 'Copier');
        }, 1300);
    }

    document.querySelectorAll('.help-copy-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            var container = button.parentElement;
            var source = container.querySelector('.help-code, .help-prompt');
            if (!source) return;
            copyText(('value' in source ? source.value : source.textContent).trim(), button);
        });
    });

    var helpLangSelect = document.getElementById('lang-select');
    if (helpLangSelect) {
        helpLangSelect.addEventListener('change', function () {
            window.setTimeout(function () { window.location.reload(); }, 0);
        });
    }

});
</script>
</body>
</html>
