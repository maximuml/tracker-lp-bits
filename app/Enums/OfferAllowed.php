<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for offer allowed status.
 *
 * Mirrors the offers.allowed column: 'allowed', 'pending', 'denied'.
 */
enum OfferAllowed: int
{
    case ALLOWED = 0;
    case PENDING = 1;
    case DENIED = 2;

    public function label(): string
    {
        return match ($this) {
            self::ALLOWED => 'Allowed',
            self::PENDING => 'Pending',
            self::DENIED => 'Denied',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::ALLOWED => 'allowed',
            self::PENDING => 'pending',
            self::DENIED => 'denied',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'allowed' => self::ALLOWED,
            'denied' => self::DENIED,
            'pending' => self::PENDING,
            default => self::PENDING,
        };
    }
}
