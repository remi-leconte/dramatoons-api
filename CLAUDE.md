# Dramatoons : API

Symfony 8.1, API Platform (JSON-LD et JSON), Doctrine ORM 3, PHP 8.4, MariaDB, Redis.
Auth : JWT Lexik (1 h) et refresh token Gesdinet (30 jours, à usage unique), sans état (stateless).

## Commandes
Toutes depuis `api/`, dans le conteneur `php` :

```sh
docker compose up -d                                              # dev (charge compose.override.yaml)
docker compose exec php php bin/console <commande>
docker compose exec php composer <commande>
docker compose exec php php bin/console doctrine:migrations:diff  # après modification d'une entité
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console app:create-admin          # admin initial, via INITIAL_ADMIN_*
docker compose exec php php bin/console app:create-deleted-user   # compte « Utilisateur supprimé », via DELETED_USER_PASSWORD
docker compose exec php php bin/console app:test-data             # dev : supprime et recrée les données de test
docker compose exec php composer ci                               # vérifications de la CI (voir « CI »)
docker compose exec php composer test                             # tests PHPUnit (voir « Tests »)
```

- `compose.override.yaml` (dev) se superpose automatiquement à `compose.yaml` ; en recette et en prod, c'est `compose.prod.yaml` (voir « Configuration »).
- En prod et staging, l'entrypoint génère les clés JWT et applique les migrations. En dev, les migrations se lancent à la main.
- Données de test (`src/Command/TestDataCommand.php`, dev uniquement) : comptes `test-…` (admin, modo, créateur, lecteur, inconnu, désactivé, non vérifié ; mot de passe `test-password`) et webtoons `[TEST] …` couvrant chaque cas de publication et de suivi. Utilisées par l'agent `testeur`.

## Configuration
Conventions de Symfony et de Docker Compose :
- **`.env`**, versionné : valeurs par défaut du dev, sans aucun secret. Il est copié dans l'image.
- **`.env.local`**, non versionné et exclu de l'image (`.dockerignore`) : valeurs propres à une machine. En dev, Symfony le lit après `.env`.
- **Dans les conteneurs**, les vraies variables d'environnement l'emportent sur ces fichiers. `compose.yaml` passe `REDIS_URL`, et `compose.prod.yaml` passe `DATABASE_URL`, construite à partir des variables `MARIADB_*` (MariaDB et PHP partagent ainsi les mêmes valeurs).
- **En dev**, Compose lit seulement `.env`, jamais `.env.local` : MariaDB prend les valeurs par défaut de `compose.yaml` (`dramatoons`, `!ChangeMe!`), et le `DATABASE_URL` de `.env` leur correspond. MariaDB ne lit ces variables qu'à la création de sa base (volume vide) : avec une base existante, `.env.local` doit donc définir le `DATABASE_URL` de ses identifiants réels.
- **En recette et en prod**, une seule configuration, `compose.prod.yaml`, avec des valeurs différentes. Chaque serveur a un `.env.local` passé à Compose par `docker compose --env-file .env.local …`. Il contient :
  - `COMPOSE_FILE=compose.yaml:compose.prod.yaml` ;
  - `DEPLOY_ENV` (`staging` ou `prod`) : nom du projet Compose et des volumes de données ;
  - `HTTP_PORT` : port local de nginx ;
  - les variables `MARIADB_*` (`MARIADB_USER` et `MARIADB_PASSWORD` sans `@ : / ? # %`, car elles entrent telles quelles dans `DATABASE_URL`), `APP_SECRET`, `DEFAULT_URI`, `FRONTEND_URL`, `CORS_ALLOW_ORIGIN`, `MAILER_DSN`, `JWT_PASSPHRASE` : obligatoires, Compose refuse de démarrer s'il en manque une ;
  - `INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PASSWORD`, `DELETED_USER_PASSWORD` : seulement pour les commandes `app:create-admin` et `app:create-deleted-user`.
- Compose remplace les `$` des valeurs de `.env.local` : une valeur qui en contient se met entre apostrophes (`'…'`).
- Sur un serveur, une commande `docker compose` lancée sans `--env-file .env.local` lit le `.env` de dev et vise la pile de dev.
- Une nouvelle variable d'environnement s'ajoute dans `.env` (valeur de dev) et dans l'`environment` de `compose.prod.yaml`.

## Organisation
- `src/Entity/` : entités ; leurs `#[ApiResource]` définissent opérations, `security` et groupes.
- `src/ApiResource/` : endpoints hors CRUD. Chaque classe est à la fois la ressource et son processor/provider.
- `src/State/` :
  - `WebtoonProvider` : listing de `GET /webtoons` ;
  - `WebtoonProcessor` : affecte le créateur et le titre ;
  - `WebtoonUserProcessor` : force `reader` = utilisateur courant ;
  - `UserProcessor` : hash du mot de passe, mail de vérification ;
  - `UserDeleteProcessor` : à la suppression d'un compte, supprime ses webtoons dépubliés suivis par personne d'autre que lui, attribue les autres au compte « Utilisateur supprimé » (`User::DELETED_USER_LOGIN`), qui lui-même ne peut pas être supprimé.
