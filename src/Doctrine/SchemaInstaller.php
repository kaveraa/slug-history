<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * Creates the table of past addresses, once and only once.
 *
 * Used by the install command and by the tests: an application that prefers
 * its own migrations can copy the definition returned by table().
 */
final class SchemaInstaller
{
    /** The length of the indexed columns: a MySQL utf8mb4 index needs this limit. */
    private const LENGTH = 191;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = 'past_slugs',
    ) {
    }

    /**
     * Creates the table if it is missing. Returns false when there was nothing to do.
     */
    public function install(): bool
    {
        if ($this->exists()) {
            return false;
        }

        $this->connection->createSchemaManager()->createTable($this->table());

        return true;
    }

    public function exists(): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$this->table]);
    }

    public function name(): string
    {
        return $this->table;
    }

    /**
     * The table definition.
     *
     * scope is never null: on MySQL as on PostgreSQL, NULL is equal to nothing,
     * not even to itself, and a nullable column would break the uniqueness of
     * the (slug, scope) pair.
     */
    public function table(): Table
    {
        $table = new Table($this->table);

        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('subject_type', Types::STRING, ['length' => self::LENGTH, 'notnull' => true]);
        $table->addColumn('subject_id', Types::STRING, ['length' => self::LENGTH, 'notnull' => true]);
        $table->addColumn('slug', Types::STRING, ['length' => self::LENGTH, 'notnull' => true]);
        $table->addColumn('current_slug', Types::STRING, ['length' => self::LENGTH, 'notnull' => true]);
        $table->addColumn('scope', Types::STRING, ['length' => self::LENGTH, 'notnull' => true, 'default' => '']);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['slug', 'scope'], $this->table . '_slug_scope_unique');
        $table->addIndex(['subject_type', 'subject_id'], $this->table . '_subject_index');

        return $table;
    }
}
