<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for agent-allow match type.
 *
 * Mirrors the string constants from App\Models\AgentAllow:
 *   MATCH_TYPE_DEC ('dec'), MATCH_TYPE_HEX ('hex').
 */
enum AgentAllowMatchType: int
{
    case DEC = 0;
    case HEX = 1;

    public function label(): string
    {
        return match ($this) {
            self::DEC => 'Decimal',
            self::HEX => 'Hexadecimal',
        };
    }

    public function stringValue(): string
    {
        return match ($this) {
            self::DEC => 'dec',
            self::HEX => 'hex',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return match ($value) {
            'hex' => self::HEX,
            'dec' => self::DEC,
            default => self::DEC,
        };
    }
}
