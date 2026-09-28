<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Sans l'attribut : le paquet ne doit jamais s'en occuper, même si l'entité a
 * une propriété qui s'appelle slug.
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
