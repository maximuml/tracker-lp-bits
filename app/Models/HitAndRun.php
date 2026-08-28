<?php

declare(strict_types=1);

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
use App\Enums\HitAndRunStatus;
use App\Enums\ModelEventEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Events;
use App\Support\Format;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Torrent $torrent
 * @property-read Snatch|null $snatch
 * @property-read User $user
 * @property int $leech_time_no_seeder_begin
 */
class HitAndRun extends NexusModel
{
    /** @var string */
    protected $table = 'hit_and_runs';

    /** @var list<string> */
    protected $fillable = ['uid', 'snatched_id', 'torrent_id', 'status', 'comment'];

    /** @var bool */
    public $timestamps = true;

    /** @var array<int|string, mixed> */
    public static array $status = [
        HitAndRunStatus::INSPECTING->value => ['text' => 'Inspecting'],
        HitAndRunStatus::REACHED->value => ['text' => 'Reached'],
        HitAndRunStatus::UNREACHED->value => ['text' => 'Unreached'],
        HitAndRunStatus::PARDONED->value => ['text' => 'Pardoned'],
    ];

    const CAN_PARDON_STATUS = [
        HitAndRunStatus::INSPECTING->value,
        HitAndRunStatus::UNREACHED->value,
    ];

    /** @var array<int|string, mixed> */
    public static $modes = [
        HitAndRunMode::DISABLED->value => ['text' => 'Disabled'],
        HitAndRunMode::MANUAL->value => ['text' => 'Manual'],
        HitAndRunMode::GLOBAL->value => ['text' => 'Global'],
    ];

    const MINIMUM_IGNORE_USER_CLASS = UserClassEnum::VIP->value;

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

    public static function getCacheKey(int $userId, int $torrentId): string
    {
        return sprintf('hit_and_run:user:%d:torrent:%d', $userId, $torrentId);
    }

    public static function clearCache(HitAndRun $hitAndRun, string $event = ModelEventEnum::HIT_AND_RUN_UPDATED): void
    {
        Cache::forgetWithLocales(self::getCacheKey($hitAndRun->uid, $hitAndRun->torrent_id));
        Events::fire($event, $hitAndRun, null);
        Logger::writeWithContext((string) sprintf('userId: %s, torrentId: %s hit and run cache cleared, and trigger event: %s', $hitAndRun->uid, $hitAndRun->torrent_id, $event), (string) 'info', (bool) false);
    }

    /** @return  Attribute<mixed, mixed> */
    protected function seedTimeRequired(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => $this->doGetSeedTimeRequired()
        );
    }

    /** @return  Attribute<mixed, mixed> */
    protected function inspectTimeLeft(): Attribute
    {
        return new Attribute(
            get: fn ($value, $attributes) => $this->doGetInspectTimeLeft()
        );
    }

    private function doGetInspectTimeLeft(): string
    {
        if ($this->status != HitAndRunStatus::INSPECTING->value) {
            return '---';
        }
        // change to use create time
        $searchBoxId = $this->torrent->basic_category->mode ?? 0;
        if ($searchBoxId == 0) {
            Logger::writeWithContext((string) sprintf('[INVALID_CATEGORY], Torrent: %s', $this->torrent_id), (string) 'error', (bool) false);

            return '---';
        }
        $inspectTime = HitAndRun::getConfig('inspect_time', $searchBoxId);
        $diffInSeconds = Carbon::now()->diffInSeconds($this->created_at->addHours(intval($inspectTime)), true);

        return Format::prettyTimeWithLocale($diffInSeconds);
    }

    private function doGetSeedTimeRequired(): string
    {
        if ($this->status != HitAndRunStatus::INSPECTING->value) {
            return '---';
        }
        $searchBoxId = $this->torrent->basic_category->mode ?? 0;
        if ($searchBoxId == 0) {
            Logger::writeWithContext((string) sprintf('[INVALID_CATEGORY], Torrent: %s', $this->torrent_id), (string) 'error', (bool) false);

            return '---';
        }
        if (! $this->snatch) {
            Logger::writeWithContext((string) "hit and run: {$this->id} no snatch", (string) 'warning', (bool) false);

            return '---';
        }
        $seedTimeMinimum = HitAndRun::getConfig('seed_time_minimum', $searchBoxId);
        $diffInSeconds = 3600 * $seedTimeMinimum - $this->snatch->seedtime;

        return Format::prettyTimeWithLocale($diffInSeconds);
    }

    /** @return  mixed */
    public function getStatusTextAttribute()
    {
        return Locale::trans('hr.status_'.$this->status, [], null);
    }

    /**
     * @param  mixed  $onlyKeyValue
     * @return array<int|string, mixed>
     */
    public static function listStatus($onlyKeyValue = false): array
    {
        $result = self::$status;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = Locale::trans('hr.status_'.$key, [], null);
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
     * @return array<int|string, mixed>
     */
    public static function listModes($onlyKeyValue = false): array
    {
        $result = self::$modes;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = Locale::trans('hr.mode_'.$key, [], null);
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
        $browseMode = self::getConfig('mode', SiteConfig::current()->main->browseCat());
        $browseEnabled = HitAndRunMode::fromStringSafe(is_string($browseMode) ? $browseMode : null)->isEnabled();
        Logger::writeWithContext((string) ('H&R browseEnabled: '.($browseEnabled ? 'true' : 'false')), (string) 'info', (bool) false);

        return $browseEnabled;
    }

    /**
     * @param  mixed  $name
     * @param  mixed  $searchBoxId
     * @return mixed
     */
    public static function getConfig($name, $searchBoxId)
    {
        if ($name == '*') {
            $key = 'hr';
        } else {
            $key = "hr.$name";
        }
        $default = Settings::get($key);

        return $default;
    }

    public static function diffInSection(): bool
    {
        return false;
    }

    /** @return  BelongsTo<Torrent, $this> */
    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class, 'torrent_id');
    }

    /** @return  BelongsTo<Snatch, $this> */
    public function snatch(): BelongsTo
    {
        return $this->belongsTo(Snatch::class, 'snatched_id');
    }

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }
}
