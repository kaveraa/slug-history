<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;

/**
 * The same slug can exist in two languages: the scope keeps them apart.
 */
#[ORM\Entity]
#[ORM\Table(name: 'recipes')]
#[KeepOldSlugs(scope: 'locale')]
class Recipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $slug = 'sans-titre',
        #[ORM\Column]
        public string $locale = 'fr',
    ) {
    }
}
