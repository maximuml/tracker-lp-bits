<?php

/**
 * @property int $id
 * @property int $uid
 * @property int $torrent_id
 * @property int $snatched_id
 * @property int $status
 * @property string $comment
 * @property string $created_at
 * @property string $updated_at
 * @property int $leech_time_no_seeder_begin
 */
namespace App\Models;

use App\Enums\HitAndRunMode;
use App\Enums\ModelEventEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexus\Database\NexusDB;

/**
 * @property-read Torrent $torrent
 * @property-read Snatch|null $snatch
 * @property-read User $user
 * @property int $leech_time_no_seeder_begin
 */
class HitAndRun extends NexusModel
{
    /** @var  string */
    protected $table = 'hit_and_runs';

    /** @var  list<string> */
    protected $fillable = ['uid', 'snatch_id', 'torrent_id', 'status', 'comment'];

    /** @var  bool */
    public $timestamps = true;

    const STATUS_INSPECTING = 1;
    const STATUS_REACHED = 2;
    const STATUS_UNREACHED = 3;
    const STATUS_PARDONED = 4;

    /** @var  array<int|string, mixed> */
    public static array $status = [
        self::STATUS_INSPECTING => ['text' => 'Inspecting'],
        self::STATUS_REACHED => ['text' => 'Reached'],
        self::STATUS_UNREACHED => ['text' => 'Unreached'],
        self::STATUS_PARDONED => ['text' => 'Pardoned'],
    ];

    const CAN_PARDON_STATUS = [
        self::STATUS_INSPECTING,
        self::STATUS_UNREACHED,
    ];

    const MODE_DISABLED = 'disabled';
    const MODE_MANUAL = 'manual';
    const MODE_GLOBAL = 'global';

    /** @var  array<int|string, mixed> */
    public static $modes = [
        self::MODE_DISABLED => ['text' => 'Disabled'],
        self::MODE_MANUAL => ['text' => 'Manual'],
        self::MODE_GLOBAL => ['text' => 'Global'],
    ];

    const MINIMUM_IGNORE_USER_CLASS = User::CLASS_VIP;

    /** @return  mixed */
    protected static function booted()
    {
        static::saved(function ($model) {
            self::clearCache($model);
        });
        static::deleted(function ($model) {
            self::clearCache($model, ModelEventEnum::HIT_AND_RUN_DELETED);
        });
    }

    /**
     * @param  int  $userId
     * @param  int  $torrentId
     */
    public static function getCacheKey(int $userId, int $torrentId): string
    {
        return sprintf("hit_and_run:user:%d:torrent:%d", $userId, $torrentId);
    }

    /**
     * @param  HitAndRun  $hitAndRun
     * @param  string  $event
     */
    public static function clearCache(HitAndRun $hitAndRun, string $event = ModelEventEnum::HIT_AND_RUN_UPDATED): void
    {
        NexusDB::cache_del(self::getCacheKey($hitAndRun->uid, $hitAndRun->torrent_id));
        fire_event($event, $hitAndRun);
        do_log(sprintf(
            "userId: %s, torrentId: %s hit and run cache cleared, and trigger event: %s",
            $hitAndRun->uid, $hitAndRun->torrent_id, $event
        ));
    }

    /** @return  \Illuminate\Database\Eloquent\Casts\Attribute<mixed, mixed> */
    protected function seedTimeRequired(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => $this->doGetSeedTimeRequired()
        );
    }

    /** @return  \Illuminate\Database\Eloquent\Casts\Attribute<mixed, mixed> */
    protected function inspectTimeLeft(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => $this->doGetInspectTimeLeft()
        );
    }

    private function doGetInspectTimeLeft(): string
    {
        if ($this->status != self::STATUS_INSPECTING) {
            return '---';
        }
        //change to use create time
//        if (!$this->snatch->completedat) {
//            //not download completed
//            return '---';
//        }
        $searchBoxId = $this->torrent->basic_category->mode ?? 0;
        if ($searchBoxId == 0) {
            do_log(sprintf('[INVALID_CATEGORY], Torrent: %s', $this->torrent_id), 'error');
            return '---';
        }
        $inspectTime = HitAndRun::getConfig('inspect_time', $searchBoxId);
        $diffInSeconds = Carbon::now()->diffInSeconds($this->created_at->addHours(intval($inspectTime)), true);
        return mkprettytime($diffInSeconds);
    }

    private function doGetSeedTimeRequired(): string
    {
        if ($this->status != self::STATUS_INSPECTING) {
            return '---';
        }
        $searchBoxId = $this->torrent->basic_category->mode ?? 0;
        if ($searchBoxId == 0) {
            do_log(sprintf('[INVALID_CATEGORY], Torrent: %s', $this->torrent_id), 'error');
            return '---';
        }
        if (!$this->snatch) {
            do_log("hit and run: {$this->id} no snatch", 'warning');
            return '---';
        }
        $seedTimeMinimum = HitAndRun::getConfig('seed_time_minimum', $searchBoxId);
        $diffInSeconds = 3600 * $seedTimeMinimum - $this->snatch->seedtime;
        return mkprettytime($diffInSeconds);
    }

    /** @return  mixed */
    public function getStatusTextAttribute()
    {
        return nexus_trans('hr.status_' . $this->status);
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @return  array<int|string, mixed>
     */
    public static function listStatus($onlyKeyValue = false): array
    {
        $result = self::$status;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = nexus_trans('hr.status_' . $key);
            $value['text'] = $text;
            $keyValues[$key] = $text;
        }
        if ($onlyKeyValue) {
            return $keyValues;
        }
        return $result;
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @return  array<int|string, mixed>
     */
    public static function listModes($onlyKeyValue = false): array
    {
        $result = self::$modes;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = nexus_trans('hr.mode_' . $key);
            $value['text'] = $text;
            $keyValues[$key] = $text;
        }
        if ($onlyKeyValue) {
            return $keyValues;
        }
        return $result;
    }

    public static function getIsEnabled(): bool
    {
        $browseMode = self::getConfig('mode', \App\Support\Config\SiteConfig::current()->main->browseCat());
        $browseEnabled = HitAndRunMode::fromStringSafe(is_string($browseMode) ? $browseMode : null)->isEnabled();
        do_log("H&R browseEnabled: " . ($browseEnabled ? 'true' : 'false'));
        return $browseEnabled;
    }

    /**
     * @param  mixed  $name
     * @param  mixed  $searchBoxId
     * @return  mixed
     */
    public static function getConfig($name, $searchBoxId)
    {
        if ($name == '*') {
            $key = "hr";
        } else {
            $key = "hr.$name";
        }
        $default = Setting::get($key);
        return apply_filter("nexus_setting_get", $default, $name, ['mode' => $searchBoxId]);
    }

    public static function diffInSection(): bool
    {
        return apply_filter("hit_and_run_diff_in_section", false);
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<Torrent, $this> */
    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class, 'torrent_id');
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<Snatch, $this> */
    public function snatch(): BelongsTo
    {
        return $this->belongsTo(Snatch::class, 'snatched_id');
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }


}
