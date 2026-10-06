<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Unit;

use Kaveraa\SlugHistory\Exception\InvalidPurge;
use Kaveraa\SlugHistory\FrozenClock;
use Kaveraa\SlugHistory\InMemoryStore;
use Kaveraa\SlugHistory\SlugHistory;
use PHPUnit\Framework\TestCase;

final class SlugHistoryTest extends TestCase
{
    private const ARTICLE = 'App\Entity\Article';

    private InMemoryStore $store;
    private FrozenClock $clock;
    private SlugHistory $history;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->clock = FrozenClock::at('2026-01-10 09:00:00');
        $this->history = new SlugHistory($this->store, $this->clock);
    }

    public function test_it_remembers_the_address_a_page_used_to_have(): void
    {
        self::assertTrue($this->history->remember(self::ARTICLE, 1, 'mon-artcile', 'mon-article'));

        $past = $this->history->find('mon-artcile');

        self::assertNotNull($past);
        self::assertSame(self::ARTICLE, $past->type);
        self::assertSame(1, $past->id);
        self::assertSame('mon-article', $past->currentSlug);
        self::assertSame('2026-01-10 09:00:00', $past->rememberedAt?->format('Y-m-d H:i:s'));
    }

    public function test_there_is_nothing_to_remember_when_the_address_does_not_change(): void
    {
        self::assertFalse($this->history->remember(self::ARTICLE, 1, 'mon-article', 'mon-article'));
        self::assertFalse($this->history->remember(self::ARTICLE, 1, '', 'mon-article'));
        self::assertFalse($this->history->remember(self::ARTICLE, 1, 'mon-article', ''));

        self::assertSame([], $this->store->all());
    }

    public function test_two_renames_in_a_row_do_not_make_a_chain(): void
    {
        $this->history->remember(self::ARTICLE, 1, 'premier', 'deuxieme');
        $this->history->remember(self::ARTICLE, 1, 'deuxieme', 'troisieme');

        // The very first address leads straight to the last one: a single
        // redirect for the visitor, and for the search engine.
        self::assertSame('troisieme', $this->history->find('premier')?->currentSlug);
        self::assertSame('troisieme', $this->history->find('deuxieme')?->currentSlug);
        self::assertSame('/blog/troisieme', $this->history->newPathFor('/blog/premier'));
    }

    public function test_a_living_page_takes_its_address_back(): void
    {
        $this->history->remember(self::ARTICLE, 1, 'agenda', 'agenda-2026');

        self::assertSame('/agenda-2026', $this->history->newPathFor('/agenda'));

        // Another content published today takes the freed address: the
        // history must let it go, otherwise we would redirect the visitor
        // away from the page they really asked for.
        $this->history->release('agenda');

        self::assertNull($this->history->find('agenda'));
        self::assertNull($this->history->newPathFor('/agenda'));
    }

    public function test_renaming_to_a_freed_address_also_releases_it(): void
    {
        $this->history->remember(self::ARTICLE, 1, 'agenda', 'agenda-2026');
        $this->history->remember(self::ARTICLE, 2, 'evenements', 'agenda');

        // "agenda" belongs to a living content again, number 2.
        self::assertNull($this->history->find('agenda'));
        self::assertSame('agenda', $this->history->find('evenements')?->currentSlug);
    }

    public function test_two_pages_can_swap_their_addresses_without_a_loop(): void
    {
        // The worst case: two contents swap their addresses. If both entries
        // survived, /agenda would lead to /programme which would lead to
        // /agenda, and the visitor would go round in circles until the
        // browser gives an error.
        $this->history->remember(self::ARTICLE, 1, 'agenda', 'programme');
        $this->history->remember(self::ARTICLE, 2, 'programme', 'agenda');

        $this->assertNoLoop($this->store, $this->history);

        // Not in the other order either.
        $store = new InMemoryStore();
        $other = new SlugHistory($store, $this->clock);

        $other->remember(self::ARTICLE, 2, 'programme', 'agenda');
        $other->remember(self::ARTICLE, 1, 'agenda', 'programme');

        $this->assertNoLoop($store, $other);
    }

    /**
     * The invariant of the package: no target address is itself a past
     * address. So a redirect can never call another one, whatever the order
     * of the renames.
     */
    private function assertNoLoop(InMemoryStore $store, SlugHistory $history): void
    {
        self::assertNotSame([], $store->all());

        foreach ($store->all() as $row) {
            self::assertNull(
                $history->newPathFor('/' . $row->currentSlug, $row->scope),
                sprintf('l\'adresse d\'arrivée "%s" redirige encore', $row->currentSlug),
            );
        }
    }

    public function test_only_the_most_precise_segment_is_replaced(): void
    {
        // A section and an article that both have a past address: we only
        // touch the last piece, the one that names the content. Replacing
        // the prefix too would risk building an address that does not
        // exist.
        $this->history->remember(self::ARTICLE, 1, 'blog', 'actualites');
        $this->history->remember(self::ARTICLE, 2, 'mon-artcile', 'mon-article');

        self::assertSame('/blog/mon-article', $this->history->newPathFor('/blog/mon-artcile'));
    }

    public function test_it_looks_at_the_most_precise_segment_first(): void
    {
        $this->history->remember(self::ARTICLE, 1, 'mon-artcile', 'mon-article');

        self::assertSame('/fr/blog/2026/mon-article', $this->history->newPathFor('/fr/blog/2026/mon-artcile'));
        self::assertNull($this->history->newPathFor('/fr/blog/2026/autre-chose'));
        self::assertNull($this->history->newPathFor('/'));
    }

    public function test_the_same_address_can_live_in_two_languages(): void
    {
        $this->history->remember(self::ARTICLE, 1, 'contact', 'nous-ecrire', 'fr');
        $this->history->remember(self::ARTICLE, 2, 'contact', 'write-to-us', 'en');

        self::assertSame('/fr/nous-ecrire', $this->history->newPathFor('/fr/contact', 'fr'));
        self::assertSame('/en/write-to-us', $this->history->newPathFor('/en/contact', 'en'));
        self::assertNull($this->history->newPathFor('/de/contact', 'de'));
    }

    public function test_a_type_can_be_asked_for(): void
    {
        $this->history->remember(self::ARTICLE, 1, 'ancien', 'nouveau');

        self::assertNotNull($this->history->find('ancien', '', self::ARTICLE));
        self::assertNull($this->history->find('ancien', '', 'App\Entity\Page'));
        self::assertNull($this->history->newPathFor('/ancien', '', 'App\Entity\Page'));
    }

    public function test_a_deleted_page_leads_nowhere(): void
    {
        $this->history->remember(self::ARTICLE, 1, 'premier', 'deuxieme');
        $this->history->remember(self::ARTICLE, 1, 'deuxieme', 'troisieme');

        self::assertCount(2, $this->history->allFor(self::ARTICLE, 1));

        $this->history->forget(self::ARTICLE, 1);

        self::assertSame([], $this->history->allFor(self::ARTICLE, 1));
        self::assertNull($this->history->newPathFor('/premier'));
    }

    public function test_a_negative_window_is_refused(): void
    {
        // max(0, $days) would have deleted the whole table without a word.
        $this->history->remember(self::ARTICLE, 1, 'ancien', 'nouveau');

        $this->expectException(InvalidPurge::class);
        $this->expectExceptionMessage('The number of days cannot be negative, -1 given.');

        $this->history->purgeOlderThan(-1);
    }

    public function test_old_entries_can_be_cleaned_up(): void
    {
        $this->history->remember(self::ARTICLE, 1, 'tres-ancien', 'actuel');

        $this->clock->moveTo('2026-06-10 09:00:00');
        $this->history->remember(self::ARTICLE, 2, 'recent', 'actuel-2');

        $this->clock->moveTo('2026-06-20 09:00:00');

        self::assertSame(1, $this->history->purgeOlderThan(30));
        self::assertNull($this->history->find('tres-ancien'));
        self::assertNotNull($this->history->find('recent'));
    }
}
