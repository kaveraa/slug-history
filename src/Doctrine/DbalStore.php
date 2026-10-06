<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\Store;

/**
 * Where the past addresses are kept in a table, through Doctrine DBAL.
 *
 * On purpose, there is no mapped entity: the application has nothing to add
 * to its ORM configuration, and these writes can happen during a flush without
 * opening a second one.
 */
final class DbalStore implements Store
{
    /** The columns we read, in the order they are read back. */
    private const COLUMNS = 'id, subject_type, subject_id, slug, current_slug, scope, created_at';

    public function __construct(
        private readonly Connection $connection,
        /** The table name, if the application does not want the default one. */
        private readonly string $table = 'past_slugs',
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->table) !== 1) {
            throw new InvalidArgumentException(sprintf('Nom de table invalide : "%s".', $this->table));
        }
    }

    public function remember(PastSlug $past): void
    {
        // An address leads to only one place: the row that already had this
        // (slug, scope) pair makes room.
        $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE slug = ? AND scope = ?', $this->table),
            [$past->slug, $past->scope],
        );

        $this->connection->insert(
            $this->table,
            [
                'subject_type' => $past->type,
                'subject_id' => (string) $past->id,
                'slug' => $past->slug,
                'current_slug' => $past->currentSlug,
                'scope' => $past->scope,
                'created_at' => $past->rememberedAt ?? new DateTimeImmutable(),
            ],
            ['created_at' => Types::DATETIME_IMMUTABLE],
        );
    }

    public function find(string $slug, string $scope = '', ?string $type = null): ?PastSlug
    {
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT %s FROM %s WHERE slug = ? AND scope = ?', self::COLUMNS, $this->table),
            [$slug, $scope],
        );

        if ($row === false) {
            return null;
        }

        $found = $this->hydrate($row);

        return $type === null || $found->type === $type ? $found : null;
    }

    public function release(string $slug, string $scope = '', ?string $type = null): void
    {
        $sql = sprintf('DELETE FROM %s WHERE slug = ? AND scope = ?', $this->table);
        $params = [$slug, $scope];

        if ($type !== null) {
            $sql .= ' AND subject_type = ?';
            $params[] = $type;
        }

        $this->connection->executeStatement($sql, $params);
    }

    public function retarget(string $type, int|string $id, string $currentSlug): void
    {
        $this->connection->executeStatement(
            sprintf('UPDATE %s SET current_slug = ? WHERE subject_type = ? AND subject_id = ?', $this->table),
            [$currentSlug, $type, (string) $id],
        );
    }

    public function forget(string $type, int|string $id): void
    {
        $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE subject_type = ? AND subject_id = ?', $this->table),
            [$type, (string) $id],
        );
    }

    public function allFor(string $type, int|string $id): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT %s FROM %s WHERE subject_type = ? AND subject_id = ? ORDER BY id',
                self::COLUMNS,
                $this->table,
            ),
            [$type, (string) $id],
        );

        return array_map(fn (array $row): PastSlug => $this->hydrate($row), $rows);
    }

    public function purge(DateTimeImmutable $before): int
    {
        return (int) $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE created_at < ?', $this->table),
            [$before],
            [Types::DATETIME_IMMUTABLE],
        );
    }

    /**
     * A table row becomes a past address again.
     *
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PastSlug
    {
        $at = Type::getType(Types::DATETIME_IMMUTABLE)
            ->convertToPHPValue($row['created_at'], $this->connection->getDatabasePlatform());

        return new PastSlug(
            (string) $row['subject_type'],
            (string) $row['subject_id'],
            (string) $row['slug'],
            (string) $row['current_slug'],
            (string) $row['scope'],
            $at instanceof DateTimeImmutable ? $at : null,
        );
    }
}
