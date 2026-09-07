<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for language translation state.
 *
 * Mirrors the string constants from App\Models\Language:
 *   TRANS_STATE_UP_TO_DATE, TRANS_STATE_OUT_DATE,
 *   TRANS_STATE_INCOMPLETE, TRANS_STATE_NEED_NEW, TRANS_STATE_UNAVAILABLE.
 */
enum LanguageTranslationState: int
{
    case UP_TO_DATE = 0;
    case OUT_DATE = 1;
    case INCOMPLETE = 2;
    case NEED_NEW = 3;
    case UNAVAILABLE = 4;

    public function label(): string
    {
        return match ($this) {
            self::UP_TO_DATE => 'Up to date',
            self::OUT_DATE => 'Outdated',
            self::INCOMPLETE => 'Incomplete',
            self::NEED_NEW => 'Need new',
            self::UNAVAILABLE => 'Unavailable',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::UP_TO_DATE => 'up-to-date',
            self::OUT_DATE => 'outdate',
            self::INCOMPLETE => 'incomplete',
            self::NEED_NEW => 'need-new',
            self::UNAVAILABLE => 'unavailable',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'up-to-date' => self::UP_TO_DATE,
            'outdate' => self::OUT_DATE,
            'incomplete' => self::INCOMPLETE,
            'need-new' => self::NEED_NEW,
            'unavailable' => self::UNAVAILABLE,
            default => self::UNAVAILABLE,
        };
    }
}
