<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for username change log types.
 *
 * Mirrors the integer constants from App\Models\UsernameChangeLog:
 *   CHANGE_TYPE_USER (1), CHANGE_TYPE_ADMIN (2).
 */
enum UsernameChangeType: int
{
    case USER = 1;
    case ADMIN = 2;

    public function label(): string
    {
        return match ($this) {
            self::USER => 'User',
            self::ADMIN => 'Admin',
        };
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::USER;
    }
}
