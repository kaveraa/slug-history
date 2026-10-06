<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory;

/**
 * Cuts and rebuilds a path, without touching anything else.
 */
final class Path
{
    /**
     * The pieces of the path, in order, without the slashes.
     *
     * @return list<string>
     */
    public static function segments(string $path): array
    {
        $trimmed = trim(parse_url($path, PHP_URL_PATH) ?? $path, '/');

        return $trimmed === '' ? [] : explode('/', $trimmed);
    }

    /**
     * Replaces one piece only and keeps all the rest as it is: the leading
     * slash, the trailing slash, and the other pieces.
     */
    public static function replaceSegment(string $path, int $index, string $value): string
    {
        $segments = self::segments($path);

        if (!isset($segments[$index])) {
            return $path;
        }

        $segments[$index] = $value;

        $rebuilt = implode('/', $segments);

        return (str_starts_with($path, '/') ? '/' : '') . $rebuilt . (self::endsWithSlash($path) ? '/' : '');
    }

    private static function endsWithSlash(string $path): bool
    {
        return $path !== '/' && str_ends_with($path, '/');
    }
}
