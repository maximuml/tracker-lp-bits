<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for exam enabled/disabled status.
 *
 * Mirrors the integer constants from App\Models\Exam:
 *   STATUS_ENABLED (0), STATUS_DISABLED (1).
 */
enum ExamStatus: int
{
    case ENABLED = 0;
    case DISABLED = 1;

    public function label(): string
    {
        return match ($this) {
            self::ENABLED => 'Enabled',
            self::DISABLED => 'Disabled',
        };
    }

    public function isEnabled(): bool
    {
        return $this === self::ENABLED;
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::ENABLED;
    }
}
