<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;

/**
 * @property int $user_id
 * @property int $stylesheet
 * @property int $caticon
 * @property string $fontsize
 * @property int $torrentsperpage
 * @property int $topicsperpage
 * @property int $postsperpage
 * @property string $clicktopic
 * @property string $tooltip
 * @property string $timetype
 * @property string $appendpromotion
 * @property int $appendnew
 * @property int $appendpicked
 * @property int $appendsticky
 * @property int $avatars
 * @property int $bmicon
 * @property int $commentpm
 * @property int $deletepms
 * @property int $dlicon
 * @property int $forumpost
 * @property int $savepms
 * @property int $showclienterror
 * @property int $showcomment
 * @property int $showcomnum
 * @property int $showdescription
 * @property int $showimdb
 * @property int $showlastcom
 * @property int $showlastpost
 * @property int $shownfo
 * @property int $showsmalldescr
 * @property int $signatures
 * @property string $acceptpms
 * @property string|null $notifs
 * @property int $lang
 * @property int $sbnum
 * @property int $sbrefresh
 * @property int $showdlnotice
 * @property int $clientselect
 * @property string|null $info
 * @property int $support
 * @property string $stafffor
 * @property string $supportfor
 * @property string $pickfor
 * @property string $supportlang
 * @property string|null $page
 * @property string $signature
 */
class UserPreference extends Model
{
    protected $table = 'user_preferences';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'stylesheet', 'caticon', 'fontsize', 'torrentsperpage',
        'topicsperpage', 'postsperpage', 'clicktopic', 'tooltip', 'timetype',
        'appendpromotion', 'appendnew', 'appendpicked', 'appendsticky', 'avatars',
        'bmicon', 'commentpm', 'deletepms', 'dlicon', 'forumpost', 'savepms',
        'showclienterror', 'showcomment', 'showcomnum', 'showdescription',
        'showimdb', 'showlastcom', 'showlastpost', 'shownfo', 'showsmalldescr',
        'signatures', 'acceptpms', 'notifs', 'lang', 'sbnum', 'sbrefresh',
        'showdlnotice', 'clientselect', 'info', 'support', 'stafffor',
        'supportfor', 'pickfor', 'supportlang', 'page', 'signature',
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
