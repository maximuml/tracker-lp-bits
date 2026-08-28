<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user medal wearing status.
 *
 * Mirrors the integer constants from App\Models\UserMedal:
 *   STATUS_NOT_WEARING (0), STATUS_WEARING (1).
 */
enum UserMedalStatus: int
{
    case NOT_WEARING = 0;
    case WEARING = 1;

    public function label(): string
    {
        return match ($this) {
            self::NOT_WEARING => 'Not wearing',
            self::WEARING => 'Wearing',
        };
    }

    public function isWearing(): bool
    {
        return $this === self::WEARING;
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::NOT_WEARING;
    }
}
