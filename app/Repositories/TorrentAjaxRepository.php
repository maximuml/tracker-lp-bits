<?php

namespace App\Repositories;

use App\Models\Torrent;
use App\Models\User;
use App\Support\Pagination;
use App\Support\SupportContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Meilisearch\Exceptions\ApiException;
use Nexus\Database\NexusDB;

final class TorrentAjaxRepository
{
    /**
     * @return Collection<int, \stdClass>
     */
    public static function fileList(int $torrentId): Collection
    {
        return NexusDB::table('files')
            ->where('torrent', $torrentId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public static function snatchList(int $torrentId): array
    {
        $torrentName = Torrent::query()->where('id', $torrentId)->value('name') ?? '';
        $count = NexusDB::table('snatched')
            ->where('finished', 'yes')
            ->where('torrentid', $torrentId)
            ->count();

        $perPage = 25;
        $scriptName = SupportContext::getServerValue('SCRIPT_NAME');
        $href = is_string($scriptName) ? $scriptName . '?id=' . $torrentId . '&' : '?id=' . $torrentId . '&';
        $pager = Pagination::pager($perPage, $count, $href);

        $offset = (int) $pager[3];
        $rpp = (int) $pager[4];

        $snatchedRows = NexusDB::table('snatched')
            ->where('finished', 'yes')
            ->where('torrentid', $torrentId)
            ->orderByDesc('completedat')
            ->offset($offset)
            ->limit($rpp)
            ->get();

        return [
            'id' => $torrentId,
            'torrentName' => $torrentName,
            'count' => $count,
            'pagertop' => (string) $pager[0],
            'pagerbottom' => (string) $pager[1],
            'snatchedRows' => $snatchedRows,
        ];
    }

    /**
     * @return list{string, list<string>, list<int>}
     */
    public static function searchSuggest(string $searchstr): array
    {
        $result = [$searchstr, [], []];

        if ($searchstr === '') {
            return $result;
        }

        $cacheKey = 'searchsuggest_' . md5($searchstr);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) === 3) {
            return $cached;
        }

        $rows = NexusDB::table('suggest')
            ->selectRaw('keywords AS suggest, COUNT(*) AS count')
            ->where('keywords', 'like', $searchstr . '%')
            ->groupBy('keywords')
            ->orderByDesc('count')
            ->orderByDesc('keywords')
            ->limit(10)
            ->get();

        foreach ($rows as $row) {
            $result[1][] = (string) $row['suggest'];
            $result[2][] = (int) $row['count'];
        }

        Cache::put($cacheKey, $result, now()->addMinutes(10));

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function autocompleteTorrents(string $query, ?User $user): array
    {
        if ($query === '' || $user === null) {
            return ['torrents' => []];
        }

        try {
            $torrents = (new MeiliSearchRepository())->autocomplete($query, 10, $user);
        } catch (ApiException) {
            $torrents = [];
        }

        return ['torrents' => $torrents];
    }
}
