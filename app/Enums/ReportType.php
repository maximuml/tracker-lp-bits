<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for report type.
 *
 * Mirrors the reports.type column: 'torrent', 'user', 'offer', 'request',
 * 'post', 'comment', 'subtitle'.
 */
enum ReportType: int
{
    case TORRENT = 0;
    case USER = 1;
    case OFFER = 2;
    case REQUEST = 3;
    case POST = 4;
    case COMMENT = 5;
    case SUBTITLE = 6;

    public function label(): string
    {
        return match ($this) {
            self::TORRENT => 'Torrent',
            self::USER => 'User',
            self::OFFER => 'Offer',
            self::REQUEST => 'Request',
            self::POST => 'Post',
            self::COMMENT => 'Comment',
            self::SUBTITLE => 'Subtitle',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::TORRENT => 'torrent',
            self::USER => 'user',
            self::OFFER => 'offer',
            self::REQUEST => 'request',
            self::POST => 'post',
            self::COMMENT => 'comment',
            self::SUBTITLE => 'subtitle',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'torrent' => self::TORRENT,
            'user' => self::USER,
            'offer' => self::OFFER,
            'request' => self::REQUEST,
            'post' => self::POST,
            'comment' => self::COMMENT,
            'subtitle' => self::SUBTITLE,
            default => self::TORRENT,
        };
    }
}
