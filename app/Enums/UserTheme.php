<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * String enum for the user colour-scheme preference.
 *
 * Mirrors the users.theme column: 'auto', 'light', 'dark'.
 */
enum UserTheme: string
{
    case AUTO = 'auto';
    case LIGHT = 'light';
    case DARK = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::AUTO => 'Auto',
            self::LIGHT => 'Light',
            self::DARK => 'Dark',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::AUTO;
    }
}
