<?php

/**
 * @property int $id
 * @property int $userid
 * @property string|null $added
 * @property string|null $body
 * @property string $title
 * @property string $notify
 */
namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;

class News extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var  string */
    protected $table = 'news';

    /** @var  list<string> */
    protected $fillable = [
        'userid', 'added', 'title', 'body', 'notify',
    ];

    /** @var  array<string, string> */
    protected $casts = [
        'added' => 'datetime',
    ];

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }


}
