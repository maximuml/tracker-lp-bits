<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent promotion / special-state codes.
 *
 * Mirrors the historical integer codes from App\Models\Torrent, but adds
 * exhaustiveness and type-safety for ratio calculation and UI rendering.
 */
enum TorrentPromotion: int
{
    case NORMAL = 1;
    case FREE = 2;
    case TWO_TIMES_UP = 3;
    case FREE_TWO_TIMES_UP = 4;
    case HALF_DOWN = 5;
    case HALF_DOWN_TWO_TIMES_UP = 6;
    case ONE_THIRD_DOWN = 7;

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::FREE => 'Free',
            self::TWO_TIMES_UP => '2X',
            self::FREE_TWO_TIMES_UP => '2X Free',
            self::HALF_DOWN => '50%',
            self::HALF_DOWN_TWO_TIMES_UP => '2X 50%',
            self::ONE_THIRD_DOWN => '30%',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NORMAL => '',
            self::FREE => 'linear-gradient(to right, rgba(0,52,206,0.5), rgba(0,52,206,1), rgba(0,52,206,0.5))',
            self::TWO_TIMES_UP => 'linear-gradient(to right, rgba(0,153,0,0.5), rgba(0,153,0,1), rgba(0,153,0,0.5))',
            self::FREE_TWO_TIMES_UP => 'linear-gradient(to right, rgba(0,153,0,1), rgba(0,52,206,1))',
            self::HALF_DOWN => 'linear-gradient(to right, rgba(220,0,3,0.5), rgba(220,0,3,1), rgba(220,0,3,0.5))',
            self::HALF_DOWN_TWO_TIMES_UP => 'linear-gradient(to right, rgba(0,153,0,1), rgba(220,0,3,1))',
            self::ONE_THIRD_DOWN => 'linear-gradient(to right, rgba(65,23,73,0.5), rgba(65,23,73,1), rgba(65,23,73,0.5))',
        };
    }

    public function upMultiplier(): int
    {
        return match ($this) {
            self::NORMAL => 1,
            self::FREE => 1,
            self::TWO_TIMES_UP => 2,
            self::FREE_TWO_TIMES_UP => 2,
            self::HALF_DOWN => 1,
            self::HALF_DOWN_TWO_TIMES_UP => 2,
            self::ONE_THIRD_DOWN => 1,
        };
    }

    public function downMultiplier(): float|int
    {
        return match ($this) {
            self::NORMAL => 1,
            self::FREE => 0,
            self::TWO_TIMES_UP => 1,
            self::FREE_TWO_TIMES_UP => 0,
            self::HALF_DOWN => 0.5,
            self::HALF_DOWN_TWO_TIMES_UP => 0.5,
            self::ONE_THIRD_DOWN => 0.3,
        };
    }

    public function isPromotion(): bool
    {
        return $this !== self::NORMAL;
    }

    /**
     * Resolve a promotion code from an untrusted integer, falling back to Normal.
     */
    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::NORMAL;
    }
}
