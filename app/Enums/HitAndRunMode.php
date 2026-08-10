<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for Hit & Run enforcement mode per section / torrent.
 *
 * Mirrors the string constants from App\Models\HitAndRun:
 *   disabled - H&R is not enforced
 *   manual   - H&R is enforced only when the torrent flag is set
 *   global   - H&R is enforced for all torrents in the section
 */
enum HitAndRunMode: string
{
    case DISABLED = 'disabled';
    case MANUAL = 'manual';
    case GLOBAL = 'global';

    public function label(): string
    {
        return match ($this) {
            self::DISABLED => 'Disabled',
            self::MANUAL => 'Manual',
            self::GLOBAL => 'Global',
        };
    }

    /**
     * Whether H&R checks run at all in this mode.
     */
    public function isEnabled(): bool
    {
        return $this !== self::DISABLED;
    }

    /**
     * Whether H&R applies unconditionally (ignoring per-torrent flag).
     */
    public function isGlobal(): bool
    {
        return $this === self::GLOBAL;
    }

    /**
     * Resolve a mode from an untrusted string, defaulting to disabled.
     */
    public static function fromStringSafe(?string $value): self
    {
        if ($value === null) {
            return self::DISABLED;
        }

        return self::tryFrom($value) ?? self::DISABLED;
    }
}
