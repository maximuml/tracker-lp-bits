<?php

namespace App\Repositories;

use App\Enums\Permission\PermissionEnum;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Pagination;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Meilisearch\Exceptions\ApiException;
use Nexus\Database\NexusDB;

class SearchPageRepository
{
    /**
     * @return array<string, mixed>
     */
    public static function dataForSearch(Request $request, User $currentUser): array
    {
        $searchParams = $request->all();
        $searchRaw = is_scalar($searchParams['search'] ?? '') ? trim((string) ($searchParams['search'] ?? '')) : '';
        $search = str_replace('.', ' ', $searchRaw);
        $searchArea = is_scalar($searchParams['search_area'] ?? '') ? (int) ($searchParams['search_area'] ?? MeiliSearchRepository::SEARCH_AREA_TITLE) : MeiliSearchRepository::SEARCH_AREA_TITLE;
        if (! in_array((string) $searchArea, [MeiliSearchRepository::SEARCH_AREA_TITLE, MeiliSearchRepository::SEARCH_AREA_DESC, MeiliSearchRepository::SEARCH_AREA_OWNER], true)) {
            $searchArea = (int) MeiliSearchRepository::SEARCH_AREA_TITLE;
        }

        $torrentsperpage = (int) ($currentUser->torrentsperpage ?: 0);
        if ($torrentsperpage <= 0) {
            $torrentsperpage = (int) (\App\Support\SupportContext::getGlobal('torrentsperpage_main', 50) ?: 50);
        }

        $approvalStatus = null;
        if (! SiteConfig::current()->torrent->approvalStatusNoneVisible() && ! Permissions::userCan(PermissionEnum::TORRENT_APPROVAL->value, false, $currentUser->id)) {
            $approvalStatus = Torrent::APPROVAL_STATUS_ALLOW;
        }

        $banned = null;
        if (! Permissions::userCan(PermissionEnum::TORRENT_VIEW_BANNED->value, false, $currentUser->id)) {
            $banned = 'no';
        }

        $modeArr = [SearchBox::getBrowseMode()];

        $shouldUseMeili = SiteConfig::current()->meiliSearch->enabled() && $searchRaw !== '';
        $meiliResult = ['total' => 0, 'list' => []];

        if ($shouldUseMeili) {
            $meiliParams = \App\Support\SupportContext::allQuery();
            $meiliParams['search'] = $searchRaw;
            $meiliParams['search_area'] = $searchArea;
            $meiliParams['mode'] = $modeArr;
            $meiliParams['incldead'] = 0;
            if ($approvalStatus !== null) {
                $meiliParams['approval_status'] = $approvalStatus;
            }
            if ($banned !== null) {
                $meiliParams['banned'] = $banned;
            }

            try {
                $searchRep = new MeiliSearchRepository();
                $meiliResult = $searchRep->search($meiliParams, $currentUser->id);
            } catch (\Throwable $e) {
                \App\Support\Logger::writeWithContext('MeiliSearch search failed, falling back to SQL: ' . $e->getMessage(), 'error', false);
                $shouldUseMeili = false;
            }
        }

        $column = 'id';
        $ascdesc = 'desc';
        $pagerSortParam = '';
        if (isset($searchParams['sort']) && $searchParams['sort'] && isset($searchParams['type']) && $searchParams['type']) {
            $column = match ((int) $searchParams['sort']) {
                1 => 'name',
                2 => 'numfiles',
                3 => 'comments',
                4 => 'added',
                5 => 'size',
                6 => 'times_completed',
                7 => 'seeders',
                8 => 'leechers',
                9 => 'owner',
                default => 'id',
            };

            $ascdesc = ((string) $searchParams['type'] === 'asc') ? 'ASC' : 'DESC';
            $linkascdesc = ((string) $searchParams['type'] === 'asc') ? 'asc' : 'desc';
            $pagerSortParam = 'sort=' . intval($searchParams['sort']) . '&type=' . $linkascdesc . '&';
        }

        $addparam = '?search=' . urlencode($searchRaw) . '&search_area=' . $searchArea . '&' . $pagerSortParam;

        if ($shouldUseMeili) {
            $count = (int) ($meiliResult['total'] ?? 0);
            $rows = (array) ($meiliResult['list'] ?? []);
            $pager = Pagination::pager($torrentsperpage, $count, $addparam);

            return self::formatResult($searchRaw, $searchArea, $count, $rows, $pager[0] ?? '', $pager[1] ?? '', $torrentsperpage);
        }

        $count = 0;
        $rows = [];
        if ($search !== '') {
            $torrentQuery = self::buildFallbackQuery($search, $searchArea, $modeArr, $approvalStatus, $banned, $currentUser->id);
            $count = (int) (clone $torrentQuery)->count();
            $pager = Pagination::pager($torrentsperpage, $count, $addparam);
            $offset = (int) ($pager[3] ?? 0);
            $rpp = (int) ($pager[4] ?? $torrentsperpage);
            $page = (int) ($pager[5] ?? 0);

            $fields = array_merge(Torrent::getFieldsForList(true), ['categories.mode as search_box_id']);
            $rows = $torrentQuery
                ->select($fields)
                ->orderBy("torrents.{$column}", $ascdesc)
                ->offset($offset)
                ->limit($rpp)
                ->get()
                ->toArray();
        } else {
            $pager = Pagination::pager($torrentsperpage, 0, $addparam);
        }

        return self::formatResult($searchRaw, $searchArea, $count, $rows, $pager[0] ?? '', $pager[1] ?? '', $torrentsperpage);
    }

