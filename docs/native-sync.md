# Synchronisation de l’application Mac

Le site fournit une API JSON privée pour les scénarios et un parcours OAuth pour le client public `scenarisation-mac`. Les comptes et les scénarios existants gardent leurs identifiants. Cette implémentation ne déploie pas le site et n’active pas à elle seule le bouton de connexion de l’app Mac.

## Parcours de connexion

L’app génère un vérificateur PKCE, son empreinte S256 et un `state` aléatoire, puis démarre un écouteur limité à `127.0.0.1` sur un port disponible. Elle ouvre le navigateur sur :

```text
GET /oauth-authorize.php
    ?client_id=scenarisation-mac
    &response_type=code
    &redirect_uri=http%3A%2F%2F127.0.0.1%3A54123%2Fauth%2Fcallback
    &code_challenge_method=S256
    &code_challenge=EMPREINTE_BASE64URL
    &state=VALEUR_ALEATOIRE
    &scope=designs%3Aread%20designs%3Awrite
```

Le chemin de retour est exactement `/auth/callback` et l’hôte exactement `127.0.0.1`. Le port doit être explicite et compris entre 1 et 65535. Les retours HTTPS tiers, les chemins différents, les paramètres ou fragments de retour et les clients inconnus sont refusés sans redirection.

Une demande non connectée passe par la connexion web existante, avec un retour lié à la session du navigateur. Le consentement affiche le compte et les permissions. Son formulaire vérifie l’origine, un jeton CSRF et le compte qui a vu la demande. Un refus renvoie `error=access_denied`. Une autorisation renvoie un code à usage unique, le `state` et `iss` (l’URL canonique du site).

Le formulaire termine sa navigation sur une confirmation du même site (`oauth-authorize.php?complete=…`), puis `js/native-consent.js` ouvre le retour local. Le lien **Revenir à l’app** reste disponible si la navigation automatique est bloquée. Cette étape évite de faire dépendre le retour local d’une redirection externe de formulaire. Le retour est lié à la session du navigateur et au compte ayant donné son accord, valable cinq minutes, non mis en cache et envoyé sans référent. Un second envoi du même consentement, avec le même compte, la même décision et un jeton CSRF valide, retrouve cette confirmation sans créer de nouveau code.

L’app doit vérifier `state`, `iss` et le retour exact avant d’échanger le code. Le code est valable cinq minutes. Son échange utilise un formulaire `application/x-www-form-urlencoded` :

```text
POST /oauth-token.php
grant_type=authorization_code
client_id=scenarisation-mac
code=CODE_RECU
code_verifier=VERIFICATEUR_ORIGINAL
redirect_uri=http://127.0.0.1:54123/auth/callback
```

La réponse contient `access_token`, `refresh_token`, `token_type=Bearer`, `expires_in=900`, `refresh_expires_in` et `scope`. Les secrets sont opaques et seule leur empreinte SHA-256 est conservée en base. Le client est public et ne possède pas de secret client.

Le renouvellement est un POST au même point d’accès avec `grant_type=refresh_token`, `client_id` et `refresh_token`. Il remplace les deux jetons. L’app doit sérialiser les renouvellements et sauvegarder les deux nouveaux jetons ensemble dans le Trousseau. La réutilisation d’un ancien jeton de renouvellement révoque toute sa session. Les permissions peuvent être réduites, jamais élargies par renouvellement. La session expire au plus tard trente jours après sa création.

La révocation utilise `POST /oauth-revoke.php`, avec `client_id` et `token` dans un formulaire. Le jeton peut être un accès ou un renouvellement ; la révocation invalide la session entière. Un jeton inconnu produit la même réponse de réussite.

## API des scénarios

Toutes les requêtes utilisent `Authorization: Bearer <access_token>`. Les cookies web et les jetons CLI ne donnent aucun accès à ces routes. Les jetons natifs ne permettent pas de publier via les routes web ou CLI.

| Route | Permission | Réponse |
| --- | --- | --- |
| `GET /api/v1/me.php` | `designs:read` | `{ "success": true, "user": { "id": 4, "username": "Nom", "email": "adresse@example.org" } }` |
| `GET /api/v1/list_designs.php` | `designs:read` | `{ "success": true, "items": [résumé, …], "deleted": [suppression, …] }` |
| `GET /api/v1/get_design.php?design_id=7` | `designs:read` | `{ "success": true, "design": document_distant }` |
| `POST /api/v1/save_design.php` | `designs:write` | `{ "success": true, "design": résumé }` |
| `POST /api/v1/delete_design.php` | `designs:write` | `{ "success": true, "deleted": { "id": 7 } }` |

