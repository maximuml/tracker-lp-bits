<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for snatch finished state.
 *
 * Mirrors the boolean columns from App\Models\Snatch:
 *   FINISHED_YES (1), FINISHED_NO (0).
 */
enum SnatchFinished: int
{
    case YES = 1;
    case NO = 0;

    public function label(): string
    {
        return match ($this) {
            self::YES => 'Finished',
            self::NO => 'Not finished',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::YES;
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::NO;
    }
}
