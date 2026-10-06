<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel;

use Illuminate\Support\Facades\DB;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Article;

/**
 * The full journey: we rename, the old address redirects.
 */
final class RedirectTest extends TestCase
{
    public function test_a_renamed_content_redirects_from_its_old_address(): void
    {
        $article = Article::query()->create(['slug' => 'mon-artcile', 'title' => 'Mon article']);

        $article->update(['slug' => 'mon-article']);

        $this->get('/articles/mon-artcile')
            ->assertStatus(301)
            ->assertRedirect('/articles/mon-article');

        $this->get('/articles/mon-article')->assertOk();
    }

    public function test_the_query_string_follows_the_redirection(): void
    {
        $article = Article::query()->create(['slug' => 'agenda', 'title' => 'Agenda']);

        $article->update(['slug' => 'agenda-2026']);

        $this->get('/articles/agenda?page=2&utm_source=lettre')
            ->assertRedirect('/articles/agenda-2026?page=2&utm_source=lettre');
    }

    public function test_the_very_first_address_leads_to_the_last_one_in_a_single_hop(): void
    {
        $article = Article::query()->create(['slug' => 'un', 'title' => 'Un']);

        $article->update(['slug' => 'deux']);
        $article->update(['slug' => 'trois']);

        $this->get('/articles/un')->assertRedirect('/articles/trois');
        $this->get('/articles/deux')->assertRedirect('/articles/trois');

        // One row per past address, and both lead to the same place.
        self::assertCount(2, $article->pastSlugs());
    }

    public function test_a_living_content_takes_the_address_back(): void
    {
        $article = Article::query()->create(['slug' => 'agenda', 'title' => 'Agenda']);
        $article->update(['slug' => 'agenda-2025']);

        $this->get('/articles/agenda')->assertRedirect('/articles/agenda-2025');

        // A new content takes the address back: the history must let it go.
        $reuse = Article::query()->create(['slug' => 'agenda', 'title' => 'Agenda 2026']);

        $this->get('/articles/agenda')->assertOk()->assertSee('article ' . $reuse->id);

        self::assertNull($this->history()->find('agenda'));
    }

    public function test_an_ordinary_404_stays_a_404(): void
    {
        $this->get('/articles/jamais-vu')->assertNotFound();
    }

    public function test_a_page_that_exists_never_touches_the_history_table(): void
    {
        Article::query()->create(['slug' => 'vivant', 'title' => 'Vivant']);

        $queries = $this->recordQueries(function (): void {
            $this->get('/articles/vivant')->assertOk();
        });

        self::assertNotSame([], $queries, 'la page doit bien avoir interrogé sa propre table');

        foreach ($queries as $sql) {
            self::assertStringNotContainsString('past_slugs', $sql, 'aucune requête sur l\'historique');
        }
    }

    public function test_a_post_request_is_left_alone(): void
    {
        $article = Article::query()->create(['slug' => 'ancien', 'title' => 'Ancien']);
        $article->update(['slug' => 'nouveau']);

        $this->post('/articles/ancien')->assertNotFound();
    }

    public function test_the_status_code_is_configurable(): void
    {
        $this->app->make('config')->set('slug-history.status', 308);

        $article = Article::query()->create(['slug' => 'ancien', 'title' => 'Ancien']);
        $article->update(['slug' => 'nouveau']);

        $this->get('/articles/ancien')->assertStatus(308);
    }

    /**
     * @return list<string>
     */
    private function recordQueries(callable $work): array
    {
        $queries = [];

        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $work();

        return $queries;
    }
}
