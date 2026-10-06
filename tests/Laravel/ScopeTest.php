<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel;

use Illuminate\Http\Request;
use Kaveraa\SlugHistory\Path;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Translation;

/**
 * The same slug in two languages does not get mixed up.
 */
final class ScopeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // On this site, the language is the first piece of the path.
        $app->make('config')->set(
            'slug-history.scope',
            static fn (Request $request): string => Path::segments($request->getPathInfo())[0] ?? '',
        );
    }

    public function test_two_languages_keep_their_own_history(): void
    {
        $fr = Translation::query()->create(['slug' => 'contact', 'locale' => 'fr']);
        $en = Translation::query()->create(['slug' => 'contact', 'locale' => 'en']);

        $fr->update(['slug' => 'nous-ecrire']);

        $this->get('/fr/pages/contact')->assertRedirect('/fr/pages/nous-ecrire');

        // The English page still has this address: nothing moves.
        $this->get('/en/pages/contact')->assertOk()->assertSee('page ' . $en->id);

        $en->update(['slug' => 'write-to-us']);

        $this->get('/en/pages/contact')->assertRedirect('/en/pages/write-to-us');
        $this->get('/fr/pages/contact')->assertRedirect('/fr/pages/nous-ecrire');
    }

    public function test_the_two_scopes_live_side_by_side_in_the_table(): void
    {
        $fr = Translation::query()->create(['slug' => 'contact', 'locale' => 'fr']);
        $en = Translation::query()->create(['slug' => 'contact', 'locale' => 'en']);

        $fr->update(['slug' => 'nous-ecrire']);
        $en->update(['slug' => 'write-to-us']);

        self::assertSame('nous-ecrire', $this->history()->find('contact', 'fr')?->currentSlug);
        self::assertSame('write-to-us', $this->history()->find('contact', 'en')?->currentSlug);
        self::assertNull($this->history()->find('contact', 'de'));
    }

    public function test_a_living_page_only_frees_its_own_language(): void
    {
        $fr = Translation::query()->create(['slug' => 'contact', 'locale' => 'fr']);
        $fr->update(['slug' => 'nous-ecrire']);

        // An English page takes the address "contact": the French one is not
        // affected.
        Translation::query()->create(['slug' => 'contact', 'locale' => 'en']);

        self::assertNotNull($this->history()->find('contact', 'fr'));
        $this->get('/fr/pages/contact')->assertRedirect('/fr/pages/nous-ecrire');
        $this->get('/en/pages/contact')->assertOk();
    }
}
