<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent custom field type.
 *
 * Mirrors the torrents_custom_fields.type column:
 * 'text', 'textarea', 'select', 'radio', 'checkbox', 'image'.
 */
enum TorrentCustomFieldType: int
{
    case TEXT = 0;
    case TEXTAREA = 1;
    case SELECT = 2;
    case RADIO = 3;
    case CHECKBOX = 4;
    case IMAGE = 5;

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text',
            self::TEXTAREA => 'Textarea',
            self::SELECT => 'Select',
            self::RADIO => 'Radio',
            self::CHECKBOX => 'Checkbox',
            self::IMAGE => 'Image',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::TEXT => 'text',
            self::TEXTAREA => 'textarea',
            self::SELECT => 'select',
            self::RADIO => 'radio',
            self::CHECKBOX => 'checkbox',
            self::IMAGE => 'image',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'textarea' => self::TEXTAREA,
            'select' => self::SELECT,
            'radio' => self::RADIO,
            'checkbox' => self::CHECKBOX,
            'image' => self::IMAGE,
            'text' => self::TEXT,
            default => self::TEXT,
        };
    }
}
