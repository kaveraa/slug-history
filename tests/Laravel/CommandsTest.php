<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel;

use DateTimeImmutable;
use Illuminate\Support\Facades\Artisan;
use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Article;
use Symfony\Component\Console\Command\Command as Console;

/**
 * Les trois commandes artisan.
 */
final class CommandsTest extends TestCase
{
    public function test_install_publishes_the_config_and_the_migration(): void
    {
        $output = $this->fire('slugs:install');

        self::assertStringContainsString('Étapes suivantes', $output);
        self::assertFileExists(config_path('slug-history.php'));
        self::assertNotSame([], $this->publishedMigrations());
    }

    public function test_purge_forgets_the_oldest_entries(): void
    {
        $this->rememberAt('vieux', 'actuel', '2025-01-01 00:00:00');
        $this->rememberAt('recent', 'actuel-2', '2026-01-09 00:00:00', id: 2);

        $output = $this->fire('slugs:purge', ['--older-than' => 30]);

        self::assertStringContainsString('1 ancienne(s) adresse(s) oubliée(s)', $output);
        self::assertNull($this->history()->find('vieux'));
        self::assertNotNull($this->history()->find('recent'));
    }

    public function test_purge_falls_back_on_the_configuration(): void
    {
        $this->app->make('config')->set('slug-history.keep_for_days', 30);

        $this->rememberAt('vieux', 'actuel', '2025-01-01 00:00:00');

        $this->fire('slugs:purge');

        self::assertNull($this->history()->find('vieux'));
    }

    public function test_purge_refuses_a_duration_that_makes_no_sense(): void
    {
        $this->withoutMockingConsoleOutput();

        self::assertSame(Console::FAILURE, $this->artisan('slugs:purge'));
        self::assertStringContainsString('Aucune durée de conservation', Artisan::output());

        self::assertSame(Console::FAILURE, $this->artisan('slugs:purge', ['--older-than' => 'longtemps']));
        self::assertStringContainsString('n\'est pas un nombre de jours', Artisan::output());

        self::assertSame(Console::FAILURE, $this->artisan('slugs:purge', ['--older-than' => '0']));
        self::assertStringContainsString('il en faut au moins 1', Artisan::output());
    }

    public function test_history_lists_the_past_addresses_of_a_content(): void
    {
        $article = Article::query()->create(['slug' => 'un', 'title' => 'Un']);
        $article->update(['slug' => 'deux']);
        $article->update(['slug' => 'trois']);

        $output = $this->fire('slugs:history', ['type' => Article::class, 'id' => (string) $article->id]);

        self::assertStringContainsString('Ancienne adresse', $output);
        self::assertStringContainsString('un', $output);
        self::assertStringContainsString('deux', $output);
        self::assertStringContainsString('trois', $output);
        self::assertStringContainsString('2 ancienne(s) adresse(s)', $output);
    }

    public function test_history_says_so_when_there_is_nothing(): void
    {
        $output = $this->fire('slugs:history', ['type' => Article::class, 'id' => '404']);

        self::assertStringContainsString('Aucune ancienne adresse', $output);
    }

    protected function tearDown(): void
    {
        $config = config_path('slug-history.php');

        if (file_exists($config)) {
            unlink($config);
        }

        foreach ($this->publishedMigrations() as $migration) {
            unlink($migration);
        }

        parent::tearDown();
    }

    private function rememberAt(string $slug, string $current, string $at, int $id = 1): void
    {
        $this->store()->remember(new PastSlug(
            Article::class,
            $id,
            $slug,
            $current,
            '',
            new DateTimeImmutable($at),
        ));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function fire(string $command, array $options = []): string
    {
        $this->withoutMockingConsoleOutput();

        self::assertSame(Console::SUCCESS, $this->artisan($command, $options));

        return Artisan::output();
    }

    /**
     * @return list<string>
     */
    private function publishedMigrations(): array
    {
        return array_values((array) glob(database_path('migrations/*_create_past_slugs_table.php')));
    }
}
