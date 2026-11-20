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

- `compose.override.yaml` (dev) se superpose automatiquement à `compose.yaml` et construit l'image `dev` ; en recette et en prod, c'est `compose.prod.yaml`, qui utilise l'image publiée (voir « Configuration » et « Publication et déploiement »).
- En prod et staging, l'entrypoint génère les clés JWT et applique les migrations ; `php` attend pour démarrer que MariaDB accepte les connexions. En dev, les migrations se lancent à la main.
- Données de test (`src/Command/TestDataCommand.php`, dev uniquement) : comptes `test-…` (admin, modo, créateur, lecteur, inconnu, désactivé, non vérifié ; mot de passe `test-password`) et webtoons `[TEST] …` couvrant chaque cas de publication et de suivi. Utilisées par l'agent `testeur`.

## Configuration
Conventions de Symfony et de Docker Compose :
- **`.env`**, versionné : valeurs par défaut du dev, sans aucun secret. Il est copié dans l'image.
- **`.env.local`**, non versionné et exclu de l'image (`.dockerignore`) : valeurs propres à une machine. En dev, Symfony le lit après `.env`.
- **Dans les conteneurs**, les vraies variables d'environnement l'emportent sur ces fichiers. `compose.yaml` passe `REDIS_URL`, et `compose.prod.yaml` passe `DATABASE_URL`, construite à partir des variables `MARIADB_*` (MariaDB et PHP partagent ainsi les mêmes valeurs).
- **En dev**, Compose lit seulement `.env`, jamais `.env.local` : MariaDB prend les valeurs par défaut de `compose.yaml` (`dramatoons`, `!ChangeMe!`), et le `DATABASE_URL` de `.env` leur correspond. MariaDB ne lit ces variables qu'à la création de sa base (volume vide) : avec une base existante, `.env.local` doit donc définir le `DATABASE_URL` de ses identifiants réels.
- **En recette et en prod**, une seule configuration, `compose.prod.yaml`, avec des valeurs différentes. Chaque serveur a un `.env.local` passé à Compose par `docker compose --env-file .env.local --env-file .env.image …`. `.env.image` contient seulement l'`IMAGE_TAG` de l'image qui tourne (`sha-<commit>` en recette, `vX.Y.Z` en prod), écrit par le script de déploiement. `.env.local` contient :
  - `COMPOSE_FILE=compose.yaml:compose.prod.yaml` ;
  - `DEPLOY_ENV` (`staging` ou `prod`) : nom du projet Compose et des volumes de données ;
  - `HTTP_PORT` : port local de nginx ;
  - les variables `MARIADB_*` (`MARIADB_USER` et `MARIADB_PASSWORD` sans `@ : / ? # %`, car elles entrent telles quelles dans `DATABASE_URL`), `APP_SECRET`, `DEFAULT_URI`, `FRONTEND_URL`, `CORS_ALLOW_ORIGIN`, `MAILER_DSN`, `JWT_PASSPHRASE` : obligatoires, Compose refuse de démarrer s'il en manque une ;
  - `INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PASSWORD`, `DELETED_USER_PASSWORD` : seulement pour les commandes `app:create-admin` et `app:create-deleted-user`.
- Compose remplace les `$` des valeurs de `.env.local` : une valeur qui en contient se met entre apostrophes (`'…'`).
- Sur un serveur, une commande `docker compose` lancée sans `--env-file .env.local` lit le `.env` de dev et vise la pile de dev ; sans `--env-file .env.image`, Compose refuse, faute de `IMAGE_TAG`.
- Une nouvelle variable d'environnement s'ajoute dans `.env` (valeur de dev) et dans l'`environment` de `compose.prod.yaml`.

