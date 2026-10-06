<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Attribute;

use Attribute;

/**
 * To put on content whose address can change: the package remembers the old
 * ones and redirects to the new one.
 *
 *     #[KeepOldSlugs]
 *     #[KeepOldSlugs(property: 'permalink')]
 *     #[KeepOldSlugs(scope: 'locale')] // the same slug once per language
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class KeepOldSlugs
{
    public function __construct(
        /** The property or the column that holds the address. */
        public readonly string $property = 'slug',
        /** Optional: the property that makes the address unique (language, parent section). */
        public readonly ?string $scope = null,
    ) {
    }
}
