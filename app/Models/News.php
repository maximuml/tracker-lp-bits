<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $userid
 * @property string|null $added
 * @property string|null $body
 * @property string $title
 * @property bool $notify
 */

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var string */
    protected $table = 'news';

    /** @var list<string> */
    protected $fillable = [
        'userid', 'added', 'title', 'body', 'notify',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
        'notify' => 'boolean',
    ];

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
