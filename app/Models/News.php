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

    protected $table = 'news';

    protected $fillable = [
        'userid', 'added', 'title', 'body', 'notify',
    ];

    protected $casts = [
        'added' => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }


}
