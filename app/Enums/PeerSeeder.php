<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for peer seeder state.
 *
 * Mirrors the string constants from App\Models\Peer:
 *   SEEDER_YES ('yes'), SEEDER_NO ('no').
 */
enum PeerSeeder: string
{
    case YES = 'yes';
    case NO = 'no';

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
