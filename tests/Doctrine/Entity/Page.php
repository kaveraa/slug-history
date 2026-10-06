<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;

/**
 * The address is not always named slug.
 */
#[ORM\Entity]
#[ORM\Table(name: 'pages')]
#[KeepOldSlugs(property: 'permalink')]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $permalink = 'sans-adresse',
    ) {
    }
}
