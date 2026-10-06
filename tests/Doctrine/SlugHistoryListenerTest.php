<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Doctrine;

use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Article;
use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Comment;
use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Page;
use Kaveraa\SlugHistory\Tests\Doctrine\Entity\Recipe;

/**
 * The full journey: rename, rename again, make room, disappear.
 */
final class SlugHistoryListenerTest extends DoctrineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->listen();
    }

    public function test_renaming_a_content_keeps_its_past_address(): void
    {
        $article = new Article('mon-artcile');
        $this->save($article);

        $article->slug = 'mon-article';
        $this->em->flush();

        $past = $this->store->find('mon-artcile');

        self::assertNotNull($past);
        self::assertSame(Article::class, $past->type);
        self::assertSame((string) $article->id, (string) $past->id);
        self::assertSame('mon-article', $past->currentSlug);
        self::assertSame('2026-01-10 09:00:00', $past->rememberedAt?->format('Y-m-d H:i:s'));
    }

    public function test_two_renames_lead_straight_to_the_last_address(): void
    {
        $article = new Article('premier');
        $this->save($article);

        $article->slug = 'deuxieme';
        $this->em->flush();

        $article->slug = 'troisieme';
        $this->em->flush();

        // No chain of redirects: the first address leads straight to the
        // last one.
        self::assertSame('troisieme', $this->store->find('premier')?->currentSlug);
        self::assertSame('troisieme', $this->store->find('deuxieme')?->currentSlug);
        self::assertSame('/troisieme', $this->history->newPathFor('/premier'));
        self::assertCount(2, $this->store->allFor(Article::class, (int) $article->id));
    }

    public function test_a_new_content_takes_a_freed_address_back(): void
    {
        $article = new Article('agenda');
        $this->save($article);

        $article->slug = 'agenda-2025';
        $this->em->flush();

        self::assertNotNull($this->store->find('agenda'));

        // Another content takes the freed address: the history lets it go,
        // otherwise we would redirect right under the nose of its new owner.
        $this->save(new Article('agenda'));

        self::assertNull($this->store->find('agenda'));
        self::assertNull($this->history->newPathFor('/agenda'));
    }

    public function test_deleting_a_content_forgets_its_addresses(): void
    {
        $article = new Article('ancien');
        $this->save($article);

        $article->slug = 'nouveau';
        $this->em->flush();

        self::assertCount(1, $this->store->allFor(Article::class, (int) $article->id));

        $this->em->remove($article);
        $this->em->flush();

        self::assertNull($this->store->find('ancien'));
        self::assertSame([], $this->store->allFor(Article::class, (int) $article->id));
    }

    public function test_the_property_can_be_named_otherwise(): void
    {
        $page = new Page('mentions-legale');
        $this->save($page);

        $page->permalink = 'mentions-legales';
        $this->em->flush();

        self::assertSame('mentions-legales', $this->store->find('mentions-legale')?->currentSlug);
    }

    public function test_the_same_address_can_be_renamed_in_two_languages(): void
    {
        $french = new Recipe('crepes', 'fr');
        $english = new Recipe('crepes', 'en');
        $this->save($french, $english);

        $french->slug = 'crepes-de-la-chandeleur';
        $english->slug = 'pancakes';
        $this->em->flush();

        self::assertSame('crepes-de-la-chandeleur', $this->store->find('crepes', 'fr')?->currentSlug);
        self::assertSame('pancakes', $this->store->find('crepes', 'en')?->currentSlug);
        self::assertNull($this->store->find('crepes'));
    }

    public function test_a_content_without_the_attribute_is_left_alone(): void
    {
        $comment = new Comment('premier-jet');
        $this->save($comment);

        $comment->slug = 'second-jet';
        $this->em->flush();

        self::assertNull($this->store->find('premier-jet'));

        $this->em->remove($comment);
        $this->em->flush();

        self::assertSame([], $this->store->allFor(Comment::class, 1));
    }

    public function test_another_change_than_the_address_remembers_nothing(): void
    {
        $article = new Article('mon-article', 'Mon article');
        $this->save($article);

        $article->title = 'Mon bel article';
        $this->em->flush();

        self::assertSame([], $this->store->allFor(Article::class, (int) $article->id));
    }

    public function test_a_content_loaded_as_a_proxy_is_followed_too(): void
    {
        $article = new Article('mon-artcile');
        $this->save($article);

        $id = (int) $article->id;
        $this->em->clear();

        $proxy = $this->em->getReference(Article::class, $id);

        self::assertNotNull($proxy);

        $proxy->slug = 'mon-article';
        $this->em->flush();

        $past = $this->store->find('mon-artcile');

        self::assertNotNull($past);
        self::assertSame(Article::class, $past->type, 'jamais le nom de classe du proxy');
        self::assertSame('mon-article', $past->currentSlug);
    }
}
