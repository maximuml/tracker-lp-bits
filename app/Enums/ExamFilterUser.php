<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for exam user-filter criteria.
 *
 * Mirrors the string constants from App\Models\Exam:
 *   FILTER_USER_CLASS ('classes'), FILTER_USER_REGISTER_TIME_RANGE ('register_time_range'),
 *   FILTER_USER_DONATE ('donate_status'), FILTER_USER_REGISTER_DAYS_RANGE ('register_days_range').
 */
enum ExamFilterUser: string
{
    case USER_CLASS = 'classes';
    case REGISTER_TIME_RANGE = 'register_time_range';
    case DONATE = 'donate_status';
    case REGISTER_DAYS_RANGE = 'register_days_range';

    public function label(): string
    {
        return match ($this) {
            self::class => 'User class',
            self::REGISTER_TIME_RANGE => 'Register time range',
            self::DONATE => 'Donate status',
            self::REGISTER_DAYS_RANGE => 'Register days range',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::class;
    }
}
