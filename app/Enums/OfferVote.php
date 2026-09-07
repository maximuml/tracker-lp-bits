<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for offer vote type.
 *
 * Mirrors the offervotes.vote column: 'yeah', 'against'.
 */
enum OfferVote: int
{
    case YEAH = 0;
    case AGAINST = 1;

    public function label(): string
    {
        return match ($this) {
            self::YEAH => 'For',
            self::AGAINST => 'Against',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::YEAH => 'yeah',
            self::AGAINST => 'against',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'against' => self::AGAINST,
            'yeah' => self::YEAH,
            default => self::YEAH,
        };
    }
}
