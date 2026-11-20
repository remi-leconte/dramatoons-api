# Dramatoons : API

[![Livraison](https://github.com/remi-leconte/dramatoons-api/actions/workflows/livraison.yml/badge.svg?branch=main)](https://github.com/remi-leconte/dramatoons-api/actions/workflows/livraison.yml)
[![Dernière release](https://img.shields.io/github/v/release/remi-leconte/dramatoons-api?label=release)](https://github.com/remi-leconte/dramatoons-api/releases/latest)
[![Licence MIT](https://img.shields.io/badge/licence-MIT-blue)](LICENSE)

API REST de [Dramatoons](https://dramatoons.ovh), un site de suivi de lecture de webtoons (bandes dessinées en ligne) : chaque lecteur tient sa bibliothèque, note les webtoons et retrouve le dernier chapitre lu.

> **Dramatoons n'héberge aucun webtoon.** Il ne conserve que la progression de chaque lecteur (dernier chapitre lu, note, état) : aucun chapitre, aucune planche, et aucun lien vers des plateformes de diffusion non autorisées. Les webtoons se lisent ailleurs, sur les plateformes choisies par le lecteur.

## Fonctionnalités

- **Webtoons** : titre principal et titres alternatifs, statut (en cours ou terminé), couverture, note moyenne et nombre de lecteurs.
- **Suivis de lecture** : pour chaque lecteur, le dernier chapitre lu, une note et un état (en cours, en pause, pas intéressé, terminé).
- **Listings** : bibliothèque du lecteur, catalogue, recherche par titre et administration, avec filtres, tri et pagination.
- **Comptes** : inscription avec validation de l'email, connexion, mot de passe oublié, profil, suppression du compte.
- **Rôles** : lecteur, modérateur et administrateur. Les modérateurs publient les webtoons ; les administrateurs gèrent aussi les comptes.
- **Visibilité** : un visiteur non connecté ne voit aucun webtoon. Un webtoon créé par un lecteur est dépublié jusqu'à sa publication par un modérateur. Un webtoon dépublié n'est visible que de son créateur, de ceux qui le suivaient déjà et des modérateurs.

L'API sert le [site web](https://github.com/remi-leconte/dramatoons-front) et les extensions de navigateur, qui lisent la bibliothèque du lecteur.

## Technologies et architecture

PHP 8.4, Symfony 8.1, API Platform 4 (JSON-LD et JSON), Doctrine ORM 3, MariaDB, Redis, le tout dans Docker.

- **Ressources** : les entités déclarent leurs opérations et leurs droits avec API Platform ; un voter Symfony centralise les droits sur un webtoon (voir, modifier).
- **Authentification** : sans état, par JWT d'une heure, renouvelé par un jeton de rafraîchissement à usage unique.
- **Listing des webtoons** : un *state provider* API Platform écrit pour le projet (`WebtoonProvider`) remplace la lecture standard de Doctrine ; il applique les règles de visibilité, les filtres et le tri, et Redis met en cache le résultat, invalidé à chaque écriture.
- **Statistiques** : note moyenne et nombre de lecteurs sont recalculés à chaque modification d'un suivi.

Le détail (organisation du code, endpoints, configuration) est dans le [`CLAUDE.md`](CLAUDE.md) du dépôt.

## Démarrer en local

Prérequis :
- Docker avec Docker Compose, et le réseau Docker externe `global_proxy_network` ;
- en dev, le conteneur `php` tourne avec l'uid 1000 et écrit dans le dossier du dépôt, qu'il monte (dépendances, clés JWT, cache) : sur Linux, cloner le dépôt avec un utilisateur d'uid 1000 (`id -u`), ou adapter la ligne `user` de `compose.override.yaml`.

```sh
git clone https://github.com/remi-leconte/dramatoons-api.git
cd dramatoons-api
docker network create global_proxy_network                                    # une seule fois
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console lexik:jwt:generate-keypair            # clés de signature des JWT
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:test-data                         # jeu de données de développement
```

L'API répond alors sur http://localhost:8081, et sa documentation OpenAPI sur http://localhost:8081/docs.

`app:test-data` crée des comptes `test-…` pour chaque rôle et chaque état de compte (mot de passe `test-password`), et des webtoons `[TEST] …` qui couvrent chaque cas de publication et de suivi. Il supprime et recrée ces données à chaque lancement.

## Qualité

- **Tests des règles métier** : PHPUnit, par de vraies requêtes HTTP sur l'API. Chaque règle de droits, de visibilité, de publication et de suivi a ses tests, décrits en français, avec des données créées pour chaque test.
- **Vérifications** : validation de Composer, audit de sécurité des dépendances, cohérence du mapping Doctrine, analyse statique PHPStan.
- **Intégration continue** (GitHub Actions) : vérifications, tests et construction de l'image de production sur chaque pull request. Une relecture par IA relit en plus chaque pull request vers une release, en priorité sur la sécurité et les règles métier ; la moindre remarque la bloque.

```sh
docker compose exec php composer ci     # vérifications
docker compose exec php composer test   # tests
```

Les tests ont besoin d'un compte MariaDB qui peut créer la base de test : voir la section « Tests » du [`CLAUDE.md`](CLAUDE.md).

## Livraison

Chaque fusion dans une branche de release publie l'image Docker et met à jour la recette. À la fusion de la release dans `main`, l'image recettée est livrée telle quelle, sans reconstruction : elle reçoit son numéro de version, et la mise en production attend l'approbation du développeur. Le détail est dans les sections « Publication et déploiement » et « Livraison » du [`CLAUDE.md`](CLAUDE.md).

## Projet Dramatoons

| Dépôt | Rôle |
| --- | --- |
| [dramatoons-api](https://github.com/remi-leconte/dramatoons-api) | API REST (ce dépôt) |
| [dramatoons-front](https://github.com/remi-leconte/dramatoons-front) | Site web (Vue 3, TypeScript) |
| [dramatoons-extension-chromium](https://github.com/remi-leconte/dramatoons-extension-chromium) | Extension pour les navigateurs Chromium |
| [dramatoons-extension-firefox](https://github.com/remi-leconte/dramatoons-extension-firefox) | Extension Firefox |
| [dramatoons-workspace](https://github.com/remi-leconte/dramatoons-workspace) | Espace de travail commun : méthode de travail avec Claude Code, règles métier, workflow |

## Licence

[MIT](LICENSE) © 2026 Dramatoons.ovh
