<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Unit;

use Kaveraa\SlugHistory\Path;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function paths(): iterable
    {
        yield 'simple' => ['/blog/mon-article', ['blog', 'mon-article']];
        yield 'without the first slash' => ['blog/mon-article', ['blog', 'mon-article']];
        yield 'with a last slash' => ['/blog/mon-article/', ['blog', 'mon-article']];
        yield 'with a query string' => ['/blog/mon-article?page=2', ['blog', 'mon-article']];
        yield 'root' => ['/', []];
        yield 'empty' => ['', []];
        yield 'one segment' => ['/contact', ['contact']];
        yield 'deep' => ['/fr/blog/2026/mon-article', ['fr', 'blog', '2026', 'mon-article']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('paths')]
    public function test_it_cuts_a_path_into_segments(string $path, array $expected): void
    {
        self::assertSame($expected, Path::segments($path));
    }

    public function test_it_replaces_one_segment_and_keeps_the_rest(): void
    {
        self::assertSame('/blog/mon-article', Path::replaceSegment('/blog/mon-artcile', 1, 'mon-article'));
        self::assertSame('/actualites/mon-artcile', Path::replaceSegment('/blog/mon-artcile', 0, 'actualites'));
    }

    public function test_it_keeps_the_shape_of_the_path(): void
    {
        self::assertSame('/blog/neuf/', Path::replaceSegment('/blog/vieux/', 1, 'neuf'));
        self::assertSame('blog/neuf', Path::replaceSegment('blog/vieux', 1, 'neuf'));
    }

    public function test_the_query_string_is_not_its_business(): void
    {
        // The query string is put back by the caller: here we only touch the path.
        self::assertSame('/blog/neuf', Path::replaceSegment('/blog/vieux?page=2', 1, 'neuf'));
    }

    public function test_an_index_that_does_not_exist_changes_nothing(): void
    {
        self::assertSame('/blog/vieux', Path::replaceSegment('/blog/vieux', 7, 'neuf'));
        self::assertSame('/', Path::replaceSegment('/', 0, 'neuf'));
    }
}
