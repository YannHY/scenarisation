<img src="assets/icon.svg" width="64" height="64" alt="">

# Scénarisation pour Mac

Application native SwiftUI pour concevoir des scénarios pédagogiques hors ligne et les travailler avec un modèle local, un compte ChatGPT ou Claude, ou une clé API.

## Démarrer

Prérequis : macOS 15 ou ultérieur, Xcode 26 ou ultérieur avec Swift 6 (le SDK 26 est nécessaire pour Liquid Glass).

```sh
bash scripts/build-app.sh
open dist/Scénarisation.app
```

L’application générée est signée localement pour le développement. Pour une distribution à d’autres utilisateurs, prévoir une signature Developer ID et la notarisation Apple.

Le projet s’ouvre aussi dans Xcode via `Package.swift`. Il ne dépend d’aucun paquet externe et ne nécessite aucun serveur pour l’édition.

## Utilisation

- **Fichier → Nouveau** crée un scénario. **Ouvrir** accepte les scénarios JSON, Markdown, CSV, Excel (`.xlsx`), HTML, Word (`.docx`) et LDJ ; **Enregistrer** conserve le document éditable au format JSON. Après ce premier enregistrement, macOS gère l’enregistrement automatique du document.
- Depuis l’accueil, un scénario créé, tiré d’un modèle, importé (quel que soit son format) ou créé avec l’IA devient aussitôt un fichier JSON du **dossier des scénarios**, puis s’enregistre automatiquement. Le dossier est demandé la première fois et se modifie dans **Réglages → Scénarios**. Si aucun dossier n’est choisi, le scénario reste un document sans titre à enregistrer soi-même.
- Au lancement, une fenêtre d’accueil en verre propose quatre entrées : créer un scénario, importer un modèle, ouvrir un scénario partagé et **Créer avec l’IA**. Ce quatrième choix ouvre un chat dans la zone principale avec un service déjà configuré, ou invite à connecter le service de son choix. Les anciennes fenêtres de conception ne sont pas restaurées automatiquement au lancement.
- Le concepteur suit les codes de macOS : barre latérale (Conception, Paramètres, Analyse, Chronologie et liste des moments pour naviguer, précédés du champ **Rechercher**, qui interroge le site), barre d’outils native et inspecteur pour l’assistant. Sur macOS 26 ou ultérieur, ces éléments adoptent Liquid Glass ; sur macOS 15, ils retombent sur les matériaux translucides. Les moments s’affichent en colonnes, liste ou grille (⌘1, ⌘2, ⌘3) ; le modèle JSON conserve la disposition choisie.
- Chaque moment contient ses objectifs, intentions, activités et notes. Une activité propose ses consignes, sa durée, son type d’apprentissage, ses modalités, son niveau AIAS et ses compétences.
- Les huit référentiels (1 198 compétences), la taxonomie de Bloom et les niveaux de treize systèmes scolaires sont embarqués.
- Les compétences se parcourent par référentiel et domaine. La recherche peut couvrir tous les référentiels et reconnaît les codes, les alias et les noms de domaines ; les compétences retenues s’alignent en pastilles avec retour à la ligne dans les activités.
- La bibliothèque reprend les **28 modèles complets du site**, répartis en huit familles, avec recherche et filtres (famille, durée, nombre de moments, mode, type d’apprentissage). Chaque modèle peut être visualisé avant import.
- Les paramètres présentent le titre modifiable en tête, puis deux colonnes : les descriptions, objectifs et acquis Bloom à gauche ; les durées, le contexte et le matériel à droite. La fiche repasse en une seule colonne dans les fenêtres étroites. Tous les réglages restent disponibles : durées prévue et conçue, heures par jour, mode, système scolaire et niveau, taille du groupe, concepteurs et enseignants.
- Les activités possèdent une barre de modalités compacte avec des symboles SF Symbols. Les menus permettent de les déplacer, dupliquer et supprimer. Les barres des moments et des activités se glissent pour déplacer un moment ou transférer une activité dans un autre moment.
- L’analyse pondère les sept répartitions par les durées : types d’apprentissage, modalités, enseignement, rythme, évaluation, groupes et AIAS. Les lignes de la chronologie sont configurables ; un clic sur une activité ramène au moment correspondant.
- Les champs longs acceptent le Markdown et le LaTeX du site. Le bouton **Agrandir** ouvre un éditeur avec la barre de formatage (gras, italique, titre, listes, citation, lien), l’assistant de formules **∑** avec ses modèles et un aperçu en direct ; les raccourcis du site (⌘B, ⌘I, ⌘K, ⇧⌘H, ⇧⌘L, ⇧⌘7, ⇧⌘Q, ⇧⌘M) et la continuation des listes y fonctionnent. Les cartes affichent toujours le Markdown et le LaTeX rendus ; on saisit le texte uniquement dans l’éditeur agrandi, ouvert par les flèches **Agrandir** ou un clic sur le contenu. Les formules sont composées par KaTeX, embarqué : aucun accès réseau. L’apparence suit le réglage clair/sombre et la couleur d’accentuation du système.
- Le menu **Aide** ouvre l’aide intégrée (⌘?) : commandes Mac, dix chapitres complets du guide web et trois pages pédagogiques (Learning design, taxonomie de Bloom avec ses 80 verbes et Cadre conversationnel avec ses neuf schémas). Les cartes, les tableaux, la numérotation et les chapitres repliables reprennent la présentation du site ; les liens entre documents restent dans l’aide. Textes, icônes et schémas sont consultables hors ligne. Les passages sur les comptes, la publication et le CLI sont identifiés comme documentation du site. La fenêtre **Licence et crédits** présente la CC BY-SA 4.0 et les composants tiers. Les **conditions d’utilisation** et la **politique de confidentialité** du site y sont intégrées telles quelles (versions en ligne du 26 et du 10 septembre 2026), précédées d’une note sur le fonctionnement local de l’app. **À propos de Scénarisation** reprend les crédits du site.
- **Édition → Annuler/Rétablir** permet de revenir sur les modifications, y compris l’application d’une proposition IA.
- Le champ **Rechercher** de la barre latérale reprend le moteur de scenarisation.eu et ses filtres (Tout, Aide, Modèles, Scénarios partagés, Compétences, Prompts, Références, Autres pages). Les pages viennent de l’index Pagefind publié avec le site, chargé dans une vue web invisible sans données persistantes ; les scénarios partagés viennent de `search_public_designs.php`. Seuls les termes saisis sont envoyés, sans jeton de compte. Les résultats s’ouvrent dans le navigateur avec les termes surlignés.
- **Assistant IA** affiche ou masque l’inspecteur de droite (`⌥⌘I`). Le menu **Scénario** regroupe l’ajout d’un moment (`⇧⌘N`), l’import de modèle (`⇧⌘M`) ou de fichier (`⇧⌘I`), l’export (`⇧⌘E`) et la recherche sur le site (`⌥⌘F`). Les réglages sont accessibles dans ce panneau ou dans le menu de l’application.

