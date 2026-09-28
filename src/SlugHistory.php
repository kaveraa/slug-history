<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory;

use DateTimeImmutable;
use Kaveraa\SlugHistory\Exception\InvalidPurge;
use Psr\Clock\ClockInterface;

/**
 * Le point d'entrée : retenir une ancienne adresse, et retrouver où elle mène
 * aujourd'hui.
 *
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
     * Le contenu change d'adresse. Renvoie false quand il n'y a rien à retenir.
     */
    public function remember(string $type, int|string $id, string $oldSlug, string $newSlug, string $scope = ''): bool
    {
        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) {
            return false;
        }

        // La nouvelle adresse appartient maintenant à un contenu vivant : si
        // l'historique la revendiquait encore, il la lâche.
        $this->store->release($newSlug, $scope);

        // Les anciennes adresses déjà connues doivent suivre le contenu.
        $this->store->retarget($type, $id, $newSlug);

        $this->store->remember(new PastSlug($type, $id, $oldSlug, $newSlug, $scope, $this->clock->now()));

        return true;
    }

    /**
     * Un contenu vivant prend cette adresse : l'historique la lâche.
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
     * Le contenu n'existe plus : ses anciennes adresses sont oubliées.
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
     * Efface les entrées plus vieilles que ce nombre de jours.
     *
     * Un nombre négatif est refusé : il viderait toute la table, en silence et
     * sans retour possible. Zéro est accepté, mais il efface tout aussi.
     */
    public function purgeOlderThan(int $days): int
    {
        if ($days < 0) {
            throw InvalidPurge::negative($days);
        }

        return $this->store->purge($this->clock->now()->modify('-' . $days . ' days'));
    }

    /**
     * Le chemin vers lequel rediriger, ou null quand ce chemin n'a pas d'ancienne
     * adresse connue.
     *
     * On regarde les morceaux du chemin du plus précis au plus général : dans
     * /blog/categorie/mon-article, c'est le dernier qui désigne le contenu.
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
