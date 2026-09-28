<?php

declare(strict_types=1);

namespace Kaveraa\SlugHistory\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kaveraa\SlugHistory\Laravel\Concerns\HasSlugHistory;

/**
 * L'adresse ne s'appelle pas "slug" non plus, mais sans attribut : c'est la
 * propriété du modèle qui le dit.
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
