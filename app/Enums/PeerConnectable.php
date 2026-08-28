<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for peer connectable state.
 *
 * Mirrors the string constants from App\Models\Peer:
 *   CONNECTABLE_YES ('yes'), CONNECTABLE_NO ('no').
 */
enum PeerConnectable: string
{
    case YES = 'yes';
    case NO = 'no';

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
