<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent visibility.
 *
 * Mirrors the string constants from App\Models\Torrent:
 *   VISIBLE_YES ('yes'), VISIBLE_NO ('no').
 */
enum TorrentVisible: string
{
    case YES = 'yes';
    case NO = 'no';

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
