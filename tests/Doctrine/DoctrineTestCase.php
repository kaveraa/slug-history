<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Kaveraa\SlugHistory\Doctrine\DbalStore;
use Kaveraa\SlugHistory\Doctrine\SchemaInstaller;
use Kaveraa\SlugHistory\Doctrine\SlugHistoryListener;
use Kaveraa\SlugHistory\FrozenClock;
use Kaveraa\SlugHistory\SlugHistory;
use PHPUnit\Framework\TestCase;

/**
 * An in-memory SQLite EntityManager, the test entities, the table of past
 * addresses and a clock that does not move.
 */
abstract class DoctrineTestCase extends TestCase
{
    protected EntityManagerInterface $em;

    protected Connection $connection;

    protected DbalStore $store;

    protected SlugHistory $history;

    protected FrozenClock $clock;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/Entity'], true);

        if (method_exists($config, 'enableNativeLazyObjects') && \PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($this->connection, $config);

        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        (new SchemaInstaller($this->connection))->install();

        $this->clock = FrozenClock::at('2026-01-10 09:00:00');
        $this->store = new DbalStore($this->connection);
        $this->history = new SlugHistory($this->store, $this->clock);
    }

    protected function tearDown(): void
    {
        $this->em->close();
    }

    /**
     * Wires the package listener into the EntityManager of this test.
     */
    protected function listen(): SlugHistoryListener
    {
        $listener = new SlugHistoryListener($this->history);

        $this->em->getEventManager()->addEventListener(
            [Events::postPersist, Events::postUpdate, Events::preRemove, Events::postRemove],
            $listener,
        );

        return $listener;
    }

    protected function save(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }
}
