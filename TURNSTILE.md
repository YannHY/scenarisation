# Activer la protection des comptes

Les inscriptions, la création du premier administrateur et les renvois d’emails de vérification utilisent Cloudflare Turnstile, un jeton CSRF et des compteurs anti-abus partagés. La confirmation d’une adresse par un lien reçu reste possible sans Turnstile, avec un jeton CSRF. Les connexions des comptes existants ne sont pas modifiées.

## 1. Créer le widget Cloudflare

Dans le tableau de bord Cloudflare, ouvrir **Turnstile → Add widget** :

- Nom : **Scenarisation — comptes**.
- Hostnames : `scenarisation.eu` et `www.scenarisation.eu` si cette seconde adresse est utilisée.
- Mode : **Managed**.
- Pre-clearance : désactivé.

Copier la **Site key** (publique) et la **Secret key** (privée). Créer un widget dédié permet de conserver séparément les réglages de la page de contact.

Documentation officielle : https://developers.cloudflare.com/turnstile/get-started/widget-management/dashboard/

## 2. Compléter la configuration privée avant l’upload

Ajouter ces entrées dans le tableau PHP **existant** de `learning-design-secret.php`, à côté des paramètres de base de données. Conserver les autres entrées et les virgules. Ne pas remplacer le fichier entier et ne pas mettre la clé secrète dans `app-config.php`, le JavaScript ou Git.

```php
'APP_TURNSTILE_SITE_KEY' => 'VOTRE_CLE_PUBLIQUE',
'APP_TURNSTILE_SECRET_KEY' => 'VOTRE_CLE_SECRETE',
'APP_TURNSTILE_HOSTNAMES' => 'scenarisation.eu,www.scenarisation.eu',
```

Les variables d’environnement de mêmes noms sont également prises en charge. Les noms d’hôtes n’incluent ni `https://`, ni chemin. Le serveur compare le domaine retourné par Cloudflare à cette liste explicite et vérifie l’action attendue (`signup`, `setup_admin` ou `resend_verification`).

L’hébergement doit autoriser les requêtes HTTPS sortantes vers `https://challenges.cloudflare.com/turnstile/v0/siteverify`, via PHP cURL ou `allow_url_fopen` avec HTTPS. La vérification des certificats TLS reste activée.

## 3. Uploader ensemble

Conserver les chemins :

- `lib/account-protection.php` (nouveau)
- `signup.php`
- `setup_admin.php`
- `verify-email.php`
- `app-config.php` (contient uniquement les valeurs distribuées, sans secret)
- `politique-confidentialite.php`

Les fichiers de tests et ce guide ne sont pas nécessaires sur le serveur. La table `account_rate_limits` est créée automatiquement à la première tentative protégée ; le compte SQL doit disposer du droit de création de table, comme pour les migrations existantes.

**Sans configuration complète, les inscriptions et renvois sont bloqués.** Une panne ou un refus de Cloudflare ne désactive jamais silencieusement la protection. Les pages de connexion restent accessibles et les comptes existants sont conservés.

## 4. Vérifier après déploiement

1. Ouvrir `https://scenarisation.eu/signup.php` dans une nouvelle session de navigation.
2. Vérifier que le widget Turnstile apparaît et permet de terminer la vérification.
3. Créer un compte avec une adresse de test `@florimont.ch` que vous contrôlez, accepter les CGU, puis confirmer l’adresse depuis l’email reçu.
4. Pour un compte encore non vérifié, vérifier le renvoi d’un lien depuis `verify-email.php`. Il doit demander Turnstile ; un renvoi immédiat supplémentaire doit rester soumis au délai d’une minute.
5. Si la vérification reste indisponible, vérifier les deux clés, les domaines autorisés dans Cloudflare et dans la configuration privée, et l’accès HTTPS sortant du serveur.

## Limites et données enregistrées

Les mêmes compteurs couvrent l’inscription et le renvoi : 60 tentatives par IP et par tranche fixe de 15 minutes, et 5 par adresse email et par tranche fixe d’une heure. Le plafond IP permet notamment plusieurs inscriptions depuis le réseau partagé de l’établissement. Les compteurs sont atomiques afin de résister aux requêtes simultanées ; une nouvelle session ne les remet pas à zéro.

Les IP et emails sont représentés dans les compteurs par des empreintes HMAC calculées avec la clé secrète et la fenêtre de temps. Les adresses brutes et les jetons Turnstile ne sont pas stockés dans cette table. Les compteurs expirés sont supprimés lors de la prochaine tentative protégée. L’IP utilisée est `REMOTE_ADDR` ; les en-têtes de proxy fournis par le client ne sont pas considérés comme fiables. Si un proxy masque les IP réelles, prévoir une configuration serveur de proxy de confiance avant de modifier ce comportement.

Le serveur ne transmet à Siteverify que sa clé secrète et le jeton Turnstile. La vérification distante impose des jetons à usage unique et un délai de validité de cinq minutes ; les jetons expirés doivent être renouvelés par le widget.

Documentation officielle : https://developers.cloudflare.com/turnstile/get-started/server-side-validation/

## Tests locaux

```sh
php tests/account-protection.test.php
php tests/account-registration.test.php
```

Les tests utilisent des bases SQLite temporaires. Les tests des formulaires simulent les réponses HTTPS de Cloudflare et l’envoi d’email ; ils ne créent aucun compte réel et ne transmettent aucun message. Le parcours réel avec les clés de production et le moteur MySQL de l’hébergeur doit être vérifié après configuration.
