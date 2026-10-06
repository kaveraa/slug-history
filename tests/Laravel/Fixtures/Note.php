<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kaveraa\SlugHistory\Laravel\Concerns\HasSlugHistory;

/**
 * The address is not named "slug" either, but without the attribute: the
 * model property says so.
 *
 * @property string $reference
 */
final class Note extends Model
{
    use HasSlugHistory;

    protected $table = 'notes';

    protected $guarded = [];

    public $timestamps = false;

    protected string $slugHistoryColumn = 'reference';
}