Les champs JSON que l’interface n’expose pas encore sont conservés lors d’une édition manuelle et d’un enregistrement. Les anciens scénarios Learning Designer (`.ldj` ou JSON avec `activities`/`slas`) sont convertis à l’import.

**Importer un scénario** (`⇧⌘I`), l’accueil et **Fichier → Ouvrir** acceptent les mêmes formats. Les exports enseignant et élève du site sont reconnus ; les CSV peuvent utiliser la virgule, le point-virgule ou la tabulation. Les classeurs Excel acceptent les chaînes partagées, les cellules numériques et plusieurs feuilles, y compris après compression par un tableur. L’import récupère les informations présentes dans le fichier : les formats de diffusion ne permettent pas de restituer les données absentes. En particulier, les exports élève du site n’indiquent pas de durée ni de modalités ; les activités reçoivent alors une durée de 10 minutes à ajuster et un type à définir. L’import dans un scénario ouvert demande son remplacement et reste annulable. Les fichiers importés sont enregistrés en JSON ; les autres formats restent disponibles dans **Exporter**.

**Exporter** propose JSON, Markdown, HTML, Word (`.docx`), Excel (`.xlsx`) et CSV, avec sélection des moments et version enseignant ou élève. La version élève retire les métadonnées, objectifs, notes et descriptions réservés à l’enseignant. Les exports Word et Excel utilisent de vrais conteneurs OOXML ; les colonnes du tableur reprennent celles du site. Les informations structurées complètes restent conservées dans le JSON ; les formats de diffusion ne sont pas des copies sans perte du document.

## Écarts encore présents avec le site

