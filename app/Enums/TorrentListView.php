<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Torrent listing display mode — row cards or the poster grid.
 * Persisted per user on `users.torrents_view`; the `view` query
 * parameter overrides and saves the choice.
 */
enum TorrentListView: string
{
    case List = 'list';
    case Grid = 'grid';

    /**
     * Resolve the effective mode: explicit ?view= wins, then the saved
     * user preference, then the list default.
     */
    public static function resolve(mixed $param, ?string $saved): self
    {
        return self::tryFrom(is_string($param) ? $param : '')
            ?? self::tryFrom((string) $saved)
            ?? self::List;
    }
}
