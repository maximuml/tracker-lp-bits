<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent visibility.
 *
 * Mirrors the boolean columns from App\Models\Torrent:
 *   VISIBLE_YES (1), VISIBLE_NO (0).
 */
enum TorrentVisible: int
{
    case YES = 1;
    case NO = 0;

    public function label(): string
    {
        return match ($this) {
            self::YES => 'Visible',
            self::NO => 'Invisible',
        };
    }

    public function isVisible(): bool
    {
        return $this === self::YES;
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::YES;
    }
}
