<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory;

use DateTimeImmutable;

/**
 * Une adresse qu'un contenu a portée, et l'adresse qu'il porte aujourd'hui.
 *
 * An address a piece of content used to have, and the one it has today.
 */
final class PastSlug
{
    public function __construct(
        /** La classe du contenu concerné. */
        public readonly string $type,
        /** Son identifiant. */
        public readonly int|string $id,
        /** L'ancienne adresse, celle qui traîne dans les liens et les moteurs. */
        public readonly string $slug,
        /** L'adresse actuelle, celle vers laquelle rediriger. */
        public readonly string $currentSlug,
        /** Facultatif : la langue, la rubrique parente, ce qui rend le slug unique. */
        public readonly string $scope = '',
        public readonly ?DateTimeImmutable $rememberedAt = null,
    ) {
    }

    public function stillUseful(): bool
    {
        return $this->slug !== $this->currentSlug;
    }

    public function withCurrentSlug(string $currentSlug): self
    {
        return new self($this->type, $this->id, $this->slug, $currentSlug, $this->scope, $this->rememberedAt);
    }
}
