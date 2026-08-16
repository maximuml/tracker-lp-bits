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
    /** @var  string */
    protected $table = 'messages';

    /** @var  list<string> */
    protected $fillable = [
        'sender', 'receiver', 'added', 'subject', 'msg', 'unread', 'location', 'saved'
    ];

    /** @var  array<string, string> */
    protected $casts = [
        'added' => 'datetime',
    ];

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function send_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'sender')->withDefault(['id' => 0, 'username' => 'System']);
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function receive_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver');
    }

    /** @param  array<string, mixed>  $data */
    public static function add(array $data): self
    {
        \App\Support\Cache::clearInboxCount($data["receiver"]);
        $message =  self::query()->create($data);
        \App\Support\Events::fire(ModelEventEnum::MESSAGE_CREATED, $message, null);
        return $message;
    }

}
