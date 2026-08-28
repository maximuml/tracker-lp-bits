<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent position / sticky state.
 *
 * Mirrors the string constants from App\Models\Torrent:
 *   POS_STATE_STICKY_NONE ('normal'), POS_STATE_STICKY_FIRST ('sticky'),
 *   POS_STATE_STICKY_SECOND ('r_sticky').
 */
enum TorrentPosState: string
{
    case NONE = 'normal';
    case STICKY_FIRST = 'sticky';
    case STICKY_SECOND = 'r_sticky';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Normal',
            self::STICKY_FIRST => 'Sticky',
            self::STICKY_SECOND => 'Re-sticky',
        };
    }

    public function isSticky(): bool
    {
        return $this !== self::NONE;
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::NONE;
    }
}
