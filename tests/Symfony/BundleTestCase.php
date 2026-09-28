<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Symfony;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Kaveraa\SlugHistory\Doctrine\SchemaInstaller;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Démarre l'application de test, crée le schéma, et remet tout en place après.
 */
abstract class BundleTestCase extends TestCase
{
    protected ?TestKernel $kernel = null;

    /** @var callable|null gestionnaire d'exceptions actif avant le démarrage du kernel */
    private mixed $exceptionHandler = null;

    protected function setUp(): void
    {
        $this->exceptionHandler = self::currentExceptionHandler();
    }

    protected function tearDown(): void
    {
        if ($this->kernel !== null) {
            (new Filesystem())->remove($this->kernel->getProjectDir());
            $this->kernel->shutdown();
            $this->kernel = null;
        }

        // Symfony installe parfois un gestionnaire d'exceptions sans le retirer.
        while (self::currentExceptionHandler() !== $this->exceptionHandler) {
            restore_exception_handler();
        }
    }

    /**
     * @param array<string, mixed> $config configuration slug_history
     */
    protected function boot(array $config = [], bool $install = true): ContainerInterface
    {
        $this->kernel = new TestKernel($config);

        (new Filesystem())->remove($this->kernel->getCacheDir());

        $this->kernel->boot();

        $container = $this->kernel->getContainer();

        $entities = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entities);

        (new SchemaTool($entities))->createSchema($entities->getMetadataFactory()->getAllMetadata());

        if ($install) {
            $installer = $container->get(SchemaInstaller::class);
            self::assertInstanceOf(SchemaInstaller::class, $installer);
            $installer->install();
        }

        return $container;
    }

    protected function entities(ContainerInterface $container): EntityManagerInterface
    {
        $entities = $container->get('doctrine.orm.entity_manager');

        self::assertInstanceOf(EntityManagerInterface::class, $entities);

        return $entities;
    }

    protected function connection(ContainerInterface $container): Connection
    {
        $connection = $container->get('doctrine.dbal.default_connection');

        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    protected function tester(ContainerInterface $container, string $command): CommandTester
    {
        $service = $container->get($command);

        self::assertInstanceOf(Command::class, $service);

        return new CommandTester($service);
    }

    private static function currentExceptionHandler(): mixed
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }
}
