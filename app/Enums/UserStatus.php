<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user account status.
 *
 * Mirrors the string constants from App\Models\User:
 *   STATUS_PENDING ('pending'), STATUS_CONFIRMED ('confirmed').
 */
enum UserStatus: int
{
    case PENDING = 0;
    case CONFIRMED = 1;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::PENDING => 'pending',
            self::CONFIRMED => 'confirmed',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'confirmed' => self::CONFIRMED,
            'pending' => self::PENDING,
            default => self::PENDING,
        };
    }
}
