<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent state notice.
 *
 * Mirrors the integer constants from App\Models\TorrentState:
 *   NOTICE_UNLIMITED (-1), NOTICE_NONE (0).
 */
enum TorrentStateNotice: int
{
    case UNLIMITED = -1;
    case NONE = 0;

    public function label(): string
    {
        return match ($this) {
            self::UNLIMITED => 'Unlimited',
            self::NONE => 'None',
        };
    }

    public function isUnlimited(): bool
    {
        return $this === self::UNLIMITED;
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::NONE;
    }
}
