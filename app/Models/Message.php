<?php

/**
 * @property int $id
 * @property int $sender
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
use Nexus\Database\NexusDB;

class Message extends NexusModel
{
    protected $table = 'messages';

    protected $fillable = [
        'sender', 'receiver', 'added', 'subject', 'msg', 'unread', 'location', 'saved'
    ];

    protected $casts = [
        'added' => 'datetime',
    ];

    public function send_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'sender')->withDefault(['id' => 0, 'username' => 'System']);
    }

    public function receive_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver');
    }

    public static function add(array $data): self
    {
        clear_inbox_count_cache($data["receiver"]);
        $message =  self::query()->create($data);
        fire_event(ModelEventEnum::MESSAGE_CREATED, $message);
        return $message;
    }

}
