<?php

namespace App\Repositories;

use App\Enums\Permission\PermissionEnum;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Database;
use App\Support\Globals;
use App\Support\Hooks;
use App\Support\Input;
use App\Support\Logger;
use App\Support\Network;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\UserDisplay;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Meilisearch\Exceptions\ApiException;

final class TorrentAjaxRepository
{
    /**
     * @return Collection<int, \stdClass>
     */
    public static function fileList(int $torrentId): Collection
    {
        return DB::table('files')
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
        $count = DB::table('snatched')
            ->where('finished', 'yes')
            ->where('torrentid', $torrentId)
            ->count();

        $perPage = 25;
        $scriptName = Input::serverValue('SCRIPT_NAME');
        $href = $scriptName !== '' ? $scriptName.'?id='.$torrentId.'&' : '?id='.$torrentId.'&';
        $pager = Pagination::pager($perPage, $count, $href);

        $offset = (int) $pager[3];
        $rpp = (int) $pager[4];

        $snatchedRows = DB::table('snatched')
            ->where('finished', 'yes')
            ->where('torrentid', $torrentId)
            ->orderByDesc('completedat')
            ->offset($offset)
            ->limit($rpp)
            ->get();

        $snatchUserIds = $snatchedRows->pluck('userid')->filter()->unique()->map('intval')->toArray();
        if ($snatchUserIds !== []) {
            UserDisplay::preload($snatchUserIds);
        }

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

        $cacheKey = 'searchsuggest_'.md5($searchstr);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && count($cached) === 3) {
            return [
                (string) ($cached[0] ?? $searchstr),
                array_values((array) ($cached[1] ?? [])),
                array_values(array_map('intval', (array) ($cached[2] ?? []))),
            ];
        }

        $rows = DB::table('suggest')
            ->selectRaw('keywords AS suggest, COUNT(*) AS count')
            ->where('keywords', 'like', $searchstr.'%')
            ->groupBy('keywords')
            ->orderByDesc('count')
            ->orderByDesc('keywords')
            ->limit(10)
            ->get();

        foreach ($rows as $row) {
            $result[1][] = (string) $row->suggest;
            $result[2][] = (int) $row->count;
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
            $torrents = (new MeiliSearchRepository)->autocomplete($query, 10, $user);
        } catch (ApiException) {
            $torrents = [];
        }

