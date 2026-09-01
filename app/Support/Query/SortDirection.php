<?php

declare(strict_types=1);

namespace App\Support\Query;

/**
 * Sort direction enum.
 *
 * Used by BaseRepository::getSortFieldAndType() to ensure
 * only 'asc' or 'desc' are accepted as sort direction.
 */
enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    /**
     * Create from a string value, defaulting to 'desc' for invalid input.
     */
    public static function fromInput(?string $value): self
    {
        if ($value !== null && str_starts_with(strtolower($value), 'asc')) {
            return self::Asc;
        }

        return self::Desc;
    }
}
