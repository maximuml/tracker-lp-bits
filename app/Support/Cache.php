<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Events\UserUpdated;
use App\Models\Setting;
use App\Repositories\StaffMessageRepository;
use App\Services\PasskeyUserLookup;
use App\Support\Cache\NexusCache;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\Redis;

/**
 * Cache invalidation helpers extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration. The `clear*()` methods back the
 * legacy invalidation paths (user/session/settings/taxonomy/etc.) and
 * funnel through `CacheFacade` plus locale-prefixed keys; the on-disk
 * `cache_check()`/`cache_save()` helpers and the torrent cache-stamp
 * pair were dropped once their last call sites went away.
 */
final class Cache
{
    public static function clearUser(int|string $uid, string $passkey = ''): void
    {
        Logger::writeWithContext("clear_user_cache, uid: $uid, passkey: $passkey");
        self::forgetWithLocales("user_{$uid}_content");
        self::forgetWithLocales("user_{$uid}_roles");
        self::forgetWithLocales("announce_user_passkey_$uid");
        self::forgetWithLocales(Setting::DIRECT_PERMISSION_CACHE_KEY_PREFIX.$uid);
        self::forgetWithLocales("user_role_ids:$uid");
        self::forgetWithLocales("direct_permissions:$uid");

        if ($passkey) {
            self::forgetWithLocales(PasskeyUserLookup::userCacheKey($passkey, 'content'));
            self::forgetWithLocales(PasskeyUserLookup::userCacheKey($passkey, 'rss'));
        }

        $userInfo = app(UserRepositoryInterface::class)->findForCacheClear($uid);
        if ($userInfo) {
            event(new UserUpdated($userInfo));
        }
    }

    public static function clearSettings(): void
    {
        Logger::writeWithContext('clear_setting_cache');
        self::forgetWithLocales('nexus_settings_in_laravel');
        self::forgetWithLocales('nexus_settings_in_nexus');
        self::forgetWithLocales('setting_protected_forum');
        $channel = Env::get('CHANNEL_NAME_SETTING');
        if (! empty($channel)) {
            Redis::connection()->client()->publish($channel, 'update');
        }
    }

    public static function clearCategory(): void
    {
        Logger::writeWithContext('clear_category_cache');
        self::forgetWithLocales('category_content');
        foreach (self::searchBoxRepository()->getOrderedIds() as $id) {
            self::forgetWithLocales("category_list_mode_{$id}");
        }
    }

    public static function clearTaxonomy(string $table): void
    {
        Logger::writeWithContext("clear_taxonomy_cache: $table");
        foreach (self::searchBoxRepository()->getOrderedIds() as $id) {
            self::forgetWithLocales("{$table}_list_mode_{$id}");
        }
        self::forgetWithLocales("{$table}_list_mode_0");
    }

    public static function clearStaffMessage(): void
    {
        Logger::writeWithContext('clear_staff_message_cache');
        app(StaffMessageRepository::class)->updateStaffMessageCountCache(false);
    }

    public static function clearSearchBox(): void
    {
        Logger::writeWithContext('clear_search_box_cache');
        self::forgetWithLocales('search_box_content');
    }

    public static function clearIcon(): void
    {
        Logger::writeWithContext('clear_icon_cache');
        self::forgetWithLocales('category_icon_content');
    }

    /**
     * @param  int|int[]  $uid
     */
    public static function clearInboxCount($uid): void
    {
        Logger::writeWithContext('clear_inbox_count_cache');
        foreach (Arr::wrap($uid) as $id) {
            self::forgetWithLocales('user_'.$id.'_inbox_count');
            self::forgetWithLocales('user_'.$id.'_unread_message_count');
        }
    }

    public static function clearAgentAllowDeny(): void
    {
        Logger::writeWithContext('clear_agent_allow_deny_cache');
        $allowCacheKey = Env::get('CACHE_KEY_AGENT_ALLOW', 'all_agent_allows');
        $denyCacheKey = Env::get('CACHE_KEY_AGENT_DENY', 'all_agent_denies');
        foreach (['', ':php', ':go'] as $suffix) {
            self::forgetWithLocales($allowCacheKey.$suffix);
            self::forgetWithLocales($denyCacheKey.$suffix);
        }
    }

    public static function clearTorrent(string $infoHash): void
    {
        Logger::writeWithContext('clear_torrent_cache');
        self::forgetWithLocales('torrent_hash_'.$infoHash.'_content');
        self::forgetWithLocales("torrent_not_exists:$infoHash");
    }

    /**
     * Forget a cache key and all its locale-prefixed variants.
     *
     * Replicates the Laravel-context behaviour of NexusDB::cache_del(),
     * which deletes both the bare key and every `{lang}_{key}` variant
     * so that per-language cached pages are invalidated together.
     */
    public static function forgetWithLocales(string $key): void
    {
        CacheFacade::forget($key);
        foreach (Locale::available() as $lf) {
            CacheFacade::forget($lf.'_'.$key);
        }
        // Legacy payloads are written through NexusCache's own phpredis
        // connection — delete there too so invalidation does not silently
        // depend on CACHE_STORE pointing at the same backend. Nullable: tests
        // bind it to null to simulate Redis being down.
        NexusCache::instance()?->forget($key, true);
    }

    private static function searchBoxRepository(): SearchBoxRepositoryInterface
    {
        return app(SearchBoxRepositoryInterface::class);
    }
}
