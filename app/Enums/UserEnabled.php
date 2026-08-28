<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user enabled/disabled state.
 *
 * Mirrors the string constants from App\Models\User:
 *   ENABLED_YES ('yes'), ENABLED_NO ('no').
 */
enum UserEnabled: string
{
    case YES = 'yes';
    case NO = 'no';

    public function label(): string
    {
        return match ($this) {
            self::YES => 'Enabled',
            self::NO => 'Disabled',
        };
    }

    public function isEnabled(): bool
    {
        return $this === self::YES;
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::YES;
    }
}
