<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for FAQ entry type.
 *
 * Mirrors the faq.type column: 'categ', 'item'.
 */
enum FaqType: int
{
    case CATEG = 0;
    case ITEM = 1;

    public function label(): string
    {
        return match ($this) {
            self::CATEG => 'Category',
            self::ITEM => 'Item',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::CATEG => 'categ',
            self::ITEM => 'item',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'item' => self::ITEM,
            'categ' => self::CATEG,
            default => self::CATEG,
        };
    }
}
