<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for bitbucket public visibility.
 *
 * Mirrors the bitbucket.public column: '0' (private), '1' (public).
 */
enum BitbucketPublic: int
{
    case NO = 0;
    case YES = 1;

    public function label(): string
    {
        return match ($this) {
            self::NO => 'Private',
            self::YES => 'Public',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::NO => '0',
            self::YES => '1',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            '1' => self::YES,
            '0' => self::NO,
            default => self::NO,
        };
    }
}
