<?php

/**
 * @property int $id
 * @property string $username
 * @property string $passhash
 * @property string $secret
 * @property string $auth_key
 * @property string $email
 * @property string $status
 * @property string|null $added
 * @property string|null $last_login
 * @property string|null $last_access
 * @property string|null $last_home
 * @property string|null $last_offer
 * @property string|null $forum_access
 * @property string|null $last_staffmsg
 * @property string|null $last_pm
 * @property string|null $last_comment
 * @property string|null $last_post
 * @property int $last_browse
 * @property int $last_music
 * @property int $last_catchup
 * @property string $editsecret
 * @property string $privacy
 * @property int $stylesheet
 * @property int $caticon
 * @property string $fontsize
 * @property string|null $info
 * @property string $acceptpms
 * @property string $commentpm
 * @property string $ip
 * @property int $class
 * @property int $max_class_once
 * @property string $avatar
 * @property int $uploaded
 * @property int $downloaded
 * @property int $seedtime
 * @property int $leechtime
 * @property string $title
 * @property int $country
 * @property string|null $notifs
 * @property string $enabled
 * @property string $avatars
 * @property string $donor
 * @property float $donated
 * @property float $donated_cny
 * @property string|null $donoruntil
 * @property string $warned
 * @property string|null $warneduntil
 * @property string $noad
 * @property string|null $noaduntil
 * @property int $torrentsperpage
 * @property int $topicsperpage
 * @property int $postsperpage
 * @property string $clicktopic
 * @property string $deletepms
 * @property string $savepms
 * @property string $support
 * @property string $picker
 * @property string $stafffor
 * @property string $supportfor
 * @property string $pickfor
 * @property string $supportlang
 * @property string $passkey
 * @property string $uploadpos
 * @property string $forumpost
 * @property string $downloadpos
 * @property int $clientselect
 * @property string $signatures
 * @property string $signature
 * @property int $lang
 * @property string $locale
 * @property int $cheat
 * @property int $invites
 * @property int $invited_by
 * @property string $gender
 * @property string $vip_added
 * @property string|null $vip_until
 * @property float $seedbonus
 * @property float $charity
 * @property string $parked
 * @property string $leechwarn
 * @property string|null $leechwarnuntil
 * @property string|null $lastwarned
 * @property int $timeswarned
 * @property int $warnedby
 * @property int $sbnum
 * @property int $sbrefresh
 * @property string|null $showimdb
 * @property string|null $showdescription
 * @property string|null $showcomment
 * @property string $showclienterror
 * @property int $showdlnotice
 * @property string $tooltip
 * @property string|null $shownfo
 * @property string|null $timetype
 * @property string|null $appendsticky
 * @property string|null $appendnew
 * @property string|null $appendpromotion
 * @property string|null $appendpicked
 * @property string|null $dlicon
 * @property string|null $bmicon
 * @property string $showsmalldescr
 * @property string|null $showcomnum
 * @property string|null $showlastcom
 * @property string $showlastpost
 * @property int $pmnum
 * @property string|null $page
 * @property string $two_step_secret
 * @property float $seed_points
 * @property float $seed_points_per_hour
 * @property float $seed_bonus_per_hour
 * @property int $attendance_card
 * @property int $offer_allowed_count
 * @property string|null $seed_points_updated_at
 * @property string|null $seed_time_updated_at
 * @property int $provider_id
 * @property int $seeding_torrent_count
 * @property int $seeding_torrent_size
 * @property string|null $last_announce_at
 * @property int $tracker_url_id
 */

namespace App\Models;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\Permission\RoutePermissionEnum;
use App\Exceptions\NexusException;
use App\Http\Middleware\Locale;
use App\Models\Traits\NexusActivityLogTrait;
use App\Repositories\ExamRepository;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Logger;
use App\Support\Url;
use App\Support\UserDisplay;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Nexus\Database\NexusDB;

