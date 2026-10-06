<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\ServiceProvider;
use Kaveraa\SlugHistory\Laravel\Console\HistoryCommand;
use Kaveraa\SlugHistory\Laravel\Console\InstallCommand;
use Kaveraa\SlugHistory\Laravel\Console\PurgeCommand;
use Kaveraa\SlugHistory\Laravel\Middleware\RedirectToCurrentSlug;
use Kaveraa\SlugHistory\SlugHistory;
use Kaveraa\SlugHistory\Store;
use Kaveraa\SlugHistory\SystemClock;
use Psr\Clock\ClockInterface;

/**
 * Wires the package into Laravel: configuration, services, redirection.
 */
final class SlugHistoryServiceProvider extends ServiceProvider
{
    private const CONFIG = __DIR__ . '/../../config/slug-history.php';

    private const MIGRATION = __DIR__ . '/../../database/migrations/create_past_slugs_table.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG, 'slug-history');

        $this->app->singleton(Store::class, static fn (Application $app): Store => new EloquentStore(
            $app->make(ConnectionResolverInterface::class),
            (string) $app->make('config')->get('slug-history.table', 'past_slugs'),
            $app->make('config')->get('slug-history.connection'),
        ));

        $this->app->singleton(EloquentStore::class, static fn (Application $app): Store => $app->make(Store::class));

        // A clock already declared by the application keeps priority.
        $this->app->singletonIf(ClockInterface::class, SystemClock::class);

        $this->app->singleton(SlugHistory::class, static fn (Application $app): SlugHistory => new SlugHistory(
            $app->make(Store::class),
            $app->make(ClockInterface::class),
        ));

        $this->app->singleton(
            RedirectToCurrentSlug::class,
            static fn (Application $app): RedirectToCurrentSlug => new RedirectToCurrentSlug(
                $app->make(SlugHistory::class),
                (int) $app->make('config')->get('slug-history.status', 301),
                self::scope($app->make('config')->get('slug-history.scope', '')),
            ),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::CONFIG => config_path('slug-history.php')], 'slug-history-config');

            $this->publishes([
                self::MIGRATION => database_path('migrations/' . date('Y_m_d_His') . '_create_past_slugs_table.php'),
            ], 'slug-history-migrations');

            $this->commands([InstallCommand::class, PurgeCommand::class, HistoryCommand::class]);
        }

        if ((bool) $this->app->make('config')->get('slug-history.auto_redirect', true)) {
            $this->autoRedirect();
        }
    }

    /**
     * The middleware adds itself: a composer require and a migrate are enough.
     *
     * It is added as a global middleware, not in the "web" group. Reason: the
     * middlewares of a group only run on a route that was found. An old address
     * matches no route: the 404 would be raised before the group runs, and
     * nothing would catch it.
     */
    private function autoRedirect(): void
    {
        if (!$this->app->bound(Kernel::class)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->pushMiddleware(RedirectToCurrentSlug::class);
        }
    }

    private static function scope(mixed $configured): Closure|string
    {
        if ($configured instanceof Closure) {
            return $configured;
        }

        return is_string($configured) ? $configured : '';
    }
}
