<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent hit-and-run flag.
 *
 * Mirrors the integer constants from App\Models\Torrent:
 *   HR_NO (0), HR_YES (1).
 */
enum TorrentHr: int
{
    case NO = 0;
    case YES = 1;

    public function label(): string
    {
        return match ($this) {
            self::NO => 'No H&R',
            self::YES => 'H&R',
        };
    }

    public function isHr(): bool
    {
        return $this === self::YES;
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::NO;
    }
}
