# API native Scénarisation — version 1

Ce contrat est implémenté côté client dans `SiteSyncAPI.swift`, `SiteOAuth.swift` et `SiteAccountAuth.swift`. Les routes PHP correspondantes ont été déployées sur le site. Les vérifications anonymes de production confirment des réponses JSON pour les accès sans jeton ; les tests de l’app utilisent un transport simulé. Une connexion réelle avec consentement de l’utilisateur reste nécessaire pour valider le parcours complet en production.

## Transport et authentification

Base : `https://scenarisation.eu/api/v1/`.

Les requêtes portent `Authorization: Bearer <access_token>` et `Accept: application/json`. Les réponses doivent avoir le type `application/json`, y compris les erreurs. Le client refuse les redirections, n’utilise pas les cookies du navigateur et limite le temps d’attente. Le gestionnaire de session fournit le jeton à chaque requête et le renouvelle si sa durée de validité restante est inférieure ou égale à une minute.

La connexion utilise Authorization Code avec PKCE S256 et le client public `scenarisation-mac`, sans secret client. Les permissions sont `designs:read designs:write`, distinctes de la publication. L’identité utilisateur provient du jeton validé côté serveur, jamais d’un identifiant de propriétaire fourni par le client. Les protections de session du site web restent applicables à ses routes existantes.

| Route à la racine du site | Usage |
| --- | --- |
| `GET oauth-authorize.php` | Connexion et consentement dans le navigateur ; paramètres `client_id`, `response_type=code`, `redirect_uri`, `code_challenge`, `code_challenge_method=S256`, `state`, `scope`. |
| `POST oauth-token.php` | Formulaire `grant_type=authorization_code`, `client_id`, `code`, `code_verifier`, `redirect_uri`, ou `grant_type=refresh_token`, `client_id`, `refresh_token`. |
| `POST oauth-revoke.php` | Formulaire `client_id`, `token` ; révocation de la session avec le jeton de renouvellement. |

Le retour est `http://127.0.0.1:<port>/auth/callback`. L’app vérifie l’adresse, le port, le chemin, l’absence de paramètres en double, `state`, l’émetteur exact `iss=https://scenarisation.eu` et l’expiration de la tentative avant tout échange. Le code reçu est à usage unique. Le mot de passe reste dans le navigateur.

Les réponses contiennent `access_token`, `refresh_token`, `token_type=Bearer`, `expires_in` (au plus 900 secondes), `refresh_expires_in` (au plus 30 jours) et `scope`. Les deux jetons sont renouvelés ensemble ; une seule requête de renouvellement est effectuée même si plusieurs opérations attendent. Le service du Trousseau `eu.scenarisation.mac.site` conserve la paire et l’identité du compte. Les préférences et les archives de scénarios ne contiennent aucun jeton.

Une rotation dont la sauvegarde au Trousseau échoue reste disponible en mémoire pour une nouvelle tentative de sauvegarde ; un marqueur sans secret interdit de rejouer l’ancien jeton après redémarrage. La déconnexion désactive d’abord les jetons locaux puis tente la révocation, y compris lorsqu’un renouvellement est en cours. Elle conserve l’identité du compte et les copies locales.

## Routes utilisées par le client

| Méthode et route | Réponse attendue |
| --- | --- |
| `GET me.php` | `{ "success": true, "user": { "id": 4, "username": "Nom", "email": "adresse@example.org" } }` |
| `GET list_designs.php` | `{ "success": true, "items": [résumé, …] }` |
| `GET get_design.php?design_id=7` | `{ "success": true, "design": document_distant }` |
| `POST save_design.php` | `{ "success": true, "design": résumé }` |
| `POST delete_design.php` | `{ "success": true, "deleted": { "id": 7 } }` |

Un résumé contient :

```json
{
  "id": 7,
  "title": "Mon scénario",
  "revision": 3,
  "updatedAt": "2026-11-16T12:00:00Z"
}
```

Un document distant ajoute `document` à ces quatre champs. `document` est le JSON pédagogique complet (`meta`, `sessions`, champs non exposés, etc.). Ses identifiants de moments et d’activités sont conservés. Les identifiants des scénarios et les révisions sont des entiers strictement positifs. Les dates restent des chaînes de présentation ; le client ne les utilise pas pour décider quelle version écraser.

La liste de cette première version est complète, sans pagination ni curseur. Elle inclut la révision. Une synchronisation individuelle relit le document avant tout envoi. L’app n’interprète pas l’absence dans cette liste comme une autorisation de suppression locale.

## Enregistrement

Le corps d’une mise à jour est :

```json
{
  "client_uuid": "D5A85CD6-2636-4F00-860D-71DCB61CE479",
  "design_id": 7,
  "expected_revision": 3,
  "title": "Mon scénario",
  "document": { "meta": { "name": "Mon scénario" }, "sessions": [] }
}
```

