<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for invite validity.
 *
 * Mirrors the integer constants from App\Models\Invite:
 *   VALID_NO (0), VALID_YES (1).
 */
enum InviteValid: int
{
    case NO = 0;
    case YES = 1;

    public function label(): string
    {
        return match ($this) {
            self::NO => 'Invalid',
            self::YES => 'Valid',
        };
    }

    public function isValid(): bool
    {
        return $this === self::YES;
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::NO;
    }
}
