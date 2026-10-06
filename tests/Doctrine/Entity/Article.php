<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;

/**
 * The ordinary case: a slug property, no scope.
 */
#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[KeepOldSlugs]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $slug = 'sans-titre',
        #[ORM\Column]
        public string $title = 'Sans titre',
    ) {
    }
}
