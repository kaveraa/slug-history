<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory;

use DateTimeImmutable;
use Kaveraa\SlugHistory\Exception\InvalidPurge;
use Psr\Clock\ClockInterface;

/**
 * The entry point: remember an old address, and find where it leads today.
 */
final class SlugHistory
{
    public function __construct(
        private readonly Store $store,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
    }

    /**
     * The content changes its address. Returns false when there is nothing to keep.
     */
    public function remember(string $type, int|string $id, string $oldSlug, string $newSlug, string $scope = ''): bool
    {
        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) {
            return false;
        }

        // The new address now belongs to a living content: if the history
        // still claimed it, it lets it go.
        $this->store->release($newSlug, $scope);

        // The past addresses already known must follow the content.
        $this->store->retarget($type, $id, $newSlug);

        $this->store->remember(new PastSlug($type, $id, $oldSlug, $newSlug, $scope, $this->clock->now()));

        return true;
    }

    /**
     * A living content takes this address: the history lets it go.
     */
    public function release(string $slug, string $scope = '', ?string $type = null): void
    {
        $this->store->release($slug, $scope, $type);
    }

    public function find(string $slug, string $scope = '', ?string $type = null): ?PastSlug
    {
        return $this->store->find($slug, $scope, $type);
    }

    /**
     * The content no longer exists: its past addresses are forgotten.
     */
    public function forget(string $type, int|string $id): void
    {
        $this->store->forget($type, $id);
    }

    /**
     * @return list<PastSlug>
     */
    public function allFor(string $type, int|string $id): array
    {
        return $this->store->allFor($type, $id);
    }

    /**
     * Deletes the entries older than this number of days.
     *
     * A negative number is refused: it would empty the whole table, silently
     * and with no way back. Zero is accepted, but it deletes everything too.
     */
    public function purgeOlderThan(int $days): int
    {
        if ($days < 0) {
            throw InvalidPurge::negative($days);
        }

        return $this->store->purge($this->clock->now()->modify('-' . $days . ' days'));
    }

    /**
     * The path to redirect to, or null when this path has no known past
     * address.
     *
     * We look at the pieces of the path from the most specific to the most
     * general: in /blog/categorie/mon-article, the last one names the content.
     */
    public function newPathFor(string $path, string $scope = '', ?string $type = null): ?string
    {
        $segments = Path::segments($path);

        for ($index = count($segments) - 1; $index >= 0; --$index) {
            $past = $this->store->find($segments[$index], $scope, $type);

            if ($past === null || !$past->stillUseful()) {
                continue;
            }

            $rebuilt = Path::replaceSegment($path, $index, $past->currentSlug);

            return $rebuilt === $path ? null : $rebuilt;
        }

        return null;
    }

    public function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
