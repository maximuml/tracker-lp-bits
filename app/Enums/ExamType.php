<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for exam types.
 *
 * Mirrors the integer constants from App\Models\Exam:
 *   TYPE_EXAM (1), TYPE_TASK (2).
 */
enum ExamType: int
{
    case EXAM = 1;
    case TASK = 2;

    public function label(): string
    {
        return match ($this) {
            self::EXAM => 'Exam',
            self::TASK => 'Task',
        };
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::EXAM;
    }
}
