<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\Store;
use stdClass;

/**
 * Le rangement des anciennes adresses dans une table, via le constructeur de
 * requêtes. Pas de modèle Eloquent : une simple table, sans événements.
 *
 * Where past addresses are kept, in a plain table, through the query builder.
 * No Eloquent model: just a table, with no events of its own.
 */
final class EloquentStore implements Store
{
    /** Le format que toutes les bases comprennent. */
    private const DATE = 'Y-m-d H:i:s';

    public function __construct(
        private readonly ConnectionResolverInterface $connections,
        private readonly string $table = 'past_slugs',
        private readonly ?string $connection = null,
    ) {
    }

    public function remember(PastSlug $past): void
    {
        // Le couple (slug, scope) porte l'index unique : la ligne existante est
        // reprise, même si elle appartenait à un autre contenu.
        $this->rows()->upsert(
            [[
                'subject_type' => $past->type,
                'subject_id' => (string) $past->id,
                'slug' => $past->slug,
                'current_slug' => $past->currentSlug,
                'scope' => $past->scope,
                'created_at' => ($past->rememberedAt ?? new DateTimeImmutable())->format(self::DATE),
            ]],
            ['slug', 'scope'],
            ['subject_type', 'subject_id', 'current_slug', 'created_at'],
        );
    }

    public function find(string $slug, string $scope = '', ?string $type = null): ?PastSlug
    {
        $query = $this->rows()->where('slug', $slug)->where('scope', $scope);

        if ($type !== null) {
            $query->where('subject_type', $type);
        }

        $row = $query->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function release(string $slug, string $scope = '', ?string $type = null): void
    {
        $query = $this->rows()->where('slug', $slug)->where('scope', $scope);

        if ($type !== null) {
            $query->where('subject_type', $type);
        }

        $query->delete();
    }

    public function retarget(string $type, int|string $id, string $currentSlug): void
    {
        $this->of($type, $id)->update(['current_slug' => $currentSlug]);
    }

    public function forget(string $type, int|string $id): void
    {
        $this->of($type, $id)->delete();
    }

    /**
     * @return list<PastSlug>
     */
    public function allFor(string $type, int|string $id): array
    {
        $rows = $this->of($type, $id)->orderBy('id')->get();

        $found = [];

        foreach ($rows as $row) {
            $found[] = $this->hydrate($row);
        }

        return $found;
    }

    public function purge(DateTimeImmutable $before): int
    {
        return $this->rows()->where('created_at', '<', $before->format(self::DATE))->delete();
    }

    private function of(string $type, int|string $id): Builder
    {
        return $this->rows()->where('subject_type', $type)->where('subject_id', (string) $id);
    }

    private function rows(): Builder
    {
        return $this->db()->table($this->table);
    }

    private function db(): ConnectionInterface
    {
        return $this->connections->connection($this->connection);
    }

    /**
     * L'identifiant revient toujours en chaîne : la colonne accueille aussi bien
     * un entier auto-incrémenté qu'un UUID.
     */
    private function hydrate(stdClass $row): PastSlug
    {
        $at = $row->created_at ?? null;

        return new PastSlug(
            (string) $row->subject_type,
            (string) $row->subject_id,
            (string) $row->slug,
            (string) $row->current_slug,
            (string) ($row->scope ?? ''),
            $at === null ? null : new DateTimeImmutable((string) $at),
        );
    }
}
