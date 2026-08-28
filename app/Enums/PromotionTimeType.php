<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent promotion time types.
 *
 * Mirrors the integer constants from App\Models\Torrent:
 *   PROMOTION_TIME_TYPE_GLOBAL, PROMOTION_TIME_TYPE_PERMANENT,
 *   PROMOTION_TIME_TYPE_DEADLINE.
 */
enum PromotionTimeType: int
{
    case GLOBAL = 0;
    case PERMANENT = 1;
    case DEADLINE = 2;

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Global',
            self::PERMANENT => 'Permanent',
            self::DEADLINE => 'Until',
        };
    }

    /**
     * Resolve a time type from an integer, falling back to Global.
     */
    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::GLOBAL;
    }
}
