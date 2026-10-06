<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Symfony;

use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\Store;
use Kaveraa\SlugHistory\Symfony\Command\HistoryCommand;
use Kaveraa\SlugHistory\Symfony\Command\InstallCommand;
use Kaveraa\SlugHistory\Symfony\Command\PurgeCommand;
use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Article;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;

final class CommandsTest extends BundleTestCase
{
    public function test_install_creates_the_table_then_says_there_is_nothing_to_do(): void
    {
        $container = $this->boot(install: false);

        $tester = $this->tester($container, InstallCommand::class);
        $tester->execute([], ['decorated' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('past_slugs', $tester->getDisplay());
        self::assertStringContainsString('créée', $tester->getDisplay());

        $again = $this->tester($container, InstallCommand::class);
        $again->execute([], ['decorated' => false]);

        self::assertSame(Command::SUCCESS, $again->getStatusCode());
        self::assertStringContainsString('existe déjà', $again->getDisplay());
    }

    public function test_purge_uses_the_number_of_days_that_is_asked_for(): void
    {
        $container = $this->boot();
        $this->seed($container);

        $tester = $this->tester($container, PurgeCommand::class);
        $tester->execute(['--older-than' => '30'], ['decorated' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 ancienne(s) adresse(s)', $tester->getDisplay());

        $store = $this->store($container);

        self::assertNull($store->find('vieux'));
        self::assertNotNull($store->find('recent'));
    }

    public function test_purge_falls_back_on_the_configured_duration(): void
    {
        $container = $this->boot(['keep_for_days' => 30]);
        $this->seed($container);

        $tester = $this->tester($container, PurgeCommand::class);
        $tester->execute([], ['decorated' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('30 jour(s)', $tester->getDisplay());
        self::assertNull($this->store($container)->find('vieux'));
    }

    public function test_purge_refuses_to_work_without_a_duration(): void
    {
        $container = $this->boot();
        $this->seed($container);

        $tester = $this->tester($container, PurgeCommand::class);
        $tester->execute([], ['decorated' => false]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Aucune durée de conservation', $tester->getDisplay());
        self::assertNotNull($this->store($container)->find('vieux'), 'rien n\'a été effacé');
    }

    public function test_purge_refuses_a_duration_that_makes_no_sense(): void
    {
        $container = $this->boot(['keep_for_days' => 30]);
        $this->seed($container);

        foreach (['0', '-5', 'beaucoup', '2,5'] as $given) {
            $tester = $this->tester($container, PurgeCommand::class);
            $tester->execute(['--older-than' => $given], ['decorated' => false]);

            self::assertSame(Command::FAILURE, $tester->getStatusCode(), $given);
            self::assertStringContainsString('Durée invalide', $tester->getDisplay(), $given);
        }

        self::assertNotNull($this->store($container)->find('vieux'), 'rien n\'a été effacé');
    }

    public function test_history_lists_the_past_addresses_of_one_content(): void
    {
        $container = $this->boot();
        $this->seed($container);

        $tester = $this->tester($container, HistoryCommand::class);
        $tester->execute(['type' => Article::class, 'id' => '1'], ['decorated' => false]);

        $output = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('vieux', $output);
        self::assertStringContainsString('actuel', $output);
        self::assertStringContainsString('recent', $output);
    }

    public function test_history_says_plainly_when_there_is_nothing(): void
    {
        $container = $this->boot();

        $tester = $this->tester($container, HistoryCommand::class);
        $tester->execute(['type' => Article::class, 'id' => '404'], ['decorated' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Aucune ancienne adresse', $tester->getDisplay());
    }

    /**
     * One very old address, one brand new.
     */
    private function seed(ContainerInterface $container): void
    {
        $store = $this->store($container);

        $store->remember(new PastSlug(Article::class, 1, 'vieux', 'actuel', '', new \DateTimeImmutable('-2 years')));
        $store->remember(new PastSlug(Article::class, 1, 'recent', 'actuel', '', new \DateTimeImmutable('-1 day')));
    }

    private function store(ContainerInterface $container): Store
    {
        $store = $container->get(Store::class);

        self::assertInstanceOf(Store::class, $store);

        return $store;
    }
}
