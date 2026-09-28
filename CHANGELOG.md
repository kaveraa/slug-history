# Changelog

**FR** Toutes les évolutions notables du paquet sont listées ici. Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte le [versionnage sémantique](https://semver.org/lang/fr/).

**EN** All important changes of the package are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [semantic versioning](https://semver.org/).

## [1.0.0] - 2026-09-28

### Ajouté / Added

- **FR** Les anciennes adresses d'un contenu sont retenues à chaque changement de slug, et l'ancienne adresse répond une redirection 301 vers l'adresse actuelle.
  **EN** The old addresses of a piece of content are remembered on every slug change, and the old address answers a 301 redirect to the current one.
- **FR** Une adresse abandonnée mène directement à l'adresse d'aujourd'hui, quel que soit le nombre de renommages : jamais de chaîne de redirections.
  **EN** A dropped address leads straight to today's address, whatever the number of renames: never a chain of redirects.
- **FR** Un contenu vivant qui reprend une adresse l'emporte toujours sur l'historique.
  **EN** Living content that takes an address back always wins over the history.
- **FR** Intégration Laravel : trait `HasSlugHistory`, pilote Eloquent, redirection branchée automatiquement sur le groupe `web`, commandes `slugs:install`, `slugs:purge` et `slugs:history`.
  **EN** Laravel side: `HasSlugHistory` trait, Eloquent store, redirect wired automatically on the `web` group, `slugs:install`, `slugs:purge` and `slugs:history` commands.
- **FR** Intégration Symfony et Doctrine : bundle configurable, rangement DBAL sans entité à déclarer, écoute des changements de slug par l'attribut `#[KeepOldSlugs]`, redirection sur les 404, commandes console.
  **EN** Symfony and Doctrine side: configurable bundle, DBAL store with no entity to declare, slug changes watched through the `#[KeepOldSlugs]` attribute, redirect on 404 answers, console commands.
- **FR** Portées (`scope`) pour les sites multilingues, conservation de la chaîne de requête, et un contrat de test partagé par les trois rangements.
  **EN** Scopes for multilingual sites, query string kept on redirect, and one test contract shared by the three stores.

[1.0.0]: https://github.com/kaveraa/slug-history/releases/tag/v1.0.0