    /**
     * @param  array<int, mixed>  $modeArr
     * @param  int|null  $approvalStatus
     * @param  string|null  $banned
     * @return \Illuminate\Database\Query\Builder
     */
    private static function buildFallbackQuery(string $search, int $searchArea, array $modeArr, ?int $approvalStatus, ?string $banned, int $currentUserId): \Illuminate\Database\Query\Builder
    {
        $tableTorrent = 'torrents';
        $tableCategory = 'categories';
        $tableUser = 'users';

        $searchArr = preg_split('/[\s]+/', $search, 10, PREG_SPLIT_NO_EMPTY) ?: [];

        $query = NexusDB::table($tableTorrent)
            ->join($tableCategory, "{$tableTorrent}.category", '=', "{$tableCategory}.id")
            ->whereIn("{$tableCategory}.mode", $modeArr);

        if ($searchArea == (int) MeiliSearchRepository::SEARCH_AREA_TITLE) {
            foreach ($searchArr as $queryString) {
                $q = '%' . addcslashes($queryString, '%_\\') . '%';
                $query->where("{$tableTorrent}.name", 'like', $q);
            }
        } elseif ($searchArea == (int) MeiliSearchRepository::SEARCH_AREA_DESC) {
            foreach ($searchArr as $queryString) {
                $q = '%' . addcslashes($queryString, '%_\\') . '%';
                $query->where("{$tableTorrent}.descr", 'like', $q);
            }
        } elseif ($searchArea == (int) MeiliSearchRepository::SEARCH_AREA_OWNER) {
            $query->join($tableUser, "{$tableTorrent}.owner", '=', "{$tableUser}.id");
            foreach ($searchArr as $queryString) {
                $q = '%' . addcslashes($queryString, '%_\\') . '%';
                $query->where("{$tableUser}.username", 'like', $q);
            }
        } else {
            foreach ($searchArr as $queryString) {
                $q = '%' . addcslashes($queryString, '%_\\') . '%';
                $query->where("{$tableTorrent}.name", 'like', $q);
            }
        }

        if ($approvalStatus !== null) {
            $query->where("{$tableTorrent}.approval_status", $approvalStatus);
        }
        if ($banned !== null) {
            $query->where("{$tableTorrent}.banned", $banned);
        }

        return $query;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private static function formatResult(string $searchRaw, int $searchArea, int $count, array $rows, string $pagertop, string $pagerbottom, int $torrentsperpage): array
    {
        return [
            'search' => $searchRaw,
            'searchArea' => $searchArea,
            'count' => $count,
            'rows' => $rows,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'torrentsperpage' => $torrentsperpage,
            'searchstr_ori' => htmlspecialchars($searchRaw),
            'hasResults' => $searchRaw !== '' && $count > 0,
        ];
    }
}
