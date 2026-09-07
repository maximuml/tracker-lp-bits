<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user gender.
 *
 * Mirrors the string constants from App\Models\User:
 *   GENDER_MALE ('Male'), GENDER_FEMALE ('Female'), GENDER_UNKNOWN ('N/A').
 */
enum UserGender: int
{
    case MALE = 0;
    case FEMALE = 1;
    case UNKNOWN = 2;

    public function label(): string
    {
        return match ($this) {
            self::MALE => 'Male',
            self::FEMALE => 'Female',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::MALE => 'Male',
            self::FEMALE => 'Female',
            self::UNKNOWN => 'N/A',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'Male' => self::MALE,
            'Female' => self::FEMALE,
            'N/A' => self::UNKNOWN,
            default => self::UNKNOWN,
        };
    }
}
