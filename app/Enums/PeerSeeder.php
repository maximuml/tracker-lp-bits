<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for peer seeder state.
 *
 * Mirrors the boolean columns from App\Models\Peer:
 *   SEEDER_YES (1), SEEDER_NO (0).
 */
enum PeerSeeder: int
{
    case YES = 1;
    case NO = 0;

    public function label(): string
    {
        return match ($this) {
            self::YES => 'Seeder',
            self::NO => 'Leecher',
        };
    }

    public function isSeeder(): bool
    {
        return $this === self::YES;
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::NO;
    }
}
