<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel;

use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Article;

/**
 * Le réglage auto_redirect à false : le paquet retient toujours les anciennes
 * adresses, mais ne pose plus le middleware.
 */
final class AutoRedirectOffTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make('config')->set('slug-history.auto_redirect', false);
    }

    public function test_nothing_is_redirected_when_the_middleware_is_not_wanted(): void
    {
        $article = Article::query()->create(['slug' => 'ancien', 'title' => 'Ancien']);
        $article->update(['slug' => 'nouveau']);

        $this->get('/articles/ancien')->assertNotFound();

        // L'historique, lui, a bien fait son travail.
        self::assertSame('nouveau', $this->history()->find('ancien')?->currentSlug);
    }
}
