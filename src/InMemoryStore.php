<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory;

use DateTimeImmutable;

/**
 * Un rangement en mémoire : pour les tests, et pour une application qui ne veut
 * pas de table.
 */
final class InMemoryStore implements Store
{
    /** @var array<string, PastSlug> */
    private array $rows = [];

    public function remember(PastSlug $past): void
    {
        $this->rows[self::key($past->slug, $past->scope)] = $past;
    }

    public function find(string $slug, string $scope = '', ?string $type = null): ?PastSlug
    {
        $found = $this->rows[self::key($slug, $scope)] ?? null;

        if ($found === null) {
            return null;
        }

        return $type === null || $found->type === $type ? $found : null;
    }

    public function release(string $slug, string $scope = '', ?string $type = null): void
    {
        $found = $this->find($slug, $scope, $type);

        if ($found !== null) {
            unset($this->rows[self::key($slug, $scope)]);
        }
    }

    public function retarget(string $type, int|string $id, string $currentSlug): void
    {
        foreach ($this->rows as $key => $row) {
            if ($row->type === $type && (string) $row->id === (string) $id) {
                $this->rows[$key] = $row->withCurrentSlug($currentSlug);
            }
        }
    }

    public function forget(string $type, int|string $id): void
    {
        foreach ($this->rows as $key => $row) {
            if ($row->type === $type && (string) $row->id === (string) $id) {
                unset($this->rows[$key]);
            }
        }
    }

    public function allFor(string $type, int|string $id): array
    {
        $found = array_filter(
            $this->rows,
            static fn (PastSlug $row): bool => $row->type === $type && (string) $row->id === (string) $id,
        );

        return array_values($found);
    }

    public function purge(DateTimeImmutable $before): int
    {
        $gone = 0;

        foreach ($this->rows as $key => $row) {
            if ($row->rememberedAt !== null && $row->rememberedAt < $before) {
                unset($this->rows[$key]);
                ++$gone;
            }
        }

        return $gone;
    }

    /**
     * @return list<PastSlug>
     */
    public function all(): array
    {
        return array_values($this->rows);
    }

    private static function key(string $slug, string $scope): string
    {
        return $scope . "\0" . $slug;
    }
}
