<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kaveraa\SlugHistory\SlugHistory;

/**
 * Branche l'historique sur Doctrine : un contenu renommé laisse son ancienne
 * adresse derrière lui, un contenu neuf reprend la sienne, un contenu supprimé
 * n'en laisse aucune.
 *
 * Plugs the history into Doctrine: renamed content leaves its old address
 * behind, brand new content takes its address back, deleted content leaves none.
 *
 * L'écriture passe par le Store, donc par DBAL, jamais par l'EntityManager :
 * ouvrir un second flush au milieu du premier ne finit jamais bien.
 */
final class SlugHistoryListener
{
    /** @var array<int, int|string> les identifiants notés avant la suppression */
    private array $doomed = [];

    public function __construct(
        private readonly SlugHistory $history,
        private readonly SlugSettings $settings = new SlugSettings(),
    ) {
    }

    /**
     * Le contenu a changé d'adresse : l'ancienne est retenue.
     */
    public function postUpdate(PostUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        $entities = $event->getObjectManager();
        $meta = $this->metadataOf($entities, $entity);

        if ($meta === null) {
            return;
        }

        $property = $this->settings->slugProperty($meta);
        $id = $this->settings->idOf($meta, $entity);

        if ($property === null || $id === null) {
            return;
        }

        $changes = $entities->getUnitOfWork()->getEntityChangeSet($entity);

        if (!isset($changes[$property])) {
            return;
        }

        [$before, $after] = [$changes[$property][0], $changes[$property][1]];

        $this->history->remember(
            $meta->getName(),
            $id,
            is_scalar($before) ? (string) $before : '',
            is_scalar($after) ? (string) $after : '',
            $this->settings->scopeOf($meta, $entity),
        );
    }

    /**
     * Un contenu vivant prend cette adresse : l'historique la lâche, sinon on
     * redirigerait au nez et à la barbe de son nouveau propriétaire.
     */
    public function postPersist(PostPersistEventArgs $event): void
    {
        $entity = $event->getObject();
        $meta = $this->metadataOf($event->getObjectManager(), $entity);

        if ($meta === null) {
            return;
        }

        $property = $this->settings->slugProperty($meta);

        if ($property === null) {
            return;
        }

        $slug = $meta->getFieldValue($entity, $property);

        if (!is_scalar($slug) || (string) $slug === '') {
            return;
        }

        $this->history->release((string) $slug, $this->settings->scopeOf($meta, $entity));
    }

    /**
     * L'identifiant est noté avant la suppression : Doctrine le remet à null dès
     * que la ligne est effacée, et postRemove ne le verrait plus.
     */
    public function preRemove(PreRemoveEventArgs $event): void
    {
        $entity = $event->getObject();
        $meta = $this->metadataOf($event->getObjectManager(), $entity);

        if ($meta === null) {
            return;
        }

        $id = $this->settings->idOf($meta, $entity);

        if ($id !== null) {
            $this->doomed[spl_object_id($entity)] = $id;
        }
    }

    /**
     * Le contenu n'existe plus : ses anciennes adresses ne mènent nulle part.
     */
    public function postRemove(PostRemoveEventArgs $event): void
    {
        $entity = $event->getObject();
        $meta = $this->metadataOf($event->getObjectManager(), $entity);

        if ($meta === null) {
            return;
        }

        $key = spl_object_id($entity);
        $id = $this->doomed[$key] ?? $this->settings->idOf($meta, $entity);

        unset($this->doomed[$key]);

        if ($id === null) {
            return;
        }

        $this->history->forget($meta->getName(), $id);
    }

    /**
     * Les métadonnées de la vraie classe : Doctrine passe parfois un proxy, dont
     * le nom de classe ne porte aucun attribut.
     *
     * @return ClassMetadata<object>|null
     */
    private function metadataOf(EntityManagerInterface $entities, object $entity): ?ClassMetadata
    {
        $meta = $entities->getClassMetadata($entity::class);

        return $this->settings->of($meta->getName()) === null ? null : $meta;
    }
}
