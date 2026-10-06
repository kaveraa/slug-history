<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;
use ReflectionClass;

/**
 * Reads the #[KeepOldSlugs] attribute of a class and resolves the properties it
 * names, once per class.
 */
final class SlugSettings
{
    /** @var array<string, KeepOldSlugs|null> what was already looked up, found or not */
    private array $known = [];

    /**
     * The settings put on the class, or null when the class is not tracked.
     * An attribute put on a parent class also applies to its children.
     */
    public function of(string $class): ?KeepOldSlugs
    {
        // The cache also keeps the classes that are not tracked: they are the
        // most common, and we meet them on every Doctrine event.
        if (array_key_exists($class, $this->known)) {
            return $this->known[$class];
        }

        return $this->known[$class] = $this->read($class);
    }

    /**
     * The name of the property that holds the address, or null when the class is
     * not tracked or the entity does not have this property.
     *
     * @param ClassMetadata<object> $meta
     */
    public function slugProperty(ClassMetadata $meta): ?string
    {
        $settings = $this->of($meta->getName());

        if ($settings === null || !$meta->hasField($settings->property)) {
            return null;
        }

        return $settings->property;
    }

    /**
     * The scope read on the entity: the language, the parent section. Empty
     * string when the attribute names none, or when the value is null.
     *
     * @param ClassMetadata<object> $meta
     */
    public function scopeOf(ClassMetadata $meta, object $entity): string
    {
        $settings = $this->of($meta->getName());

        if ($settings?->scope === null || !$meta->hasField($settings->scope)) {
            return '';
        }

        $value = $meta->getFieldValue($entity, $settings->scope);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * The identifier of the entity, or null when it does not have a single one yet.
     *
     * @param ClassMetadata<object> $meta
     */
    public function idOf(ClassMetadata $meta, object $entity): int|string|null
    {
        $values = $meta->getIdentifierValues($entity);

        if (count($values) !== 1) {
            return null;
        }

        $id = reset($values);

        return is_int($id) || is_string($id) ? $id : null;
    }

    /**
     * The attribute, looking up the parent classes: an entity that extends a
     * mapped superclass must inherit it too.
     */
    private function read(string $class): ?KeepOldSlugs
    {
        if (!class_exists($class)) {
            return null;
        }

        for ($reflection = new ReflectionClass($class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            $attributes = $reflection->getAttributes(KeepOldSlugs::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        return null;
    }
}
