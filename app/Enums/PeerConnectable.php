<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for peer connectable state.
 *
 * Mirrors the boolean columns from App\Models\Peer:
 *   CONNECTABLE_YES (1), CONNECTABLE_NO (0).
 */
enum PeerConnectable: int
{
    case YES = 1;
    case NO = 0;

    public function label(): string
    {
        return match ($this) {
            self::YES => 'Connectable',
            self::NO => 'Not connectable',
        };
    }

    public function isConnectable(): bool
    {
        return $this === self::YES;
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::NO;
    }
}
