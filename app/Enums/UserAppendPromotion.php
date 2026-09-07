<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user promotion display preference.
 *
 * Mirrors the users.appendpromotion column: 'highlight', 'word', 'icon', 'off'.
 */
enum UserAppendPromotion: int
{
    case HIGHLIGHT = 0;
    case WORD = 1;
    case ICON = 2;
    case OFF = 3;

    public function label(): string
    {
        return match ($this) {
            self::HIGHLIGHT => 'Highlight',
            self::WORD => 'Word',
            self::ICON => 'Icon',
            self::OFF => 'Off',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::HIGHLIGHT => 'highlight',
            self::WORD => 'word',
            self::ICON => 'icon',
            self::OFF => 'off',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'highlight' => self::HIGHLIGHT,
            'word' => self::WORD,
            'icon' => self::ICON,
            'off' => self::OFF,
            default => self::ICON,
        };
    }
}
