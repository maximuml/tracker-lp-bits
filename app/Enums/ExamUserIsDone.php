<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for exam-user is_done flag.
 *
 * Mirrors the integer constants from App\Models\ExamUser:
 *   IS_DONE_NO (0), IS_DONE_YES (1).
 */
enum ExamUserIsDone: int
{
    case NO = 0;
    case YES = 1;

    public function label(): string
    {
        return match ($this) {
            self::NO => 'Not done',
            self::YES => 'Done',
        };
    }

    public function isDone(): bool
    {
        return $this === self::YES;
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::NO;
    }
}
