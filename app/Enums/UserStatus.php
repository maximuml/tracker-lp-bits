<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user account status.
 *
 * Mirrors the string constants from App\Models\User:
 *   STATUS_CONFIRMED ('confirmed'), STATUS_PENDING ('pending').
 */
enum UserStatus: string
{
    case CONFIRMED = 'confirmed';
    case PENDING = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::CONFIRMED => 'Confirmed',
            self::PENDING => 'Pending',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::PENDING;
    }
}
