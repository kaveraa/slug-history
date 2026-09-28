<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;
use ReflectionClass;

/**
 * Lit l'attribut #[KeepOldSlugs] d'une classe et résout les propriétés qu'il
 * désigne, une seule fois par classe.
 *
 * Reads the #[KeepOldSlugs] attribute of a class and resolves the properties it
 * names, once per class.
 */
final class SlugSettings
{
    /** @var array<string, KeepOldSlugs|null> ce qui a déjà été cherché, trouvé ou non */
    private array $known = [];

    /**
     * Les réglages posés sur la classe, ou null quand elle n'est pas suivie.
     * L'attribut posé sur une classe parente vaut pour ses filles.
     */
    public function of(string $class): ?KeepOldSlugs
    {
        // Le cache retient aussi les classes non suivies : ce sont les plus
        // nombreuses, et on les rencontre à chaque événement Doctrine.
        if (array_key_exists($class, $this->known)) {
            return $this->known[$class];
        }

        return $this->known[$class] = $this->read($class);
    }

    /**
     * Le nom de la propriété portant l'adresse, ou null quand la classe n'est pas
     * suivie ou que l'entité ne porte pas cette propriété.
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
     * La portée lue sur l'entité : la langue, la rubrique parente. Chaîne vide
     * quand l'attribut n'en désigne pas, ou quand la valeur est nulle.
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
     * L'identifiant de l'entité, ou null quand elle n'en a pas encore un seul.
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
     * L'attribut, en remontant les classes parentes : une entité qui hérite d'une
     * superclasse mappée doit en hériter aussi.
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
