<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;
use Kaveraa\SlugHistory\Laravel\Concerns\HasSlugHistory;

/**
 * The same slug can exist in each language: the scope is the "locale"
 * column.
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
