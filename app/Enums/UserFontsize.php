<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user font size preference.
 *
 * Mirrors the users.fontsize column: 'small', 'medium', 'large'.
 */
enum UserFontsize: int
{
    case SMALL = 0;
    case MEDIUM = 1;
    case LARGE = 2;

    public function label(): string
    {
        return match ($this) {
            self::SMALL => 'Small',
            self::MEDIUM => 'Medium',
            self::LARGE => 'Large',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::SMALL => 'small',
            self::MEDIUM => 'medium',
            self::LARGE => 'large',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'small' => self::SMALL,
            'large' => self::LARGE,
            'medium' => self::MEDIUM,
            default => self::MEDIUM,
        };
    }
}
