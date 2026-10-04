<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\UserMeta;
use App\Repositories\UserMetaRepository;
use App\Support\Html\SafeHtml;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

/**
 * Legacy user display helpers extracted from `include/functions.php`.
 *
 * Backs `get_plain_username`, `return_avatar_image` and
 * `username_for_admin`.
 */
final class UserDisplay
{
    /** @var array<int|string, array<int|string, mixed>|false> */
    private static array $rowCache = [];

    /** @var array<int, string> */
    private static array $usernameCache = [];

    /**
     * Clear the in-process caches. Called between tests and on each
     * Octane/queue job reset so rows read under a rolled-back transaction
     * or a previous request cannot leak stale data.
     */
    public static function resetState(): void
    {
        foreach (array_keys(self::$rowCache) as $id) {
            RedisGuard::attempt(static fn () => Cache::forget("user_{$id}_content"), false);
        }
        self::$rowCache = [];
        self::$usernameCache = [];
    }

    /**
     * Return the current user's class value, or '' in the legacy context
     * when the user is not loaded.
     *
     * Mirrors `get_user_class()`.
     */
    public static function currentClass(): string|int
    {
        $user = CurrentUser::instance()->get();
        if (LegacyRuntime::instance()->isLegacy()) {
            return $user['class'] ?? '';
        }

        if (! auth()->check()) {
            return '';
        }

        return auth()->user()->class ?? '';
    }

    /**
     * Resolve a user id from a username (case-insensitive).
     *
     * Mirrors the legacy `get_user_id_from_name()` helper.
     */
    public static function userIdFromName(string $username): int
    {
        return LegacyAuth::userIdFromName($username, LegacyAuthContext::fromSupportContext());
    }

    /**
     * Return the current user's id, or 0 when not authenticated.
     *
     * Mirrors `get_user_id()`.
     */
    public static function currentId(): int
    {
        $user = CurrentUser::instance()->get();
        if (LegacyRuntime::instance()->isLegacy()) {
            return (int) ($user['id'] ?? 0);
        }

        if (! auth()->check()) {
            return 0;
        }

        return (int) (auth()->user()->id ?? 0);
    }

    /**
     * Return the current user's raw username, or '' when not available.
     *
     * Mirrors `get_pure_username()`.
     */
    public static function currentUsername(): string
    {
        $user = CurrentUser::instance()->get();
        if (LegacyRuntime::instance()->isLegacy()) {
            return $user['username'] ?? '';
        }

        if (! auth()->check()) {
            return '';
        }

        return (string) (auth()->user()->username ?? '');
    }

    /**
     * Fetch a user row with the legacy common columns and in-request cache.
     *
     * Mirrors `get_user_row()`.
     *
     * @return array<int|string, mixed>|false
     */
    public static function row(int|string $id): array|false
    {
        if (isset(self::$rowCache[$id])) {
            return self::$rowCache[$id];
        }

        $row = RedisGuard::remember("user_{$id}_content", 3600, function () use ($id) {
            $user = self::userRepository()->findForDisplay($id);

            if (! $user) {
                return false;
            }

            $arr = $user->toArray();
            $metas = self::userRepository()->listMetas($id, UserMeta::META_KEY_PERSONALIZED_USERNAME);
            $arr['__is_rainbow'] = $metas->isNotEmpty() ? 1 : 0;
            $arr['__is_donor'] = self::isDonor($arr);

            return $arr;
        });

        if (is_array($row)) {
            /** @var array<int|string, mixed> $row */
            self::$rowCache[$id] = $row;

            return $row;
        }

        self::$rowCache[$id] = false;

        return false;
    }

    /**
     * Preload user display rows for a list of ids in a single query.
     *
     * This warms the in-request cache used by {@see row()} and therefore
     * by {@see username()}, avoiding N+1 queries when rendering tables
     * with many distinct owners/posters.
     *
     * @param  array<int, int|string>  $ids
     */
    public static function preload(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return;
        }

        $missing = array_values(array_diff($ids, array_keys(self::$rowCache)));
        if ($missing === []) {
            return;
        }

        $columns = [
            'id', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded',
            'last_access', 'username', 'donor', 'donoruntil', 'leechwarn', 'warned', 'title',
            'downloadpos', 'parked', 'clientselect', 'showclienterror',
        ];

        $users = self::userRepository()->getByIds($missing, $columns);
        if ($users->isEmpty()) {
            foreach ($missing as $id) {
                self::$rowCache[$id] = false;
            }

            return;
        }

        $rainbowIds = array_flip(
            self::userMetaRepository()->pluckActiveMetaUids($missing, UserMeta::META_KEY_PERSONALIZED_USERNAME)
        );

        foreach ($users as $user) {
            $id = (int) $user->id;
            $arr = $user->toArray();
            $arr['__is_rainbow'] = isset($rainbowIds[$id]) ? 1 : 0;
            $arr['__is_donor'] = self::isDonor($arr);

            self::$rowCache[$id] = $arr;
        }

