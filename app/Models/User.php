<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserAcceptPms;
use App\Enums\UserAppendPromotion;
use App\Enums\UserClass;
use App\Enums\UserClickTopic;
use App\Enums\UserDonate;
use App\Enums\UserFontsize;
use App\Enums\UserGender;
use App\Enums\UserPrivacy;
use App\Enums\UserStatus;
use App\Enums\UserTimeType;
use App\Enums\UserTooltip;
use App\Models\Traits\HasClassLadder;
use App\Models\Traits\HasFilamentAccess;
use App\Models\Traits\HasUserAccessors;
use App\Models\Traits\HasUserApi;
use App\Models\Traits\HasUserAuth;
use App\Models\Traits\HasUserModeration;
use App\Models\Traits\HasUserRelationships;
use App\Models\Traits\HasUserScopes;
use App\Models\Traits\NexusActivityLogTrait;
use App\Support\Locale;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string|null $username
 * @property string|null $passhash
 * @property string|null $secret
 * @property string|null $auth_key
 * @property string|null $email
 * @property int|null $status
 * @property string|null $added
 * @property Carbon|null $last_login
 * @property string|null $last_access
 * @property string|null $last_home
 * @property string|null $last_offer
 * @property string|null $forum_access
 * @property string|null $last_staffmsg
 * @property string|null $last_pm
 * @property string|null $last_comment
 * @property string|null $last_post
 * @property int|null $last_browse
 * @property int|null $last_music
 * @property int|null $last_catchup
 * @property string|null $editsecret
 * @property int|null $privacy
 * @property int|null $stylesheet
 * @property int|null $caticon
 * @property int|null $fontsize
 * @property string|null $info
 * @property int|null $acceptpms
 * @property bool $commentpm
 * @property string|null $ip
 * @property int|null $class
 * @property int|null $max_class_once
 * @property string|null $avatar
 * @property int|null $uploaded
 * @property int|null $downloaded
 * @property int|null $seedtime
 * @property int|null $leechtime
 * @property string|null $title
 * @property int|null $country
 * @property string|null $notifs
 * @property string|null $modcomment
 * @property bool $enabled
 * @property bool $avatars
 * @property bool $donor
 * @property string|null $donated
 * @property string|null $donated_cny
 * @property Carbon|null $donoruntil
 * @property bool $warned
 * @property string|null $warneduntil
 * @property bool $noad
 * @property string|null $noaduntil
 * @property int|null $torrentsperpage
 * @property int|null $topicsperpage
 * @property int|null $postsperpage
 * @property int|null $clicktopic
 * @property bool $deletepms
 * @property bool $savepms
 * @property bool $support
 * @property bool $picker
 * @property string|null $stafffor
 * @property string|null $supportfor
 * @property string|null $pickfor
 * @property string|null $supportlang
 * @property string|null $passkey
 * @property bool $uploadpos
 * @property bool $forumpost
 * @property bool $downloadpos
 * @property int|null $clientselect
 * @property bool $signatures
 * @property string|null $signature
 * @property int|null $lang
 * @property string|null $locale
 * @property int|null $cheat
 * @property int|null $invites
 * @property int|null $invited_by
 * @property int|null $gender
 * @property bool $vip_added
 * @property string|null $vip_until
 * @property float|null $seedbonus
 * @property float|null $charity
 * @property bool $parked
 * @property bool $leechwarn
 * @property string|null $leechwarnuntil
 * @property string|null $lastwarned
 * @property int|null $timeswarned
 * @property int|null $warnedby
 * @property int|null $sbnum
 * @property int|null $sbrefresh
 * @property bool $showimdb
 * @property bool $showdescription
 * @property bool $showcomment
 * @property bool $showclienterror
 * @property int|null $showdlnotice
 * @property int|null $tooltip
 * @property bool $shownfo
 * @property int|null $timetype
 * @property bool $appendsticky
 * @property bool $appendnew
 * @property int|null $appendpromotion
 * @property bool $appendpicked
 * @property bool $dlicon
 * @property bool $bmicon
 * @property bool $showsmalldescr
 * @property bool $showcomnum
 * @property bool $showlastcom
 * @property bool $showlastpost
 * @property int|null $pmnum
 * @property string|null $page
 * @property string|null $two_step_secret
 * @property float|null $seed_points
 * @property float|null $seed_points_per_hour
 * @property float|null $seed_bonus_per_hour
 * @property int|null $attendance_card
 * @property int|null $offer_allowed_count
 * @property string|null $seed_points_updated_at
 * @property string|null $seed_time_updated_at
 * @property int|null $seeding_torrent_count
 * @property int|null $seeding_torrent_size
 * @property string|null $last_announce_at
 * @property int|null $tracker_url_id
 * @property string|null $bonuscomment
 * @property-read Language|null $language
 */
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasClassLadder, HasFactory, HasFilamentAccess, HasUserAccessors, HasUserApi, HasUserAuth, HasUserModeration, HasUserRelationships, HasUserScopes, NexusActivityLogTrait, Notifiable {
        HasUserAuth::tokenCan insteadof HasApiTokens;
    }

    public $timestamps = false;

    protected $perPage = 50;

    /** @var array<string, array<string, string>> */
    public static array $donateStatus = [
        UserDonate::YES->value => ['text' => 'Yes'],
        UserDonate::NO->value => ['text' => 'No'],
    ];

    /** @var array<int, string> */
    public static array $genders = [
        UserGender::MALE->value => 'Male',
        UserGender::FEMALE->value => 'Female',
        UserGender::UNKNOWN->value => 'N/A',
    ];

    /** @var array<string, class-string> */
    public static array $ENUM_STRING_KEYS = [
        'status' => UserStatus::class,
        'privacy' => UserPrivacy::class,
        'fontsize' => UserFontsize::class,
        'acceptpms' => UserAcceptPms::class,
        'clicktopic' => UserClickTopic::class,
        'gender' => UserGender::class,
        'tooltip' => UserTooltip::class,
        'timetype' => UserTimeType::class,
        'appendpromotion' => UserAppendPromotion::class,
    ];

    /** @var array<string, string> */
    public static array $cardTitles = [
        'uploaded_human' => '上传量',
        'downloaded_human' => '下载量',
        'share_ratio' => '分享率',
        'bonus' => '魔力值',
        'seed_points' => '做种积分',
        'invites' => '邀请',
    ];

    /** @var list<string> */
    public static array $notificationOptions = ['topic_reply', 'hr_reached'];

    private const USER_ENABLE_LATELY = 'user_enable_lately:%s';

    /**
     * W3-01: Maps partitioned column names to their partition table.
     * Used by UserPartitionObserver to decide which partition table(s)
     * to update during dual-write. The columns remain on the users
     * table during phase 1 — this mapping only drives the observer.
     *
     * @var array<string, string>
     */
    public static array $partitionedColumns = [
        // user_preferences
        'stylesheet' => 'user_preferences',
        'caticon' => 'user_preferences',
        'fontsize' => 'user_preferences',
        'torrentsperpage' => 'user_preferences',
        'topicsperpage' => 'user_preferences',
        'postsperpage' => 'user_preferences',
        'clicktopic' => 'user_preferences',
        'tooltip' => 'user_preferences',
        'timetype' => 'user_preferences',
        'appendpromotion' => 'user_preferences',
        'appendnew' => 'user_preferences',
        'appendpicked' => 'user_preferences',
        'appendsticky' => 'user_preferences',
        'avatars' => 'user_preferences',
        'bmicon' => 'user_preferences',
        'commentpm' => 'user_preferences',
        'deletepms' => 'user_preferences',
        'dlicon' => 'user_preferences',
        'forumpost' => 'user_preferences',
        'savepms' => 'user_preferences',
        'showclienterror' => 'user_preferences',
        'showcomment' => 'user_preferences',
        'showcomnum' => 'user_preferences',
        'showdescription' => 'user_preferences',
        'showimdb' => 'user_preferences',
        'showlastcom' => 'user_preferences',
        'showlastpost' => 'user_preferences',
        'shownfo' => 'user_preferences',
        'showsmalldescr' => 'user_preferences',
        'signatures' => 'user_preferences',
        'acceptpms' => 'user_preferences',
        'notifs' => 'user_preferences',
        'lang' => 'user_preferences',
        'sbnum' => 'user_preferences',
        'sbrefresh' => 'user_preferences',
        'showdlnotice' => 'user_preferences',
        'clientselect' => 'user_preferences',
        'info' => 'user_preferences',
        'support' => 'user_preferences',
        'stafffor' => 'user_preferences',
        'supportfor' => 'user_preferences',
        'pickfor' => 'user_preferences',
        'supportlang' => 'user_preferences',
        'page' => 'user_preferences',
        'signature' => 'user_preferences',
        // user_activity
        'last_login' => 'user_activity',
        'last_access' => 'user_activity',
        'last_home' => 'user_activity',
        'last_offer' => 'user_activity',
        'forum_access' => 'user_activity',
        'last_staffmsg' => 'user_activity',
        'last_pm' => 'user_activity',
        'last_comment' => 'user_activity',
        'last_post' => 'user_activity',
        'last_browse' => 'user_activity',
        'last_music' => 'user_activity',
        'last_catchup' => 'user_activity',
        'last_announce_at' => 'user_activity',
        // user_seed_stats
        'seed_points' => 'user_seed_stats',
        'seed_points_per_hour' => 'user_seed_stats',
        'seed_bonus_per_hour' => 'user_seed_stats',
        'seed_points_updated_at' => 'user_seed_stats',
        'seed_time_updated_at' => 'user_seed_stats',
        'seeding_torrent_count' => 'user_seed_stats',
        'seeding_torrent_size' => 'user_seed_stats',
        'attendance_card' => 'user_seed_stats',
        'offer_allowed_count' => 'user_seed_stats',
    ];

    public function getConnectionName(): string
    {
        return Config::get('nexus.database.default', null);
    }

    public static function getUserEnableLatelyCacheKey(int $userId): string
    {
        return sprintf(self::USER_ENABLE_LATELY, $userId);
    }

    /**
     * @param  int|string  $id
     */
    public static function exists($id): bool
    {
        return self::query()->where('id', $id)->exists();
    }

    /**
     * 为数组 / JSON 序列化准备日期。
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format($this->dateFormat ?: 'Y-m-d H:i:s');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username', 'email', 'passhash', 'passhash_algo', 'secret', 'stylesheet', 'editsecret', 'added', 'enabled', 'status',
        'leechwarn', 'leechwarnuntil', 'page', 'class', 'uploaded', 'downloaded', 'clientselect', 'showclienterror', 'last_home',
        'seedbonus', 'downloadpos', 'vip_added', 'vip_until', 'title', 'invites', 'attendance_card',
        'seed_points_per_hour', 'passkey', 'auth_key', 'last_login', 'lang', 'last_pm',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [
        'secret', 'passhash', 'passkey', 'auth_key',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'must_change_password' => 'boolean',
        'added' => 'datetime',
        'last_login' => 'datetime',
        'last_access' => 'datetime',
        'last_home' => 'datetime',
        'last_offer' => 'datetime',
        'forum_access' => 'datetime',
        'last_staffmsg' => 'datetime',
        'last_pm' => 'datetime',
        'last_comment' => 'datetime',
        'last_post' => 'datetime',
        'lastwarned' => 'datetime',
        'last_browse' => 'datetime:U',
        'last_music' => 'datetime:U',
        'last_catchup' => 'datetime:U',
        'donoruntil' => 'datetime',
        'warneduntil' => 'datetime',
        'noaduntil' => 'datetime',
        'vip_until' => 'datetime',
        'leechwarnuntil' => 'datetime',
        'status' => UserStatus::class,
        'privacy' => UserPrivacy::class,
        'fontsize' => UserFontsize::class,
        'acceptpms' => UserAcceptPms::class,
        'clicktopic' => UserClickTopic::class,
        'gender' => UserGender::class,
        'tooltip' => UserTooltip::class,
        'timetype' => UserTimeType::class,
        'appendpromotion' => UserAppendPromotion::class,
        'appendnew' => 'boolean',
        'appendpicked' => 'boolean',
        'appendsticky' => 'boolean',
        'avatars' => 'boolean',
        'bmicon' => 'boolean',
        'commentpm' => 'boolean',
        'deletepms' => 'boolean',
        'dlicon' => 'boolean',
        'donor' => 'boolean',
        'downloadpos' => 'boolean',
        'enabled' => 'boolean',
        'forumpost' => 'boolean',
        'leechwarn' => 'boolean',
        'noad' => 'boolean',
        'parked' => 'boolean',
        'picker' => 'boolean',
        'savepms' => 'boolean',
        'showclienterror' => 'boolean',
        'showcomment' => 'boolean',
        'showcomnum' => 'boolean',
        'showdescription' => 'boolean',
        'showimdb' => 'boolean',
        'showlastcom' => 'boolean',
        'showlastpost' => 'boolean',
        'shownfo' => 'boolean',
        'showsmalldescr' => 'boolean',
        'signatures' => 'boolean',
        'support' => 'boolean',
        'uploadpos' => 'boolean',
        'vip_added' => 'boolean',
        'warned' => 'boolean',
    ];

    /** @var list<string> */
    public static array $commonFields = [
        'id', 'username', 'email', 'class', 'status', 'added', 'avatar', 'passkey',
        'uploaded', 'downloaded', 'seedbonus', 'seedtime', 'leechtime',
        'invited_by', 'enabled', 'seed_points', 'last_access', 'invites',
        'lang', 'attendance_card', 'privacy', 'noad', 'downloadpos', 'donoruntil', 'donor',
        'downloadpos', 'vip_added', 'vip_until', 'title', 'invites', 'attendance_card',
        'seed_points_per_hour',
    ];

    /** @return array<string, mixed> */
    public static function getDefaultUserAttributes(): array
    {
        return [
            'id' => 0,
            'username' => Locale::trans('user.deleted_username', [], null),
            'class' => UserClass::PEASANT->value,
            'email' => '',
            'status' => UserStatus::CONFIRMED->value,
            'added' => '1970-01-01 08:00:00',
            'avatar' => '',
            'uploaded' => 0,
            'downloaded' => 0,
            'seedbonus' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'enabled' => false,
            'seed_points' => 0,
        ];
    }

    public static function defaultUser(): self
    {
        return (new self)->forceFill(self::getDefaultUserAttributes());
    }
}
