<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kaveraa\SlugHistory\Attribute\KeepOldSlugs;
use Kaveraa\SlugHistory\Laravel\Concerns\HasSlugHistory;

/**
 * The address is not named "slug": the attribute says so.
 *
 * @property string $permalink
 */
#[KeepOldSlugs(property: 'permalink')]
final class Post extends Model
{
    use HasSlugHistory;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}
