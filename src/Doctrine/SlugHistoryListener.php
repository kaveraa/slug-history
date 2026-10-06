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
 * Plugs the history into Doctrine: renamed content leaves its old address
 * behind, brand new content takes its address back, deleted content leaves none.
 *
 * Writes go through the Store, so through DBAL, never through the EntityManager:
 * opening a second flush in the middle of the first one never ends well.
 */
final class SlugHistoryListener
{
    /** @var array<int, int|string> the identifiers noted before the deletion */
    private array $doomed = [];

    public function __construct(
        private readonly SlugHistory $history,
        private readonly SlugSettings $settings = new SlugSettings(),
    ) {
    }

    /**
     * The content changed its address: the old one is kept.
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
     * A living content takes this address: the history lets it go, otherwise we
     * would redirect right under the nose of its new owner.
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
     * The identifier is noted before the deletion: Doctrine sets it back to null
     * as soon as the row is deleted, and postRemove would not see it any more.
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
     * The content no longer exists: its past addresses lead nowhere.
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
     * The metadata of the real class: Doctrine sometimes gives a proxy, whose
     * class name carries no attribute.
     *
     * @return ClassMetadata<object>|null
     */
    private function metadataOf(EntityManagerInterface $entities, object $entity): ?ClassMetadata
    {
        $meta = $entities->getClassMetadata($entity::class);

        return $this->settings->of($meta->getName()) === null ? null : $meta;
    }
}
