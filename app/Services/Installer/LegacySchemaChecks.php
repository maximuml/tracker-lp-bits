<?php

declare(strict_types=1);

namespace App\Services\Installer;

use Illuminate\Support\Facades\Schema;

/**
 * Schema probes for `app:upgrade` legacy fixups — column metadata and
 * index detection used to decide whether a pre-Laravel database still
 * needs each conditional migration.
 */
final class LegacySchemaChecks
{
    /**
     * The unique (torrent, peer_id, userid) index the 2023_04_01 migration
     * adds. Checked by columns, not name: Laravel auto-names it
     * `peers_torrent_peer_id_userid_unique`, so the legacy name check
     * (`unique_torrent_peer_user`) never matched and re-ran the dedupe on
     * every upgrade.
     */
    public function peersHasUniqueTorrentPeerUser(): bool
    {
        foreach (Schema::getIndexes('peers') as $index) {
            if (! empty($index['unique'])
                && in_array('torrent', $index['columns'], true)
                && in_array('peer_id', $index['columns'], true)
                && in_array('userid', $index['columns'], true)) {
                return true;
            }
        }

        return false;
    }

    public function isSnatchedTableTorrentUserUnique(): bool
    {
        foreach (Schema::getIndexes('snatched') as $index) {
            if (! empty($index['unique'])
                && in_array('torrentid', $index['columns'], true)
                && in_array('userid', $index['columns'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null column metadata (Schema::getColumns entry)
     */
    public function columnInfo(string $table, string $column): ?array
    {
        foreach (Schema::getColumns($table) as $col) {
            if ($col['name'] === $column) {
                return $col;
            }
        }

        return null;
    }
}
