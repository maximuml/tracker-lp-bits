<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int|null $sender
 * @property int $receiver
 * @property string|null $added
 * @property string $subject
 * @property string $msg
 * @property string $unread
 * @property int $location
 * @property string $saved
 */

namespace App\Models;

use App\Enums\ModelEventEnum;
use App\Support\Cache;
use App\Support\Events;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends NexusModel
{
    /** @var string */
    protected $table = 'messages';

    /** @var list<string> */
    protected $fillable = [
        'sender', 'receiver', 'added', 'subject', 'msg', 'unread', 'location', 'saved',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
    ];

    /** @return  BelongsTo<User, $this> */
    public function send_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender')->withDefault(['id' => 0, 'username' => 'System']);
    }

    /** @return  BelongsTo<User, $this> */
    public function receive_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver');
    }

    /** @param  array<string, mixed>  $data */
    public static function add(array $data): self
    {
        Cache::clearInboxCount($data['receiver']);
        $message = self::query()->create($data);
        Events::fire(ModelEventEnum::MESSAGE_CREATED, $message, null);

        return $message;
    }
}
