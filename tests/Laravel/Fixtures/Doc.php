<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kaveraa\SlugHistory\Laravel\Concerns\HasSlugHistory;

/**
 * Un contenu qui passe par la corbeille avant de disparaître.
 *
 * @property string $slug
 */
final class Doc extends Model
{
    use HasSlugHistory;
    use SoftDeletes;

    protected $table = 'docs';

    protected $guarded = [];

    public $timestamps = false;
}
