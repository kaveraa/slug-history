<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kaveraa\SlugHistory\Laravel\Concerns\HasSlugHistory;

/**
 * Le cas ordinaire : une colonne "slug", pas de portée.
 *
 * @property string $slug
 * @property string $title
 */
final class Article extends Model
{
    use HasSlugHistory;

    protected $table = 'articles';

    protected $guarded = [];

    public $timestamps = false;
}
