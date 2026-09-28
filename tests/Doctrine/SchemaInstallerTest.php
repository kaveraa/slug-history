<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Index;
use Kaveraa\SlugHistory\Doctrine\SchemaInstaller;
use PHPUnit\Framework\TestCase;

final class SchemaInstallerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function test_it_creates_the_table_once_and_says_so(): void
    {
        $installer = new SchemaInstaller($this->connection);

        self::assertFalse($installer->exists());
        self::assertTrue($installer->install(), 'la table manquait');
        self::assertTrue($installer->exists());
    }

    public function test_installing_twice_changes_nothing(): void
    {
        $installer = new SchemaInstaller($this->connection);
        $installer->install();

        $this->connection->insert('past_slugs', [
            'subject_type' => 'App\Entity\Article',
            'subject_id' => '1',
            'slug' => 'ancien',
            'current_slug' => 'nouveau',
            'scope' => '',
            'created_at' => '2026-01-10 09:00:00',
        ]);

        self::assertFalse($installer->install(), 'il n\'y avait rien à faire');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM past_slugs'));
    }

    public function test_the_scope_column_is_never_null(): void
    {
        $column = (new SchemaInstaller($this->connection))->table()->getColumn('scope');

        self::assertTrue($column->getNotnull(), 'NULL n\'étant égal à rien, il ferait sauter l\'unicité');
        self::assertSame('', $column->getDefault());
    }

    public function test_an_address_is_unique_in_its_scope_and_the_content_is_indexed(): void
    {
        $table = (new SchemaInstaller($this->connection))->table();

        $unique = array_values(array_filter(
            $table->getIndexes(),
            static fn (Index $index): bool => $index->isUnique() && !$index->isPrimary(),
        ));

        self::assertCount(1, $unique);
        self::assertSame(['slug', 'scope'], $unique[0]->getColumns());

        $plain = array_values(array_filter(
            $table->getIndexes(),
            static fn (Index $index): bool => !$index->isUnique() && !$index->isPrimary(),
        ));

        self::assertCount(1, $plain);
        self::assertSame(['subject_type', 'subject_id'], $plain[0]->getColumns());
    }

    public function test_the_table_can_be_named_otherwise(): void
    {
        $installer = new SchemaInstaller($this->connection, 'old_urls');

        self::assertSame('old_urls', $installer->name());
        self::assertTrue($installer->install());
        self::assertTrue($this->connection->createSchemaManager()->tablesExist(['old_urls']));
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['past_slugs']));
    }
}