Un résumé contient `id`, `title`, `revision` et `updatedAt`. Le document distant ajoute `document` (JSON pédagogique complet) et `provenance` (les informations d’origine vérifiées par le serveur). Les identifiants et les révisions sont des entiers strictement positifs. Les dates servent à l’affichage ; les conflits reposent sur les révisions.

La liste est complète, sans pagination ni curseur. Le champ supplémentaire `deleted` contient les identifiants supprimés et `deletedAt` (secondes Unix), séparés par compte. Le client Mac actuel ignore ce champ et détecte les suppressions lors d’une lecture individuelle.

Un enregistrement utilise un corps `application/json` :

```json
{
  "client_uuid": "d5a85cd6-2636-4f00-860d-71dcb61ce479",
  "design_id": 7,
  "expected_revision": 3,
  "title": "Mon scénario",
  "document": {
    "meta": { "name": "Mon scénario" },
    "sessions": []
  }
}
```

Pour une création, omettre `design_id` et `expected_revision`. Le couple utilisateur/UUID est unique ; deux requêtes simultanées ou une nouvelle tentative après une coupure renvoient le même scénario. Une suppression conserve cette association et une nouvelle tentative retourne 404. Le client doit relire le contenu après une création réessayée avant de la considérer comme synchronisée.

Pour une mise à jour, la révision attendue est obligatoire. L’écriture conditionnelle réutilise le mécanisme des sauvegardes web et CLI. Une réussite incrémente la révision et retourne celle de cette écriture précise. Une révision dépassée renvoie 409 ; aucune mise à jour forcée n’est effectuée.

Les nouveaux scénarios sont privés. Une mise à jour conserve la provenance, la licence et la visibilité existantes. Comme les sauvegardes web, elle peut mettre à jour une page déjà publiée. Le champ local `_scenarisationMac` est retiré ; les autres champs non exposés, dont les objets JSON vides, sont conservés.

Les documents sont limités à 20 Mo et doivent contenir `meta`, `sessions`, des identifiants uniques de moments/activités et des durées d’activités valides. Un document existant incompatible doit être vérifié dans le site avant sa synchronisation.

| Code HTTP | Sens |
| --- | --- |
| 400 | Demande OAuth ou JSON invalide |
| 401 | Session absente, expirée ou révoquée |
| 403 | Permission insuffisante ou consentement invalide |
| 404 | Scénario absent ou appartenant à un autre utilisateur |
| 405 | Méthode HTTP non autorisée |
| 409 | Conflit de révision |
| 413 / 415 / 422 | Taille, type de contenu ou structure invalide |
| 500 | Erreur interne, sans détails sensibles dans la réponse |

Les réponses portent `Cache-Control: no-store`. Les erreurs contiennent `success=false`, `error` et `error_description`. Aucune route native n’active CORS ou ne réutilise les cookies du navigateur.

## Base de données et accès

Le schéma 10 a ajouté cinq tables :

- `native_app_sessions` : compte, permissions, empreinte d’accès, expirations et révocation ;
- `native_oauth_codes` : code à usage unique, PKCE et retour exact ;
- `native_refresh_tokens` : empreintes et historique de rotation jusqu’à l’expiration de la session ;
- `native_creations` : correspondance durable entre UUID client et scénario ;
- `native_design_deletions` : suppressions effectuées depuis l’éditeur, la liste des scénarios et l’app Mac.

Le schéma 11 ajoute les tables de la collaboration bêta, `design_collaborators` et `design_presence` (voir « Collaboration (bêta) » ci-dessous).

La migration est additive et idempotente. Elle est intégrée à `ensure_app_schema()` et prévue pour SQLite et MySQL/InnoDB. Les tests automatisés locaux utilisent SQLite ; la variante MySQL doit être vérifiée sur une base de test correspondant à celle de l’hébergement.

Le profil affiche les accès encore valables et permet de révoquer chaque connexion avec vérification CSRF. Les changements de mot de passe, les réinitialisations, les changements d’email et la suspension d’un compte invalident aussi les accès natifs et les codes en attente. Une réactivation n’accorde pas de nouveau les anciennes connexions.

