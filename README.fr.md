# Slug History

<p align="center"><img src="https://raw.githubusercontent.com/kaveraa/slug-history/main/art/banner.svg" alt="Slug History" width="100%"></p>

[![Tests](https://github.com/kaveraa/slug-history/actions/workflows/tests.yml/badge.svg)](https://github.com/kaveraa/slug-history/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/kaveraa/slug-history.svg)](https://packagist.org/packages/kaveraa/slug-history)
[![Téléchargements](https://img.shields.io/packagist/dt/kaveraa/slug-history.svg)](https://packagist.org/packages/kaveraa/slug-history)
[![PHP](https://img.shields.io/packagist/dependency-v/kaveraa/slug-history/php.svg)](https://packagist.org/packages/kaveraa/slug-history)
[![Licence](https://img.shields.io/github/license/kaveraa/slug-history.svg)](https://github.com/kaveraa/slug-history/blob/main/LICENSE)

[English](https://github.com/kaveraa/slug-history/blob/main/README.md) - **Français**

Quelqu'un corrige une faute dans le titre d'un article. Le slug change. Et d'un coup l'ancienne adresse renvoie un 404 : les liens entrants sont morts, les partages ne mènent plus nulle part, et le référencement patiemment construit repart de zéro.

Les paquets qui fabriquent des slugs sont partout et excellents. Aucun ne garde l'ancienne adresse. Celui-ci ne fait que ça, pour **Laravel** et pour **Symfony / Doctrine**.

```php
#[KeepOldSlugs]
class Article extends Model
{
    use HasSlugHistory;
}
```

```
GET /blog/mon-artcile     ->  301  /blog/mon-article
```

- **Rien à écrire** : après l'installation et la migration, la redirection est branchée. Vous renommez, les anciennes adresses continuent de fonctionner.
- **Une seule redirection** : une adresse abandonnée il y a trois renommages mène **directement** à l'adresse d'aujourd'hui. Jamais de chaîne de redirections, que les moteurs de recherche pénalisent.
- **Le contenu vivant gagne toujours** : si une autre page reprend une adresse libérée, l'historique la lâche aussitôt. Personne n'est redirigé loin de la page qu'il demande vraiment.
- **Aucune requête en trop** : la base n'est interrogée **que** lorsque la réponse est un 404. Une page qui existe ne coûte rien.
- **La chaîne de requête est conservée** : `?page=2&utm_source=newsletter` arrive intact sur la nouvelle adresse.
- **Multilingue** : le même slug peut vivre dans deux langues sans se mélanger.
- **Léger** : une table, une interface PSR, rien d'autre.

---

## Sommaire

- [Le problème](#le-problème)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Déclarer un contenu](#déclarer-un-contenu)
- [Les trois règles](#les-trois-règles)
- [Plusieurs langues, plusieurs rubriques](#plusieurs-langues-plusieurs-rubriques)
- [Quand un contenu est supprimé](#quand-un-contenu-est-supprimé)
- [Les commandes](#les-commandes)
- [Ce que cela coûte](#ce-que-cela-coûte)
- [Toutes les options](#toutes-les-options)
- [Reprendre l'historique en main](#reprendre-lhistorique-en-main)
- [Ce que ce paquet ne fait pas](#ce-que-ce-paquet-ne-fait-pas)
- [Développement](#développement)

## Le problème

Un slug se construit à partir d'un titre. Un titre, ça se corrige : une faute, un mot en trop, une reformulation après relecture. Chaque correction casse une adresse publique.

```
Publié en 2024      /blog/les-10-meilleurs-cadeaux-de-noel
Corrigé en 2026     /blog/les-10-meilleures-idees-cadeaux-de-noel
```

Entre les deux : les liens des newsletters envoyées, les partages sur les réseaux, les signets, les articles qui vous citent, et l'index des moteurs de recherche. Tout pointe vers la première adresse, qui ne répond plus.

La parade habituelle est une table de redirections remplie à la main, que personne ne tient à jour. Ce paquet la remplit tout seul.

## Prérequis

- PHP 8.2 ou plus.
- Laravel 12+, ou Symfony 7.2+ avec Doctrine ORM 3+ et DBAL 4+.
- Un contenu qui a déjà un slug. Ce paquet ne fabrique pas de slugs : gardez `spatie/laravel-sluggable`, `cviebrock/eloquent-sluggable`, l'extension Sluggable de Doctrine, ou votre propre code. Il se contente de retenir les anciens.

## Installation

```bash
composer require kaveraa/slug-history
```

### Laravel

```bash
php artisan slugs:install
php artisan migrate
```

C'est tout. La redirection est branchée toute seule.

Elle est posée dans la pile globale des middlewares, et non dans un groupe de routes : une ancienne adresse ne correspond à aucune route, donc un middleware de groupe ne la verrait jamais passer.

### Symfony

Ajoutez le bundle dans `config/bundles.php` :

```php
return [
    // ...
    Kaveraa\SlugHistory\Symfony\SlugHistoryBundle::class => ['all' => true],
];
```

Puis créez la table :

```bash
php bin/console slugs:install
```

## Déclarer un contenu

### Laravel

```php
use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;
use Kaveraa\SlugHistory\Laravel\Concerns\HasSlugHistory;

#[KeepOldSlugs]
class Article extends Model
{
    use HasSlugHistory;
}
```

Si votre colonne ne s'appelle pas `slug` :

```php
#[KeepOldSlugs(property: 'permalink')]
```

### Symfony et Doctrine

L'attribut suffit, il n'y a pas de trait à poser :

```php
use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;

#[ORM\Entity]
#[KeepOldSlugs]
class Article
{
    #[ORM\Column(length: 191, unique: true)]
    private string $slug;
}
```

## Les trois règles

Tout le paquet tient dans ces trois règles. Elles sont couvertes par des tests, dans les deux frameworks.

**1. Une adresse abandonnée mène à l'adresse d'aujourd'hui, en une seule fois.**

```
/blog/premier-titre   ->  301  /blog/titre-final
/blog/deuxieme-titre  ->  301  /blog/titre-final
```

Peu importe le nombre de renommages : jamais deux redirections à la suite.

**2. Un contenu vivant l'emporte toujours sur l'historique.**

L'article A libère `/agenda` en devenant `/agenda-2026`. Demain, l'article B est publié sous `/agenda`. À cet instant, l'historique lâche l'adresse : `/agenda` affiche l'article B, sans redirection. Sans cette règle, le paquet enverrait les visiteurs de B vers A, ce qui serait bien pire qu'un 404.

**3. La base n'est lue que sur un 404.**

Une page qui existe traverse le paquet sans une seule requête. L'historique n'est consulté qu'au moment où la réponse serait une erreur.

## Plusieurs langues, plusieurs rubriques

Beaucoup de sites autorisent le même slug plusieurs fois, à condition qu'il soit unique dans sa langue ou dans sa rubrique. Dites-le avec `scope` : c'est la propriété qui rend l'adresse unique.

```php
#[KeepOldSlugs(scope: 'locale')]
class Article extends Model
{
    use HasSlugHistory;
}
```

`/fr/contact` et `/en/contact` deviennent alors deux adresses indépendantes, avec chacune leur histoire.

Au moment de rediriger, l'adresse seule ne dit pas dans quelle portee chercher : donnez une fonction à l'option `scope`, elle reçoit la requête.

```php
// config/slug-history.php
'scope' => fn ($request) => $request->segment(1) ?? '', // la langue est le premier morceau du chemin
```

## Quand un contenu est supprimé

Ses anciennes adresses sont oubliées : elles ne doivent pas mener vers une page qui n'existe plus.

Avec les suppressions douces de Laravel (`SoftDeletes`), la mise à la corbeille **garde** l'historique, parce que le contenu peut revenir. C'est la suppression définitive (`forceDelete`) qui l'efface.

## Les commandes

```bash
php artisan slugs:history "App\Models\Article" 12   # les anciennes adresses d'un contenu
php artisan slugs:purge --older-than=365            # efface les entrees de plus d'un an
```

Sous Symfony : `php bin/console slugs:history` et `php bin/console slugs:purge`.

Faut-il purger ? Une entrée pèse quelques dizaines d'octets et une redirection qui marche encore dix ans plus tard n'a jamais gêné personne. Purgez seulement si la table devient vraiment grosse, ou si votre politique de conservation l'impose.

## Ce que cela coûte

- **Une table**, `past_slugs`, avec un index unique sur `(slug, scope)`.
- **Une ligne écrite par renommage**, pas par enregistrement : sauvegarder un article sans toucher au titre n'écrit rien.
- **Une requête par 404**, et zéro le reste du temps.

## Toutes les options

| Option | Défaut | Rôle |
|---|---|---|
| `table` | `past_slugs` | Nom de la table |
| `status` | `301` | Code de la redirection. `308` si vous tenez à conserver la méthode HTTP |
| `auto_redirect` | `true` | Branche la redirection toute seule, dans la pile globale. Mettez `false` pour la poser vous-même |
| `keep_for_days` | `null` | Durée de conservation par défaut de `slugs:purge`. `null` : pour toujours |
| `scope` | `''` | Portée par défaut. Une fonction recevant la requête est acceptée, pour un site multilingue |

## Reprendre l'historique en main

Le service est injectable, pour les cas particuliers : une reprise de données, une refonte d'URL, un import.

```php
use Kaveraa\SlugHistory\SlugHistory;

public function __construct(private SlugHistory $history)
{
}

// Rediriger une adresse qui n'a jamais existe dans votre base
$this->history->remember(Article::class, $article->id, 'ancienne-adresse', $article->slug);

// Rendre une adresse a un contenu vivant
$this->history->release('agenda');

// Ou mene cette adresse aujourd'hui ?
$this->history->newPathFor('/blog/mon-artcile'); // /blog/mon-article
```

## Ce que ce paquet ne fait pas

- **Il ne fabrique pas les slugs.** C'est le travail des paquets existants, qui le font bien.
- **Il ne gère pas les redirections décidées à la main** (une campagne, une adresse courte, un ancien site). Pour cela, une table de redirections classique reste le bon outil, et les deux cohabitent très bien.
- **Il ne connaît pas vos routes** : il remplace le morceau de chemin qui correspond à une ancienne adresse et redirige. Si votre structure d'URL change complètement, c'est une migration, pas un renommage.
- **Il ne réécrit pas vos pages** : les liens internes qui pointent vers l'ancienne adresse fonctionneront, mais par une redirection. Mettez-les à jour quand vous le pouvez.

## Développement

```bash
git clone https://github.com/kaveraa/slug-history.git
cd slug-history
composer install
vendor/bin/phpunit
```

Les trois rangements (mémoire, Eloquent, DBAL) passent le même contrat de test, `tests/Support/StoreContract.php` : c'est ce qui garantit que Laravel et Symfony se comportent exactement pareil.

Pour proposer une modification, lisez le guide [CONTRIBUTING.md](https://github.com/kaveraa/slug-history/blob/main/CONTRIBUTING.md). Voir le [CHANGELOG](https://github.com/kaveraa/slug-history/blob/main/CHANGELOG.md) pour l'historique des versions.

Pour signaler une faille, ouvrez une [alerte de sécurité privée](https://github.com/kaveraa/slug-history/security/advisories/new) plutôt qu'une issue publique.

## Licence

MIT. Voir [LICENSE](https://github.com/kaveraa/slug-history/blob/main/LICENSE).
