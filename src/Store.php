<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory;

use DateTimeImmutable;

/**
 * Where the past addresses are kept: one implementation per ORM.
 *
 * The (slug, scope) pair is unique: an address cannot lead to two contents
 * at the same time.
 */
interface Store
{
    /**
     * Keeps an old address. Replaces the existing row for the same
     * (slug, scope) pair, if there is one.
     */
    public function remember(PastSlug $past): void;

    /**
     * The past address we look for, or null. The type limits the search to
     * one content class.
     */
    public function find(string $slug, string $scope = '', ?string $type = null): ?PastSlug;

    /**
     * A living content takes this address: the history must let it go,
     * otherwise we would redirect right under the nose of the new owner.
     */
    public function release(string $slug, string $scope = '', ?string $type = null): void;

    /**
     * The content changed its address again: all its past addresses now
     * point to the new one.
     */
    public function retarget(string $type, int|string $id, string $currentSlug): void;

    /**
     * The content no longer exists: its past addresses lead nowhere.
     */
    public function forget(string $type, int|string $id): void;

    /**
     * @return list<PastSlug>
     */
    public function allFor(string $type, int|string $id): array;

    /**
     * Deletes the entries older than this date. Returns how many were deleted.
     */
    public function purge(DateTimeImmutable $before): int;
}
