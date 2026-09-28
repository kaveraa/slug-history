<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;
use Kaveraa\SlugHistory\Laravel\Concerns\HasSlugHistory;

/**
 * Le même slug peut exister dans chaque langue : la portée est la colonne
 * "locale".
 *
 * @property string $slug
 * @property string $locale
 */
#[KeepOldSlugs(scope: 'locale')]
final class Translation extends Model
{
    use HasSlugHistory;

    protected $table = 'translations';

    protected $guarded = [];

    public $timestamps = false;
}
