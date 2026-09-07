<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user time display preference.
 *
 * Mirrors the users.timetype column: 'timeadded', 'timealive'.
 */
enum UserTimeType: int
{
    case TIMEADDED = 0;
    case TIMEALIVE = 1;

    public function label(): string
    {
        return match ($this) {
            self::TIMEADDED => 'Time added',
            self::TIMEALIVE => 'Time alive',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::TIMEADDED => 'timeadded',
            self::TIMEALIVE => 'timealive',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'timeadded' => self::TIMEADDED,
            'timealive' => self::TIMEALIVE,
            default => self::TIMEALIVE,
        };
    }
}
