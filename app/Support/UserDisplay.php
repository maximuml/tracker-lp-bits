<?php

namespace App\Support;

use App\Models\UserMedal;
use App\Models\UserMeta;
use App\Repositories\UserRepository;
use App\Support\Config\SiteConfig;
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
     * Return the current user's class value, or '' in the legacy context
     * when the user is not loaded.
     *
     * Mirrors `get_user_class()`.
     */
    public static function currentClass(): string|int
    {
        $user = SupportContext::getUser();
        if (defined('IN_NEXUS') && IN_NEXUS) {
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
        return \App\Support\LegacyAuth::userIdFromName($username, \App\Support\LegacyAuthContext::fromSupportContext());
    }

    /**
     * Return the current user's id, or 0 when not authenticated.
     *
     * Mirrors `get_user_id()`.
     */
    public static function currentId(): int
    {
        $user = SupportContext::getUser();
        if (defined('IN_NEXUS') && IN_NEXUS) {
            return (int) ($user['id'] ?? 0);
        }

        if (! auth()->check()) {
            return 0;
        }

        return (int) (auth()->user()->id ?? 0);
    }

    /**
     * Return the current user's passkey, or '' when not available.
     *
     * Mirrors `get_user_passkey()`.
     */
    public static function currentPasskey(): string
    {
        $user = SupportContext::getUser();
        if (defined('IN_NEXUS') && IN_NEXUS) {
            return $user['passkey'] ?? '';
        }

        if (! auth()->check()) {
            return '';
        }

        return (string) (auth()->user()->passkey ?? '');
    }

    /**
     * Return the current user's raw username, or '' when not available.
     *
     * Mirrors `get_pure_username()`.
     */
    public static function currentUsername(): string
    {
        $user = SupportContext::getUser();
        if (defined('IN_NEXUS') && IN_NEXUS) {
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

        $row = \Nexus\Database\NexusDB::remember("user_{$id}_content", 3600, function () use ($id) {
            $user = UserRepository::findForDisplay($id);

            if (! $user) {
                return null;
            }

            $arr = $user->toArray();
            $metas = (new UserRepository())->listMetas($id, UserMeta::META_KEY_PERSONALIZED_USERNAME);
            $arr['__is_rainbow'] = $metas->isNotEmpty() ? 1 : 0;
            $arr['__is_donor'] = self::isDonor($arr);

            return Hooks::applyFilter('user_row', $arr);
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

        $users = UserRepository::getByIds($missing, $columns);
        if ($users->isEmpty()) {
            foreach ($missing as $id) {
                self::$rowCache[$id] = false;
            }

            return;
        }

        $maxMedals = (int) SiteConfig::current()->system->maximumNumberOfMedalsCanBeWorn(3);

        $rainbowIds = array_flip(
            UserMeta::query()
                ->whereIn('uid', $missing)
                ->where('meta_key', UserMeta::META_KEY_PERSONALIZED_USERNAME)
                ->where('status', 0)
                ->where(function ($query) {
                    $query->whereNull('deadline')->orWhere('deadline', '>=', now());
                })
                ->pluck('uid')
                ->toArray()
        );

        $medalRows = UserMedal::query()
            ->whereIn('uid', $missing)
            ->where('status', UserMedal::STATUS_WEARING)
            ->where(function ($query) {
                $query->whereNull('expire_at')->orWhere('expire_at', '>=', now());
            })
            ->with('medal')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $medalsByUser = [];
        foreach ($medalRows as $userMedal) {
            $uid = (int) $userMedal->uid;
            if (! isset($medalsByUser[$uid])) {
                $medalsByUser[$uid] = [];
            }
            if (count($medalsByUser[$uid]) >= $maxMedals) {
                continue;
            }

            $medal = $userMedal->medal;
            if (! $medal) {
                continue;
            }
            $medalsByUser[$uid][] = $medal->toArray();
        }

        foreach ($users as $user) {
            $id = (int) $user->id;
            $arr = $user->toArray();
            $arr['wearing_medals'] = $medalsByUser[$id] ?? [];
            $arr['__is_rainbow'] = isset($rainbowIds[$id]) ? 1 : 0;
            $arr['__is_donor'] = self::isDonor($arr);

            self::$rowCache[$id] = Hooks::applyFilter('user_row', $arr);
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

        return $userInfo['donor'] == 'yes'
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
        $row = \App\Support\UserDisplay::row($id);

        return (string) ($row['username'] ?? '');
    }

    /**
     * Build the avatar `<img>` tag.
     *
     * Mirrors `return_avatar_image()`.
     */
    public static function avatarImage(string $url, string $langFolder): string
    {
        return '<img src="' . $url . '" alt="avatar" width="150px" onload="check_avatar(this, \'' . $langFolder . '\');" />';
    }

    /**
     * Context-aware wrapper for {@see avatarImage()}.
     */
    public static function avatarImageWithContext(string $url): string
    {
        return self::avatarImage($url, (string) SupportContext::getGlobal('CURLANGDIR', ''));
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

        return new HtmlString(\App\Support\UserDisplay::username($id, false, true, true, true));
    }

    /**
     * Build a rich username display with icons, medals and link.
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
    ): string {
        $id = (int) $id;

        if (func_num_args() === 1 && isset(self::$usernameCache[$id])) {
            return self::$usernameCache[$id];
        }

        $arr = \App\Support\UserDisplay::row($id);
        if ($arr) {
            if ($big) {
                $donorpic = 'starbig';
                $leechwarnpic = 'leechwarnedbig';
                $warnedpic = 'warnedbig';
                $disabledpic = 'disabledbig';
                $marginLeft = '4pt';
                $medalSize = '16px';
                $medalClass = 'nexus-username-medal-big';
                $style = "style='margin-left: $marginLeft'";
            } else {
                $donorpic = 'star';
                $leechwarnpic = 'leechwarned';
                $warnedpic = 'warned';
                $disabledpic = 'disabled';
                $marginLeft = '2pt';
                $medalSize = '11px';
                $medalClass = 'nexus-username-medal';
                $style = "style='margin-left: $marginLeft'";
            }

            $now = date('Y-m-d H:i:s');
            $donorUntil = $arr['donoruntil'] ?? null;
            $isDonor = $arr['donor'] === 'yes' && ($donorUntil === null || $donorUntil < '1970' || $donorUntil >= $now);
            $pics = $isDonor ? "<img class=\"" . $donorpic . "\" src=\"/pic/trans.gif\" alt=\"Donor\" " . $style . " />" : '';

            if ($arr['enabled'] === 'yes') {
                $pics .= ($arr['leechwarn'] === 'yes' ? "<img class=\"" . $leechwarnpic . "\" src=\"/pic/trans.gif\" alt=\"Leechwarned\" " . $style . " />" : '')
                    . ($arr['warned'] === 'yes' ? "<img class=\"" . $warnedpic . "\" src=\"/pic/trans.gif\" alt=\"Warned\" " . $style . " />" : '');
            } else {
                $pics .= "<img class=\"" . $disabledpic . "\" src=\"/pic/trans.gif\" alt=\"Disabled\" " . $style . " />\n";
            }

            $username = $arr['username'];
            $rainbow = '';
            $hasSetRainbow = false;
            if (isset($arr['__is_rainbow']) && $arr['__is_rainbow']) {
                $rainbow = ' class="rainbow"';
            }
            if ($underline) {
                $hasSetRainbow = true;
                $username = "<u{$rainbow}>{$username}</u>";
            }
            if ($bold) {
                if ($hasSetRainbow) {
                    $username = "<b>{$username}</b>";
                } else {
                    $hasSetRainbow = true;
                    $username = "<b{$rainbow}>{$username}</b>";
                }
            }

            $medalHtml = '';
            foreach ($arr['wearing_medals'] ?? [] as $medal) {
                $medalHtml .= sprintf(
                    '<img src="%s" title="%s" class="%s preview" style="max-height: %s;max-width: %s;margin-left: %s"/>',
                    $medal['image_large'],
                    $medal['name'],
                    $medalClass,
                    $medalSize,
                    $medalSize,
                    $marginLeft
                );
            }

            $href = Url::schemeAndHost() . "/userdetails.php?id=$id";
            $classNameColored = \App\Support\UserClass::name($arr['class'], true, false, false);
            $className = \App\Support\UserClass::name($arr['class'], false, true, true, ['with_alias' => true]);
            $title = $arr['title'] ?? '';

            $username = ($link
                ? "<a " . $link_ext . ' href="' . $href . '"' . ($target ? ' target="_blank"' : '') . " class='" . $classNameColored . "_Name'>" . $username . '</a>'
                : $username)
                . $pics
                . ($withtitle
                    ? ' (' . ($title === '' ? $className : "<span class='" . $classNameColored . "_Name'><b>" . htmlspecialchars($title)) . '</b></span>)'
                    : '');

            $username = '<span class="nowrap">' . ($bracket ? '(' . $username . ')' : $username) . $medalHtml . '</span>';
        } else {
            $username = '<i>' . Locale::trans('nexus.user_not_exists') . '</i>';
            $username = '<span class="nowrap">' . ($bracket ? '(' . $username . ')' : $username) . '</span>';
        }

        if (func_num_args() === 1) {
            self::$usernameCache[$id] = $username;
        }

        return $username;
    }
}
