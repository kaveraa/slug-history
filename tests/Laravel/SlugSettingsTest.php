<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel;

use Kaveraa\SlugHistory\Laravel\SlugSettings;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Article;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Post;
use Kaveraa\SlugHistory\Tests\Laravel\Fixtures\Translation;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * La lecture de l'attribut, sans base ni application.
 */
final class SlugSettingsTest extends PhpUnitTestCase
{
    public function test_without_the_attribute_it_keeps_the_given_column(): void
    {
        $settings = SlugSettings::for(Article::class, 'reference');

        self::assertSame('reference', $settings->column);
        self::assertNull($settings->scopeColumn);
    }

    public function test_the_attribute_wins_over_the_given_column(): void
    {
        $settings = SlugSettings::for(Post::class, 'reference');

        self::assertSame('permalink', $settings->column);
    }

    public function test_the_attribute_can_name_a_scope_column(): void
    {
        $settings = SlugSettings::for(Translation::class);

        self::assertSame('slug', $settings->column);
        self::assertSame('locale', $settings->scopeColumn);
    }

    public function test_the_scope_is_read_on_the_content(): void
    {
        $settings = new SlugSettings('slug', 'locale');

        $page = new Translation(['slug' => 'contact', 'locale' => 'fr']);

        self::assertSame('fr', $settings->scopeOf($page));
        self::assertSame('', (new SlugSettings())->scopeOf($page));
        self::assertSame('boutique', (new SlugSettings())->scopeOf($page, 'boutique'));
    }
}
