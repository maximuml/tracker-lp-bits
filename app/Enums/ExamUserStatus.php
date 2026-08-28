<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for exam-user status.
 *
 * Mirrors the integer constants from App\Models\ExamUser:
 *   STATUS_AVOIDED (-1), STATUS_NORMAL (0), STATUS_FINISHED (1).
 */
enum ExamUserStatus: int
{
    case AVOIDED = -1;
    case NORMAL = 0;
    case FINISHED = 1;

    public function label(): string
    {
        return match ($this) {
            self::AVOIDED => 'Avoided',
            self::NORMAL => 'Normal',
            self::FINISHED => 'Finished',
        };
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::NORMAL;
    }
}
