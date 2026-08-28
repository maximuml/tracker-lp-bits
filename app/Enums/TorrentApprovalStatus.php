<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent approval status.
 *
 * Mirrors the integer constants from App\Models\Torrent:
 *   APPROVAL_STATUS_NONE (0), APPROVAL_STATUS_ALLOW (1), APPROVAL_STATUS_DENY (2).
 */
enum TorrentApprovalStatus: int
{
    case NONE = 0;
    case ALLOW = 1;
    case DENY = 2;

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::ALLOW => 'Allowed',
            self::DENY => 'Denied',
        };
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::NONE;
    }
}