## Déploiement

1. Sauvegarder la base de production et les fichiers de configuration.
2. Tester la branche sur un environnement distinct, avec une base de test et la même version de PHP/PDO que l’hébergement.
3. Déployer ensemble les fichiers PHP modifiés, les nouvelles routes, `lib/native-*.php`, `css/native-app.css` et `.htaccess`.
4. Vérifier `APP_BASE_URL` dans la configuration privée ou les variables d’environnement. Il doit contenir l’URL canonique réelle, par exemple `https://scenarisation.eu`, sans le chemin `/api/v1`. Cette valeur sert d’émetteur OAuth.
5. Depuis la racine du site, exécuter `php -r 'require "lib/bootstrap.php"; app_db(); echo APP_SCHEMA_VERSION, PHP_EOL;'` avec la configuration du serveur. Le résultat attendu est 11. Le premier accès web peut également effectuer cette migration.
6. Vérifier le passage de l’en-tête Authorization à PHP. La règle Apache fournie transmet cet en-tête ; sous Nginx/FastCGI, vérifier le paramètre `HTTP_AUTHORIZATION` dans la configuration du serveur.
7. Vérifier les routes OAuth et API en HTTPS, puis le parcours de consentement avec un compte de test. Le serveur PHP local sur 127.0.0.1 accepte HTTP pour les tests ; l’hébergement public doit utiliser HTTPS.
8. Raccorder et tester le gestionnaire OAuth de l’app Mac avant d’y activer la connexion réelle.

Ne pas recopier la base ou les secrets de cette copie iCloud sur le serveur pour appliquer une mise à jour du code. Aucun déploiement de production n’est effectué par ces tests.

Pour limiter les données techniques expirées, une maintenance peut supprimer les lignes de `native_oauth_codes` expirées et les sessions dont `expires_at` est dépassé ; les jetons de renouvellement associés aux sessions sont supprimés en cascade. Conserver l’historique des jetons tant que leur session est valable. Les correspondances `native_creations` et les suppressions doivent rester disponibles pour reconnaître les clients longtemps restés hors ligne.

## Vérifications

```sh
php tests/native-sync.test.php
php tests/native-collaboration.test.php
php tests/native-http.test.php
php tests/native-concurrency.test.php
php tests/server-state.test.php
node --test tests/*.test.cjs
```

Les tests utilisent des bases, sessions et processus temporaires. Le test HTTP parcourt les vraies routes avec un serveur PHP sur 127.0.0.1. Les tests de concurrence lancent deux processus pour vérifier les créations identiques et les consommations uniques des codes et des renouvellements. Ils ne contactent pas le site en production.

