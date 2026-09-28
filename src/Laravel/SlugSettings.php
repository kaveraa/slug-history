<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel;

use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;
use ReflectionClass;

/**
 * Où lire l'adresse d'un contenu, et où lire sa portée. Lu une fois sur la
 * classe, jamais deviné à chaque requête.
 *
 * Where to read a content address, and where to read its scope. Read once on
 * the class, never guessed on every request.
 */
final class SlugSettings
{
    public function __construct(
        /** La colonne qui porte l'adresse. */
        public readonly string $column = 'slug',
        /** La colonne qui rend l'adresse unique, ou null. */
        public readonly ?string $scopeColumn = null,
    ) {
    }

    /**
     * L'attribut #[KeepOldSlugs] posé sur la classe l'emporte : il est plus
     * visible que la propriété, et c'est lui que lisent les autres frameworks.
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
     * La portée de ce contenu. Sans colonne de portée, la valeur par défaut.
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
