# Changelog

**FR** Toutes les évolutions notables du paquet sont listées ici. Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte le [versionnage sémantique](https://semver.org/lang/fr/).

**EN** All important changes of the package are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [semantic versioning](https://semver.org/).

## [Unreleased]

### Maintenance

- **FR** PHPUnit 13 accepté en développement. Aucun changement dans le code.
  **EN** PHPUnit 13 allowed for development. No code change.

## [1.0.3] - 2026-09-29

### Maintenance

- **FR** Configuration Dependabot ajoutée et actions de la CI mises à jour (`actions/checkout` v7, `ramsey/composer-install` v4). Aucun changement dans le code.
  **EN** Dependabot configuration added and CI actions updated (`actions/checkout` v7, `ramsey/composer-install` v4). No code change.

## [1.0.2] - 2026-09-29

### Documentation

- **FR** La description du paquet dans `composer.json` est maintenant en anglais.
  **EN** The package description in `composer.json` is now in English.

## [1.0.1] - 2026-09-29

### Documentation

- **FR** Le README affiché par défaut est maintenant en anglais (`README.md`), le français est dans `README.fr.md`.
  **EN** The default README is now in English (`README.md`), the French version is in `README.fr.md`.

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

[1.0.3]: https://github.com/kaveraa/slug-history/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/kaveraa/slug-history/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/kaveraa/slug-history/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/kaveraa/slug-history/releases/tag/v1.0.0