        return ['torrents' => $torrents];
    }

    /**
     * @return array<string, mixed>
     */
    public static function peerList(int $torrentId, ?User $currentUser = null): array
    {
        $torrent = Torrent::query()->findOrFail($torrentId, ['id', 'owner', 'size', 'anonymous', 'seeders', 'leechers']);
        $torrentArr = $torrent->toArray();

        $seedersAndLeechers = Hooks::applyFilter('torrent_seeder_leecher_list', [], $torrentId);
        if (is_array($seedersAndLeechers) && isset($seedersAndLeechers['seeders'], $seedersAndLeechers['leechers'])) {
            $seeders = (array) $seedersAndLeechers['seeders'];
            $leechers = (array) $seedersAndLeechers['leechers'];
            Logger::writeWithContext('SEEDER_LEECHER_FROM_FILTER: torrent_seeder_leecher_list', 'info', false);
        } else {
            $startedField = Database::unixTimestampField('started');
            $lastActionField = Database::unixTimestampField('last_action');
            $peerRows = DB::table('peers')
                ->where('torrent', $torrentId)
                ->selectRaw("id, seeder, finishedat, downloadoffset, uploadoffset, ip, ipv4, ipv6, port, uploaded, downloaded, to_go, {$startedField} AS st, connectable, agent, peer_id, {$lastActionField} AS la, userid")
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            $seeders = [];
            $leechers = [];
            foreach ($peerRows as $subrow) {
                if ($subrow['seeder'] === 'yes') {
                    $seeders[] = $subrow;
                } else {
                    $leechers[] = $subrow;
                }
            }
        }

        usort($seeders, fn ($a, $b) => $b['uploaded'] <=> $a['uploaded']);
        usort($leechers, fn ($a, $b) => $a['to_go'] <=> $b['to_go']);

        $seedersCount = count($seeders);
        $leechersCount = count($leechers);
        if (($torrentArr['seeders'] ?? 0) != $seedersCount || ($torrentArr['leechers'] ?? 0) != $leechersCount) {
            $update = ['seeders' => $seedersCount, 'leechers' => $leechersCount];
            $torrent->update($update);
            Logger::writeWithContext("[UPDATE_TORRENT_SEEDERS_LEECHERS], torrent: {$torrentId}, original: ".$torrent->toJson().', update: '.json_encode($update), 'info', false);
            $torrentArr = array_merge($torrentArr, $update);
        }

        $allPeers = array_merge($seeders, $leechers);
        $userIds = array_unique(array_filter(array_column($allPeers, 'userid')));

        if ($userIds !== []) {
            UserDisplay::preload($userIds);
        }

        $privacyData = [];
        foreach ($userIds as $uid) {
            $row = UserDisplay::row((int) $uid);
            $privacyData[$uid] = is_array($row) ? (string) ($row['privacy'] ?? '') : '';
        }

        $seedBoxRep = new SeedBoxRepository;
        $isSeedBoxMap = [];
        $caseWhens = [];
        foreach ($allPeers as $peer) {
            $isSeedBox = false;
            foreach (array_filter([$peer['ipv4'] ?? '', $peer['ipv6'] ?? '']) as $ip) {
                if ($seedBoxRep->renderIcon($ip, (int) $peer['userid']) !== '') {
                    $isSeedBox = true;
                    break;
                }
            }
            $isSeedBoxMap[$peer['id']] = $isSeedBox;
            $caseWhens[$peer['id']] = sprintf('when %s then %s', $peer['id'], intval($isSeedBox));
        }

        $peerIpInfo = [];
        $usernameSeedBoxIconMap = [];
        $locationMap = [];
        foreach (array_merge($seeders, $leechers) as $peer) {
            $peerId = (int) $peer['id'];
            $userId = (int) $peer['userid'];
            $ips = array_filter([$peer['ipv4'] ?? '', $peer['ipv6'] ?? '']);
            $usernameSeedBoxIconMap[$peerId] = $seedBoxRep->renderIcon($ips, $userId);
            $peerIpInfo[$peerId] = [];
            foreach ($ips as $ip) {
                if (! isset($locationMap[$ip])) {
                    $locationMap[$ip] = Network::ipLocationWithContext($ip);
                }
                [$locPub, $locMod] = $locationMap[$ip];
                $peerIpInfo[$peerId][] = [
                    'ip' => $ip,
                    'public' => $locPub,
                    'mod' => $locMod,
                    'seedBoxIcon' => $seedBoxRep->renderIcon($ip, $userId),
                ];
            }
        }

        $usernameHtmlMap = [];
        foreach ($userIds as $uid) {
            $usernameHtmlMap[(int) $uid] = UserDisplay::username((int) $uid, false, true, true, true);
        }

        if (! empty($caseWhens) && SiteConfig::current()->seedBox->enabled()) {
            $caseSql = sprintf('case id %s end', implode(' ', array_values($caseWhens)));
            Logger::writeWithContext("[IS_SEED_BOX], caseSql: {$caseSql}, ids: ".implode(',', array_keys($caseWhens)), 'info', false);
            DB::table('peers')->whereIn('id', array_keys($caseWhens))->update(['is_seed_box' => DB::raw($caseSql)]);
        }

        $enablelocationTweak = app(Globals::class)->get('enablelocation_tweak');
        $showLocationColumn = $enablelocationTweak === 'yes' || ($currentUser !== null && Permissions::userCan(PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO->value, false, $currentUser->id));

        return [
            'torrent' => $torrentArr,
            'seeders' => $seeders,
            'leechers' => $leechers,
            'privacyData' => $privacyData,
            'isSeedBoxMap' => $isSeedBoxMap,
            'showLocationColumn' => $showLocationColumn,
            'peerIpInfo' => $peerIpInfo,
            'usernameSeedBoxIconMap' => $usernameSeedBoxIconMap,
            'usernameHtmlMap' => $usernameHtmlMap,
            'enablelocationTweak' => $enablelocationTweak,
            'currentUser' => $currentUser,
            'CURUSER' => $currentUser !== null ? $currentUser->toArray() : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function userTorrentList(int $targetUserId, string $type, int $page, ?User $currentUser = null): array
    {
        if (! in_array($type, ['uploaded', 'seeding', 'leeching', 'completed', 'incomplete'], true)) {
            return [
                'id' => $targetUserId,
                'type' => $type,
                'rows' => [],
                'count' => 0,
                'total_size' => 0,
                'pagertop' => '',
                'pagerbottom' => '',
                'torrentlist' => '',
                'seedTimeAndUploaded' => collect(),
            ];
        }

        $pageSize = 100;
        $href = "getusertorrentlistajax.php?userid={$targetUserId}&type={$type}&";

        $query = self::buildUserTorrentQuery($targetUserId, $type, $currentUser);
        $count = (int) (clone $query)->count();
        $totalSize = (int) (clone $query)->sum('torrents.size');

        $pager = Pagination::pager($pageSize, $count, $href);
        $offset = (int) $pager[3];
        $rpp = (int) $pager[4];

        $rows = $query->offset($offset)->limit($rpp)->get()->map(fn ($r) => (array) $r)->all();

        $seedTimeAndUploaded = collect();
        if ($type === 'uploaded' && ! empty($rows)) {
            $torrentIds = array_column($rows, 'torrent');
            $seedTimeAndUploaded = Snatch::query()
                ->where('userid', $targetUserId)
                ->whereIn('torrentid', $torrentIds)
                ->select(['seedtime', 'uploaded', 'torrentid'])
                ->get()
                ->keyBy('torrentid');
        }

        return [
            'id' => $targetUserId,
            'type' => $type,
            'rows' => $rows,
            'count' => $count,
            'total_size' => $totalSize,
            'pagertop' => (string) $pager[0],
            'pagerbottom' => (string) $pager[1],
            'torrentRep' => new TorrentRepository,
            'seedBoxRep' => new SeedBoxRepository,
            'seedTimeAndUploaded' => $seedTimeAndUploaded,
        ];
    }

    private static function buildUserTorrentQuery(int $targetUserId, string $type, ?User $currentUser = null): Builder
    {
        switch ($type) {
            case 'uploaded':
                $query = DB::table('torrents')
                    ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
                    ->where('torrents.owner', $targetUserId)
                    ->select([
                        'torrents.id as torrent', 'torrents.name as torrentname', 'torrents.seeders', 'torrents.leechers',
                        'torrents.anonymous', 'torrents.banned', 'torrents.approval_status', 'categories.name as catname',
                        'categories.image', 'torrents.category', 'torrents.sp_state', 'torrents.size', 'torrents.hr',
                        'torrents.added', 'torrents.owner as userid', 'categories.mode as search_box_id',
                    ])
                    ->orderByDesc('torrents.id');

                if ($currentUser === null || ($currentUser->id !== $targetUserId && ! Permissions::userCan(PermissionEnum::VIEW_ANONYMOUS->value, false, $currentUser->id))) {
                    $query->where('torrents.anonymous', 'no');
                }

                return $query;

            case 'seeding':
                return DB::table('peers')
                    ->leftJoin('torrents', 'peers.torrent', '=', 'torrents.id')
                    ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
                    ->leftJoin('snatched', 'torrents.id', '=', 'snatched.torrentid')
                    ->where('peers.userid', $targetUserId)
                    ->where('snatched.userid', $targetUserId)
                    ->where('peers.seeder', 'yes')
                    ->select([
                        'peers.torrent', 'torrents.added', 'snatched.uploaded', 'snatched.downloaded', 'snatched.seedtime',
                        'torrents.name as torrentname', 'torrents.sp_state', 'torrents.banned', 'torrents.approval_status',
                        'categories.name as catname', 'torrents.size', 'torrents.hr', 'categories.image',
                        'torrents.category', 'torrents.seeders', 'torrents.leechers', 'snatched.userid',
                        'categories.mode as search_box_id', 'peers.peer_id', 'peers.agent', 'peers.port', 'peers.ipv4', 'peers.ipv6',
                    ])
                    ->orderByDesc('peers.id');

            case 'leeching':
                return DB::table('peers')
                    ->leftJoin('torrents', 'peers.torrent', '=', 'torrents.id')
                    ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
                    ->leftJoin('snatched', 'torrents.id', '=', 'snatched.torrentid')
                    ->where('peers.userid', $targetUserId)
                    ->where('snatched.userid', $targetUserId)
                    ->where('peers.seeder', 'no')
                    ->select([
                        'peers.torrent', 'torrents.added', 'snatched.uploaded', 'snatched.downloaded', 'snatched.seedtime',
                        'torrents.name as torrentname', 'torrents.sp_state', 'torrents.banned', 'torrents.approval_status',
                        'categories.name as catname', 'torrents.size', 'torrents.hr', 'categories.image',
                        'torrents.category', 'torrents.seeders', 'torrents.leechers', 'snatched.userid',
                        'categories.mode as search_box_id', 'peers.peer_id', 'peers.agent', 'peers.port', 'peers.ipv4', 'peers.ipv6',
                    ])
                    ->orderByDesc('peers.id');

            case 'completed':
                return DB::table('torrents')
                    ->leftJoin('snatched', 'torrents.id', '=', 'snatched.torrentid')
                    ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
                    ->where('snatched.finished', 'yes')
                    ->where('snatched.userid', $targetUserId)
                    ->where('torrents.owner', '!=', $targetUserId)
                    ->select([
                        'torrents.id as torrent', 'torrents.name as torrentname', 'categories.name as catname',
                        'torrents.banned', 'torrents.approval_status', 'categories.image', 'torrents.category',
                        'torrents.sp_state', 'torrents.size', 'torrents.hr', 'torrents.added', 'snatched.uploaded',
                        'snatched.seedtime', 'snatched.leechtime', 'snatched.completedat', 'snatched.userid',
                        'categories.mode as search_box_id',
                    ])
                    ->orderByDesc('snatched.id');

            case 'incomplete':
                return DB::table('torrents')
                    ->leftJoin('snatched', 'torrents.id', '=', 'snatched.torrentid')
                    ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
                    ->where('snatched.finished', 'no')
                    ->where('snatched.userid', $targetUserId)
                    ->where('torrents.owner', '!=', $targetUserId)
                    ->select([
                        'torrents.id as torrent', 'torrents.name as torrentname', 'torrents.banned',
                        'torrents.approval_status', 'categories.name as catname', 'categories.image',
                        'torrents.category', 'torrents.sp_state', 'torrents.size', 'torrents.hr', 'torrents.added',
                        'snatched.uploaded', 'snatched.downloaded', 'snatched.leechtime', 'snatched.seedtime',
                        'snatched.userid', 'categories.mode as search_box_id',
                    ])
                    ->orderByDesc('snatched.id');
        }

        throw new \InvalidArgumentException("Unknown user torrent list type: {$type}");
    }
}
