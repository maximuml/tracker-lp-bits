<?php

declare(strict_types=1);

namespace App\Models;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\Permission\RoutePermissionEnum;
use App\Enums\UserClass;
use App\Enums\UserDonate;
use App\Enums\UserGender;
use App\Enums\UserStatus;
use App\Exceptions\NexusException;
use App\Models\Traits\HasClassLadder;
use App\Models\Traits\HasFilamentAccess;
use App\Models\Traits\HasUserAccessors;
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
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string|null $username
 * @property string|null $passhash
 * @property string|null $secret
 * @property string|null $auth_key
 * @property string|null $email
 * @property string|null $status
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
 * @property string|null $privacy
 * @property int|null $stylesheet
 * @property int|null $caticon
 * @property string|null $fontsize
 * @property string|null $info
 * @property string|null $acceptpms
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
 * @property string|null $clicktopic
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
 * @property string|null $gender
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
 * @property string|null $tooltip
 * @property bool $shownfo
 * @property string|null $timetype
 * @property bool $appendsticky
 * @property bool $appendnew
 * @property string|null $appendpromotion
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
    use HasApiTokens, HasClassLadder, HasFactory, HasFilamentAccess, HasUserAccessors, HasUserRelationships, HasUserScopes, NexusActivityLogTrait, Notifiable;

    public $timestamps = false;

    protected $perPage = 50;

    /** @var array<string, array<string, string>> */
    public static array $donateStatus = [
        UserDonate::YES->value => ['text' => 'Yes'],
        UserDonate::NO->value => ['text' => 'No'],
    ];

    /** @var array<string, string> */
    public static array $genders = [
        UserGender::MALE->value => 'Male',
        UserGender::FEMALE->value => 'Female',
        UserGender::UNKNOWN->value => 'N/A',
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

    /**
     * Return the user as an array with the legacy columns that are normally
     * hidden (passkey, auth_key) included. Used when populating SupportContext
     * for legacy views that need those values.
     *
     * @return array<string, mixed>
     */
    public function toLegacyArray(): array
    {
        return $this->makeVisible(['passkey', 'auth_key'])->toArray();
    }

    /**
     * @param  list<string>  $fields
     */
    public function checkIsNormal(array $fields = ['status', 'enabled']): bool
    {
        $params = [
            'user_id' => $this->id,
            'username' => $this->username,
        ];
        if (in_array('status', $fields) && $this->getAttribute('status') != UserStatus::CONFIRMED->value) {
            throw new NexusException(Locale::trans('user.user_is_not_confirmed', $params, null));
        }
        if (in_array('enabled', $fields) && ! $this->getAttribute('enabled')) {
            throw new NexusException(Locale::trans('user.user_is_disabled', $params, null));
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $update
     * @param  string  $modComment
     */
    public function updateWithModComment(array $update, $modComment): bool
    {
        return $this->updateWithComment($update, $modComment, 'modcomment');
    }

    /**
     * @param  array<string, mixed>  $update
     * @param  string  $comment
     * @param  string  $commentField
     */
    public function updateWithComment(array $update, $comment, $commentField): bool
    {
        if (! $this->exists) {
            throw new \RuntimeException('This method only works when user exists !');
        }

        if ($commentField != 'modcomment') {
            throw new \RuntimeException("unsupported commentField: $commentField !");
        }

        return DB::transaction(function () use ($update, $comment) {
            $this->modifyLogs()->create(['content' => $comment]);

            return $this->update($update);
        });
    }

    public function isDonating(): bool
    {
        $rawDonorUntil = $this->getRawOriginal('donoruntil');
        $donorUntil = $this->donoruntil;
        if (
            $this->donor === true
            && ($rawDonorUntil === null || $rawDonorUntil == '0000-00-00 00:00:00' || ($donorUntil instanceof Carbon && $donorUntil->gte(Carbon::now())))
        ) {
            return true;
        }

        return false;
    }

    /** @param string $name */
    public function acceptNotification($name): bool
    {
        return $this->original['notifs'] === null || str_contains((string) $this->notifs, "[{$name}]");
    }

    public function tokenCan(string $ability): bool
    {
        if ($this->accessToken === null) {
            return false;
        }

        $routePermission = RoutePermissionEnum::tryFrom($ability);
        if ($routePermission !== null) {
            $legacyPermission = $routePermission->toPermissionEnum();
            if ($legacyPermission === null) {
                return $this->accessToken->can($ability);
            }

            return Permission::can($legacyPermission, $this)
                && $this->accessToken->can($ability);
        }

        $legacyPermission = PermissionEnum::tryFrom($ability);
        if ($legacyPermission !== null) {
            return Permission::can($legacyPermission, $this)
                && $this->accessToken->can($ability);
        }

        return $this->accessToken->can($ability);
    }
}
