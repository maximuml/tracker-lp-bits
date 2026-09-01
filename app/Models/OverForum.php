<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $minclassview
 * @property int $sort
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class OverForum extends NexusModel
{
    /** @var string */
    protected $table = 'overforums';

    /** @var list<string> */
    protected $fillable = ['id', 'name', 'description', 'minclassview', 'sort'];

    /** @var array<string, string> */
    protected $casts = [
        'minclassview' => 'integer',
        'sort' => 'integer',
    ];

    /** @return HasMany<Forum, $this> */
    public function forums()
    {
        return $this->hasMany(Forum::class, 'forid');
    }
}
