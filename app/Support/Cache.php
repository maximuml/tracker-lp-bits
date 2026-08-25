<?php

namespace App\Support;

use App\Models\Setting;
use App\Repositories\MessageRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\Redis;

/**
 * Stateless filesystem-cache helpers extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `cache_check()`  (build a per-language cache path, decide whether the
 *                      file on disk is still fresh, otherwise open an output
 *                      buffer for the caller to fill)
 *   - `cache_save()`   (write the current output buffer into the cache file
 *                      built from the same path convention, then flush)
 *
 * collapse into the static methods below. Both legacy functions are
 * effectively dead today — the only call sites in the tree are commented
 * out in `public/faq.php` — but the contract is still pinned here so a
 * future revival cannot silently change the on-disk layout or freshness
 * semantics.
 *
 * The class only deals with the *pure* parts of the contract:
 *
 *   - path construction (`{rootpath}{cacheDir}/{lang}/{file}.html`)
 *   - freshness check    (`file_exists(p) && now - maxAge < mtime(p)`)
 *   - buffer write       (`fopen('w') + fwrite + fclose`)
 *
 * The side-effecting orchestration — `include`, `print`, `ob_start`,
 * `ob_end_flush`, `exit` — stays in the legacy proxies, because moving
 * those into this class would couple it to the legacy template stack
 * (`end_main_frame`, `stdfoot`, `$lang_functions`) and defeat the point
 * of an `App\Support` extraction.
 *
 * The torrent cache-stamp methods (`touchTorrent`, `resetTorrent`) are a
 * small exception: they talk to the `torrents` table through `NexusDB`
 * because they back the legacy `set_cachetimestamp()` /
 * `reset_cachetimestamp()` helpers. They are kept here rather than in a
 * dedicated service to avoid a one-method class.
 */
final class Cache
{
    /**
     * Build the on-disk cache file path used by the legacy
     * `cache_check()` / `cache_save()` pair.
     *
     * Pins the exact path-join convention:
     *
     *     {rootpath}{cacheDir}/{lang}/{file}.{ext}
     *
     * Note that `$rootpath` and `$cacheDir` are concatenated WITHOUT a
     * separator — the legacy `$rootpath` global already ends in a
     * trailing slash (e.g. `/var/www/html/`), and the legacy `$cache`
     * global is a bare relative dir name (e.g. `cache`). The legacy
     * source then injects a literal `'/'` between `$cacheDir` and the
     * language folder, and again between language folder and file.
     */
    public static function path(
        string $rootpath,
        string $cacheDir,
        string $lang,
        string $file,
        string $ext = 'html',
    ): string {
        return $rootpath.$cacheDir.'/'.$lang.'/'.$file.'.'.$ext;
    }

    /**
     * Return true if the cache file exists AND its last-modified time
     * is newer than `now - $maxAge` seconds (i.e. the file is still
     * fresh and can be served).
     *
     * Pins the exact comparison from the legacy source:
     *
     *     time() - $cachetime < filemtime($cachefile)
     *
     * which is strictly-less-than, so an mtime that lands exactly on
     * the cutoff is treated as stale (NOT fresh).
     *
     * `$now` defaults to `time()` and is exposed only so tests can
     * pin the boundary deterministically.
     */
    public static function isFresh(string $cachefile, int $maxAge, ?int $now = null): bool
    {
        if (! file_exists($cachefile)) {
            return false;
        }
        $now ??= time();
        $mtime = filemtime($cachefile);
        if ($mtime === false) {
            return false;
        }

        return $now - $maxAge < $mtime;
    }

