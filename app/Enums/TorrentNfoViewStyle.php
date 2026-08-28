<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for NFO view style.
 *
 * Mirrors the string constants from App\Models\Torrent:
 *   NFO_VIEW_STYLE_DOS ('magic'), NFO_VIEW_STYLE_WINDOWS ('latin-1').
 */
enum TorrentNfoViewStyle: string
{
    case DOS = 'magic';
    case WINDOWS = 'latin-1';

    public function label(): string
    {
        return match ($this) {
            self::DOS => 'DOS',
            self::WINDOWS => 'Windows',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::DOS;
    }
}
