<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for shoutbox entry type.
 *
 * Mirrors the shoutbox.type column: 'sb'.
 */
enum ShoutboxType: int
{
    case SB = 0;

    public function label(): string
    {
        return 'Shoutbox';
    }

    public function stringValue(): string
    {
        return 'sb';
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::SB;
    }
}
