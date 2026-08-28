<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for medal acquisition type.
 *
 * Mirrors the integer constants from App\Models\Medal:
 *   GET_TYPE_EXCHANGE (1), GET_TYPE_GRANT (2).
 */
enum MedalGetType: int
{
    case EXCHANGE = 1;
    case GRANT = 2;

    public function label(): string
    {
        return match ($this) {
            self::EXCHANGE => 'Exchange',
            self::GRANT => 'Grant',
        };
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::EXCHANGE;
    }
}
