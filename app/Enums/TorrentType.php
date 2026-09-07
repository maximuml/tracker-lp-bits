<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent type.
 *
 * Mirrors the torrents.type column: 'single', 'multi'.
 */
enum TorrentType: int
{
    case SINGLE = 0;
    case MULTI = 1;

    public function label(): string
    {
        return match ($this) {
            self::SINGLE => 'Single',
            self::MULTI => 'Multi',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::SINGLE => 'single',
            self::MULTI => 'multi',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'multi' => self::MULTI,
            'single' => self::SINGLE,
            default => self::SINGLE,
        };
    }
}