/**
 * @property int $id
 * @property string|null $username
 * @property string|null $passhash
 * @property string|null $secret
 * @property string|null $email
 * @property string|null $added
 * @property string|null $last_login
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
 * @property int|null $stylesheet
 * @property int|null $caticon
 * @property string|null $info
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
 * @property string|null $donated
 * @property string|null $donated_cny
 * @property Carbon|null $donoruntil
 * @property string|null $warneduntil
 * @property string|null $noaduntil
 * @property int|null $torrentsperpage
 * @property int|null $topicsperpage
 * @property int|null $postsperpage
 * @property string|null $stafffor
 * @property string|null $supportfor
 * @property string|null $pickfor
 * @property string|null $supportlang
 * @property string|null $passkey
 * @property int|null $clientselect
 * @property string|null $signature
 * @property int|null $lang
 * @property int|null $cheat
 * @property int|null $invites
 * @property int|null $invited_by
 * @property string|null $vip_until
 * @property string|null $bonuscomment
 * @property string|null $leechwarnuntil
 * @property string|null $lastwarned
 * @property int|null $timeswarned
 * @property int|null $warnedby
 * @property int|null $sbnum
 * @property int|null $sbrefresh
 * @property int|null $showdlnotice
 * @property int|null $pmnum
 * @property string|null $page
 * @property-read Language|null $language
 */
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, NexusActivityLogTrait, Notifiable;

    public $timestamps = false;

    protected $perPage = 50;

    const STATUS_CONFIRMED = 'confirmed';

    const STATUS_PENDING = 'pending';

    const ENABLED_YES = 'yes';

    const ENABLED_NO = 'no';

    const CLASS_PEASANT = '0';

    const CLASS_USER = '1';

    const CLASS_POWER_USER = '2';

    const CLASS_ELITE_USER = '3';

    const CLASS_CRAZY_USER = '4';

    const CLASS_INSANE_USER = '5';

    const CLASS_VETERAN_USER = '6';

    const CLASS_EXTREME_USER = '7';

    const CLASS_ULTIMATE_USER = '8';

    const CLASS_NEXUS_MASTER = '9';

    const CLASS_VIP = '10';

    const CLASS_RETIREE = '11';

    const CLASS_UPLOADER = '12';

    const CLASS_MODERATOR = '13';

    const CLASS_ADMINISTRATOR = '14';

    const CLASS_SYSOP = '15';

    const CLASS_STAFF_LEADER = '16';

    /** @var array<int|string, array<string, mixed>> */
    public static array $classes = [
        self::CLASS_PEASANT => ['text' => 'Peasant'],
        self::CLASS_USER => ['text' => 'User', 'min_seed_points' => 0],
        self::CLASS_POWER_USER => ['text' => 'Power User', 'min_seed_points' => 40000],
        self::CLASS_ELITE_USER => ['text' => 'Elite User', 'min_seed_points' => 80000],
        self::CLASS_CRAZY_USER => ['text' => 'Crazy User', 'min_seed_points' => 150000],
        self::CLASS_INSANE_USER => ['text' => 'Insane User', 'min_seed_points' => 250000],
        self::CLASS_VETERAN_USER => ['text' => 'Veteran User', 'min_seed_points' => 400000],
        self::CLASS_EXTREME_USER => ['text' => 'Extreme User', 'min_seed_points' => 600000],
        self::CLASS_ULTIMATE_USER => ['text' => 'Ultimate User', 'min_seed_points' => 800000],
        self::CLASS_NEXUS_MASTER => ['text' => 'Nexus Master', 'min_seed_points' => 1000000],
        self::CLASS_VIP => ['text' => 'VIP'],
        self::CLASS_RETIREE => ['text' => 'Retiree'],
        self::CLASS_UPLOADER => ['text' => 'Uploader'],
        self::CLASS_MODERATOR => ['text' => 'Moderator'],
        self::CLASS_ADMINISTRATOR => ['text' => 'Administrator'],
        self::CLASS_SYSOP => ['text' => 'Sysop'],
        self::CLASS_STAFF_LEADER => ['text' => 'Staff Leader'],
    ];

    const DONATE_YES = 'yes';

    const DONATE_NO = 'no';

    /** @var array<string, array<string, string>> */
    public static array $donateStatus = [
        self::DONATE_YES => ['text' => 'Yes'],
        self::DONATE_NO => ['text' => 'No'],
    ];

    const GENDER_FEMALE = 'Female';

    const GENDER_MALE = 'Male';

    const GENDER_UNKNOWN = 'N/A';

    /** @var array<string, string> */
    public static array $genders = [
        self::GENDER_MALE => 'Male',
        self::GENDER_FEMALE => 'Female',
        self::GENDER_UNKNOWN => 'N/A',
    ];

    /** @var array<string, string> */
    public static array $cardTitles = [
        'uploaded_human' => '上传量',
        'downloaded_human' => '下载量',
        'share_ratio' => '分享率',
        //        'seed_time' => '做种时间',
        'bonus' => '魔力值',
        'seed_points' => '做种积分',
        'invites' => '邀请',
    ];

    /** @var list<string> */
    public static array $notificationOptions = ['topic_reply', 'hr_reached'];

    private const USER_ENABLE_LATELY = 'user_enable_lately:%s';

    /** @return string */
    public function getConnectionName()
    {
        return NexusDB::getConnectionName();
    }

    public static function getUserEnableLatelyCacheKey(int $userId): string
    {
        return sprintf(self::USER_ENABLE_LATELY, $userId);
    }

    public function getClassTextAttribute(): string
    {
        return self::getClassText((int) $this->class);
    }

    /**
     * @param  int|string  $class
     * @return string
     */
    public static function getClassText($class)
    {
        if (! is_numeric($class) || ! isset(self::$classes[$class])) {
            return '';
        }
        $classText = self::$classes[$class]['text'];
        if ($class >= self::CLASS_VIP) {
            $alias = \App\Support\Locale::trans('user.class_names.'.$class, [], null);
        } else {
            $alias = SiteConfig::current()->account->classAlias($class);
        }
        if (! empty($alias)) {
            $classText .= "({$alias})";
        }

        return $classText;
    }

    /**
     * @param  int|string  $min
     * @param  int|string  $max
     * @return array<int|string, string>
     */
    public static function listClass($min = self::CLASS_PEASANT, $max = self::CLASS_STAFF_LEADER): array
    {
        $result = [];
        foreach (self::$classes as $class => $info) {
            if ($class >= $min && $class <= $max) {
                $result[$class] = self::getClassText($class);
            }
        }

        return $result;
    }

    /**
     * @param  int|string  $id
     */
    public static function exists($id): bool
    {
        return self::query()->where('id', $id)->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canAccessAdmin();
    }

    public function getFilamentName(): string
    {
        return (string) $this->username;
    }

    /**
     * @see ExamRepository::isExamMatchUser()
     */
    public function getDonateStatusAttribute(): string
    {
        if ($this->isDonating()) {
            return self::DONATE_YES;
        }

        return self::DONATE_NO;
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
        'username', 'email', 'passhash', 'secret', 'stylesheet', 'editsecret', 'added', 'enabled', 'status',
        'leechwarn', 'leechwarnuntil', 'page', 'class', 'uploaded', 'downloaded', 'clientselect', 'showclienterror', 'last_home',
        'seedbonus', 'downloadpos', 'vip_added', 'vip_until', 'title', 'invites', 'attendance_card',
        'seed_points_per_hour', 'passkey', 'auth_key', 'last_login', 'lang', 'provider_id',
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
            'username' => \App\Support\Locale::trans('user.deleted_username', [], null),
            'class' => self::CLASS_PEASANT,
            'email' => '',
            'status' => self::STATUS_CONFIRMED,
            'added' => '1970-01-01 08:00:00',
            'avatar' => '',
            'uploaded' => 0,
            'downloaded' => 0,
            'seedbonus' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'enabled' => self::ENABLED_NO,
            'seed_points' => 0,
        ];
    }

    public static function defaultUser(): self
    {
        return new self(self::getDefaultUserAttributes());
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
     * @param  int|string  $class
     * @param  bool  $compact
     * @param  bool  $b_colored
     * @param  bool  $I18N
     * @return string
     */
    public static function getClassName($class, $compact = false, $b_colored = false, $I18N = false)
    {
        $class_name = self::$classes[$class]['text'] ?? '';
        if ($class >= self::CLASS_VIP && $I18N) {
            $class_name = \App\Support\Locale::trans("user.class_names.{$class}", [], null);
        }
        $class_name_color = self::$classes[$class]['text'] ?? '';
        if ($compact) {
            $class_name = str_replace(' ', '', $class_name);
        }
        if ($class_name && $b_colored) {
            return "<b class='".str_replace(' ', '', $class_name_color)."_Name'>".$class_name.'</b>';
        }

        return $class_name;
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
        if (in_array('status', $fields) && $this->getAttribute('status') != self::STATUS_CONFIRMED) {
            throw new NexusException(\App\Support\Locale::trans('user.user_is_not_confirmed', $params, null));
        }
        if (in_array('enabled', $fields) && $this->getAttribute('enabled') != self::ENABLED_YES) {
            throw new NexusException(\App\Support\Locale::trans('user.user_is_disabled', $params, null));
        }

        return true;
    }

    /** @return string */
    public function getLocaleAttribute()
    {
        $locale = null;
        $log = 'user: '.$this->id;
        if (UserDisplay::currentId() == $this->id) {
            $locale = Locale::getLocaleFromCookie();
            $log .= ", locale from cookie: $locale";
        }
        if (! $locale) {
            $lang = $this->language?->site_lang_folder ?: 'en';
            $locale = Locale::$languageMaps[$lang] ?? 'en';
            $log .= ", [NO_DATA_FROM_COOKIE], lang from database: $lang, locale: $locale";
        }
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return $locale;
    }

    /** @return string */
    public function getSiteLangFolderAttribute()
    {
        return 'en';
    }

    /** @return Attribute<string, mixed> */
    protected function uploadedText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => Format::size($attributes['uploaded'])
        );
    }

    /** @return Attribute<string, mixed> */
    protected function downloadedText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => Format::size($attributes['downloaded'])
        );
    }

    /** @return Attribute<string, mixed> */
    protected function genderText(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => \App\Support\Locale::trans('user.genders.'.$attributes['gender'], [], null)
        );
    }

    protected function getTwoFactorAuthenticationStatusAttribute(): string
    {
        return $this->two_step_secret != '' ? 'yes' : 'no';
    }

    /**
     * @param  int|string  $class
     * @return int|float|false
     */
    public static function getMinSeedPoints($class)
    {
        $setting = SiteConfig::current()->account->classMinSeedPoints($class);
        if (is_numeric($setting)) {
            return $setting;
        }

        return self::$classes[$class]['min_seed_points'] ?? false;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeNormal(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED)->where('enabled', self::ENABLED_YES);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeDonating(Builder $query): Builder
    {
        return $query->where('donor', 'yes')->where(function (Builder $query) {
            return $query->whereNull('donoruntil')
                ->orWhere('donoruntil', '>=', now());
        });
    }

    /**
     * @return HasMany<ExamUser, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(ExamUser::class, 'uid');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'lang');
    }

    /** @return HasOne<Invite, $this> */
    public function invitee_code()
    {
        return $this->hasOne(Invite::class, 'invitee_register_uid');
    }

    /** @return BelongsTo<User, $this> */
    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return BelongsTo<Country, $this> */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country');
    }

    /** @return HasMany<Invite, $this> */

    /** @return HasMany<Invite, $this> */
    public function temporary_invites()
    {
        return $this->hasMany(Invite::class, 'inviter')
            ->where('invitee', '')
            ->whereNotNull('expired_at')
            ->where('expired_at', '>=', Carbon::now());
    }

    /** @return HasMany<Message, $this> */
    public function send_messages()
    {
        return $this->hasMany(Message::class, 'sender');
    }

    /** @return HasMany<Message, $this> */
    public function receive_messages()
    {
        return $this->hasMany(Message::class, 'receiver');
    }

    /** @return HasMany<Comment, $this> */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'user');
    }

    /** @return HasMany<Post, $this> */
    public function posts()
    {
        return $this->hasMany(Post::class, 'userid');
    }

    /**
     * @return HasMany<Torrent, $this>
     */
    public function torrents(): HasMany
    {
        return $this->hasMany(Torrent::class, 'owner');
    }

    /** @return HasMany<Bookmark, $this> */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'userid');
    }

    /** @return HasManyThrough<Torrent, Peer, $this> */
    public function peers_torrents()
    {
        return $this->hasManyThrough(
            Torrent::class,
            Peer::class,
            'userid',
            'id',
            'id',
            'torrent');
    }

    /** @return HasManyThrough<Torrent, Snatch, $this> */
    public function snatched_torrents()
    {
        return $this->hasManyThrough(
            Torrent::class,
            Snatch::class,
            'userid',
            'id',
            'id',
            'torrentid');
    }

    /** @return HasManyThrough<Torrent, Peer, $this> */
    public function seeding_torrents()
    {
        return $this->peers_torrents()->where('peers.seeder', Peer::SEEDER_YES);
    }

    /** @return HasManyThrough<Torrent, Peer, $this> */
    public function leeching_torrents()
    {
        return $this->peers_torrents()->where('peers.seeder', Peer::SEEDER_NO);
    }

    /** @return HasManyThrough<Torrent, Snatch, $this> */
    public function completed_torrents()
    {
        return $this->snatched_torrents()->where('snatched.finished', Snatch::FINISHED_YES);
    }

    /** @return HasManyThrough<Torrent, Snatch, $this> */
    public function incomplete_torrents()
    {
        return $this->snatched_torrents()->where('snatched.finished', Snatch::FINISHED_NO);
    }

    /** @return HasMany<HitAndRun, $this> */
    public function hitAndRuns(): HasMany
    {
        return $this->hasMany(HitAndRun::class, 'uid');
    }

    /**
     * @return BelongsToMany<Medal, $this>
     */
    public function medals(): BelongsToMany
    {
        return $this->belongsToMany(Medal::class, 'user_medals', 'uid', 'medal_id')
            ->withPivot(['id', 'expire_at', 'status', 'priority', 'bonus_addition_expire_at'])
            ->withTimestamps()
            ->orderByPivot('priority', 'desc');
    }

    /**
     * @return BelongsToMany<Medal, $this>
     */
    public function valid_medals(): BelongsToMany
    {
        return $this->medals()->where(function ($query) {
            $query->whereNull('user_medals.expire_at')->orWhere('user_medals.expire_at', '>=', Carbon::now());
        });
    }

    /**
     * @return BelongsToMany<Medal, $this>
     */
    public function wearing_medals(): BelongsToMany
    {
        return $this->valid_medals()->where('user_medals.status', UserMedal::STATUS_WEARING);
    }

    /** @return HasMany<Reward, $this> */
    public function reward_torrent_logs(): HasMany
    {
        return $this->hasMany(Reward::class, 'userid');
    }

    /** @return HasMany<Thank, $this> */
    public function thank_torrent_logs(): HasMany
    {
        return $this->hasMany(Thank::class, 'userid');
    }

    /** @return HasMany<PollAnswer, $this> */
    public function poll_answers(): HasMany
    {
        return $this->hasMany(PollAnswer::class, 'userid');
    }

    /** @return HasMany<UserMeta, $this> */
    public function metas()
    {
        return $this->hasMany(UserMeta::class, 'uid');
    }

    /** @return HasMany<UsernameChangeLog, $this> */
    public function usernameChangeLogs()
    {
        return $this->hasMany(UsernameChangeLog::class, 'uid');
    }

    /** @return BelongsToMany<Exam, $this> */
    public function examAndTasks(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_users', 'uid', 'exam_id');
    }

    /** @return BelongsToMany<Exam, $this> */
    public function onGoingExamAndTasks(): BelongsToMany
    {
        return $this->examAndTasks()->wherePivot('status', ExamUser::STATUS_NORMAL);
    }

    /** @return HasMany<UserModifyLog, $this> */
    public function modifyLogs(): HasMany
    {
        return $this->hasMany(UserModifyLog::class, 'user_id');
    }

    /**
     * @param  mixed  $value
     * @return string
     */
    public function getAvatarAttribute($value)
    {
        if ($value) {
            if (substr($value, 0, 4) == 'http') {
                return $value;
            } else {
                Logger::writeWithContext((string) "user: {$this->id} avatar: {$value} is not valid url.", (string) 'info', (bool) false);
            }
        }

        return Url::schemeAndHost(false).'/pic/default_avatar.png';

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
        // @todo how to do prepare bindings here ?
        //        $comment = addslashes($comment);
        //        do_log("update: " . json_encode($update) . ", $commentField: $comment", 'notice');
        //        $update[$commentField] = NexusDB::raw("if($commentField = '', '$comment', concat_ws('\n', '$comment', $commentField))");

        if ($commentField != 'modcomment') {
            throw new \RuntimeException("unsupported commentField: $commentField !");
        }

        return NexusDB::transaction(function () use ($update, $comment) {
            $this->modifyLogs()->create(['content' => $comment]);

            return $this->update($update);
        });
    }

    public function canAccessAdmin(): bool
    {
        $targetClass = self::getAccessAdminClassMin();
        if (! $this->class || $this->class < $targetClass) {
            Logger::writeWithContext((string) sprintf('user: %s, no class or class < %s, can not access admin.', $this->id, $targetClass), (string) 'info', (bool) false);

            return false;
        }

        return true;
    }

    /** @return int|string */
    public static function getAccessAdminClassMin()
    {
        return SiteConfig::current()->system->accessAdminClassMin() ?: User::CLASS_ADMINISTRATOR;
    }

    public function isDonating(): bool
    {
        $rawDonorUntil = $this->getRawOriginal('donoruntil');
        $donorUntil = $this->donoruntil;
        if (
            $this->donor == 'yes'
            && ($rawDonorUntil === null || $rawDonorUntil == '0000-00-00 00:00:00' || ($donorUntil instanceof Carbon && $donorUntil->gte(Carbon::now())))
        ) {
            return true;
        }

        return false;
    }

    /** @param string $name */
    public function acceptNotification($name): bool
    {
        return is_null($this->original['notifs']) || str_contains((string) $this->notifs, "[{$name}]");
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
