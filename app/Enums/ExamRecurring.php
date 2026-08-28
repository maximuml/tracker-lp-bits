<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for exam recurring period.
 *
 * Mirrors the string constants from App\Models\Exam:
 *   RECURRING_DAILY ('Daily'), RECURRING_WEEKLY ('Weekly'),
 *   RECURRING_MONTHLY ('Monthly').
 */
enum ExamRecurring: string
{
    case DAILY = 'Daily';
    case WEEKLY = 'Weekly';
    case MONTHLY = 'Monthly';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::DAILY;
    }
}
