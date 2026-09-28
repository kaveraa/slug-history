<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory;

use DateTimeImmutable;

/**
 * Le rangement des anciennes adresses : une implémentation par ORM.
 *
 * Where the past addresses are kept: one implementation per ORM.
 *
 * Le couple (slug, scope) est unique : une adresse ne peut pas mener à deux
 * contenus à la fois.
 */
interface Store
{
    /**
     * Garde une ancienne adresse. Remplace la ligne existante pour le même
     * couple (slug, scope), s'il y en a une.
     */
    public function remember(PastSlug $past): void;

    /**
     * L'ancienne adresse cherchée, ou null. Le type restreint la recherche à
     * une seule classe de contenu.
     */
    public function find(string $slug, string $scope = '', ?string $type = null): ?PastSlug;

    /**
     * Un contenu vivant prend cette adresse : l'historique doit la lâcher,
     * sinon on redirigerait au nez et à la barbe du nouveau propriétaire.
     */
    public function release(string $slug, string $scope = '', ?string $type = null): void;

    /**
     * Le contenu a encore changé d'adresse : toutes ses anciennes adresses
     * pointent désormais vers la nouvelle.
     */
    public function retarget(string $type, int|string $id, string $currentSlug): void;

    /**
     * Le contenu n'existe plus : ses anciennes adresses ne mènent nulle part.
     */
    public function forget(string $type, int|string $id): void;

    /**
     * @return list<PastSlug>
     */
    public function allFor(string $type, int|string $id): array;

    /**
     * Efface les entrées plus vieilles que cette date. Renvoie le nombre effacé.
     */
    public function purge(DateTimeImmutable $before): int;
}
