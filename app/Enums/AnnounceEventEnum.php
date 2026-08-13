<?php

namespace App\Enums;

use function PHPUnit\Framework\matches;

enum AnnounceEventEnum: string
{
    case STARTED = "started";
    case STOPPED = "stopped";
    case PAUSED = "paused";
    case COMPLETED = "completed";
    case NONE = "none";

    public function label(): string
    {
        return match ($this) {
            self::STARTED => \App\Support\Locale::trans("announce_log.events.started", [], null),
            self::STOPPED => \App\Support\Locale::trans("announce_log.events.stopped", [], null),
            self::PAUSED => \App\Support\Locale::trans("announce_log.events.paused", [], null),
            self::COMPLETED => \App\Support\Locale::trans("announce_log.events.completed", [], null),
            self::NONE => \App\Support\Locale::trans("announce_log.events.none", [], null),
            default => '',
        };
    }
}
