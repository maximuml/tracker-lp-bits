<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for snatch finished state.
 *
 * Mirrors the string constants from App\Models\Snatch:
 *   FINISHED_YES ('yes'), FINISHED_NO ('no').
 */
enum SnatchFinished: string
{
    case YES = 'yes';
    case NO = 'no';

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
