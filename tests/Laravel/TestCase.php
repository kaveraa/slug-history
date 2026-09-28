<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Schema;
use Kaveraa\SlugHistory\FrozenClock;
use Kaveraa\SlugHistory\Laravel\SlugHistoryServiceProvider;
use Kaveraa\SlugHistory\SlugHistory;
use Kaveraa\SlugHistory\Store;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Article;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Doc;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Translation;
use Orchestra\Testbench\TestCase as Testbench;
use Psr\Clock\ClockInterface;

/**
 * Socle des tests Laravel : le fournisseur de services, SQLite en mémoire, la
 * vraie migration du paquet, et quelques routes qui répondent par le slug.
 */
abstract class TestCase extends Testbench
{
    protected FrozenClock $clock;

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SlugHistoryServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->clock = FrozenClock::at('2026-01-10 09:00:00');
        $app->instance(ClockInterface::class, $this->clock);

        $config = $app->make('config');

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // La migration publiée du paquet, jouée telle quelle.
        $migration = require __DIR__ . '/../../database/migrations/create_past_slugs_table.php';
        $migration->up();

        Schema::create('articles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('slug');
            $table->string('title')->nullable();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('permalink');
            $table->string('title')->nullable();
        });

        Schema::create('notes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('reference');
        });

        Schema::create('translations', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('slug');
            $table->string('locale');
            $table->string('title')->nullable();
        });

        Schema::create('docs', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('slug');
            $table->softDeletes();
        });
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('/articles/{slug}', static function (string $slug): string {
            $article = Article::query()->where('slug', $slug)->first();

            abort_if($article === null, 404);

            return 'article ' . $article->id;
        });

        $router->get('/docs/{slug}', static function (string $slug): string {
            $doc = Doc::query()->where('slug', $slug)->first();

            abort_if($doc === null, 404);

            return 'doc ' . $doc->id;
        });

        // Une route POST qui répond 404 : de quoi vérifier que le middleware
        // ne touche pas aux méthodes autres que GET et HEAD.
        $router->post('/articles/{slug}', static function (string $slug): string {
            $article = Article::query()->where('slug', $slug)->first();

            abort_if($article === null, 404);

            return 'article ' . $article->id;
        });

        $router->get('/{locale}/pages/{slug}', static function (string $locale, string $slug): string {
            $page = Translation::query()->where('locale', $locale)->where('slug', $slug)->first();

            abort_if($page === null, 404);

            return 'page ' . $page->id;
        });
    }

    protected function history(): SlugHistory
    {
        return $this->app->make(SlugHistory::class);
    }

    protected function store(): Store
    {
        return $this->app->make(Store::class);
    }
}
