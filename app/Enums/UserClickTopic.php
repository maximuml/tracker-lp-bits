<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user click-topic preference.
 *
 * Mirrors the users.clicktopic column: 'firstpage', 'lastpage'.
 */
enum UserClickTopic: int
{
    case FIRSTPAGE = 0;
    case LASTPAGE = 1;

    public function label(): string
    {
        return match ($this) {
            self::FIRSTPAGE => 'First page',
            self::LASTPAGE => 'Last page',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::FIRSTPAGE => 'firstpage',
            self::LASTPAGE => 'lastpage',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'lastpage' => self::LASTPAGE,
            'firstpage' => self::FIRSTPAGE,
            default => self::FIRSTPAGE,
        };
    }
}
