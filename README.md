# Scenarisation

Scenarisation est une application libre pour concevoir, analyser et partager des scénarios pédagogiques.

Elle aide à passer d'une intention pédagogique à une séquence exploitable : organiser les étapes, préciser les activités et les consignes, formuler les acquis d'apprentissage, estimer les durées et vérifier l'équilibre des modalités proposées aux élèves.

Le projet est inspiré de l'[UCL Learning Designer](https://www.ucl.ac.uk/learning-designer/) et s'appuie sur le travail de [François Jourde](https://github.com/jourde/learning-designer-revised).

## Ce que Scenarisation apporte

- **Concevoir avec un cadre pédagogique commun** : chaque scénario est structuré en moments et activités, reliés à des types d'apprentissage, des modalités, des compétences issues de sept cadres (Florimont, Socle commun, GreenComp, DigComp 3.0, CRCN, Pix et Pix IA), des niveaux AIAS et des acquis issus de la taxonomie de Bloom.
- **Partir d'un modèle plutôt que d'une page blanche** : 28 scénarios génériques, répartis en huit familles, sont prêts à être adaptés à une discipline et à un contexte.
- **Relire un scénario sous plusieurs angles** : la répartition du temps et des types d'apprentissage aide à repérer les déséquilibres, les enchaînements trop denses ou les modalités trop peu variées.
- **Partager et réutiliser les productions** : un design peut être sauvegardé dans un compte, publié par lien, consulté en lecture seule et proposé dans le catalogue public sous licence Creative Commons.
- **Travailler avec les outils déjà utilisés** : les imports et exports permettent de poursuivre le travail dans un tableur, un traitement de texte, une plateforme web ou un autre outil compatible.
- **Choisir un référentiel scolaire cohérent** : le niveau dépend du système sélectionné. Le catalogue couvre la France, la Suisse, les États-Unis, les communautés belge française, flamande et germanophone, l’Angleterre, le pays de Galles, l’Écosse, l’Irlande du Nord les Écoles européennes et le Baccalauréat international (IB), ainsi que la classification internationale ISCED 2011.
- **Concevoir avec une IA sans perdre la structure pédagogique** : une Skill réutilisable, une bibliothèque de prompts et le CLI `scenarisation` accompagnent la création, la validation et la publication des scénarios.

## Documentation

- [Aide complète](./help.php) : prise en main, conception, sauvegarde, partage et import/export
- [Créer avec une IA, la Skill et le CLI](./help.php#cli) : trois manières d'utiliser un agent pour produire un scénario structuré
- [Skill Scenarisation](./skills/scenarisation/SKILL.md) : méthode réutilisable par un agent compatible
- [Skill de développement du site](./skills/scenarisation-site/SKILL.md) : conventions techniques et direction visuelle pour maintenir l’application
- [Modèles de scénarios](./models.php) : bibliothèque de scénarios génériques préremplis
- [Bibliothèque de prompts](./prompts.php) : prompts prêts à copier pour analyser, adapter ou prolonger un scénario
- [Comprendre le learning design](./learning-design.php) : principes et cadre pédagogique

## Recherche sur le site

La loupe de la barre de navigation, ainsi que le raccourci `⌘K` sur macOS ou `Ctrl+K` sur les autres systèmes, ouvrent une recherche locale propulsée par [Pagefind](https://pagefind.app/). Aucun contenu de recherche n'est envoyé à un service tiers.

L'index couvre les pages publiques de contenu (aide, modèles, prompts, référentiels, cadre pédagogique et pages d'information). Les comptes, l'administration, les designs privés et les pages de consultation dynamiques ne sont pas indexés.

Après une modification du contenu, régénérez l'index :

```bash
./build-search-index.sh
```

Le script ouvre temporairement les pages en français puis en anglais, et crée deux index indépendants dans `pagefind/fr/` et `pagefind/en/`. Il n'existe toujours qu'une seule page source à maintenir : les rendus bilingues ne servent qu'à la construction de l'index et sont supprimés ensuite. Quand l'utilisateur change la langue de l'interface, la recherche bascule automatiquement vers l'index correspondant.

Google Chrome ou Chromium, Node.js 22 (ou une version ultérieure) et PHP doivent être installés sur la machine qui construit l'index. Le dossier `pagefind/` généré est versionné afin que les deux index soient automatiquement déployés avec le reste du site. Après une régénération, ses modifications doivent donc être incluses dans le prochain déploiement.

## Concevoir avec une IA

Scenarisation propose trois niveaux d'intégration, selon le besoin :

1. **Les prompts** servent à enrichir ponctuellement un scénario : différenciation, conception universelle de l'apprentissage, modèle SAMR, charge de travail ou création d'une fiche destinée aux élèves.
2. **La Skill Scenarisation** donne à un agent une méthode de travail complète : recueillir les choix pédagogiques, construire le scénario avec le CLI, le valider, puis préparer sa publication.
3. **Le CLI `scenarisation`** permet de créer et modifier un design depuis le terminal, de contrôler sa structure, de transmettre le travail à Codex et de le publier ou le mettre à jour sur le site.

La Skill ne se contente donc pas de générer du texte libre : elle guide l'agent vers le format attendu par l'application et impose une validation avant publication.

### Installer la Skill pour Claude Code ou Codex

Depuis la racine de votre projet, une seule commande installe ou actualise la Skill pour Claude Code et Codex ainsi que le CLI, puis vérifie leur compatibilité :

```bash
curl -fsSL https://raw.githubusercontent.com/YannHY/learning-designer/main/install-skill.sh | sh
```

Utilisez ensuite `/scenarisation` dans Claude Code ou `$scenarisation` dans Codex.

### Installer le CLI

```bash
curl -fsSL https://raw.githubusercontent.com/YannHY/learning-designer/main/install.sh | sh
scenarisation status
```

Quelques commandes utiles :

```bash
scenarisation list school-systems
scenarisation list school-levels --system france
scenarisation list activity-options
scenarisation init mon-scenario.json --school-system france --school-level quatrieme
scenarisation add-moment mon-scenario.json --title "Découvrir"
scenarisation validate mon-scenario.json --strict-pedagogy
scenarisation handoff mon-scenario.json
scenarisation login
scenarisation publish mon-scenario.json
```

Le CLI enregistre des identifiants stables dans `schoolSystem` et `schoolLevel`, accepte aussi les principaux libellés et alias français ou anglais, et refuse une association incohérente entre un système et un niveau. Pour chaque activité créée, il exige un choix explicite de groupe, d’enseignement, de rythme, de mode de formation, d’évaluation et de niveau AIAS.

Les instructions d'installation et d'utilisation de la Skill sont détaillées dans la section [Créer avec l'IA](./help.php#cli) de l'aide.

## Modèles de scénarios

La page [Modèles de scénarios](./models.php) regroupe des structures pour entrer dans un apprentissage, comprendre, argumenter, s'entraîner, produire, évaluer, organiser une séquence ou travailler avec l'IA.

Chaque modèle contient déjà des moments, des activités, des durées, des modalités, des acquis et des consignes. Il peut être chargé directement dans le concepteur ou téléchargé au format JSON. Les jalons entre crochets, comme `[MATIÈRE]` ou `[NOTION 1]`, indiquent les éléments à contextualiser.

## Importer, exporter et publier

Scenarisation accepte les scénarios issus de son ancien format LDJ ainsi que des fichiers JSON, CSV, Excel et Markdown.

Un scénario peut être exporté en Markdown, HTML, JSON, Excel ou Word. L'export peut produire une version enseignant ou élève et se limiter aux moments sélectionnés. La publication en ligne crée une page de consultation partageable ; les auteurs qui le souhaitent peuvent également rendre leur design visible dans le catalogue public.

## Installation locale

Le projet ne nécessite pas d'étape de compilation. PHP avec PDO SQLite suffit pour le lancer. L’export de tous les scénarios depuis le profil nécessite aussi l’extension PHP `zip` et fournit une archive contenant un fichier JSON par scénario, réimportable dans l’éditeur après extraction :

```bash
git clone https://github.com/YannHY/learning-designer.git
cd learning-designer
php -S localhost:8000
```

Ouvrez ensuite [http://localhost:8000](http://localhost:8000). La base SQLite locale et ses tables sont créées automatiquement. Pour activer les comptes, ouvrez `setup_admin.php` et créez le premier administrateur.

### Configuration d'un déploiement

La configuration peut être fournie par variables d'environnement ou à partir du gabarit [app-config.php](./app-config.php). Les principales variables reconnues sont :

- `APP_DB_DSN`, `APP_DB_USER` et `APP_DB_PASS` pour utiliser MySQL ou une autre base PDO ;
- `APP_DB_SQLITE_PATH` pour choisir l'emplacement de la base SQLite ;
- `APP_BASE_URL` pour définir l'URL publique de l'application ;
- `APP_FEEDBACK_HASH_KEY` pour fournir une clé secrète dédiée à l’empreinte antispam des retours utilisateurs ;
- `APP_MAIL_FROM` et `APP_MAIL_FROM_NAME` pour l'expéditeur des emails de vérification et de réinitialisation du mot de passe. L'envoi utilise la fonction `mail()` de PHP, qui doit être activée sur l'hébergement.

Conservez les secrets dans un fichier local non versionné, par exemple `learning-design-secret.php`, ou dans des variables d'environnement.

## Repères dans le dépôt

- [designer.php](./designer.php) et [interface.js](./js/interface.js) : concepteur de scénarios ;
- [models.php](./models.php) : bibliothèque et API JSON des modèles ;
- [prompts.php](./prompts.php) : bibliothèque de prompts pédagogiques ;
- [share.php](./share.php) et [view.php](./view.php) : catalogue public et consultation des designs publiés ;
- [skills/scenarisation](./skills/scenarisation) : Skill et configuration de l'agent ;
- [skills/scenarisation-site](./skills/scenarisation-site) : Skill de développement et de maintenance du site ;
- [bin/scenarisation](./bin/scenarisation) : CLI de création, de validation et de publication ;
- [lib/bootstrap.php](./lib/bootstrap.php) : configuration, base de données et fonctions PHP communes.

### Organisation du JavaScript du concepteur

`js/interface.js` conserve l'état courant, la sauvegarde locale, les cartes et les interactions de l'éditeur. Il assemble les modules de `js/editor/` en leur transmettant leurs dépendances :

- `config.js` : options pédagogiques, traductions FR/EN et normalisation des libellés ;
- `competencies.js` : référentiels, recherche et présentation des compétences ;
- `exports.js` : exports Markdown, HTML, Word et Excel ;
- `imports.js` : lecture des fichiers CSV, Markdown et anciens LDJ ;
- `analysis.js` : répartitions du temps, graphiques et alertes ;
- `fields.js` : champs extensibles, aperçu Markdown et raccourcis clavier.

Ces scripts classiques partagent uniquement l'espace de noms `window.LearningDesignerModules`. Les modules qui consultent le document reçoivent une fonction `getState` pour toujours lire le document courant après un import ou un changement de design. L'API `window.learningDesignerApp` utilisée par le compte reste inchangée.

L'ordre de chargement est explicite dans `designer.php` : sources des compétences, configuration, autres modules, `interface.js`, puis `account-ui.js`. Aucune compilation n'est nécessaire. Pour déployer ce découpage, transférez **tout le dossier `js/editor/`**, `js/interface.js` et `designer.php` ensemble ; les nouveaux modules sont nécessaires au fonctionnement du concepteur.

## Crédits et licence

Développé par Yann Houry sur la base du travail de François Jourde et inspiré de l'UCL Learning Designer. Le projet est distribué sous licence [CC BY-SA](./LICENSE).

## Vérifications de développement

Les tests utilisent des données isolées ; ils ne modifient pas la base du site :

```bash
node --test tests/*.test.cjs
php tests/server-state.test.php
```

Le dossier `tests/` reste versionné mais n’est pas nécessaire sur le serveur web.

Les sauvegardes web utilisent une révision entière pour détecter les écritures concurrentes. La colonne `learning_designs.revision` est ajoutée automatiquement lors du passage au schéma 5, sans modifier le contenu des designs. Déployez les fichiers PHP et JavaScript correspondants ensemble. Un ancien onglet sans révision sera bloqué comme un conflit et pourra conserver son brouillon dans une copie.

La configuration suit cet ordre de priorité : variables d’environnement, paramètres du serveur, fichiers locaux ou secrets, puis valeurs par défaut de `app-config.php`.

## Renommage en Scenarisation

Le CLI s’utilise désormais avec `scenarisation`, et la skill avec `$scenarisation` dans Codex ou `/scenarisation` dans Claude Code. Relancez les installateurs ci-dessus pour obtenir ces nouvelles commandes. Les URL du dépôt et du site restent inchangées. La configuration de connexion existante dans `~/.learning-designer/config.json` est conservée ; `SCENARISATION_CONFIG` permet de choisir un autre fichier. Les variables d’installation `SCENARISATION_*` acceptent aussi leurs anciens équivalents `LEARNING_*`.