- La barre de formatage n’apparaît que dans l’éditeur agrandi (comme le plein écran du site). L’export HTML compose les formules mais conserve le Markdown en texte, pour rester réimportable.
- Les analyses sont affichées dans l’app ; leur export en image/PDF n’est pas encore implémenté.
- Les modèles sont des instantanés embarqués. L’app ne consulte pas encore le catalogue communautaire en ligne.
- La publication se gère sur le site. L’app propose la connexion au compte et la synchronisation manuelle des scénarios privés.

## Compte et synchronisation

**Fichier → Mon compte et mes scénarios…**, la carte de l’accueil et le bouton de la barre d’outils ouvrent ce parcours. **Se connecter à Scénarisation** ouvre le navigateur : connectez-vous à `scenarisation.eu` puis autorisez l’accès à vos scénarios. Le retour dans l’app utilise une adresse temporaire limitée à `127.0.0.1`. Les jetons de lecture/écriture sont conservés ensemble dans un service distinct du Trousseau macOS et renouvelés avant expiration. Aucun mot de passe du site, jeton CLI ou compte IA n’est utilisé par cette connexion.

Après connexion, **Ouvrir** télécharge une copie éditable d’un scénario du site. **Synchroniser** dans la bibliothèque ou dans la barre d’outils du concepteur échange les modifications avec le site. Un scénario Mac peut être envoyé explicitement à votre compte ; sa création reste privée. Quand vous êtes connecté, un nouveau scénario du dossier des scénarios rejoint votre compte dès qu’il a un titre ou un moment ; créé hors connexion, il le rejoint à sa prochaine ouverture avec une connexion. Une fois lié au compte, un scénario se synchronise automatiquement : chaque modification part sur le site trois secondes après la dernière saisie, et l’ouverture récupère les changements faits sur le site. **Actualiser** relit la liste des scénarios disponibles.

La corbeille de la bibliothèque supprime un scénario de ce Mac et de votre compte, après confirmation ; celle de la section « Sur le site » le supprime du compte. Un scénario modifié sur le site depuis sa dernière synchronisation n’est pas supprimé : synchronisez-le d’abord pour voir ces changements. Supprimer un fichier dans le Finder ne touche pas au site.

Un scénario supprimé sur le site disparaît aussi de ce Mac : l’app relit votre compte à son ouverture, chaque fois que vous y revenez, toutes les deux minutes et avec **Actualiser**. Sa fenêtre se ferme et son fichier du dossier des scénarios part à la Corbeille, d’où il reste récupérable. La corbeille de la bibliothèque fait de même pour le fichier lié. Seules des modifications faites sur le Mac et pas encore envoyées retiennent le scénario : il reste alors marqué « Supprimé sur le site » et **Conserver une copie** en fait un nouveau scénario.

**Compte → Se déconnecter** désactive les jetons locaux et tente de révoquer la session distante. Les copies locales et leurs changements restent accessibles hors ligne ; reconnectez le même compte pour reprendre leur synchronisation. Un avertissement indique si la révocation distante n’a pas pu être confirmée. En cas de session expirée ou révoquée, l’app demande une reconnexion et conserve les documents.

- Hors connexion, les changements restent en attente et repartent à la modification suivante, ou avec **Synchroniser**.
- Si un scénario a changé à la fois sur le Mac et sur le site, **Synchroniser** signale un conflit. **Examiner les versions** affiche les deux documents ; **Conserver les deux versions** garde le scénario distant et crée une copie Mac indépendante, dont l’envoi reste explicite.
- Les copies et leurs versions de référence sont conservées dans `~/Library/Application Support/Scenarisation/SiteSync/`, séparées par compte.
- Une modification pendant un envoi reste en attente après la confirmation de cet envoi. Une création réessayée conserve son UUID pour éviter les doublons. Un document supprimé sur le serveur n’est jamais recréé automatiquement.
- Un fichier suivi reçoit un champ technique `_scenarisationMac` contenant uniquement son identité locale et sa partition de compte. Ce champ est retiré du scénario à l’ouverture et exclu des exports et des envois API. Les autres champs JSON sont conservés.

Le [contrat de l’API native](docs/site-sync-api.md) décrit les routes OAuth et les échanges de scénarios implémentés dans l’app et sur le site. La synchronisation automatique et les actions de publication ne sont pas proposées dans l’app.

## Intelligence artificielle

### Avec votre compte : ChatGPT, Claude, Gemini / Antigravity ou Grok

Dans les réglages de l’assistant, la rubrique **Avec votre compte** propose quatre connexions sans saisie de clé API.

