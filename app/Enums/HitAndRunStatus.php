<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for Hit & Run inspection status.
 *
 * Mirrors the integer constants from App\Models\HitAndRun:
 *   STATUS_INSPECTING, STATUS_REACHED, STATUS_UNREACHED, STATUS_PARDONED.
 */
enum HitAndRunStatus: int
{
    case INSPECTING = 1;
    case REACHED = 2;
    case UNREACHED = 3;
    case PARDONED = 4;

    public function label(): string
    {
        return match ($this) {
            self::INSPECTING => 'Inspecting',
            self::REACHED => 'Reached',
            self::UNREACHED => 'Unreached',
            self::PARDONED => 'Pardoned',
        };
    }

    public function canPardon(): bool
    {
        return in_array($this, [self::INSPECTING, self::UNREACHED], true);
    }

    public static function fromIntSafe(int $value): self
    {
        return self::tryFrom($value) ?? self::INSPECTING;
    }
}
