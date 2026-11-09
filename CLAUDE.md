# Dramatoons : API

Symfony 8.0, API Platform (JSON-LD et JSON), Doctrine ORM 3, PHP 8.4, MariaDB, Redis.
Auth : JWT Lexik (1 h) et refresh token Gesdinet (30 jours, à usage unique), sans état (stateless).

## Commandes
Toutes depuis `api/`, dans le conteneur `php` :

```sh
docker compose up -d                                              # dev (charge compose.override.yml)
docker compose exec php php bin/console <commande>
docker compose exec php composer <commande>
docker compose exec php php bin/console doctrine:migrations:diff  # après modification d'une entité
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console app:create-admin          # admin initial, via INITIAL_ADMIN_*
docker compose exec php php bin/console app:create-deleted-user   # compte « Utilisateur supprimé », via DELETED_USER_PASSWORD
```

- Les fichiers `compose.prod.yml` et `compose.staging.yml` se superposent à `compose.yml`.
- En prod et staging, l'entrypoint génère les clés JWT et applique les migrations. En dev, les migrations se lancent à la main.
- Il n'y a pas de tests automatisés.

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
  - `user:patch:admin` (rôles) et `user:read:owner` (id, email) à l'admin.
- `src/EventListener/` :
  - recalcul des stats d'un webtoon (`WebtoonUserListener`) ;
  - invalidation du cache (`WebtoonCacheInvalidatorListener`) ;
  - `lastLogin` et `user` dans la réponse de `/login`.
- `src/Service/UserMailer.php` : mails de vérification et de mot de passe oublié. Lien `FRONTEND_URL/verify?token=` ou `/reset-password?token=`, valable 1 h ; les deux utilisent `User.resetToken`.
- `src/Security/UserChecker.php` : refuse la connexion si `User.publish` est faux.

## Endpoints
| Endpoint | Rôle |
| --- | --- |
| `POST /login` `{login, password}` | Renvoie `token`, `refresh_token`, `user {id, login, roles}` |
| `POST /token/refresh` `{refresh_token}` | Nouveau JWT |
| `GET /me` | `id`, `email`, `username`, `total` (nombre de webtoons suivis) |
| `/users` (CRUD) | `POST` = inscription. `PATCH` et `DELETE` : admin ou soi-même |
| `POST /users/verify`, `/users/resend-verification` | Validation de l'email |
| `POST /users/forgot-password`, `/users/reset-password` | Mot de passe oublié |
| `/webtoons` (CRUD) | `PATCH` et `DELETE` : MODO, ou créateur si personne d'autre ne le suit (`isFollowedByOtherThanCreator()`) |
| `POST /webtoons/{id}/cover` | Multipart `file`, JPEG ≤ 1 Mo, enregistré dans `public/upload/cover/webtoon_presentation_{slug}.jpg` (VichUploader) |
| `/webtoon_titles` (CRUD) | `PATCH` et `DELETE` : MODO ou créateur du webtoon |
| `/webtoon_users` (sans `PUT`) | Liste : MODO. Détail : le lecteur ou MODO. `PATCH`/`DELETE` : le lecteur seulement. `reader` et `webtoon` fixés à la création |

## Listing `GET /webtoons`
- **`mode`** (obligatoire, réponse vide sinon) :
  - `bibliotheque` : webtoons suivis avec au moins un `state`, un `bookmark` ou un `rate` ;
  - `catalogue` et `search` : webtoons publiés ;
  - `ext` : comme `bibliotheque`, pour l'extension ;
  - `admin` : tous les webtoons, admin uniquement.
- **Filtres** :
  - `title` : titre principal ou secondaire ;
  - `status[]` et `exclude_status[]` : `state` du suivi ;
  - en mode admin uniquement : `id`, `publish=false`, `statusWebtoon`.
- **Tri** : `sortBy` = `added` (défaut, date de création), `title`, `rating`, `user_rating`, `id`, `status`, `publish`, `updated`. Ordre par `sortOrder`.
- **Pagination** : `page`, `itemsPerPage` (20 par défaut), `pagination=false`.
- **Réponse** : chaque webtoon porte `userProgress`, le WebtoonUser de l'utilisateur courant.
- **Cache Redis à tags** : seuls les ids et le total, pendant 10 jours. Tags `webtoons_list` (toute écriture sur Webtoon ou WebtoonTitle) et `user_{id}` (écriture d'un suivi).

## Git
Workflow (branches, PR, tickets, releases) : skill `workflow-github`. `.env.local` et `config/jwt/*.pem` ne sont pas versionnés.
