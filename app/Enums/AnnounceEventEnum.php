<?php

namespace App\Enums;

use App\Support\Locale;

enum AnnounceEventEnum: string
{
    case STARTED = 'started';
    case STOPPED = 'stopped';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::STARTED => Locale::trans('announce_log.events.started', [], null),
            self::STOPPED => Locale::trans('announce_log.events.stopped', [], null),
            self::PAUSED => Locale::trans('announce_log.events.paused', [], null),
            self::COMPLETED => Locale::trans('announce_log.events.completed', [], null),
            self::NONE => Locale::trans('announce_log.events.none', [], null),
            default => '',
        };
    }
}
