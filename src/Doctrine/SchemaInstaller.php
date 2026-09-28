<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * Crée la table des anciennes adresses, une fois et une seule.
 *
 * Creates the table of past addresses, once and only once.
 *
 * Sert à la commande d'installation et aux tests : une application qui préfère
 * ses propres migrations peut recopier la définition renvoyée par table().
 */
final class SchemaInstaller
{
    /** La longueur des colonnes indexées : un index MySQL utf8mb4 tient à ce prix. */
    private const LENGTH = 191;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = 'past_slugs',
    ) {
    }

    /**
     * Crée la table si elle manque. Renvoie false quand il n'y avait rien à faire.
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
     * La définition de la table.
     *
     * scope n'est jamais nul : sur MySQL comme sur PostgreSQL, NULL n'est égal à
     * rien, pas même à lui-même, et une colonne nullable ferait sauter l'unicité
     * du couple (slug, scope).
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