Pour une création, `design_id` et `expected_revision` sont absents. `client_uuid` reste identique lors des tentatives suivantes, y compris après redémarrage de l’app.

Le serveur applique les règles suivantes :

1. Vérifier l’accès au compte et la structure du document.
2. Pour une création, rendre `(owner_user_id, client_uuid)` unique et traiter les requêtes simultanées de façon atomique. Un nouvel essai retourne le résumé du document déjà créé, même si son contenu a changé depuis. L’app relit ce document avant de confirmer la synchronisation.
3. Pour une mise à jour, exiger `expected_revision` et effectuer une écriture conditionnelle atomique sur cette révision. Une réussite incrémente la révision et renvoie celle de cette écriture précise. Aucun remplacement de la révision attendue par la révision courante ne doit avoir lieu.
4. Conserver les données non exposées, les droits de provenance et la visibilité existante. Une création est privée. La synchronisation n’active pas la publication. Les scénarios déjà publiés continuent de suivre la politique du site concernant la mise à jour des pages partagées.

Le champ `_scenarisationMac` est réservé aux fichiers Mac et n’est jamais envoyé par le client.

## Erreurs et reprise

| Code HTTP | Comportement du client |
| --- | --- |
| `401` | Conserver les changements locaux et demander une nouvelle connexion si le jeton utilisé est toujours celui de la session courante. Une ancienne réponse ne déconnecte pas un compte plus récent. |
| `403` | Signaler le refus d’accès à cette ressource et conserver la session et les changements locaux. |
| `404` sur un scénario | Retirer la copie de ce Mac si elle ne contient aucune modification non envoyée ; sinon la marquer comme indisponible côté site et proposer une copie indépendante. Jamais de recréation automatique. |
| `409` | Relire la version distante, fusionner champ par champ et renvoyer (trois essais au plus) ; le document reste ensuite en attente. Sans version commune connue, conserver les deux versions et proposer leur examen. |
| `400`, `422` | Afficher `error_description` du site lorsqu’il est présent (adresse inconnue, invitation impossible…). |
| Autre erreur / réponse HTML / JSON invalide / coupure | Conserver le suivi local et permettre une nouvelle tentative manuelle. |

Pour éviter de réutiliser un UUID de création après suppression, le serveur conserve une trace de cette association et répond `404` plutôt que recréer le document. Les comptes et les scénarios existants gardent leurs identifiants.

Une mise à jour dont la réponse est perdue est récupérée à la lecture suivante : si le contenu distant correspond au contenu local, l’app reconnaît la nouvelle révision sans renvoyer le document. Sinon, les deux versions sont fusionnées à partir de la dernière version confirmée.

## Suppression

Le corps de `POST delete_design.php` est `{ "design_id": 7, "expected_revision": 3 }`, avec la dernière révision synchronisée. Le serveur ne supprime que si le scénario est encore à cette révision ; sinon il répond `409` et l’app conserve sa copie en invitant à synchroniser. La suppression enregistre la même trace que celles du site, si bien qu’une création réessayée répond `404`. Une nouvelle demande pour un scénario déjà supprimé réussit.

Comme une route absente répond aussi `404`, l’app relit le scénario après un `404` : elle n’oublie sa copie locale que si cette lecture confirme la disparition. La copie locale n’est retirée qu’après la réponse du site ; hors connexion, rien n’est supprimé.

À chaque lecture de `list_designs.php` (ouverture, retour dans l’app, toutes les deux minutes, **Actualiser**), l’app relit avec `get_design.php` chaque scénario suivi qui n’apparaît plus dans la liste. Seul un `404` confirme la suppression : une liste incomplète, une coupure ou une autre erreur laissent la copie intacte. Une copie sans modification non envoyée est alors retirée, sa fenêtre fermée et son fichier du dossier des scénarios placé dans la Corbeille.

## État local et limites de cette étape

Les archives versionnées contiennent les documents locaux, la dernière version confirmée, la révision, l’identifiant distant et les conflits éventuels. Elles sont écrites atomiquement avant un envoi et sont séparées par serveur/compte ; elles ne contiennent aucun jeton. Une archive illisible provoque une erreur et n’est pas remplacée silencieusement.

Un document lié au compte se synchronise automatiquement trois secondes après la dernière modification et à son ouverture ; un envoi en cours n’est jamais interrompu et une modification reçue pendant celui-ci est envoyée ensuite. Les erreurs laissent le document en attente. Une copie locale téléchargée peut être ouverte hors ligne. Les modifications intervenues pendant une requête restent en attente après la confirmation de la version envoyée. Deux versions modifiées de part et d’autre sont fusionnées (voir ci-dessous). Un conflit sans version commune crée une copie Mac avec un nouvel UUID ; son envoi ultérieur crée un scénario distinct. La publication et les pièces jointes ne sont pas proposées dans cette version.

