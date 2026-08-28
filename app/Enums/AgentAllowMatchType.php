<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for agent-allow match type.
 *
 * Mirrors the string constants from App\Models\AgentAllow:
 *   MATCH_TYPE_DEC ('dec'), MATCH_TYPE_HEX ('hex').
 */
enum AgentAllowMatchType: string
{
    case DEC = 'dec';
    case HEX = 'hex';

    public function label(): string
    {
        return match ($this) {
            self::DEC => 'Decimal',
            self::HEX => 'Hexadecimal',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::DEC;
    }
}
