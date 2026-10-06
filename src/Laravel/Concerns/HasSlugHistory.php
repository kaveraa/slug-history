<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kaveraa\SlugHistory\Laravel\SlugSettings;
use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\SlugHistory;

/**
 * To put on a model whose address can change: renaming the content no longer
 * breaks any link.
 *
 * The address column can be declared in two ways:
 *
 *     #[KeepOldSlugs(property: 'permalink')]   on the class, it wins
 *     protected string $slugHistoryColumn = 'permalink';   in the model
 *
 * The trait does not declare $slugHistoryColumn itself: PHP does not let a
 * class give a trait property another default value. So your model declares
 * it, and the trait reads it when it is there.
 */
trait HasSlugHistory
{
    public static function bootHasSlugHistory(): void
    {
        // A living content takes this address: the history must let it go.
        static::created(static function (Model $model): void {
            $model->releaseCurrentSlug();
        });

        static::updated(static function (Model $model): void {
            $model->rememberSlugChange();
        });

        // Trash and final deletion do not mean the same thing. A model that is
        // only put in the trash can come back: it keeps its addresses,
        // otherwise restoring it would leave 404s behind. So we forget only
        // on forceDeleted when the model uses SoftDeletes, and on deleted
        // otherwise, where the deletion is really final.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(static function (Model $model): void {
                $model->releaseCurrentSlug();
            });

            static::forceDeleted(static function (Model $model): void {
                $model->forgetPastSlugs();
            });

            return;
        }

        static::deleted(static function (Model $model): void {
            $model->forgetPastSlugs();
        });
    }

    /**
     * The past addresses of this content.
     *
     * @return list<PastSlug>
     */
    public function pastSlugs(): array
    {
        return $this->slugHistory()->allFor($this->slugHistoryType(), $this->getKey());
    }

    /**
     * This content has no past addresses any more.
     */
    public function forgetPastSlugs(): void
    {
        $this->slugHistory()->forget($this->slugHistoryType(), $this->getKey());
    }

    /**
     * The history lets go of the address this content has today.
     */
    public function releaseCurrentSlug(): void
    {
        $settings = $this->slugHistorySettings();
        $slug = (string) ($this->{$settings->column} ?? '');

        if ($slug === '') {
            return;
        }

        $this->slugHistory()->release($slug, $this->slugHistoryScope());
    }

    /**
     * The type stored in the history. The model class, unless you decide
     * otherwise.
     */
    public function slugHistoryType(): string
    {
        return static::class;
    }

    public function slugHistorySettings(): SlugSettings
    {
        return SlugSettings::for($this, $this->slugHistoryColumnName());
    }

    /**
     * Keeps the old address when it has just changed. Nothing to do in all
     * other cases: no query.
     */
    protected function rememberSlugChange(): void
    {
        $settings = $this->slugHistorySettings();
        $column = $settings->column;

        if (!$this->wasChanged($column)) {
            return;
        }

        $old = $this->getOriginal($column);
        $new = $this->{$column};

        if (!is_scalar($old) || !is_scalar($new)) {
            return;
        }

        $this->slugHistory()->remember(
            $this->slugHistoryType(),
            $this->getKey(),
            (string) $old,
            (string) $new,
            $this->slugHistoryScope(),
        );
    }

    protected function slugHistoryScope(): string
    {
        return $this->slugHistorySettings()->scopeOf($this, $this->slugHistoryDefaultScope());
    }

    /**
     * The model property if it exists, otherwise the usual column.
     */
    protected function slugHistoryColumnName(): string
    {
        if (property_exists($this, 'slugHistoryColumn')) {
            /** @var mixed $declared */
            $declared = $this->slugHistoryColumn;

            if (is_string($declared) && $declared !== '') {
                return $declared;
            }
        }

        return 'slug';
    }

    protected function slugHistoryDefaultScope(): string
    {
        $configured = function_exists('config') ? config('slug-history.scope', '') : '';

        return is_string($configured) ? $configured : '';
    }

    protected function slugHistory(): SlugHistory
    {
        return app(SlugHistory::class);
    }
}
