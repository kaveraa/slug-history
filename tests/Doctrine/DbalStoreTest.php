<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use InvalidArgumentException;
use Kaveraa\SlugHistory\Doctrine\DbalStore;
use Kaveraa\SlugHistory\Doctrine\SchemaInstaller;
use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\Store;
use Kaveraa\SlugHistory\Tests\Support\StoreContract;

/**
 * The DBAL store keeps exactly the same contract as the in-memory one.
 */
final class DbalStoreTest extends StoreContract
{
    private Connection $connection;

    private DbalStore $dbal;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        (new SchemaInstaller($this->connection))->install();

        $this->dbal = new DbalStore($this->connection);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    protected function store(): Store
    {
        return $this->dbal;
    }

    public function test_the_table_refuses_two_contents_behind_one_address(): void
    {
        $this->connection->insert('past_slugs', [
            'subject_type' => 'App\Entity\Article',
            'subject_id' => '1',
            'slug' => 'agenda',
            'current_slug' => 'agenda-2025',
            'scope' => '',
            'created_at' => '2026-01-10 09:00:00',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->connection->insert('past_slugs', [
            'subject_type' => 'App\Entity\Page',
            'subject_id' => '2',
            'slug' => 'agenda',
            'current_slug' => 'agenda-2026',
            'scope' => '',
            'created_at' => '2026-01-10 09:00:00',
        ]);
    }

    public function test_the_same_address_in_two_scopes_is_allowed_by_the_table(): void
    {
        $this->dbal->remember(new PastSlug('App\Entity\Article', 1, 'contact', 'nous-ecrire', 'fr'));
        $this->dbal->remember(new PastSlug('App\Entity\Article', 2, 'contact', 'write-to-us', 'en'));

        self::assertSame(
            2,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM past_slugs WHERE slug = ?', ['contact']),
        );
    }

    public function test_the_table_can_be_named_otherwise(): void
    {
        (new SchemaInstaller($this->connection, 'old_urls'))->install();

        $other = new DbalStore($this->connection, 'old_urls');
        $other->remember(new PastSlug('App\Entity\Article', 1, 'ancien', 'nouveau'));

        self::assertNotNull($other->find('ancien'));
        self::assertNull($this->dbal->find('ancien'), 'les deux tables sont bien distinctes');
    }

    public function test_a_table_name_that_is_not_one_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DbalStore($this->connection, 'past_slugs; DROP TABLE articles');
    }

    public function test_the_moment_survives_the_round_trip(): void
    {
        $this->dbal->remember(new PastSlug(
            'App\Entity\Article',
            1,
            'ancien',
            'nouveau',
            '',
            new \DateTimeImmutable('2026-01-10 09:30:00'),
        ));

        self::assertSame('2026-01-10 09:30:00', $this->dbal->find('ancien')?->rememberedAt?->format('Y-m-d H:i:s'));
    }
}
