<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $sender
 * @property string|null $added
 * @property string|null $msg
 * @property string $subject
 * @property int $answeredby
 * @property int $answered
 * @property string|null $answer
 * @property string $permission
 */

namespace App\Models;

use App\Enums\ModelEventEnum;
use App\Support\Events;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffMessage extends NexusModel
{
    /** @var string */
    protected $table = 'staffmessages';

    /** @var list<string> */
    protected $fillable = [
        'sender', 'added', 'subject', 'msg', 'answeredby', 'answered', 'answer', 'permission',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
    ];

    /** @return  BelongsTo<User, $this> */
    public function send_user()
    {
        return $this->belongsTo(User::class, 'sender')->withDefault(['id' => 0, 'username' => 'System']);
    }

    /** @return  BelongsTo<User, $this> */
    public function answer_user()
    {
        return $this->belongsTo(User::class, 'answeredby');
    }

    /**
     * @return mixed
     */
    public static function add(int $sender, string $subject, string $msg)
    {
        $record = self::query()->create([
            'sender' => $sender,
            'subject' => $subject,
            'msg' => $msg,
            'added' => now(),
        ]);
        Events::fire(ModelEventEnum::STAFF_MESSAGE_CREATED, $record, null);

        return $record;
    }
}
