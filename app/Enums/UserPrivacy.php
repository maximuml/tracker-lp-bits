<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user privacy level.
 *
 * Mirrors the users.privacy column: 'strong', 'normal', 'low'.
 */
enum UserPrivacy: int
{
    case STRONG = 0;
    case NORMAL = 1;
    case LOW = 2;

    public function label(): string
    {
        return match ($this) {
            self::STRONG => 'Strong',
            self::NORMAL => 'Normal',
            self::LOW => 'Low',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::STRONG => 'strong',
            self::NORMAL => 'normal',
            self::LOW => 'low',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'strong' => self::STRONG,
            'low' => self::LOW,
            'normal' => self::NORMAL,
            default => self::NORMAL,
        };
    }
}
