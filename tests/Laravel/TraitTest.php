<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel;

use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Article;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Doc;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Note;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Post;

/**
 * Le trait posé sur un modèle : ce qu'il retient, et ce qu'il laisse tomber.
 */
final class TraitTest extends TestCase
{
    public function test_it_lists_the_past_addresses(): void
    {
        $article = Article::query()->create(['slug' => 'un', 'title' => 'Un']);

        $article->update(['slug' => 'deux']);
        $article->update(['slug' => 'trois']);

        $slugs = array_map(static fn (PastSlug $past): string => $past->slug, $article->pastSlugs());

        sort($slugs);

        self::assertSame(['deux', 'un'], $slugs);
        self::assertSame(['trois', 'trois'], array_map(
            static fn (PastSlug $past): string => $past->currentSlug,
            $article->pastSlugs(),
        ));
    }

    public function test_nothing_is_kept_when_the_address_does_not_change(): void
    {
        $article = Article::query()->create(['slug' => 'stable', 'title' => 'Avant']);

        $article->update(['title' => 'Apres']);

        self::assertSame([], $article->pastSlugs());
    }

    public function test_the_attribute_names_the_column(): void
    {
        $post = Post::query()->create(['permalink' => 'ancien', 'title' => 'Ancien']);

        $post->update(['permalink' => 'nouveau']);

        self::assertSame('ancien', $post->pastSlugs()[0]->slug);
        self::assertSame('nouveau', $post->pastSlugs()[0]->currentSlug);
    }

    public function test_a_model_property_can_name_the_column_too(): void
    {
        $note = Note::query()->create(['reference' => 'ancienne']);

        $note->update(['reference' => 'nouvelle']);

        self::assertSame('ancienne', $note->pastSlugs()[0]->slug);
    }

    public function test_forget_past_slugs_empties_the_history(): void
    {
        $article = Article::query()->create(['slug' => 'un', 'title' => 'Un']);
        $article->update(['slug' => 'deux']);

        $article->forgetPastSlugs();

        self::assertSame([], $article->pastSlugs());
    }

    public function test_deleting_a_content_drops_its_addresses(): void
    {
        $article = Article::query()->create(['slug' => 'ancien', 'title' => 'Ancien']);
        $article->update(['slug' => 'nouveau']);

        $this->get('/articles/ancien')->assertRedirect('/articles/nouveau');

        $article->delete();

        self::assertSame([], $article->pastSlugs());
        $this->get('/articles/ancien')->assertNotFound();
    }

    public function test_the_wastebasket_keeps_the_addresses_and_the_force_delete_erases_them(): void
    {
        $doc = Doc::query()->create(['slug' => 'guide']);
        $doc->update(['slug' => 'guide-2026']);

        $doc->delete();

        // À la corbeille : le contenu peut revenir, ses adresses restent.
        self::assertTrue($doc->trashed());
        self::assertCount(1, $doc->pastSlugs());
        $this->get('/docs/guide')->assertRedirect('/docs/guide-2026');

        $doc->forceDelete();

        self::assertSame([], $doc->pastSlugs());
        $this->get('/docs/guide')->assertNotFound();
    }

    public function test_restoring_a_content_takes_its_address_back_from_the_history(): void
    {
        $doc = Doc::query()->create(['slug' => 'guide-2026']);
        $doc->delete();

        // Pendant ce temps, l'historique se met à revendiquer cette adresse.
        $this->store()->remember(new PastSlug('Autre\Contenu', 7, 'guide-2026', 'ailleurs', '', $this->clock->now()));

        self::assertNotNull($this->history()->find('guide-2026'));

        $doc->restore();

        self::assertNull($this->history()->find('guide-2026'), 'le contenu revenu reprend son adresse');
    }
}