        foreach ($missing as $id) {
            if (! isset(self::$rowCache[$id])) {
                self::$rowCache[$id] = false;
            }
        }
    }

    /**
     * Check whether the user is a donor with an active donoruntil window.
     *
     * Mirrors `is_donor()`.
     *
     * @param  array<int|string, mixed>  $userInfo
     */
    public static function isDonor(array $userInfo): bool
    {
        $donorUntil = $userInfo['donoruntil'] ?? null;

        return $userInfo['donor']
            && ($donorUntil === null
                || $donorUntil == '0000-00-00 00:00:00'
                || $donorUntil >= date('Y-m-d H:i:s'));
    }

    /**
     * Return the raw username for a user id.
     *
     * Mirrors `get_plain_username()`.
     */
    public static function plainUsername(int|string $id): string
    {
        $row = UserDisplay::row($id);

        return (string) ($row['username'] ?? '');
    }

    public static function avatarImage(string $url, string $langFolder): string
    {
        return trim(view('support._avatar-img', ['url' => $url, 'langFolder' => $langFolder])->render());
    }

    /**
     * Context-aware wrapper for {@see avatarImage()}.
     */
    public static function avatarImageWithContext(string $url): string
    {
        return self::avatarImage($url, Locale::currentLangDir());
    }

    /**
     * Build the admin-area username link.
     *
     * Mirrors `username_for_admin()`.
     */
    public static function adminUsername(int $id): HtmlString
    {
        if ($id <= 0) {
            return new HtmlString('');
        }

        return new HtmlString((string) UserDisplay::username($id, false, true, true, true));
    }

    /**
     * Build a rich username display with icons and link.
     *
     * Mirrors `get_username()`.
     */
    public static function username(
        int|string $id,
        bool $big = false,
        bool $link = true,
        bool $bold = true,
        bool $target = false,
        bool $bracket = false,
        bool $withtitle = false,
        string $link_ext = '',
        bool $underline = false,
    ): SafeHtml {
        $id = (int) $id;

        if (func_num_args() === 1 && isset(self::$usernameCache[$id])) {
            return SafeHtml::fromTrustedHtml(self::$usernameCache[$id]);
        }

        $arr = UserDisplay::row($id);
        if ($arr) {
            if ($big) {
                $donorpic = 'starbig';
                $leechwarnpic = 'leechwarnedbig';
                $warnedpic = 'warnedbig';
                $disabledpic = 'disabledbig';
            } else {
                $donorpic = 'star';
                $leechwarnpic = 'leechwarned';
                $warnedpic = 'warned';
                $disabledpic = 'disabled';
            }

            $now = date('Y-m-d H:i:s');
            $donorUntil = $arr['donoruntil'] ?? null;
            $isDonor = $arr['donor'] && ($donorUntil === null || $donorUntil < '1970' || $donorUntil >= $now);
            $pic = fn (string $cls, string $alt): string => trim(view('support._user-pic', ['cls' => $cls, 'alt' => $alt])->render());
            $pics = $isDonor ? $pic($donorpic, 'Donor') : '';

            if ($arr['enabled']) {
                $pics .= ($arr['leechwarn'] ? $pic($leechwarnpic, 'Leechwarned') : '')
                    .($arr['warned'] ? $pic($warnedpic, 'Warned') : '');
            } else {
                $pics .= $pic($disabledpic, 'Disabled')."\n";
            }

            $username = htmlspecialchars((string) $arr['username']);
            $rainbow = '';
            $hasSetRainbow = false;
            if (isset($arr['__is_rainbow']) && $arr['__is_rainbow']) {
                $rainbow = ' class="rainbow"';
            }
            if ($underline) {
                $hasSetRainbow = true;
                $username = self::inlineWrap('u', $rainbow, $username);
            }
            if ($bold) {
                if ($hasSetRainbow) {
                    $username = self::inlineWrap('b', '', $username);
                } else {
                    $hasSetRainbow = true;
                    $username = self::inlineWrap('b', $rainbow, $username);
                }
            }

            $href = Url::schemeAndHost()."/userdetails.php?id=$id";
            $classNameColored = UserClass::name($arr['class'], true, false, false);
            $className = UserClass::name($arr['class'], false, true, true, ['with_alias' => true]);
            $title = $arr['title'] ?? '';

            $username = ($link
                ? trim(view('support._user-link', [
                    'linkExt' => SafeHtml::fromTrustedHtml($link_ext),
                    'href' => $href,
                    'targetAttr' => SafeHtml::fromTrustedHtml($target ? ' target="_blank"' : ''),
                    'cls' => $classNameColored,
                    'inner' => SafeHtml::fromTrustedHtml($username),
                ])->render())
                : $username)
                .$pics
                .($withtitle
                    ? ' ('.($title === '' ? $className : trim(view('support._user-title', [
                        'cls' => $classNameColored,
                        'title' => $title,
                    ])->render())).')'
                    : '');

            $username = trim(view('support._user-nowrap', [
                'inner' => SafeHtml::fromTrustedHtml($bracket ? '('.$username.')' : $username),
            ])->render());
        } else {
            $username = self::inlineWrap('i', '', (string) Locale::trans('nexus.user_not_exists'));
            $username = trim(view('support._user-nowrap', [
                'inner' => SafeHtml::fromTrustedHtml($bracket ? '('.$username.')' : $username),
            ])->render());
        }

        if (func_num_args() === 1) {
            self::$usernameCache[$id] = $username;
        }

        return SafeHtml::fromTrustedHtml($username);
    }

    private static function inlineWrap(string $tag, string $rainbow, string $inner): string
    {
        return trim(view('support._user-inline', [
            'tag' => $tag,
            'rainbow' => SafeHtml::fromTrustedHtml($rainbow),
            'inner' => SafeHtml::fromTrustedHtml($inner),
        ])->render());
    }

    private static function userRepository(): UserRepositoryInterface
    {
        return app(UserRepositoryInterface::class);
    }

    private static function userMetaRepository(): UserMetaRepository
    {
        return app(UserMetaRepository::class);
    }
}
