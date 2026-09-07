<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user PM acceptance preference.
 *
 * Mirrors the users.acceptpms column: 'yes', 'friends', 'no'.
 */
enum UserAcceptPms: int
{
    case YES = 0;
    case FRIENDS = 1;
    case NO = 2;

    public function label(): string
    {
        return match ($this) {
            self::YES => 'Yes',
            self::FRIENDS => 'Friends only',
            self::NO => 'No',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::YES => 'yes',
            self::FRIENDS => 'friends',
            self::NO => 'no',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'friends' => self::FRIENDS,
            'no' => self::NO,
            'yes' => self::YES,
            default => self::YES,
        };
    }
}
