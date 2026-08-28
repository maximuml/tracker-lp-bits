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
enum LanguageTranslationState: string
{
    case UP_TO_DATE = 'up-to-date';
    case OUT_DATE = 'outdate';
    case INCOMPLETE = 'incomplete';
    case NEED_NEW = 'need-new';
    case UNAVAILABLE = 'unavailable';

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

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::UNAVAILABLE;
    }
}
