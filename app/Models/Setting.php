<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string $name
 * @property string|null $value
 * @property string $created_at
 * @property string $updated_at
 * @property bool $autoload
 */

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use App\Support\RedisGuard;
use App\Support\RequestValues;
use App\Support\State\SettingState;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class Setting extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var list<string> */
    protected $fillable = ['name', 'value', 'autoload'];

    /** @var array<string, string> */
    protected $casts = [
        'autoload' => 'boolean',
    ];

    /** @var bool */
    public $timestamps = true;

    const PERMISSION_NO_CLASS = 100;

    /** @var array<int|string, mixed> */
    public static array $permissionMustHaveClass = ['defaultclass', 'staffmem'];

    public static function resetState(): void
    {
        SettingState::instance()->reset();
    }

    const DIRECT_PERMISSION_CACHE_KEY_PREFIX = 'nexus_direct_permissions_';

    const ROLE_PERMISSION_CACHE_KEY_PREFIX = 'nexus_role_permissions_';

    const TORRENT_GLOBAL_STATE_CACHE_KEY = 'global_promotion_state';

    const USER_TOKEN_PERMISSION_ALLOWED_CACHE_KRY = 'user_token_permission_allowed';

    /**
     * get setting autoload = true with cache
     */
    public static function get(?string $name = null, mixed $default = null): mixed
    {
        if (SettingState::instance()->autoloadedSettings === null) {
            SettingState::instance()->autoloadedSettings = RedisGuard::attempt(
                static fn () => Cache::remember('nexus_settings_in_laravel', 600, static fn () => self::getFromDb()),
            ) ?? self::getFromDb();
        }
        if ($name === null) {
            return SettingState::instance()->autoloadedSettings;
        }

        return Arr::get(SettingState::instance()->autoloadedSettings ?? [], $name, $default);
    }

    /**
     * get setting autoload = true without cache
     */
    public static function getFromDb(?string $name = null, mixed $default = null): mixed
    {
        $rows = self::query()->where('autoload', true)->get(['name', 'value']);
        $result = [];
        foreach ($rows as $row) {
            $value = self::normalizeValue($row);
            Arr::set($result, $row->name, $value);
        }
        if ($name === null) {
            return $result;
        }

        return Arr::get($result, $name, $default);
    }

    /**
     * get from db by name, generally used for `autoload` = false
     */
    public static function getByName(string $name, mixed $default = null): mixed
    {
        $result = self::query()->where('name', $name)->first();
        if ($result) {
            return self::normalizeValue($result);
        }

        return $default;
    }

    /**
     * @return mixed
     */
    public static function normalizeValue(Setting $setting)
    {
        $value = $setting->value;
        if ($value !== null) {
            $arr = json_decode($value, true);
            if (is_array($arr)) {
                $value = $arr;
            }
        }

        return $value;
    }

    /** @param  array<int|string, mixed>  $allowed */
    public static function updateUserTokenPermissionAllowedCache(array $allowed = []): void
    {
        $redis = Redis::connection()->client();
        $key = self::USER_TOKEN_PERMISSION_ALLOWED_CACHE_KRY;
        $redis->unlink($key);
        // must not use cache
        if (empty($allowed)) {
            $allowed = self::getFromDb('permission.user_token_allowed');
        }
        if (! empty($allowed)) {
            $redis->sAdd($key, ...$allowed);
        }
    }

    public static function getDefaultLang(): string
    {
        return (string) (self::get('main.defaultlang') ?: 'en');
    }

    public static function getIsUploadOpenAtWeekend(): bool
    {
        return self::get('main.sptime') == 'yes';
    }

    public static function getIsComplainEnabled(): bool
    {
        return self::get('main.complain_enabled') == 'yes';
    }

    public static function getBaseUrl(): string
    {
        $result = self::get('basic.BASEURL', RequestValues::serverValue('HTTP_HOST', ''));
        if (empty($result)) {
            $result = RequestValues::serverValue('HTTP_HOST', 'localhost');
        }

        return rtrim($result, '/');
    }

    public static function getSiteName(): string
    {
        return self::get('basic.SITENAME');
    }

    /** @return  array<int|string, mixed> */
    public static function getPermissionUserTokenAllowed(): array
    {
        return self::get('permission.user_token_allowed');
    }

    public static function getBackupExportPath(): ?string
    {
        return self::get('backup.export_path');
    }

    public static function getIsRequireSeedSectionEnabled(): bool
    {
        return self::get('require_seed_section.enabled') == 'yes';
    }

    public static function getRequireSeedSectionSeederGte(): int
    {
        return (int) self::get('require_seed_section.seeder_gte');
    }

    public static function getRequireSeedSectionSeederLte(): int
    {
        return (int) self::get('require_seed_section.seeder_lte');
    }

    public static function getRequireSeedSectionPromotionState(): int
    {
        return self::get('require_seed_section.promotion_state', Torrent::REQUIRE_SEED_SECTION_DEFAULT_PROMOTION_STATE);
    }

    /** @return  array<int|string, mixed> */
    public static function getRequireSeedSectionTags(): array
    {
        return self::get('require_seed_section.require_tags', []);
    }

    public static function getRequireSeedSectionTorrentCountMax(): int
    {
        return self::get('require_seed_section.torrent_count_max', Torrent::REQUIRE_SEED_SECTION_DEFAULT_TORRENT_COUNT_MAX);
    }

    /** @return  array<int|string, mixed> */
    public static function getBonusRewardOptions(): array
    {
        $result = self::get('torrent.reward_bonus_options');
        if (is_string($result) && trim($result) !== '') {
            $split = preg_split('/[,，\s]+/', trim($result));

            return $split === false ? [] : $split;
        }

        return Torrent::BONUS_REWARD_VALUES;
    }

    public static function getBonusRewardTimesLimit(): int
    {
        return (int) self::get('torrent.reward_times_limit', 0);
    }

    public static function getSelfEnableBonus(): int
    {
        return (int) self::get('bonus.self_enable', BonusLogs::DEFAULT_BONUS_SELF_ENABLE);
    }

    public static function getIsSiteOnline(): bool
    {
        return self::get('main.site_online') == 'yes';
    }
}
