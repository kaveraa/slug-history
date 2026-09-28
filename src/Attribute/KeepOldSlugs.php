<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Attribute;

use Attribute;

/**
 * À poser sur un contenu dont l'adresse peut changer : le paquet retient les
 * anciennes et redirige vers la nouvelle.
 *
 * To put on content whose address can change: the package remembers the old
 * ones and redirects to the new one.
 *
 *     #[KeepOldSlugs]
 *     #[KeepOldSlugs(property: 'permalink')]
 *     #[KeepOldSlugs(scope: 'locale')] // un même slug par langue
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class KeepOldSlugs
{
    public function __construct(
        /** La propriété ou la colonne qui porte l'adresse. */
        public readonly string $property = 'slug',
        /** Facultatif : la propriété qui rend l'adresse unique (langue, rubrique parente). */
        public readonly ?string $scope = null,
    ) {
    }
}
