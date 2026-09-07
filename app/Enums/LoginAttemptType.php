<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for login attempt type.
 *
 * Mirrors the loginattempts.type column: 'login', 'recover'.
 */
enum LoginAttemptType: int
{
    case LOGIN = 0;
    case RECOVER = 1;

    public function label(): string
    {
        return match ($this) {
            self::LOGIN => 'Login',
            self::RECOVER => 'Recover',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::LOGIN => 'login',
            self::RECOVER => 'recover',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'recover' => self::RECOVER,
            'login' => self::LOGIN,
            default => self::LOGIN,
        };
    }
}