## Collaboration

Le propriétaire d’un scénario peut le partager avec d’autres comptes vérifiés, avec l’un de deux droits : « Peut modifier » (`editor`), qui lit et enregistre avec les mêmes révisions conditionnelles, ou « Peut consulter » (`viewer`), qui lit seulement. Le site étant sur un hébergement mutualisé, aucune connexion permanente n’est utilisée : chaque fenêtre ouverte appelle `presence.php` toutes les deux secondes pour un scénario partagé (douze secondes sinon), et la révision renvoyée déclenche la synchronisation.

| Méthode et route | Permission | Réponse |
| --- | --- | --- |
| `GET collaborators.php?design_id=7` | `designs:read` | `{ "success": true, "sharing": partage }` |
| `POST share_design.php` `{ "design_id": 7, "email": "…", "role": "editor" \| "viewer" }` | `designs:write`, propriétaire | `{ "success": true, "sharing": partage }` |
| `POST collaborator_role.php` `{ "design_id": 7, "user_id": 9, "role": "editor" \| "viewer" }` | `designs:write`, propriétaire | `{ "success": true, "sharing": partage }` |
| `POST unshare_design.php` `{ "design_id": 7, "user_id": 9 }` | `designs:write` | `{ "success": true, "sharing": partage ou null }` |
| `POST presence.php` `{ "design_id": 7, "client_id": "uuid", "focus": "activity:<id>:instructions", "leave": true? }` | `designs:read` | `{ "success": true, "presence": { "revision": 12, "shared": true, "participants": [ { "userId": 9, "username": "Nom", "clientId": "uuid", "focus": "…", "updatedAt": 1763650000 } ] } }` |

Un partage contient `designId`, `canManage` (l’appelant est propriétaire) et `members` : `{ "id", "username", "role": "owner" | "editor" | "viewer", "email" }`, l’adresse n’étant fournie qu’au propriétaire. La liste des scénarios et `get_design.php` ajoutent `role`, `owner` (nom du propriétaire) et `shared`. Sans `role`, une invitation donne le droit de modifier ; une invitation répétée change seulement les droits précisés. Un enseignant `editor` peut lire et enregistrer ; un enseignant `viewer` reçoit `403` à l’enregistrement : l’app n’envoie alors rien, sa copie suit le site (seuls ses réglages d’affichage restent locaux) et la fenêtre passe en consultation seule. La suppression répond `403` à tout enseignant invité et il quitte le partage avec `unshare_design.php` sur son propre identifiant (`sharing: null`). Les participants excluent la fenêtre appelante et les signaux de plus de trente secondes.

`focus` vaut `activity:<id>:<champ>`, `moment:<id>:<champ>` ou `scenario::<champ>` : le champ ouvert dans l’éditeur agrandi, sinon la dernière modification de moins de vingt secondes. Il ne contient aucun texte du scénario.

La fusion se fait dans l’app, à partir de la dernière version confirmée (`base`), de la copie locale et du document du site :

- un champ modifié d’un seul côté est repris ; les objets sont parcourus récursivement et les champs inconnus conservés ;
- moments et activités sont suivis par identifiant, l’activité gardant son moment parent comme un champ : un déplacement d’un côté et une modification de l’autre se combinent ;
- un élément supprimé d’un côté et modifié de l’autre est conservé, avec son moment si nécessaire ; l’ordre suit le côté qui a réordonné, et les ajouts s’insèrent après leur prédécesseur ;
- les listes de compétences appliquent les ajouts et retraits des deux côtés ;
- un même texte est fusionné par ligne puis par mot ; si les deux modifications se recouvrent encore, la version locale l’emporte ;
- `meta.boardLayout`, `intentionsCollapsed`, `allNotesExpanded`, `topPanelCollapsed` et `notesExpanded` restent propres à chaque appareil ; une différence limitée à ces réglages n’est pas renvoyée.

Une fenêtre ouverte reçoit la version fusionnée et la fusionne à son tour avec ce qui a été saisi pendant la requête. Annuler applique l’inverse de sa propre modification par la même fusion, sans retirer les modifications reçues depuis.

Les fichiers de travail de la bibliothèque ont un chemin stable pour réutiliser une fenêtre déjà ouverte. Les documents Finder restent des fichiers JSON éditables ; leur identité de suivi est une métadonnée locale, distincte des identifiants de moments et d’activités et absente des exports.

## Vérification

`swift test --filter SiteSync` vérifie le client HTTP et les règles de synchronisation avec des réponses interceptées et des services isolés. `swift test --filter SiteOAuth` vérifie le protocole de connexion, les rotations simultanées, les annulations, les déconnexions, les erreurs de stockage et l’isolation des comptes. Le navigateur et le Trousseau sont simulés. Aucun test ne contacte le site, ne se connecte à un compte réel ou ne publie de scénario.
