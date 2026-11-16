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

Le schéma 10 ajoute cinq tables :

- `native_app_sessions` : compte, permissions, empreinte d’accès, expirations et révocation ;
- `native_oauth_codes` : code à usage unique, PKCE et retour exact ;
- `native_refresh_tokens` : empreintes et historique de rotation jusqu’à l’expiration de la session ;
- `native_creations` : correspondance durable entre UUID client et scénario ;
- `native_design_deletions` : suppressions effectuées depuis l’éditeur et la liste des scénarios.

La migration est additive et idempotente. Elle est intégrée à `ensure_app_schema()` et prévue pour SQLite et MySQL/InnoDB. Les tests automatisés locaux utilisent SQLite ; la variante MySQL doit être vérifiée sur une base de test correspondant à celle de l’hébergement.

Le profil affiche les accès encore valables et permet de révoquer chaque connexion avec vérification CSRF. Les changements de mot de passe, les réinitialisations, les changements d’email et la suspension d’un compte invalident aussi les accès natifs et les codes en attente. Une réactivation n’accorde pas de nouveau les anciennes connexions.

## Déploiement

1. Sauvegarder la base de production et les fichiers de configuration.
2. Tester la branche sur un environnement distinct, avec une base de test et la même version de PHP/PDO que l’hébergement.
3. Déployer ensemble les fichiers PHP modifiés, les nouvelles routes, `lib/native-*.php`, `css/native-app.css` et `.htaccess`.
4. Vérifier `APP_BASE_URL` dans la configuration privée ou les variables d’environnement. Il doit contenir l’URL canonique réelle, par exemple `https://scenarisation.eu`, sans le chemin `/api/v1`. Cette valeur sert d’émetteur OAuth.
5. Depuis la racine du site, exécuter `php -r 'require "lib/bootstrap.php"; app_db(); echo APP_SCHEMA_VERSION, PHP_EOL;'` avec la configuration du serveur. Le résultat attendu est 10. Le premier accès web peut également effectuer cette migration.
6. Vérifier le passage de l’en-tête Authorization à PHP. La règle Apache fournie transmet cet en-tête ; sous Nginx/FastCGI, vérifier le paramètre `HTTP_AUTHORIZATION` dans la configuration du serveur.
7. Vérifier les routes OAuth et API en HTTPS, puis le parcours de consentement avec un compte de test. Le serveur PHP local sur 127.0.0.1 accepte HTTP pour les tests ; l’hébergement public doit utiliser HTTPS.
8. Raccorder et tester le gestionnaire OAuth de l’app Mac avant d’y activer la connexion réelle.

Ne pas recopier la base ou les secrets de cette copie iCloud sur le serveur pour appliquer une mise à jour du code. Aucun déploiement de production n’est effectué par ces tests.

Pour limiter les données techniques expirées, une maintenance peut supprimer les lignes de `native_oauth_codes` expirées et les sessions dont `expires_at` est dépassé ; les jetons de renouvellement associés aux sessions sont supprimés en cascade. Conserver l’historique des jetons tant que leur session est valable. Les correspondances `native_creations` et les suppressions doivent rester disponibles pour reconnaître les clients longtemps restés hors ligne.

## Vérifications

```sh
php tests/native-sync.test.php
php tests/native-http.test.php
php tests/native-concurrency.test.php
php tests/server-state.test.php
node --test tests/*.test.cjs
```

Les tests utilisent des bases, sessions et processus temporaires. Le test HTTP parcourt les vraies routes avec un serveur PHP sur 127.0.0.1. Les tests de concurrence lancent deux processus pour vérifier les créations identiques et les consommations uniques des codes et des renouvellements. Ils ne contactent pas le site en production.

Références : [OAuth pour applications natives, RFC 8252](https://www.rfc-editor.org/rfc/rfc8252.html) et [bonnes pratiques de sécurité OAuth, RFC 9700](https://www.rfc-editor.org/rfc/rfc9700.html).
