<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel;

use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;
use ReflectionClass;

/**
 * Where to read a content address, and where to read its scope. Read once on
 * the class, never guessed on every request.
 */
final class SlugSettings
{
    public function __construct(
        /** The column that holds the address. */
        public readonly string $column = 'slug',
        /** The column that makes the address unique, or null. */
        public readonly ?string $scopeColumn = null,
    ) {
    }

    /**
     * The #[KeepOldSlugs] attribute put on the class wins: it is more visible
     * than the property, and it is what the other frameworks read.
     *
     * @param object|class-string $subject
     */
    public static function for(object|string $subject, string $fallbackColumn = 'slug'): self
    {
        $class = is_object($subject) ? $subject::class : $subject;

        if (!class_exists($class)) {
            return new self($fallbackColumn);
        }

        $attributes = (new ReflectionClass($class))->getAttributes(KeepOldSlugs::class);

        if ($attributes === []) {
            return new self($fallbackColumn);
        }

        $keep = $attributes[0]->newInstance();

        return new self($keep->property, $keep->scope);
    }

    /**
     * The scope of this content. Without a scope column, the default value.
     */
    public function scopeOf(object $subject, string $fallback = ''): string
    {
        if ($this->scopeColumn === null) {
            return $fallback;
        }

        $value = $subject->{$this->scopeColumn} ?? null;

        return $value === null ? '' : (string) $value;
    }
}
