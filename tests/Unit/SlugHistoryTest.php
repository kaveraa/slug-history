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

        // La toute première adresse mène directement à la dernière : une seule
        // redirection pour le visiteur, et pour le moteur de recherche.
        self::assertSame('troisieme', $this->history->find('premier')?->currentSlug);
        self::assertSame('troisieme', $this->history->find('deuxieme')?->currentSlug);
        self::assertSame('/blog/troisieme', $this->history->newPathFor('/blog/premier'));
    }

    public function test_a_living_page_takes_its_address_back(): void
    {
        $this->history->remember(self::ARTICLE, 1, 'agenda', 'agenda-2026');

        self::assertSame('/agenda-2026', $this->history->newPathFor('/agenda'));

        // Un autre contenu publié aujourd'hui prend l'adresse laissée libre :
        // l'historique doit la lâcher, sinon on redirigerait le visiteur loin
        // de la page qu'il demande vraiment.
        $this->history->release('agenda');

        self::assertNull($this->history->find('agenda'));
        self::assertNull($this->history->newPathFor('/agenda'));
    }

    public function test_renaming_to_a_freed_address_also_releases_it(): void
    {
        $this->history->remember(self::ARTICLE, 1, 'agenda', 'agenda-2026');
        $this->history->remember(self::ARTICLE, 2, 'evenements', 'agenda');

        // "agenda" appartient de nouveau à un contenu vivant, le numéro 2.
        self::assertNull($this->history->find('agenda'));
        self::assertSame('agenda', $this->history->find('evenements')?->currentSlug);
    }

    public function test_two_pages_can_swap_their_addresses_without_a_loop(): void
    {
        // Le pire cas : deux contenus échangent leurs adresses. Si les deux
        // entrées survivaient, /agenda mènerait à /programme qui mènerait à
        // /agenda, et le visiteur tournerait en rond jusqu'à l'erreur du
        // navigateur.
        $this->history->remember(self::ARTICLE, 1, 'agenda', 'programme');
        $this->history->remember(self::ARTICLE, 2, 'programme', 'agenda');

        $this->assertNoLoop($this->store, $this->history);

        // Dans l'autre ordre non plus.
        $store = new InMemoryStore();
        $other = new SlugHistory($store, $this->clock);

        $other->remember(self::ARTICLE, 2, 'programme', 'agenda');
        $other->remember(self::ARTICLE, 1, 'agenda', 'programme');

        $this->assertNoLoop($store, $other);
    }

    /**
     * L'invariant du paquet : aucune adresse d'arrivée n'est elle-même une
     * ancienne adresse. Une redirection ne peut donc jamais en appeler une
     * autre, quel que soit l'ordre des renommages.
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
        // Une rubrique et un article portant tous deux une ancienne adresse :
        // on ne touche qu'au dernier morceau, celui qui désigne le contenu.
        // Remplacer aussi le préfixe ferait courir le risque de fabriquer une
        // adresse qui n'existe pas.
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
        // max(0, $days) aurait effacé toute la table sans rien dire.
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
