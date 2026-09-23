<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fallback avatars for accounts without a custom one.
 *
 * Deterministic by user id — the same user always gets the same
 * neutral silhouette instead of a random image per render.
 */
final class Avatar
{
    private const int VARIANTS = 20;

    private const string BUILTIN_DEFAULT = 'pic/default_avatar.png';

    /**
     * Resolve the avatar URL, falling back to a per-user neutral variant
     * when the account has no custom avatar (empty or the builtin default).
     */
    public static function forUser(int|string $id, string $avatar): string
    {
        if ($avatar !== '' && ! str_ends_with($avatar, self::BUILTIN_DEFAULT)) {
            return $avatar;
        }

        return 'pic/avatars/a'.((abs((int) $id) % self::VARIANTS) + 1).'.png';
    }
}
