<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Laravel\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kaveraa\SlugHistory\Laravel\SlugSettings;
use Kaveraa\SlugHistory\PastSlug;
use Kaveraa\SlugHistory\SlugHistory;

/**
 * À poser sur un modèle dont l'adresse peut changer : renommer le contenu ne
 * casse plus aucun lien.
 *
 * To put on a model whose address can change: renaming the content no longer
 * breaks any link.
 *
 * La colonne de l'adresse se déclare de deux façons :
 *
 *     #[KeepOldSlugs(property: 'permalink')]   sur la classe, elle l'emporte
 *     protected string $slugHistoryColumn = 'permalink';   dans le modèle
 *
 * Le trait ne déclare pas lui-même $slugHistoryColumn : PHP refuse qu'une
 * classe redonne à une propriété de trait une autre valeur par défaut. C'est
 * donc votre modèle qui la déclare, et le trait la lit si elle est là.
 */
trait HasSlugHistory
{
    public static function bootHasSlugHistory(): void
    {
        // Un contenu vivant prend cette adresse : l'historique doit la lâcher.
        static::created(static function (Model $model): void {
            $model->releaseCurrentSlug();
        });

        static::updated(static function (Model $model): void {
            $model->rememberSlugChange();
        });

        // Corbeille et suppression définitive n'ont pas le même sens. Un modèle
        // simplement mis à la corbeille peut revenir : il garde ses adresses,
        // sinon la restauration laisserait des 404 derrière elle. On n'oublie
        // donc qu'au forceDeleted quand le modèle utilise SoftDeletes, et au
        // deleted sinon, où la suppression est bel et bien définitive.
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
     * Les anciennes adresses de ce contenu.
     *
     * @return list<PastSlug>
     */
    public function pastSlugs(): array
    {
        return $this->slugHistory()->allFor($this->slugHistoryType(), $this->getKey());
    }

    /**
     * Ce contenu n'a plus d'anciennes adresses.
     */
    public function forgetPastSlugs(): void
    {
        $this->slugHistory()->forget($this->slugHistoryType(), $this->getKey());
    }

    /**
     * L'historique lâche l'adresse que ce contenu porte aujourd'hui.
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
     * Le type rangé dans l'historique. La classe du modèle, sauf si vous en
     * décidez autrement.
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
     * Retient l'ancienne adresse quand elle vient de changer. Rien à faire dans
     * tous les autres cas : aucune requête.
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
     * La propriété du modèle si elle existe, sinon la colonne habituelle.
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
