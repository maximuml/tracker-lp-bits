<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for exam discovered flag.
 *
 * Mirrors the integer constants from App\Models\Exam:
 *   DISCOVERED_NO (0), DISCOVERED_YES (1).
 */
enum ExamDiscovered: int
{
    case NO = 0;
    case YES = 1;

    public function label(): string
    {
        return match ($this) {
            self::NO => 'Not discovered',
            self::YES => 'Discovered',
        };
    }

    public function isDiscovered(): bool
    {
        return $this === self::YES;
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::NO;
    }
}
