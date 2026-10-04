<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Torrent;
use App\Support\Database;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Torrent-row access on the announce/scrape hot path — base-builder
 * projections consumed as plain arrays by the AnnounceContext DTO.
 */
final class AnnounceTorrentRepository
{
    /**
     * Announce-path torrent row: torrents + categories.mode + unix-ts
     * projection.
     *
     * @return array<string, mixed>|null
     */
    public function findForAnnounce(string $infoHashBinary): ?array
    {
        $tsField = Database::unixTimestampField('added');
        $torrent = Torrent::query()
            ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
            ->toBase()
            ->select([
                'torrents.id', 'torrents.size', 'torrents.owner', 'torrents.sp_state',
                'torrents.seeders', 'torrents.leechers', 'torrents.times_completed',
                'torrents.banned', 'torrents.hr', 'torrents.approval_status', 'torrents.price',
                'torrents.visible', 'torrents.last_action', 'categories.mode',
                DB::raw("{$tsField} AS ts"), // @phpstan-ignore argument.type
            ])
            ->where('torrents.info_hash', $infoHashBinary)
            ->first();

        return $torrent ? (array) $torrent : null;
    }

    /**
     * Scrape rows by info-hash list; binaries on MySQL, hex + decode() on
     * PostgreSQL.
     *
     * @param  array<int, string>  $infoHashBinaries
     * @param  array<int, string>  $infoHashHexes
     * @return Collection<int, Torrent>
     */
    public function listScrapeRows(array $infoHashBinaries, array $infoHashHexes): Collection
    {
        $query = Torrent::query()->select(['info_hash', 'times_completed', 'seeders', 'leechers']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->where(function ($q) use ($infoHashHexes) {
                foreach ($infoHashHexes as $hex) {
                    $q->orWhereRaw("info_hash = decode(?, 'hex')", [$hex]);
                }
            });
        } else {
            $query->whereIn('info_hash', $infoHashBinaries);
        }

        return $query->get();
    }

    /**
     * Announce-path torrent write (seeder/leecher counters, last_action).
     *
     * @param  array<string, mixed>  $fields
     */
    public function updateById(int $id, array $fields): int
    {
        return Torrent::query()->where('id', $id)->update($fields);
    }
}
