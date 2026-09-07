<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user tooltip display preference.
 *
 * Mirrors the users.tooltip column: 'minorimdb', 'medianimdb', 'off'.
 */
enum UserTooltip: int
{
    case MINORIMDB = 0;
    case MEDIANIMDB = 1;
    case OFF = 2;

    public function label(): string
    {
        return match ($this) {
            self::MINORIMDB => 'Minor IMDB',
            self::MEDIANIMDB => 'Median IMDB',
            self::OFF => 'Off',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::MINORIMDB => 'minorimdb',
            self::MEDIANIMDB => 'medianimdb',
            self::OFF => 'off',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'minorimdb' => self::MINORIMDB,
            'medianimdb' => self::MEDIANIMDB,
            'off' => self::OFF,
            default => self::OFF,
        };
    }
}
