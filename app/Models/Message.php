<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int|null $sender
 * @property int $receiver
 * @property string|null $added
 * @property string $subject
 * @property string $msg
 * @property bool $unread
 * @property int $location
 * @property bool $saved
 */

namespace App\Models;

use App\Events\MessageCreated;
use App\Support\Cache;
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
        'unread' => 'boolean',
        'saved' => 'boolean',
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
        event(new MessageCreated($message));

        return $message;
    }
}
