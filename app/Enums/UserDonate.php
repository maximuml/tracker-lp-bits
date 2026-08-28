<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user donate flag.
 *
 * Mirrors the string constants from App\Models\User:
 *   DONATE_YES ('yes'), DONATE_NO ('no').
 */
enum UserDonate: string
{
    case YES = 'yes';
    case NO = 'no';

    public function label(): string
    {
        return match ($this) {
            self::YES => 'Donor',
            self::NO => 'Non-donor',
        };
    }

    public function isDonor(): bool
    {
        return $this === self::YES;
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::NO;
    }
}
