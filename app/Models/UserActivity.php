<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;

/**
 * @property int $user_id
 * @property Carbon|null $last_login
 * @property Carbon|null $last_access
 * @property Carbon|null $last_home
 * @property Carbon|null $last_offer
 * @property Carbon|null $forum_access
 * @property Carbon|null $last_staffmsg
 * @property Carbon|null $last_pm
 * @property Carbon|null $last_comment
 * @property Carbon|null $last_post
 * @property Carbon|null $last_browse
 * @property Carbon|null $last_music
 * @property Carbon|null $last_catchup
 * @property Carbon|null $last_announce_at
 */
class UserActivity extends Model
{
    protected $table = 'user_activity';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'last_login', 'last_access', 'last_home', 'last_offer',
        'forum_access', 'last_staffmsg', 'last_pm', 'last_comment', 'last_post',
        'last_browse', 'last_music', 'last_catchup', 'last_announce_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_login' => 'datetime',
        'last_access' => 'datetime',
        'last_home' => 'datetime',
        'last_offer' => 'datetime',
        'forum_access' => 'datetime',
        'last_staffmsg' => 'datetime',
        'last_pm' => 'datetime',
        'last_comment' => 'datetime',
        'last_post' => 'datetime',
        'last_announce_at' => 'datetime',
        'last_browse' => 'datetime:U',
        'last_music' => 'datetime:U',
        'last_catchup' => 'datetime:U',
    ];

    public function getConnectionName(): string
    {
        return Config::get('nexus.database.default', null);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
