<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for bookmark filter values.
 *
 * Mirrors the string constants from App\Models\Bookmark:
 *   FILTER_IGNORE ('0'), FILTER_INCLUDE ('1'), FILTER_EXCLUDE ('2').
 */
enum BookmarkFilter: string
{
    case IGNORE = '0';
    case INCLUDE = '1';
    case EXCLUDE = '2';

    public function label(): string
    {
        return match ($this) {
            self::IGNORE => 'Ignore',
            self::INCLUDE => 'Include',
            self::EXCLUDE => 'Exclude',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::IGNORE;
    }
}
