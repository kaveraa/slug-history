<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory;

/**
 * Découpe et recompose un chemin, sans toucher à ce qui n'est pas concerné.
 *
 * Cuts and rebuilds a path, without touching anything else.
 */
final class Path
{
    /**
     * Les morceaux du chemin, dans l'ordre, sans les barres obliques.
     *
     * @return list<string>
     */
    public static function segments(string $path): array
    {
        $trimmed = trim(parse_url($path, PHP_URL_PATH) ?? $path, '/');

        return $trimmed === '' ? [] : explode('/', $trimmed);
    }

    /**
     * Remplace un seul morceau et garde tout le reste tel quel : la barre de
     * début, celle de fin, et les autres morceaux.
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
