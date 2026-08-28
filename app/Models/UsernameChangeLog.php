<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $uid
 * @property string $operator
 * @property int $change_type
 * @property string $username_old
 * @property string $username_new
 * @property string|null $created_at
 * @property string|null $updated_at
 */

namespace App\Models;

use App\Support\Locale;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsernameChangeLog extends NexusModel
{
    /** @var list<string> */
    protected $fillable = ['uid', 'username_old', 'username_new', 'operator', 'change_type'];

    /** @var bool */
    public $timestamps = true;

    /** @deprecated Use App\Enums\UsernameChangeType enum instead. */
    const CHANGE_TYPE_USER = 1;

    /** @deprecated Use App\Enums\UsernameChangeType enum instead. */
    const CHANGE_TYPE_ADMIN = 2;

    /** @var array<int|string, mixed> */
    public static array $changeTypes = [
        self::CHANGE_TYPE_USER => ['text' => 'User'],
        self::CHANGE_TYPE_ADMIN => ['text' => 'Administrator'],
    ];

    /** @return  mixed */
    public function getChangeTypeTextAttribute()
    {
        return Locale::trans('username-change-log.change_type.'.$this->change_type, [], null);
    }

    /** @return  BelongsTo<User, $this> */
    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }

    /** @return  mixed */
    public static function listChangeType()
    {
        $result = [];
        foreach (self::$changeTypes as $type => $info) {
            $result[$type] = Locale::trans('username-change-log.change_type.'.$type, [], null);
        }

        return $result;
    }
}