- `src/Repository/WebtoonRepository.php` : requêtes du listing.
- `src/Serializer/UserContextBuilder.php` : ajoute des groupes selon le rôle :
  - `webtoon:write:modo` (`publish`) à partir de MODO ;
  - `user:patch:owner` (email, mot de passe, login) au propriétaire et aux MODO ;
  - `user:read:owner` (id, email, rôles, état, dates de modification et de dernière connexion) au propriétaire et à l'admin ;
  - `user:patch:admin` (rôles) à l'admin.
- `src/EventListener/` :
  - recalcul des stats d'un webtoon (`WebtoonUserListener`) ;
  - invalidation du cache (`WebtoonCacheInvalidatorListener`) ;
  - `lastLogin` et `user` dans la réponse de `/login`.
- `src/Service/UserMailer.php` : mails de vérification et de mot de passe oublié. Lien `FRONTEND_URL/verify?token=` ou `/reset-password?token=`, valable 1 h ; les deux utilisent `User.resetToken`.
- `src/Security/UserChecker.php` : refuse la connexion si `User.publish` est faux.
- `src/Security/WebtoonVoter.php` (webtoon, titres, couverture) :
  - `WEBTOON_VIEW` : connecté, et webtoon publié, créé ou suivi (`isFollowedBy()`), ou MODO ;
  - `WEBTOON_EDIT` : MODO, ou créateur d'un webtoon dépublié que personne d'autre ne suit.

