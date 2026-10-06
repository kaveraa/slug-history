<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Without the attribute: the package must never touch it, even if the entity
 * has a property named slug.
 */
#[ORM\Entity]
#[ORM\Table(name: 'comments')]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $slug = 'sans-titre',
    ) {
    }
}
