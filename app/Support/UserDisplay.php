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
}
