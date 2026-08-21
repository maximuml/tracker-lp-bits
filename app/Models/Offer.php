<?php

/**
 * @property int $id
 * @property int $userid
 * @property string $name
 * @property string|null $descr
 * @property string|null $added
 * @property string|null $allowedtime
 * @property int $yeah
 * @property int $against
 * @property int $category
 * @property int $comments
 * @property string $allowed
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends NexusModel
{
    /** @var list<string> */
    protected $fillable = ['userid', 'name', 'descr', 'comments', 'added'];

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
    ];

    /** @return  BelongsTo<User, $this> */
    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