## Organisation
- `src/Entity/` : entités ; leurs `#[ApiResource]` définissent opérations, `security` et groupes.
- `src/ApiResource/` : endpoints hors CRUD. Chaque classe est à la fois la ressource et son processor/provider.
- `src/State/` :
  - `WebtoonProvider` : listing de `GET /webtoons` ;
  - `WebtoonProcessor` : affecte le créateur et le titre ;
  - `WebtoonUserProcessor` : force `reader` = utilisateur courant ;
  - `UserProcessor` : hash du mot de passe (seulement s'il a été désérialisé, donc autorisé par les groupes), mail de vérification ;
  - `UserDeleteProcessor` : à la suppression d'un compte, supprime ses webtoons dépubliés suivis par personne d'autre que lui, attribue les autres au compte « Utilisateur supprimé » (`User::DELETED_USER_LOGIN`), qui lui-même ne peut pas être supprimé.
- `src/Repository/WebtoonRepository.php` : requêtes du listing.
- `src/Serializer/UserContextBuilder.php` : ajoute des groupes selon le rôle :
  - `webtoon:write:modo` (`publish`) à partir de MODO ;
  - `user:read:owner` (id, email, rôles, état, dates de modification et de dernière connexion), en lecture seulement, au propriétaire et à l'admin ;
  - `user:patch:owner` (login, email, mot de passe), en écriture, au propriétaire ;
  - `user:patch:admin` (login, email, rôles : un seul, parmi `ROLE_USER`, `ROLE_MODO` et `ROLE_ADMIN`, sinon 422 ; activation `publish`), en écriture, à l'admin, seulement sur un compte existant (pas à la création) : jamais le mot de passe d'un autre compte.
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
- **Tri** (les filtres standard d'API Platform, comme `order[…]`, ne sont pas déclarés sur `Webtoon` : `WebtoonProvider` les ignorerait) : `sortBy` = `added` (défaut, date de création), `title`, `rating`, `user_rating`, `id`, `status`, `publish`, `updated`. Ordre par `sortOrder` : `ASC` ou `DESC` (casse ignorée), `DESC` par défaut et pour toute autre valeur. Les ex aequo sont rangés par `id`, dans le même sens, pour que la pagination ne répète ni n'omette un webtoon.
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
`.github/workflows/ci.yml` (GitHub Actions) tourne sur chaque PR vers `main` ou `release/*`, sur un runner Ubuntu de GitHub. Trois checks, obligatoires pour fusionner (vers `release/*`, s'y ajoute la relecture IA, ci-dessous) :
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

### Relecture IA
`.github/workflows/relecture.yml` relit chaque PR vers `release/*` (ouverture, nouveau push, sortie de brouillon) avec Claude Code (`anthropics/claude-code-action`), et produit le check « Relecture IA » :
- Seulement pour les PR de `remi-leconte` dont la branche vient du dépôt : le jeton de l'abonnement Claude (secret `CLAUDE_CODE_OAUTH_TOKEN`) n'est jamais utilisé pour une PR extérieure. Pour toute autre PR, le check est rouge (« pas de relecture IA ») plutôt que sauté, car un check sauté compte comme réussi. Une PR extérieure n'est donc jamais fusionnée telle quelle : si son contenu est retenu, il est repris sur une branche de ticket du dépôt et suit le cycle normal. Un brouillon n'est pas relu : son check est rouge (« pas encore relu ») jusqu'au passage en prêt, qui lance la relecture ; sauté, il serait vert. Un nouveau push annule la relecture en cours.
- Une PR de synchronisation (`main` → `release/*`, après un hotfix ou une livraison) a le check vert sans relecture IA : son contenu a déjà été relu et livré.
- Une PR qui versionne `_relecture/` ou `_workspace/`, dossiers écrits par le job, fait échouer le check sans verdict, avec un message explicite : ces fichiers passeraient pour le contexte de la relecture.
- Le job rassemble d'abord, sans IA, le contexte dans `_relecture/` : la PR, son ticket, le ticket principal et ses sous-tickets, et les PR ouvertes des autres dépôts pour ces tickets, seulement celles de `remi-leconte` dont la branche vient du dépôt (plusieurs PR pour un même ticket sont signalées). Les PR d'un même ticket sont ainsi relues ensemble, pour la cohérence entre l'API et le front. Elles sont ouvertes en brouillon, puis passées prêtes ensemble (`gh pr ready`) : chaque relecture trouve ainsi toutes les autres.
- Une branche hors convention, ou un ticket introuvable (le sien, le ticket principal ou un sous-ticket), n'arrête pas le job : la relecture la signale comme remarque. Une autre erreur de lecture d'un ticket (GitHub indisponible) fait échouer le job sans verdict.
- Claude lit ensuite les consignes de `relecture/consignes.md` du dépôt `dramatoons-workspace` (cloné dans `_workspace/`), avec les règles métier et le workflow. Ses seuls outils disponibles sont Read, Glob et Grep (`--tools`) : ni Bash, ni écriture, ni sous-agent, ni accès à GitHub. Ils ne lisent que le dossier de travail : sans `--allowedTools`, toute lecture ailleurs sur le runner demande une permission que personne ne peut accorder, donc refusée. La configuration Claude du dépôt relu (`CLAUDE.md`, `.claude/`, `.mcp.json`) n'est pas chargée (`--setting-sources user`) : elle ne peut ni changer les consignes du relecteur ni lui faire lancer une commande par un hook ou un serveur MCP. L'action remet en outre dans l'arbre le `CLAUDE.md` de la branche cible : celui de la PR est copié avant dans `_relecture/CLAUDE.pr.md`, que le relecteur lit comme une donnée. Il rend un verdict JSON.
- Limite : sur une PR, GitHub exécute le `relecture.yml` de la PR elle-même. Une PR qui modifie ce fichier change donc sa propre relecture ; les consignes demandent de relire ce fichier en premier et de bloquer tout affaiblissement. C'est aussi pourquoi une PR extérieure n'est jamais fusionnée telle quelle, et pourquoi ses workflows attendent une approbation avant de tourner : réglage du dépôt Settings → Actions → General, « Require approval for all external contributors » (par défaut, GitHub ne l'exige que pour un premier contributeur).
- Les actions du job de relecture (`actions/checkout` et l'action Claude) sont épinglées sur un commit, et non sur une étiquette comme `v1` : elles tournent dans le job qui reçoit le jeton de l'abonnement, et une étiquette déplacée leur donnerait un autre code. Les mettre à jour volontairement.
- On ne change jamais la cible d'une PR relue (par exemple vers une autre release) : son check resterait celui de l'ancienne cible. On la ferme et on en ouvre une nouvelle. La relecture n'écoute pas les modifications de PR (`edited`), dont chacune lancerait un run, et un job sauté remplacerait un verdict rouge par un check réussi.
- Le check « Relecture IA » est obligatoire pour fusionner dans `release/*` (ruleset des releases), en plus des trois checks de la CI. Les PR `release/*` → `main` ne sont pas relues, leur contenu l'ayant été ticket par ticket : le ruleset de `main` ne doit pas l'exiger, sans quoi elles resteraient bloquées faute de run.
- Check rouge à la moindre remarque, vert sinon. Le verdict et les remarques sont publiés en commentaire de la PR par un second job, seul à avoir le droit d'écrire sur la PR. Le texte lui est passé par une sortie de job, ce qui permet de relancer le run sans conflit.
- La relecture ne fusionne rien, et la fusion automatique de GitHub n'est pas utilisée : un ticket qui touche l'API et le front a une PR dans chaque dépôt, que GitHub fusionnerait chacune de son côté. Claude fusionne toutes les PR du ticket ensemble, une fois toutes vertes, ou aucune (skill `workflow-github`).
- Chaque relecture consomme le quota de l'abonnement. Si le quota est atteint, le check échoue : relancer le job plus tard (« Re-run failed jobs »).
- Après une correction poussée sur une seule PR d'un ticket, relancer aussi la relecture des autres PR du ticket, qui ont vu l'ancienne version.

## Publication et déploiement
`.github/workflows/publish.yml` construit l'étape `prod` du Dockerfile (`linux/amd64`) et la publie, publique, sur `ghcr.io/remi-leconte/dramatoons-api`. Seule l'image `php` est publiée : nginx, MariaDB et Redis sont des images officielles.
- Déclenché par un `push` sur `release/*` (donc la fusion d'une PR), jamais par une PR : seul ce qui est déjà passé par la CI est publié.
- Étiquettes : `release-vX.Y.Z` (dernière fusion dans la release) et `sha-<commit>`. Pas de `latest`. L'étiquette `vX.Y.Z` est ajoutée à la livraison, sans reconstruire l'image (voir « Livraison »).
- Job « Publication image », distinct du check « Image prod » de la CI. Les publications d'une même branche passent l'une après l'autre, sans annulation ; seule exception de GitHub : sur trois fusions très rapprochées, celle du milieu, encore en attente, est remplacée et son `sha-<commit>` n'est pas publié.
- Droits : aucun par défaut (`permissions: {}`). Le job « Publication image » n'a que le `GITHUB_TOKEN`, limité à `contents: read` et `packages: write`, et aucun secret ; dans ce workflow, les secrets (SSH, `PROJECT_TOKEN`) ne servent qu'au job « Déploiement recette » (ci-dessous).
- `.dockerignore` exclut de l'image ce qui ne sert pas en production : tests, outillage de développement, `CLAUDE.md` et `README.md`.

Sur `release/*`, le job « Déploiement recette » suit la publication :
- Il tourne dans l'environnement GitHub `staging`, dont les secrets (`SSH_HOST`, `SSH_KNOWN_HOSTS`, `SSH_PRIVATE_KEY`, `PROJECT_TOKEN`) ne sont donnés qu'aux branches `release/*`.
- Il se connecte au serveur avec une clé dédiée, limitée côté serveur à un script de déploiement (hors dépôt), et ne lui transmet que `api` et le commit. Le script télécharge `compose.yaml`, `compose.prod.yaml` et `docker/nginx/default.conf` de ce commit, récupère l'image `sha-<commit>`, redémarre la pile (les migrations passent au démarrage de `php`), vérifie que l'API répond, puis supprime les anciennes images.
- La recette déployée ainsi ne construit rien : son dossier ne contient que `.env.local`, `.env.image`, `config/jwt/` et les fichiers téléchargés (`compose.yaml`, `compose.prod.yaml`, `docker/nginx/default.conf`), sans le code ; les données restent dans les volumes.
- Revenir à une version précédente :
  - dans GitHub, ouvrir le run « Publication » d'un commit précédent de la release et relancer **seulement** son job « Déploiement recette » (« Re-run this job ») : GitHub ne relance pas la publication dont il dépend, et l'image déjà publiée, celle qui a été recettée, est redéployée. « Re-run all jobs » reconstruirait l'image, qui pourrait différer, et déplacerait `release-vX.Y.Z` sur ce commit : à éviter ;
  - GitHub ne permet de relancer un job que dans les 30 jours qui suivent son run ; au-delà, lancer à la main sur le serveur le script de déploiement avec le commit voulu ;
  - seuls les commits fusionnés depuis l'arrivée du déploiement automatique (remi-leconte/dramatoons-front#149 et #111) se redéploient ainsi : avant, `compose.prod.yaml` construisait l'image à partir du code, absent du dossier de la recette. Pour revenir à un commit plus ancien, relancer l'ancienne recette, qui se construit dans un autre dossier du serveur contenant le code (procédure hors dépôt). Ce dossier y reste jusqu'au retrait de git du serveur, dernière étape de la mise en place du déploiement.
  - L'ancienne image ne défait pas les migrations : son entrypoint ignore celles qu'il ne connaît pas, et l'ancien code tournerait sur le nouveau schéma. Si les commits annulés contiennent une migration, la défaire d'abord depuis l'image qui tourne encore (la seule qui contient sa méthode `down()`) : `docker compose --env-file .env.local --env-file .env.image exec -u www-data php php bin/console doctrine:migrations:migrate 'DoctrineMigrations\Version<précédente>' --no-interaction`, puis redéployer. Une migration irréversible (son `down()` lève une exception) impose de restaurer la sauvegarde de la base.
- Les journaux de GitHub Actions sont publics : le script n'y écrit ni configuration ni journaux des conteneurs.
- Après un déploiement réussi, le job passe en `In QA` le ticket de la PR fusionnée, lu dans le nom de sa branche (voir « Statuts des tickets »). Une fusion qui ne vient pas d'une branche de ticket (synchronisation `main` → release) ne change aucun statut.

## Livraison
`.github/workflows/livraison.yml` livre une release à sa fusion dans `main` (`push` sur `main`) :
1. **Release fusionnée** : retrouve la PR fusionnée et sa branche `release/vX.Y.Z`. Un hotfix passe lui aussi par sa propre release (`release/vX.Y.Z` de la version corrigée, skill `workflow-github`) et se livre de la même façon ; une fusion qui ne vient pas d'une release n'a pas d'image recettée et ne livre rien. Le workflow échoue sans rien livrer si le contenu de `main` diffère de celui du dernier commit de la release (hotfix non repris dans la release, conflit résolu à la fusion) ou si le tag `vX.Y.Z` existe déjà.
2. **Version** : ajoute l'étiquette `vX.Y.Z` à l'image `sha-<dernier commit de la release>`, celle qui a été recettée, sans la reconstruire, puis pose le tag `vX.Y.Z` sur `main`. Un tag posé avec le `GITHUB_TOKEN` ne déclenche aucun workflow : la suite se fait donc dans celui-ci.
3. **Déploiement production** : attend l'approbation de l'utilisateur, puis déploie `vX.Y.Z`. La recette n'est pas redéployée : elle tourne déjà sur l'image recettée, la même que `vX.Y.Z`. L'environnement GitHub `production` exige cette approbation, sans contournement par un administrateur, et ne donne ses secrets qu'à `main`. Sa clé SSH est distincte de celle de la recette : le serveur la limite au déploiement de la production, qui n'accepte qu'une version `vX.Y.Z`. Avant de démarrer la nouvelle version, le script sauvegarde la base sur le serveur et ne déploie rien si la sauvegarde échoue. Après le déploiement réussi, le job passe en `Done` les tickets du dépôt de cette version qui sont en `Ready to Deploy` (voir « Statuts des tickets »).

- Droits : aucun par défaut (`permissions: {}`). « Release fusionnée » a `contents: read` et `pull-requests: read` ; « Version », `contents: write` (tag) et `packages: write` (étiquette de l'image) ; « Déploiement production » n'a aucun droit, seulement les secrets de son environnement (SSH, `PROJECT_TOKEN`).
- Les livraisons passent l'une après l'autre (groupe de concurrence `livraison`) : tant que son déploiement en production attend l'approbation, un run bloque la livraison suivante, et une nouvelle fusion dans `main` remplacerait celle qui attend. Si la version ne doit finalement pas partir en production, **rejeter** le job « Déploiement production » (« Reject »), puis corriger par un hotfix.
- Approuver la production de l'API avant celle du front : le nouveau front peut appeler des points d'entrée que seule la nouvelle API connaît.
- Revenir à la version précédente en production :
  - dans le run « Livraison » de la version précédente, relancer **seulement** le job « Déploiement production » (« Re-run this job », dans les 30 jours, avec une nouvelle approbation), ou lancer à la main sur le serveur le script de déploiement avec le `vX.Y.Z` de cette version ;
  - seules les versions livrées par ce workflow (à partir de la v2.4.0) se redéploient ainsi : les plus anciennes n'ont ni `compose.prod.yaml` qui utilise une image publiée, ni run « Livraison ». Pour revenir avant la v2.4.0, relancer l'ancienne production, qui se construit dans un autre dossier du serveur contenant le code (procédure hors dépôt). Ce dossier y reste jusqu'au retrait de git du serveur.
  - Les migrations ne sont pas défaites (voir le retour arrière de la recette). Pour revenir avant une migration irréversible, ou avant la v2.4.0 (sa migration supprime la colonne `slug`), ou si des données sont abîmées, restaurer la sauvegarde de la base faite par le script juste avant le déploiement.

## Statuts des tickets
Les jobs « Déploiement recette » et « Déploiement production » finissent par une étape « Statuts des tickets », qui change le champ Status du projet GitHub Dramatoons avec `gh project` :
- recette : le ticket de la PR fusionnée passe en `In QA`, s'il est en `In Review`. Une relance du job (retour arrière, voir « Publication et déploiement », ou après un déploiement échoué) ne change aucun statut : le commit redéployé n'est pas forcément la dernière version du ticket, qui ne doit ni reculer ni avancer à tort ;
- production : les tickets du dépôt dont le champ Release vaut la version livrée, et qui sont en `Ready to Deploy`, passent en `Done`. Chaque ticket, sous-ticket compris, a donc sa propre Release (skill `workflow-github`). Relancer le job d'une version précédente ne change rien : ses tickets sont déjà en `Done`. Les tickets de l'API passent ainsi à l'approbation de sa production, ceux du front à la sienne.

Le `GITHUB_TOKEN` ne peut pas écrire dans un projet d'utilisateur : l'étape utilise le secret d'environnement `PROJECT_TOKEN`, qui expire. Il n'existe que dans les environnements `staging` et `production` : seuls les jobs de déploiement le reçoivent, jamais une PR. À son expiration, le renouveler dans les deux environnements de l'API et du front.

L'étape ne peut pas faire échouer le job (`continue-on-error`) : relancer un déploiement, en production surtout (nouvelle approbation), pour un statut serait pire que de le corriger à la main. Le run signale une étape en échec (jeton expiré) par une erreur, sans que le job échoue, une étape qui n'a trouvé aucun ticket à changer (ticket qui n'est plus en `In Review`, aucun ticket en `Ready to Deploy`) par un avertissement, et une relance du job par une notice. Ces cas se corrigent à la main (skill `workflow-github`), comme ceux que l'étape ne couvre pas, sans rien signaler :
- ticket principal en avance sur son sous-ticket ;
- ticket dont le run « Publication » a été annulé par la concurrence (trois fusions rapprochées, voir « Publication et déploiement ») : son code est déployé par le run suivant, mais l'étape de celui-ci ne passe en `In QA` que le ticket de sa propre fusion.

L'étape contient les identifiants du projet, du champ Status et de ses options, qui survivent à un renommage, mais aussi les noms des états `In Review` et `Ready to Deploy` et du champ Release, qui servent de filtres : renommer ou recréer l'un d'eux impose de mettre l'étape à jour, sinon elle ne trouve plus aucun ticket.

## Git
Workflow (branches, PR, tickets, releases) : skill `workflow-github`. `.env.local` et `config/jwt/*.pem` ne sont pas versionnés.
