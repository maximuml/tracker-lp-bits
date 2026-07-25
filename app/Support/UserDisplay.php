<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Legacy user display helpers extracted from `include/functions.php`.
 *
 * Backs `get_plain_username`, `return_avatar_image` and
 * `username_for_admin`.
 */
final class UserDisplay
{
    /**
     * Return the current user's class value, or '' in the legacy context
     * when the user is not loaded.
     *
     * Mirrors `get_user_class()`.
     */
    public static function currentClass(): string|int
    {
        if (defined('IN_NEXUS') && IN_NEXUS) {
            global $CURUSER;
            return $CURUSER['class'] ?? '';
        }

        return auth()->user()?->class ?? '';
    }

    /**
     * Return the current user's id, or 0 when not authenticated.
     *
     * Mirrors `get_user_id()`.
     */
    public static function currentId(): int
    {
        if (defined('IN_NEXUS') && IN_NEXUS) {
            global $CURUSER;
            return (int) ($CURUSER['id'] ?? 0);
        }

        return (int) (auth()->user()?->id ?? 0);
    }

    /**
     * Return the current user's passkey, or '' when not available.
     *
     * Mirrors `get_user_passkey()`.
     */
    public static function currentPasskey(): string
    {
        if (defined('IN_NEXUS') && IN_NEXUS) {
            global $CURUSER;
            return $CURUSER['passkey'] ?? '';
        }

        return auth()->user()?->passkey ?? '';
    }

    /**
     * Return the current user's raw username, or '' when not available.
     *
     * Mirrors `get_pure_username()`.
     */
    public static function currentUsername(): string
    {
        if (defined('IN_NEXUS') && IN_NEXUS) {
            global $CURUSER;
            return $CURUSER['username'] ?? '';
        }

        return auth()->user()?->username ?? '';
    }

    /**
     * Return the raw username for a user id.
     *
     * Mirrors `get_plain_username()`.
     */
    public static function plainUsername(int|string $id): string
    {
        $row = \get_user_row($id);

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
     * Build the admin-area username link.
     *
     * Mirrors `username_for_admin()`.
     */
    public static function adminUsername(int $id): HtmlString
    {
        if ($id <= 0) {
            return new HtmlString('');
        }

        return new HtmlString(\get_username($id, false, true, true, true));
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
        static $usernameArray = [];
        $id = (int) $id;

        if (func_num_args() === 1 && isset($usernameArray[$id])) {
            return $usernameArray[$id];
        }

        $arr = \get_user_row($id);
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

            $href = \getSchemeAndHttpHost() . "/userdetails.php?id=$id";
            $classNameColored = \get_user_class_name($arr['class'], true, false, false);
            $className = \get_user_class_name($arr['class'], false, true, true, ['with_alias' => true]);
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
            $username = '<i>' . nexus_trans('nexus.user_not_exists') . '</i>';
            $username = '<span class="nowrap">' . ($bracket ? '(' . $username . ')' : $username) . '</span>';
        }

        if (func_num_args() === 1) {
            $usernameArray[$id] = $username;
        }

        return $username;
    }
}
