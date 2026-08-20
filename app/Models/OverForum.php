<?php

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $minclassview
 * @property int $sort
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverForum extends NexusModel
{
    /** @var  string */
    protected $table = "overforums";

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<Forum, $this> */
    public function forums()
    {
        return $this->hasMany(Forum::class, 'forid');
    }
}
