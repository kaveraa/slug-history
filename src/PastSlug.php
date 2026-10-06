<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory;

use DateTimeImmutable;

/**
 * An address a piece of content used to have, and the one it has today.
 */
final class PastSlug
{
    public function __construct(
        /** The class of the content. */
        public readonly string $type,
        /** Its identifier. */
        public readonly int|string $id,
        /** The old address, the one still found in links and search engines. */
        public readonly string $slug,
        /** The current address, the one to redirect to. */
        public readonly string $currentSlug,
        /** Optional: the language, the parent section, what makes the slug unique. */
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