## Endpoints
| Endpoint | Rôle |
| --- | --- |
| `POST /login` `{login, password}` | Renvoie `token`, `refresh_token`, `user {id, login, roles}` |
| `POST /token/refresh` `{refresh_token}` | Nouveau JWT |
| `GET /me` | `id`, `email`, `username`, `total` (nombre de webtoons suivis) |
| `/users` (CRUD) | Liste : admin. Détail : connecté (`login`, `role`, `created` ; le reste au propriétaire et à l'admin). `POST` = inscription. `PATCH` et `DELETE` : admin ou soi-même |
| `POST /users/verify`, `/users/resend-verification` | Validation de l'email |
| `POST /users/forgot-password`, `/users/reset-password` | Mot de passe oublié |
| `/webtoons` (CRUD) | `GET /webtoons/{id}` : `WEBTOON_VIEW`. `POST` : connecté. `PATCH` et `DELETE` : `WEBTOON_EDIT`. `creator` (auteur du `POST`) et `image` (seulement via `/cover`) ne sont pas modifiables par le client ; `title` : un nouveau titre, ou un titre de ce webtoon |
| `POST /webtoons/{id}/cover` | Connecté (sinon 401), puis `WEBTOON_EDIT`. Multipart `file`, JPEG ≤ 1 Mo (sinon 422, validé dans le processor ; au-delà d'environ 1 Mio, nginx refuse en 413), enregistré dans `public/upload/cover/` sous un nom unique à chaque envoi (`UniqidNamer` de VichUploader), l'ancienne image est supprimée |
| `/webtoon_titles` (CRUD) | Liste : MODO. Détail : `WEBTOON_VIEW`, et `POST`, `PATCH`, `DELETE` : `WEBTOON_EDIT`, sur le webtoon du titre |
| `/webtoon_users` (sans `PUT`) | Liste : MODO. Détail : le lecteur ou MODO. `PATCH`/`DELETE` : le lecteur seulement. `POST` : `WEBTOON_VIEW` sur le webtoon visé. `reader` et `webtoon` fixés à la création. Un suivi vide (sans note ni état, chapitre vide ou 0) est refusé (422) : le front le supprime |

## Listing `GET /webtoons`
- **`mode`** (obligatoire, réponse vide sinon) :
  - `bibliotheque` : webtoons suivis avec au moins un `state`, un `bookmark` (non nul) ou un `rate`, et webtoons dépubliés créés par l'utilisateur, même sans suivi ;
  - `catalogue` et `search` : webtoons publiés ;
  - `ext` : comme `bibliotheque`, pour l'extension ;
  - `admin` : tous les webtoons, à partir de MODO (sinon : bibliothèque).
- **Filtres** :
  - `title` : titre principal ou secondaire ;
  - `status[]` et `exclude_status[]` : `state` du suivi ;
  - `statusWebtoon` (`completed` ou `ongoing`, sinon ignoré) : statut du webtoon, dans tous les modes (le front ne l'envoie pas) ;
  - en mode admin uniquement : `id`, `publish=false`.
- **Tri** : `sortBy` = `added` (défaut, date de création), `title`, `rating`, `user_rating`, `id`, `status`, `publish`, `updated`. Ordre par `sortOrder` : `ASC` ou `DESC` (casse ignorée), `DESC` par défaut et pour toute autre valeur.
- **Pagination** : `page`, `itemsPerPage` (20 par défaut), `pagination=false`.
- **Réponse** : chaque webtoon porte `userProgress`, le WebtoonUser de l'utilisateur courant.
- **Cache Redis à tags** : seuls les ids et le total, pendant 10 jours. Tags `webtoons_list` (toute écriture sur Webtoon ou WebtoonTitle) et `user_{id}` (écriture d'un suivi).

## Tests
PHPUnit, dans `tests/` :
- `tests/Api/` : tests de l'API par de vraies requêtes HTTP (`ApiTestCase` d'API Platform). Ils héritent de `tests/Api/ApiTestCase.php` : `client($utilisateur)` envoie les requêtes avec le JWT de cet utilisateur (`null` : non connecté), `post()` et `patch()` avec le bon `Content-Type`, `listing()` renvoie les ids d'un mode de `GET /webtoons`, `connexion()` appelle `POST /login`, `jetonDuMail()` vérifie le mail envoyé (un seul, au bon destinataire) et renvoie le jeton de son lien, `reponseComparable()` donne le code et le corps d'une réponse sans le `@id` aléatoire de JSON-LD, pour comparer deux réponses. Un fichier par sujet des règles métier (`tests/Api/Webtoon/` : accès, création, publication, modification, titres, couverture, listings, suivis ; `tests/Api/Utilisateur/` : compte et session, vérification de l'email, mot de passe oublié, profil, suppression, administration, données visibles, commandes d'installation), chaque test décrit en français par `#[TestDox]`. Les mails envoyés se vérifient avec les assertions mailer de Symfony (`assertEmailCount()`, `getMailerMessage()`).
- Un écart connu entre une règle et le code n'est pas supprimé : le test appelle `markTestIncomplete()` avec l'écart et son ticket, et PHPUnit le compte comme incomplet sans échouer. On retire cet appel quand l'écart est corrigé.
- `tests/Factory/` : factories Foundry. Chaque test crée les données dont il a besoin (`WebtoonFactory::new()->publie()->create()`, `UserFactory::new()->moderateur()->create()`, `avecMotDePasse()` pour un compte qui se connecte par `/login`…) ; il n'utilise pas `app:test-data`.
- DAMA Doctrine Test Bundle annule chaque test à la fin : la base est vide au début de chaque test.

`composer test` recrée la base `dramatoons_test` (environnement `test` : Symfony ajoute `_test` au nom de la base de `DATABASE_URL`), applique les migrations, génère si besoin les clés JWT de test dans `var/test/jwt/`, puis lance PHPUnit. En environnement `test`, Symfony ne lit pas `.env.local` mais `.env.test` (versionné) puis `.env.test.local` (non versionné) : en dev, `.env.test.local` donne le `DATABASE_URL` d'un compte MariaDB qui peut créer la base de test. Les couvertures envoyées pendant les tests sont écrites dans `var/test/upload/`, que `composer test` vide avant de lancer PHPUnit. En test, le journal de Symfony n'affiche que les erreurs 500 (`config/services.yaml`), et `composer test` vide le cache Redis de test, dont les clés contiennent des ids que la base recréée réutilise.

## CI
`.github/workflows/ci.yml` (GitHub Actions) tourne sur chaque PR vers `main` ou `release/*`, sur un runner Ubuntu de GitHub. Trois checks, obligatoires pour fusionner :
- **Vérifications** : `composer install` (dépendances de dev comprises), puis `composer ci`, qui enchaîne :
  1. `composer validate` : `composer.json` est valide (les avertissements ne bloquent pas) ;
  2. `composer audit` : aucune faille de sécurité connue dans les dépendances installées. Une dépendance abandonnée par son mainteneur est seulement signalée, sans faire échouer la CI ;
  3. `doctrine:schema:validate --skip-sync` : mapping des entités cohérent, sans comparaison avec une base ;
  4. `cache:warmup --env=dev` : compile le conteneur de dev que lit PHPStan (`var/cache/dev/App_KernelDevDebugContainer.xml`) ;
  5. PHPStan, niveau 5, sur `src/` (`phpstan.dist.neon`, extensions Symfony et Doctrine).
- **Tests** : démarre MariaDB et Redis (services du job, `DATABASE_URL` et `REDIS_URL` dans l'`env` du job), `composer install`, puis `composer test`.
- **Image prod** : construit l'étape `prod` de `docker/php/Dockerfile`, sans la publier.

Le job « Vérifications » n'a pas de base de données : Symfony y lit le `.env` versionné, dont le `DATABASE_URL` n'est jamais contacté (Doctrine exige seulement une URL valide avec la version du serveur). PHPStan, PHPUnit et les outils de test sont en `require-dev`, donc absents de l'image prod (`composer install --no-dev`).

`lint:container` n'est pas lancé : il échoue sur un service de formulaire déclaré par VichUploader, qui exige `symfony/form`, absent car l'API n'a pas de formulaire.

## Git
Workflow (branches, PR, tickets, releases) : skill `workflow-github`. `.env.local` et `config/jwt/*.pem` ne sont pas versionnés.