    /**
     * Write `$contents` to `$cachefile` using the legacy `fopen('w')`
     * + `fwrite` + `fclose` sequence.
     *
     * Returns the number of bytes written, or `false` if `fopen` failed
     * or `fwrite` returned `false`. The legacy source ignores both
     * return values and trusts the open/write/close sequence.
     */
    /**
     * Legacy page-cache check. Returns true when the caller should
     * generate and buffer the page; returns false and exits when a
     * fresh cached file was served.
     *
     * Backs the legacy `cache_check()` helper.
     */
    public static function pageCheck(string $file = 'cachefile', bool $endpage = true, int $cachetime = 600): bool
    {
        $rootpath = SupportContext::getGlobal('rootpath', defined('ROOT_PATH') ? constant('ROOT_PATH') : '');
        $cacheDir = SupportContext::getGlobal('cache', '');
        $langDir = SupportContext::getGlobal('CURLANGDIR', '');
        $lang = SupportContext::getLangFunctions();

        $cachefile = self::path($rootpath, $cacheDir, $langDir, $file);
        if (self::isFresh($cachefile, $cachetime)) {
            include $cachefile;
            if ($endpage) {
                $cacheMtime = filemtime($cachefile);
                $cacheMtime = $cacheMtime === false ? null : $cacheMtime;
                echo '<p align="center"><font class="small">'.($lang['text_page_last_updated'] ?? '').date('Y-m-d H:i:s', $cacheMtime).'</font></p>';
                Frame::mainFrameClose();
                Html::stdfoot();
                exit;
            }

            return false;
        }
        ob_start();

        return true;
    }

    /**
     * Write the current output buffer into the legacy page-cache file.
     *
     * Backs the legacy `cache_save()` helper.
     */
    public static function pageSave(string $file = 'cachefile'): void
    {
        $rootpath = SupportContext::getGlobal('rootpath', defined('ROOT_PATH') ? constant('ROOT_PATH') : '');
        $cacheDir = SupportContext::getGlobal('cache', '');
        $langDir = SupportContext::getGlobal('CURLANGDIR', '');

        $cachefile = self::path($rootpath, $cacheDir, $langDir, $file);
        $contents = ob_get_contents();
        self::writeBuffer($cachefile, (string) $contents);
        ob_end_flush();
    }

    public static function writeBuffer(string $cachefile, string $contents): int|false
    {
        $fp = @fopen($cachefile, 'w');
        if ($fp === false) {
            return false;
        }
        $written = fwrite($fp, $contents);
        fclose($fp);

        return $written;
    }

    /**
     * Touch the cache timestamp on a torrent row.
     *
     * Mirrors the legacy `set_cachetimestamp($id, $field)` helper.
     */
    public static function touchTorrent(int|string $torrentId, string $field = 'cache_stamp'): void
    {
        app(TorrentRepository::class)->touchCacheStamp($torrentId, $field);
    }

    /**
     * Reset the cache timestamp on a torrent row to zero.
     *
     * Mirrors the legacy `reset_cachetimestamp($id, $field)` helper.
     */
    public static function resetTorrent(int|string $torrentId, string $field = 'cache_stamp'): void
    {
        app(TorrentRepository::class)->resetCacheStamp($torrentId, $field);
    }

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
            self::forgetWithLocales('user_passkey_'.$passkey.'_content');
            self::forgetWithLocales('user_passkey_'.$passkey.'_rss');
        }

        $userInfo = app(UserRepository::class)->findForCacheClear($uid);
        if ($userInfo) {
            Events::fire('user_updated', $userInfo, null);
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
        foreach (SearchBoxRepository::getOrderedIds() as $id) {
            self::forgetWithLocales("category_list_mode_{$id}");
        }
    }

    public static function clearTaxonomy(string $table): void
    {
        Logger::writeWithContext("clear_taxonomy_cache: $table");
        foreach (SearchBoxRepository::getOrderedIds() as $id) {
            self::forgetWithLocales("{$table}_list_mode_{$id}");
        }
        self::forgetWithLocales("{$table}_list_mode_0");
    }

    public static function clearStaffMessage(): void
    {
        Logger::writeWithContext('clear_staff_message_cache');
        MessageRepository::updateStaffMessageCountCache(false);
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
    }
}
