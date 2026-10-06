<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Support;

use DateTimeImmutable;
use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\Store;
use PHPUnit\Framework\TestCase;

/**
 * The contract every Store implementation has to keep: in memory, with
 * Eloquent, with DBAL. All three pass exactly these tests, otherwise the two
 * frameworks would not behave the same.
 */
abstract class StoreContract extends TestCase
{
    private const ARTICLE = 'App\Entity\Article';
    private const PAGE = 'App\Entity\Page';

    abstract protected function store(): Store;

    public function test_it_keeps_a_past_address_and_finds_it_again(): void
    {
        $store = $this->store();
        $store->remember($this->past('mon-artcile', 'mon-article'));

        $found = $store->find('mon-artcile');

        self::assertNotNull($found);
        self::assertSame(self::ARTICLE, $found->type);
        self::assertSame('1', (string) $found->id);
        self::assertSame('mon-artcile', $found->slug);
        self::assertSame('mon-article', $found->currentSlug);
        self::assertSame('', $found->scope);
        self::assertNull($store->find('jamais-vu'));
    }

    public function test_an_address_can_only_lead_to_one_place(): void
    {
        $store = $this->store();

        $store->remember($this->past('agenda', 'agenda-2025', id: 1));
        $store->remember($this->past('agenda', 'agenda-2026', id: 2));

        self::assertCount(1, $store->allFor(self::ARTICLE, 2));
        self::assertSame([], $store->allFor(self::ARTICLE, 1));
        self::assertSame('agenda-2026', $store->find('agenda')?->currentSlug);
    }

    public function test_the_same_address_can_live_in_two_scopes(): void
    {
        $store = $this->store();

        $store->remember($this->past('contact', 'nous-ecrire', scope: 'fr'));
        $store->remember($this->past('contact', 'write-to-us', scope: 'en', id: 2));

        self::assertSame('nous-ecrire', $store->find('contact', 'fr')?->currentSlug);
        self::assertSame('write-to-us', $store->find('contact', 'en')?->currentSlug);
        self::assertNull($store->find('contact', 'de'));
    }

    public function test_a_search_can_be_limited_to_one_type(): void
    {
        $store = $this->store();
        $store->remember($this->past('ancien', 'nouveau'));

        self::assertNotNull($store->find('ancien', '', self::ARTICLE));
        self::assertNull($store->find('ancien', '', self::PAGE));
    }

    public function test_releasing_an_address_gives_it_back(): void
    {
        $store = $this->store();
        $store->remember($this->past('agenda', 'agenda-2026'));

        $store->release('agenda', '', self::PAGE);
        self::assertNotNull($store->find('agenda'), 'un autre type ne doit rien libérer');

        $store->release('agenda');
        self::assertNull($store->find('agenda'));
    }

    public function test_every_past_address_follows_the_content(): void
    {
        $store = $this->store();
        $store->remember($this->past('premier', 'deuxieme'));
        $store->remember($this->past('deuxieme', 'troisieme'));

        $store->retarget(self::ARTICLE, 1, 'quatrieme');

        self::assertSame('quatrieme', $store->find('premier')?->currentSlug);
        self::assertSame('quatrieme', $store->find('deuxieme')?->currentSlug);
    }

    public function test_retarget_leaves_the_other_contents_alone(): void
    {
        $store = $this->store();
        $store->remember($this->past('un', 'deux', id: 1));
        $store->remember($this->past('trois', 'quatre', id: 2));

        $store->retarget(self::ARTICLE, 1, 'cinq');

        self::assertSame('cinq', $store->find('un')?->currentSlug);
        self::assertSame('quatre', $store->find('trois')?->currentSlug);
    }

    public function test_forgetting_a_content_drops_all_its_addresses(): void
    {
        $store = $this->store();
        $store->remember($this->past('un', 'deux', id: 1));
        $store->remember($this->past('deux', 'trois', id: 1));
        $store->remember($this->past('autre', 'encore', id: 2));

        self::assertCount(2, $store->allFor(self::ARTICLE, 1));

        $store->forget(self::ARTICLE, 1);

        self::assertSame([], $store->allFor(self::ARTICLE, 1));
        self::assertCount(1, $store->allFor(self::ARTICLE, 2));
    }

    public function test_only_the_old_entries_are_cleaned_up(): void
    {
        $store = $this->store();
        $store->remember($this->past('vieux', 'actuel', at: '2025-01-01 00:00:00'));
        $store->remember($this->past('recent', 'actuel-2', id: 2, at: '2026-09-01 00:00:00'));

        self::assertSame(1, $store->purge(new DateTimeImmutable('2026-01-01 00:00:00')));

        self::assertNull($store->find('vieux'));
        self::assertNotNull($store->find('recent'));
    }

    public function test_an_identifier_can_be_a_string(): void
    {
        $store = $this->store();
        $store->remember($this->past('ancien', 'nouveau', id: '9f1c-4b2e'));

        self::assertCount(1, $store->allFor(self::ARTICLE, '9f1c-4b2e'));
        self::assertSame('9f1c-4b2e', (string) $store->find('ancien')?->id);
    }

    private function past(
        string $slug,
        string $currentSlug,
        string $scope = '',
        int|string $id = 1,
        string $at = '2026-01-10 09:00:00',
    ): PastSlug {
        return new PastSlug(self::ARTICLE, $id, $slug, $currentSlug, $scope, new DateTimeImmutable($at));
    }
}
