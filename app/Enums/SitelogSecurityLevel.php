<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for sitelog security level.
 *
 * Mirrors the sitelog.security_level column: 'normal', 'mod'.
 */
enum SitelogSecurityLevel: int
{
    case NORMAL = 0;
    case MOD = 1;

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::MOD => 'Moderator',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::NORMAL => 'normal',
            self::MOD => 'mod',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'mod' => self::MOD,
            'normal' => self::NORMAL,
            default => self::NORMAL,
        };
    }
}
