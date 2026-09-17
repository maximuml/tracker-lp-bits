<?php

declare(strict_types=1);

namespace App\Repositories\Topten;

use Illuminate\Support\Facades\DB;

/**
 * Top-ten torrent leaderboard sections — extracted from ToptenRepository to keep both
 * classes under the 400-line ratchet.
 */
final class TorrentSections extends SectionQueries
{
    /**
     * @return list<array<string, mixed>>
     */
    public function build(int $limit, ?string $subtype): array
    {
        $base = DB::table('torrents as t')
            ->leftJoin('peers as p', 't.id', '=', 'p.torrent')
            ->selectRaw('t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data')
            ->where('p.seeder', 0)
            ->groupBy('t.id');

        $sections = [];

        if ($limit === 10 || $subtype === 'act') {
            $sections[] = [
                'view' => 'torrents',
                'data' => $this->toArray((clone $base)->orderByRaw('seeders + leechers DESC, seeders DESC, added ASC')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_most_active_torrents')),
                'limits' => [25, 50],
                'subtype' => 'act',
            ];
        }

        if ($limit === 10 || $subtype === 'sna') {
            $sections[] = [
                'view' => 'torrents',
                'data' => $this->toArray((clone $base)->orderBy('times_completed', 'desc')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_most_snatched_torrents')),
                'limits' => [25, 50],
                'subtype' => 'sna',
            ];
        }

        if ($limit === 10 || $subtype === 'mdt') {
            $sections[] = [
                'view' => 'torrents',
                'data' => $this->toArray((clone $base)->where('times_completed', '>', 0)->orderBy('data', 'desc')->orderBy('added', 'asc')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_most_data_transferred_torrents')),
                'limits' => [25, 50],
                'subtype' => 'mdt',
            ];
        }

        if ($limit === 10 || $subtype === 'bse') {
            $sections[] = [
                'view' => 'torrents',
                'data' => $this->toArray((clone $base)->where('seeders', '>=', 5)->orderByRaw('seeders / leechers DESC, seeders DESC, added ASC')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_best_seeded_torrents'), __('legacy/topten.text_best_seeded_torrents_note')),
                'limits' => [25, 50],
                'subtype' => 'bse',
            ];
        }

        if ($limit === 10 || $subtype === 'wse') {
            $sections[] = [
                'view' => 'torrents',
                'data' => $this->toArray(
                    DB::table('torrents as t')
                        ->selectRaw('t.*, (t.size * t.times_completed) AS data')
                        ->where('leechers', '>', 0)
                        ->where('times_completed', '>', 0)
                        ->orderByRaw('seeders / leechers ASC, leechers DESC')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_worst_seeded_torrents'), __('legacy/topten.text_worst_seeded_torrents_note')),
                'limits' => [25, 50],
                'subtype' => 'wse',
            ];
        }

        return $sections;
    }
}