- **ChatGPT** : cliquez sur **Continuer avec ChatGPT**, connectez-vous dans le navigateur et autorisez l’application à utiliser votre abonnement. Le retour dans l’app est automatique. Les modèles autorisés pour votre compte sont récupérés et le premier est sélectionné ; vous pouvez ensuite en changer. L’accès dépend de l’éligibilité de votre compte et des permissions accordées. **Gérer l’utilisation** ouvre les limites et les accès dans ChatGPT. Une identité connectée sans permission d’utiliser l’abonnement ne permet aucun envoi de scénario.
- **Claude (via Claude Code)** : installez une version récente du binaire officiel Claude Code, puis cliquez sur **Continuer avec Claude**. Claude Code réalise lui-même sa connexion OAuth et conserve ses identifiants ; l’application ne lit ni ne réutilise ses jetons. Sonnet est sélectionné au départ ; Opus et Haiku restent disponibles selon votre abonnement. Retirer le compte de Scénarisation conserve la connexion de Claude Code pour vos autres usages.

- **Gemini / Antigravity** : installez [Antigravity CLI](https://antigravity.google/docs/cli/tutorial), puis choisissez **Continuer avec Google**. L’outil officiel gère la connexion au compte personnel Google et expose les modèles autorisés par ce compte. Aucun accès aux modèles tiers réservé à d’autres clients n’est revendiqué.
- **Grok** : installez [Grok Build](https://docs.x.ai/build/overview), puis choisissez **Continuer avec Grok**. Le programme officiel ouvre la connexion par navigateur ; l’application utilise ensuite son authentification par compte.

Antigravity et Grok communiquent avec l’application par [ACP pour Antigravity](https://antigravity.google/docs/ide/extensions/acp/) et [ACP pour Grok](https://docs.x.ai/build/cli/headless-scripting). Les modèles sont détectés sans générer de texte ni envoyer le scénario. Les demandes passent par l’entrée standard, dans un répertoire temporaire privé. L’application n’expose aucun outil de lecture, d’écriture ou de terminal au protocole et refuse les demandes de permission qu’il lui transmet. Les réglages et la conservation éventuelle des sessions de l’outil officiel restent applicables. Scénarisation ne lit ni ne conserve ses jetons et ne transmet aucune clé API ; les quotas et conditions de l’abonnement s’appliquent. **Retirer ce compte de l’application** désactive son utilisation dans Scénarisation, sans déconnecter l’outil officiel. Les binaires `agy` et `grok` sont recherchés dans les emplacements d’installation courants et le `PATH` de l’app.

Pour ChatGPT, le parcours officiel des applications ouvertes utilise OAuth avec PKCE, un retour HTTP limité à `127.0.0.1`, la validation de l’identité signée, des permissions et du compte choisi. Les jetons sont conservés dans le Trousseau et renouvelés avant expiration. Les inscriptions restent séparées par compte et espace de travail. La déconnexion tente de révoquer la session et retire les jetons locaux ; l’application signale si la révocation distante n’a pas pu être confirmée. Aucun identifiant OAuth d’une autre application ni secret client n’est utilisé.

Pour Claude, cette intégration repose sur l’utilisation du **binaire officiel non modifié**, avec sa propre connexion. Anthropic ne propose pas de connexion Claude.ai OAuth directe aux applications tierces. Les demandes sont passées à Claude Code par l’entrée standard, dans un répertoire temporaire, avec les outils, MCP, personnalisations et la conservation des conversations désactivés. Les méthodes d’authentification par clé API sont refusées pour cette option, afin de ne pas basculer silencieusement sur une autre facturation. Les éventuelles politiques gérées de Claude Code continuent de s’appliquer.

Références : [connexion ChatGPT pour les applications ouvertes](https://developers.openai.com/siwc/token-sharing-open-source/sign-in), [modèles et Responses](https://developers.openai.com/siwc/token-sharing-open-source/models-and-inference), [authentification et intégration de Claude Code](https://code.claude.com/docs/en/legal-and-compliance), [commandes Claude Code](https://code.claude.com/docs/en/cli-reference).

### Sur ce Mac : Ollama ou LM Studio

Dans l’assistant, cliquez sur **Choisir un modèle**, puis choisissez **Ollama** ou **LM Studio**. L’adresse locale est préremplie et les modèles sont détectés automatiquement. Avec un seul modèle, celui-ci est sélectionné ; sinon, choisissez-le dans la liste. **Actualiser les modèles** permet de récupérer un modèle ajouté depuis. Pour Ollama, les capacités sont vérifiées sans charger les modèles ; ceux qui ne proposent pas la génération de texte sont exclus de la liste.

Ollama ou LM Studio doit être installé et disposer d’un modèle téléchargé. Avec LM Studio, chargez le modèle et activez le serveur local. Des liens d’installation sont proposés dans les réglages ; Scénarisation ne télécharge pas de modèle à votre place.

Un modèle local répond plus lentement qu’un service en ligne : comptez une à deux minutes pour un scénario complet. Avec Ollama comme avec LM Studio, la réponse est lue au fil de sa génération et sa longueur est plafonnée. Avec Ollama, le mode « réflexion » des modèles qui le proposent est désactivé, car il multiplie l’attente ; avec LM Studio, il reste actif et la réflexion est simplement écartée de la réponse. Le bouton **Arrêter** interrompt une demande à tout moment.

### En ligne : OpenAI, Mistral ou un autre service

Choisissez **OpenAI** ou **Mistral**, collez votre clé API, puis cliquez sur **Connecter**. L’adresse est préremplie, la clé est enregistrée dans le Trousseau macOS et les modèles de conversation disponibles sont proposés. La détection ne génère aucune réponse et ne transmet pas le scénario. Un abonnement à un assistant web ne fournit pas nécessairement de crédits API.

Pour un autre fournisseur, choisissez **Autre service compatible** et renseignez son adresse de base HTTPS, généralement terminée par `/v1`, ainsi que sa clé si nécessaire. Les serveurs sur ce Mac peuvent utiliser HTTP. Chaque service conserve son adresse et son modèle ; les clés restent séparées par service et adresse. Les anciens réglages Ollama et API compatible sont conservés.

Les **Options avancées** permettent de personnaliser l’adresse, de supprimer une clé ou de saisir un modèle manuellement si le service ne fournit pas de liste. **Vérifier la connexion** envoie alors un bref message au modèle, sans le scénario ; ce test peut être facturé par le fournisseur. Le menu sous le titre de l’assistant permet ensuite de changer de modèle parmi ceux qui ont été détectés.

Les accès par clé utilisent `/chat/completions`. Le filtrage OpenAI écarte les familles spécialisées les plus courantes ; la présence dans la liste ne garantit pas la compatibilité de chaque modèle avec Chat Completions. Les identifiants non reconnus restent accessibles par saisie manuelle. La découverte OpenAI suit la [documentation officielle de l’API Models](https://developers.openai.com/api/reference/resources/models/methods/list). La connexion par compte ChatGPT utilise séparément Responses avec `store: false` et un flux suivi jusqu’à sa confirmation finale ; elle ne se rabat pas sur une clé API. L’API native d’Anthropic par clé n’est pas encore proposée.

### Examiner avant d’appliquer

Chaque demande transmet le scénario complet et l’historique de la conversation au serveur configuré. Avec l’adresse Ollama locale par défaut, cet échange reste sur le Mac ; avec une API distante, le contenu est envoyé au fournisseur choisi. Aucun envoi n’est réalisé pendant la simple édition.

L’assistant peut poser des questions, expliquer un choix pédagogique ou préparer une proposition. Il s’appuie sur les acquis observables de Bloom, les types d’apprentissage de Laurillard et l’alignement entre activités et évaluation. Il réutilise les réponses déjà données et limite ses questions aux informations utiles. Les conseils ne créent pas de proposition à appliquer.

**Créer avec l’IA** commence par le sujet, le public/niveau/système scolaire et la durée. Le dialogue reprend ensuite le cadrage du parcours CLI : modalité, taille du groupe, objectifs d’enseignement, acquis attendus, contraintes, niveau de détail et référentiels pertinents, avec les questions complémentaires sur Bloom, les compétences et les supports. Les informations déjà données sont réutilisées ; les questions sont posées par petits groupes. Le menu du chat permet de changer de modèle, de reprendre un autre service mémorisé ou d’en connecter un nouveau : Ollama, LM Studio, ChatGPT, Claude, OpenAI, Mistral ou un autre service compatible. Les listes de modèles détectées sont conservées par service et adresse. **Appliquer** ouvre le scénario créé dans le concepteur.

Des outils internes lui permettent de consulter les catalogues de compétences, de systèmes scolaires et de Bloom, d’analyser les durées, d’ajouter un moment ou un acquis et de modifier une activité par son identifiant. Ils réutilisent les fonctions de l’app, sans installation du CLI ni accès au terminal. Les modifications sont préparées sur une copie temporaire et les résultats des outils sont retournés au modèle. Une demande peut nécessiter plusieurs appels au modèle, avec une limite de six appels d’outils. La validation porte sur la structure et les identifiants ; elle ne garantit pas la qualité pédagogique.

La structure, les durées, les identifiants, les nouveaux codes de compétences et niveaux scolaires et les acquis Bloom ajoutés ou modifiés sont validés avant de proposer l’application du scénario. **Examiner** affiche les versions actuelle et proposée, avec leur JSON complet. Une nouvelle demande peut préciser la proposition en attente. **Appliquer** remplace le scénario et reste annulable ; **Écarter** laisse le document intact. Si le document a changé depuis la demande, l’application de la proposition est bloquée.

Pour rédiger un seul champ, l’icône **Aide IA** (étincelles) est disponible dans l’éditeur agrandi des descriptions d’activités, des consignes, des objectifs du moment et de la formation et des acquis de Bloom. Le service configuré reçoit uniquement le texte et le contexte pédagogique utile. Une demande libre ou les actions **Reformuler**, **Raccourcir** et **Préciser** produisent une proposition modifiable ; **Insérer** ou **Remplacer** ne change que le champ ciblé et reste annulable avec ⌘Z. Une proposition n’est pas insérée si le champ a changé pendant la génération.

Le bouton **Nouvelle conversation** efface les échanges et la proposition en attente. La conversation reste temporaire, dans la mémoire de l’app, et n’est pas enregistrée dans le fichier pédagogique. L’affichage progressif des réponses n’est pas encore proposé.

## Version iPad

L’app iPad se développe en parallèle de l’app Mac, sur le même code. Le projet `iPad/ScenarisationPad.xcodeproj` (iPadOS 26 ou ultérieur) compile directement les fichiers de `Sources/Scenarisation/` : un nouveau fichier y rejoint automatiquement l’app iPad. Seuls `ScenarisationApp.swift`, `LaunchView.swift`, `ClaudeCodeClient.swift` et `SubscriptionCLIClient.swift` en sont exclus (dans la liste d’exceptions du projet). Les différences entre plateformes sont regroupées dans `Platform.swift` (gardes `#if os(macOS)`), et les pièces propres à l’iPad dans `iPad/Sources/`.

```sh
bash scripts/build-ipad.sh                          # compilation pour le simulateur
bash scripts/build-ipad.sh "iPad Pro 13-inch (M5)"  # compilation, installation et lancement
```

Pour un iPad réel, ouvrez le projet dans Xcode et choisissez votre équipe de signature. Les icônes se régénèrent avec `swift iPad/CreateIcon.swift iPad/Resources/Assets.xcassets`.

- **Bibliothèque** : l’équivalent du dossier des scénarios du Mac est le dossier Documents de l’app, visible dans **Fichiers › Sur mon iPad › Scénarisation**. Un scénario créé, tiré d’un modèle, importé ou créé avec l’IA y devient aussitôt un fichier JSON, enregistré automatiquement moins d’une seconde après chaque modification. Un fichier ouvert depuis Fichiers ou une autre app est importé en copie ; l’original n’est pas modifié.
- **Éditeur** : le même que sur Mac (conception, paramètres, analyse, chronologie, assistant en inspecteur), avec boutons Annuler/Rétablir. Les raccourcis des menus et ceux de l’éditeur Markdown agrandi fonctionnent avec un clavier physique.
- **Aide, licence, compte et réglages de l’assistant** s’affichent en feuilles au lieu de fenêtres. L’export passe par le sélecteur de Fichiers.
- **Connexions** : le site et ChatGPT s’ouvrent dans une session d’authentification intégrée ; le retour utilise la même adresse locale `127.0.0.1` que sur Mac (client OAuth `scenarisation-mac` pour l’instant).
- **IA** : ChatGPT, OpenAI, Mistral et les services compatibles. Claude via Claude Code, Gemini / Antigravity, Grok et les modèles locaux (Ollama, LM Studio) dépendent de programmes installés sur Mac et ne sont pas proposés.
- L’import Word, Excel et HTML utilise libxml2 à la place du DOM XML de macOS (`iPad/Sources/XMLDocument+iPad.swift`), sans entités externes ni accès réseau.

## Vérifier

```sh
swift test
bash scripts/build-app.sh release
bash scripts/build-ipad.sh
```

Les tests couvrent les quatre parcours d’accueil, la reprise des services et modèles configurés, le cadrage commun au parcours CLI, les échanges JSON sans perte de champs non exposés, la validation des propositions, les 28 modèles originaux et les catalogues embarqués, les répartitions pondérées, l’absence de contenu enseignant dans les exports élève, la structure ZIP/XML des exports Word/Excel, les adresses autorisées, la conservation des réglages IA et les protocoles de découverte et de génération via des réponses réseau simulées. Ils ne nécessitent ni modèle ni clé et ne font aucun appel à un fournisseur réel.

Les tests d’import utilisent aussi des exports enseignant et élève générés par le code du site, puis des variantes Office compressées avec chaînes partagées et plusieurs feuilles. Ils vérifient les textes multilignes, les séances vides, les compétences, les modalités, les durées décimales, la migration LDJ et le rejet des fichiers malformés.

Les tests de l’aide chargent les documents dans WebKit et vérifient les cartes, la numérotation, l’ouverture des chapitres par ancre, les destinations locales et l’absence de débordement dans une fenêtre étroite. Les instantanés du guide et des trois pages pédagogiques peuvent être actualisés avec `python3 scripts/sync-help.py` (accès Internet requis uniquement pour cette actualisation). Les styles d’adaptation Mac restent dans `Web/help.css` et les interactions dans `Web/help.js`.

Les tests de synchronisation utilisent un serveur local simulé et des réponses HTTP interceptées, sans connexion au site. Ils couvrent les reprises après coupure et redémarrage, les créations réessayées sans doublon, les modifications pendant un envoi, les conflits de révision, les suppressions, les partitions de compte et les erreurs d’écriture du suivi local. Les tests OAuth vérifient PKCE, le retour du navigateur, le renouvellement partagé des jetons, leur conservation après annulation, les échecs du Trousseau, la révocation et l’isolation des comptes. Le navigateur et le Trousseau sont remplacés par des services de test ; la connexion avec un compte réel reste à vérifier manuellement.

## Sources et licence

Application dérivée de [Scénarisation](https://github.com/YannHY/scenarisation), développé par Yann Houry sur la base du travail de François Jourde et inspiré de l’UCL Learning Designer.

Les catalogues embarqués proviennent de `data/cli-competencies.json` et des constantes `SCHOOL_SYSTEM_OPTIONS` / `SCHOOL_LEVEL_OPTIONS` / `BLOOM_TAXONOMY` de `js/editor/config.js` du site, consultés le 15 novembre 2026. Les 28 modèles sont générés à partir des fonctions originales de `models.php`, sans exécuter le serveur ni sa base de données. Ce sont des instantanés locaux : ils ne sont pas actualisés en arrière-plan. Les identifiants de compétences et de niveaux sont conservés pour la compatibilité avec le site.

Les contrôles natifs utilisent les SF Symbols de macOS. L’aide reprend les icônes [Font Awesome Free](https://fontawesome.com) 6.5.2 du site (© Fonticons, Inc. ; fontes SIL OFL 1.1, code MIT, icônes CC BY 4.0 ; licences embarquées dans `Web/font-awesome-LICENSE.txt`). Les documents d’aide et leurs styles proviennent de `help.php`, `learning-design.php`, `bloom.php` et `cadre-conversationnel.php`, consultés le 15 novembre 2026 ; le contenu français est conservé, avec une note distinguant les fonctions du site et de l’app et une adaptation de la présentation à la fenêtre Mac. Les formules sont composées par [KaTeX](https://katex.org) 0.16 (licence MIT, © Khan Academy et contributeurs), repris du dossier `assets/vendor/katex` du site ; seules les polices WOFF2 sont embarquées. L’icône représente un disque plein aux six couleurs des types d’apprentissage et figure dans l’aide. L’accueil présente une illustration vectorielle distincte : un carnet ouvert, des fiches et un crayon, dans la même palette et adaptés aux modes clair et sombre.

Code et contenus dérivés distribués sous [CC BY-SA 4.0](LICENSE). Les référentiels tiers conservent leurs conditions et attributions d’origine ; leurs liens source figurent dans les catalogues embarqués.
