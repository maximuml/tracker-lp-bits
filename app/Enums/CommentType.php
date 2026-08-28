<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for comment types.
 *
 * Mirrors the string constants from App\Models\Comment:
 *   TYPE_TORRENT ('torrent'), TYPE_OFFER ('offer').
 */
enum CommentType: string
{
    case TORRENT = 'torrent';
    case OFFER = 'offer';

    public function label(): string
    {
        return match ($this) {
            self::TORRENT => 'Torrent',
            self::OFFER => 'Offer',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::TORRENT;
    }
}
