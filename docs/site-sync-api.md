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
| `404` sur un scénario | Marquer la copie comme indisponible côté site ; proposer une copie indépendante, sans recréation automatique. |
| `409` | Relire la version distante ; conserver les deux versions et proposer leur examen si elles divergent. |
| Autre erreur / réponse HTML / JSON invalide / coupure | Conserver le suivi local et permettre une nouvelle tentative manuelle. |

Pour éviter de réutiliser un UUID de création après suppression, le serveur conserve une trace de cette association et répond `404` plutôt que recréer le document. Les comptes et les scénarios existants gardent leurs identifiants.

Une mise à jour dont la réponse est perdue est récupérée à la lecture suivante : si le contenu distant correspond au contenu local, l’app reconnaît la nouvelle révision sans renvoyer le document. Si les deux versions ont divergé, elle conserve les deux copies.

## Suppression

Le corps de `POST delete_design.php` est `{ "design_id": 7, "expected_revision": 3 }`, avec la dernière révision synchronisée. Le serveur ne supprime que si le scénario est encore à cette révision ; sinon il répond `409` et l’app conserve sa copie en invitant à synchroniser. La suppression enregistre la même trace que celles du site, si bien qu’une création réessayée répond `404`. Une nouvelle demande pour un scénario déjà supprimé réussit.

Comme une route absente répond aussi `404`, l’app relit le scénario après un `404` : elle n’oublie sa copie locale que si cette lecture confirme la disparition. La copie locale n’est retirée qu’après la réponse du site ; hors connexion, rien n’est supprimé.

## État local et limites de cette étape

Les archives versionnées contiennent les documents locaux, la dernière version confirmée, la révision, l’identifiant distant et les conflits éventuels. Elles sont écrites atomiquement avant un envoi et sont séparées par serveur/compte ; elles ne contiennent aucun jeton. Une archive illisible provoque une erreur et n’est pas remplacée silencieusement.

Un document lié au compte se synchronise automatiquement trois secondes après la dernière modification et à son ouverture ; un envoi en cours n’est jamais interrompu et une modification reçue pendant celui-ci est envoyée ensuite. Les erreurs laissent le document en attente. Une copie locale téléchargée peut être ouverte hors ligne. Les modifications intervenues pendant une requête restent en attente après la confirmation de la version envoyée. Un conflit crée une copie Mac avec un nouvel UUID ; son envoi ultérieur crée un scénario distinct. La fusion automatique, la publication, les pièces jointes et les curseurs de changements ne sont pas proposés dans cette version.

Les fichiers de travail de la bibliothèque ont un chemin stable pour réutiliser une fenêtre déjà ouverte. Les documents Finder restent des fichiers JSON éditables ; leur identité de suivi est une métadonnée locale, distincte des identifiants de moments et d’activités et absente des exports.

## Vérification

`swift test --filter SiteSync` vérifie le client HTTP et les règles de synchronisation avec des réponses interceptées et des services isolés. `swift test --filter SiteOAuth` vérifie le protocole de connexion, les rotations simultanées, les annulations, les déconnexions, les erreurs de stockage et l’isolation des comptes. Le navigateur et le Trousseau sont simulés. Aucun test ne contacte le site, ne se connecte à un compte réel ou ne publie de scénario.