Références : [OAuth pour applications natives, RFC 8252](https://www.rfc-editor.org/rfc/rfc8252.html) et [bonnes pratiques de sécurité OAuth, RFC 9700](https://www.rfc-editor.org/rfc/rfc9700.html).

## Collaboration (bêta)

Un propriétaire peut partager un scénario avec d’autres comptes vérifiés de Scénarisation, avec l’un de deux droits : `editor` (peut modifier) ou `viewer` (peut consulter). Les enseignants invités en modification le lisent et l’enregistrent depuis l’app avec les mêmes révisions conditionnelles que le propriétaire : deux écritures simultanées ne s’écrasent jamais, la seconde reçoit 409 et l’app fusionne avant de renvoyer. La présence indique qui a le scénario ouvert et sur quel élément il travaille. Le site web offre la même collaboration (voir « Collaboration depuis le site » ci-dessous) : un scénario partagé depuis l’app s’ouvre sur le site, et inversement.

Toutes les routes ci-dessous utilisent le jeton natif. Un compte sans accès au scénario reçoit 404, comme pour un scénario inexistant.

| Route | Permission | Accès |
| --- | --- | --- |
| `GET /api/v1/collaborators.php?design_id=7` | `designs:read` | propriétaire et enseignants invités |
| `POST /api/v1/share_design.php` | `designs:write` | propriétaire |
| `POST /api/v1/collaborator_role.php` | `designs:write` | propriétaire |
| `POST /api/v1/unshare_design.php` | `designs:write` | propriétaire, ou enseignant invité pour lui-même |
| `POST /api/v1/presence.php` | `designs:read` | propriétaire et enseignants invités |

### Routes existantes

- `list_designs.php` renvoie aussi les scénarios partagés avec le compte. Chaque résumé ajoute `role` (`owner`, `editor` ou `viewer`), `owner` (nom du propriétaire) et `shared` (vrai si au moins un enseignant est invité). La liste `deleted` reste propre au propriétaire.
- `get_design.php` accepte les enseignants invités et ajoute `role`, `owner` et `shared` au document distant.
- `save_design.php` permet à un enseignant invité en modification (`editor`) d’enregistrer une mise à jour avec `expected_revision`. Un enseignant en consultation (`viewer`) reçoit 403 (`forbidden`). Le scénario reste au propriétaire. Une création appartient toujours à son auteur.
- `delete_design.php` reste réservé au propriétaire. Un enseignant invité reçoit 403 (`forbidden`) et peut quitter le partage. Toute suppression, depuis l’app ou le site, retire aussi les invitations et la présence du scénario.

### Membres

`collaborators.php` et `share_design.php` répondent :

```json
{
  "success": true,
  "sharing": {
    "designId": 7,
    "canManage": true,
    "members": [
      { "id": 4, "username": "Nom", "role": "owner", "email": "proprietaire@example.org" },
      { "id": 9, "username": "Autre", "role": "editor", "email": "collegue@example.org" },
      { "id": 12, "username": "Lecteur", "role": "viewer", "email": "lecteur@example.org" }
    ]
  }
}
```

Le propriétaire figure en premier, puis les enseignants invités par ordre alphabétique. `canManage` vaut vrai pour le propriétaire. Les adresses ne sont transmises qu’au propriétaire. Seuls les comptes actifs à l’adresse vérifiée apparaissent et accèdent au scénario : un compte suspendu perd l’accès et le retrouve à sa réactivation.

Le corps de `share_design.php` est `{ "design_id": 7, "email": "collegue@example.org", "role": "viewer" }`. `role` vaut `editor` (par défaut) ou `viewer` ; toute autre valeur renvoie 422. L’adresse est comparée sans tenir compte de la casse. Une adresse inconnue, non vérifiée ou d’un compte suspendu renvoie 422 avec l’erreur `unknown_user`. Une adresse invalide, l’adresse du propriétaire ou un vingt-et-unième enseignant renvoient 422 (`invalid_request`). Une invitation répétée ne change que les droits, s’ils sont précisés.

Le corps de `collaborator_role.php` est `{ "design_id": 7, "user_id": 9, "role": "viewer" }`. Seul le propriétaire change les droits (403 sinon) ; un compte qui n’est pas invité, ou un rôle absent ou inconnu, renvoie 422. La réponse contient le même objet `sharing`. Le changement prend effet à la requête suivante de l’enseignant : son app repasse en consultation ou en modification à la synchronisation suivante.

Le corps de `unshare_design.php` est `{ "design_id": 7, "user_id": 9 }`. Le propriétaire retire n’importe quel enseignant invité, mais pas lui-même (422). Un enseignant invité ne peut retirer que lui-même : il quitte alors le partage et la réponse contient `"sharing": null`. Sinon, elle contient le même objet `sharing` que ci-dessus. Retirer un compte non invité réussit sans effet. Le retrait supprime aussi la présence du compte sur ce scénario.

### Présence

Chaque fenêtre ouverte envoie régulièrement `POST /api/v1/presence.php` :

```json
{ "design_id": 7, "client_id": "1f0e4f6a-3c2b-4d5e-8f70-112233445566", "focus": "activity:a-1:instructions" }
```

`client_id` est un UUID propre à la fenêtre. `focus` vaut `null` ou indique l’élément en cours de modification ; ses caractères de contrôle sont retirés et il est limité à 300 octets. `"leave": true` retire la fenêtre à sa fermeture. La réponse donne la révision courante, pour récupérer aussitôt les modifications des autres, et les participants actifs depuis 30 secondes, sauf la fenêtre appelante :

```json
{
  "success": true,
  "presence": {
    "revision": 12,
    "shared": true,
    "participants": [
      { "userId": 9, "username": "Autre", "clientId": "2a1b3c4d-5e6f-4a7b-9c8d-0e1f2a3b4c5d", "focus": null, "updatedAt": 1795197600 }
    ]
  }
}
```

Les autres fenêtres du même compte sont incluses. Les signaux de plus de deux minutes sont supprimés au signal suivant sur le même scénario. La présence ne contient aucun contenu du scénario.

### Base de données

- `design_collaborators` : scénario, compte invité, rôle (`editor` ou `viewer`), auteur et date de l’invitation. Les lignes disparaissent avec le compte invité et avec le scénario. Si une ancienne colonne `learning_designs.id` refuse la clé étrangère sous MySQL, la table est créée sans elle ; les suppressions nettoient de toute façon les invitations explicitement.
- `design_presence` : scénario, compte, fenêtre, élément en cours et date du dernier signal.

Le site mutualisé n’a besoin d’aucune connexion permanente : l’app interroge `presence.php` toutes les quelques secondes pendant qu’un scénario partagé est ouvert.

### Collaboration depuis le site

Le concepteur web utilise les mêmes règles, avec la session du site au lieu du jeton de l’app. `POST /design_sharing.php` (JSON, même origine) prend un champ `action` :

| `action` | Corps et réponse |
| --- | --- |
| `members` | `design_id` ; même objet `sharing` que `collaborators.php` |
| `share` | `design_id`, `email`, `role` facultatif ; comme `share_design.php` |
| `role` | `design_id`, `user_id`, `role` ; comme `collaborator_role.php` |
| `unshare` | `design_id`, `user_id` ; comme `unshare_design.php` |
| `presence` | `design_id`, `client_id`, `focus`, `leave` facultatif ; comme `presence.php` |

Les erreurs ajoutent `code` (`not_found`, `forbidden`, `unknown_user`, `invalid_request`) au message. `get_design.php` accepte les enseignants invités et ajoute `role`, `owner` et `shared` ; `save_design.php` enregistre pour un enseignant en modification (403 en consultation) ; `delete_design.php` reste réservé au propriétaire (403 pour un invité). La page « Mes scénarios » liste les scénarios « Partagés avec moi » et permet de quitter un partage ; chaque scénario du compte indique avec combien d’enseignants il est partagé.

Dans le concepteur (`js/collaboration.js`), le bouton **Collaborer** ouvre la liste des enseignants : le propriétaire invite, change les droits ou retire ; un invité peut quitter le partage. Chaque onglet se signale toutes les deux secondes lorsque le scénario est partagé (toutes les trente secondes sinon), avec le champ en cours de saisie ; une carte où travaille un collègue prend sa couleur et son nom, et un message signale un collègue qui écrit dans le même texte. Dans un scénario partagé, chaque modification part 1,2 seconde après la dernière saisie.

Une révision plus récente, ou un enregistrement refusé (409), déclenche la fusion à trois voies de `js/scenario-merge.js`, portage de `ScenarioMerge.swift` : mêmes règles et mêmes cas de test (`tests/scenario-merge.test.cjs`). La dernière version commune est conservée dans le navigateur (`localStorage`, par compte et par scénario) ; elle permet aussi de fusionner un brouillon retrouvé après un rechargement. Sans version commune connue, l’ancien comportement s’applique : le brouillon est conservé et « Enregistrer une copie » est proposé. La version fusionnée remplace l’affichage sans perdre le champ en cours de saisie, son curseur ni l’éditeur plein écran ; elle attend la fermeture d’une fenêtre ou d’un menu ouvert. Les réglages d’affichage (disposition, onglet, notes dépliées, langue) restent propres à chacun. Les boutons **Annuler** et **Rétablir** (⌘Z / Ctrl+Z, ⇧⌘Z / Ctrl+Y) regroupent chaque pause de saisie en une étape et appliquent cette même fusion à l’envers : on ne revient que sur ses propres modifications, et ce qu’un collègue a changé depuis reste en place.

Un enseignant en consultation voit un bandeau « Consultation seule » ; les champs passent en lecture et les actions de modification disparaissent. Les champs que le site n’affiche pas, ajoutés par l’app, sont désormais conservés à l’ouverture et à l’enregistrement.

## Suppression depuis l’app

Le corps de `POST /api/v1/delete_design.php` est `{ "design_id": 7, "expected_revision": 3 }`. La suppression n’a lieu que si le scénario est encore à cette révision : un scénario modifié sur le site depuis la dernière synchronisation de l’app renvoie 409 et reste intact. Elle enregistre la même trace que les suppressions du site, si bien qu’une création réessayée ne fait pas réapparaître le scénario. Une nouvelle demande pour un scénario déjà supprimé par son propriétaire réussit ; un identifiant inconnu ou appartenant à un autre compte renvoie 404.
