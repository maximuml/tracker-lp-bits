<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user gender.
 *
 * Mirrors the string constants from App\Models\User:
 *   GENDER_FEMALE ('Female'), GENDER_MALE ('Male'), GENDER_UNKNOWN ('N/A').
 */
enum UserGender: string
{
    case FEMALE = 'Female';
    case MALE = 'Male';
    case UNKNOWN = 'N/A';

    public function label(): string
    {
        return match ($this) {
            self::FEMALE => 'Female',
            self::MALE => 'Male',
            self::UNKNOWN => 'Unknown',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::UNKNOWN;
    }
}
