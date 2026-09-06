<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\TorrentApprovalStatus;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Env;
use App\Support\Json;
use App\Support\Logger;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

class MeiliSearchService
{
    public const SEARCH_AREA_TITLE = '0';

    public const SEARCH_AREA_DESC = '1';

    public const SEARCH_AREA_OWNER = '3';

    /** @var array<int|string, mixed> */
    private static array $searchAreas = [
        self::SEARCH_AREA_TITLE => ['text' => 'title'],
        self::SEARCH_AREA_DESC => ['text' => 'desc'],
        self::SEARCH_AREA_OWNER => ['text' => 'owner'],
    ];

    /** @var array<int|string, mixed> */
    private static array $sortFieldMaps = [
        '1' => 'name',
        '3' => 'comments',
        '4' => 'added',
        '5' => 'size',
        '6' => 'times_completed',
        '7' => 'seeders',
        '8' => 'leechers',
        '9' => 'owner',
    ];

    public function __construct(
        private MeiliSearchFilterService $filterService,
    ) {}

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $user
     * @return mixed
     */
    public function search(array $params, $user)
    {
        $results = ['total' => 0, 'list' => []];
        if (! SiteConfig::current()->meiliSearch->enabled()) {
            Logger::writeWithContext((string) 'Not enabled!', (string) 'info', (bool) false);

            return $results;
        }
        $filters = [];
        // think about search area
        $searchArea = $this->getSearchArea($params);
        $searchQuery = is_scalar($params['search'] ?? '') ? (string) ($params['search'] ?? '') : '';
        if ($searchArea == self::SEARCH_AREA_OWNER) {
            // Use LIKE to match partial usernames, consistent with the SQL
            // path in TorrentSearchRepository which does username LIKE %term%.
            $searchOwnerIds = User::query()
                ->where('username', 'LIKE', '%'.trim($searchQuery).'%')
                ->pluck('id')
                ->all();
            if (empty($searchOwnerIds)) {
                return $results;
            }
            $filters[] = 'owner IN ['.implode(',', $searchOwnerIds).']';
        }
        if (! ($user instanceof User)) {
            $user = User::query()->findOrFail(intval($user));
        }
        $filters = array_merge($filters, $this->filterService->getFilters($params, $user));
        $query = $this->getQuery($params);
        $page = isset($params['page']) && is_numeric($params['page']) ? (int) $params['page'] : 0;
        if ($page < 0) {
            $page = 0;
        }
        $perPage = $this->getPerPage($user);

        $options = [
            'filter' => implode(' AND ', $filters),
            'attributesToRetrieve' => $this->getAttributesToRetrieve(),
        ];
        $sort = $this->getSort($params);
        if (! empty($sort)) {
            $options['sort'] = $sort;
        }

        // Clamp page to valid range: MeiliSearch returns empty for pages
        // beyond the last, but we want the caller to get the last page's
        // data so the pager and results are consistent.
        // First do a count-only query to determine max page.
        $countOptions = $options;
        $countOptions['attributesToRetrieve'] = ['id'];
        $countPaginator = Torrent::search($query)->options($countOptions)->paginate(1, 'page', 1);
        $total = $countPaginator->total();
        $maxPage = $perPage > 0 ? (int) ceil($total / $perPage) - 1 : 0;
        if ($maxPage < 0) {
            $maxPage = 0;
        }
        if ($page > $maxPage) {
            $page = $maxPage;
        }

        $paginator = Torrent::search($query)->options($options)->paginate($perPage, 'page', $page + 1);
        $torrents = new Collection($paginator->items());
        $total = $paginator->total();
        Logger::writeWithContext((string) ('search params: '.Json::encode($options).', page: '.($page + 1).", perPage: {$perPage}, total: {$total}"), (string) 'info', (bool) false);
        if ($total > 0) {
            $torrents->load('basic_category');
            $list = [];
            foreach ($torrents as $torrent) {
                if (! $torrent instanceof Torrent) {
                    throw new LogicException('Expected torrent to be a Torrent instance.');
                }
                $searchBoxId = $torrent->basic_category->mode;
                $arr = $torrent->toArray();
                $arr['search_box_id'] = $searchBoxId;
                $list[] = $arr;
            }
            $results['list'] = $list;
        }
        $results['total'] = $total;

        return $results;
    }

    /**
     * Fast autocomplete over MeiliSearch index for search-as-you-type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function autocomplete(string $query, int $limit, User $user): array
    {
        if (! SiteConfig::current()->meiliSearch->enabled()) {
            return [];
        }

        $params = ['mode' => SearchBox::listAuthorizedSectionId()];
        if (! Permission::canViewBannedTorrent()) {
            $params['banned'] = 0;
        }
        if (! SiteConfig::current()->torrent->approvalStatusNoneVisible() && ! Permission::canApproveTorrent()) {
            $params['approval_status'] = TorrentApprovalStatus::ALLOW->value;
        }
        $filters = $this->filterService->getFilters($params, $user);

        $options = [
            'limit' => $limit,
            'attributesToRetrieve' => ['id', 'name'],
            'filter' => implode(' AND ', $filters),
        ];
        $result = Torrent::search($query)->options($options)->take($limit)->raw();

        $torrents = [];
        foreach ($result['hits'] ?? [] as $hit) {
            $torrents[] = [
                'id' => (int) $hit['id'],
                'name' => (string) $hit['name'],
            ];
        }

        return $torrents;
    }

    /** @return  array<int|string, mixed> */
    public function getAttributesToRetrieve(): array
    {
        if (Env::get('APP_ENV', null) == 'production') {
            return ['id'];
        }

        return ['*'];
    }

    /** @return  array<int|string, mixed> */
    public function getSearchableAttributes(): array
    {
        $attributes = ['name', 'url'];
        if (SiteConfig::current()->meiliSearch->searchDescription()) {
            $attributes[] = 'descr';
        }

        return $attributes;
    }

    /** @param  array<int|string, mixed>  $params */
    private function getQuery(array $params): string
    {
        $q = trim(is_scalar($params['search'] ?? '') ? (string) ($params['search'] ?? '') : '');
        $searchMode = SearchBox::getDefaultSearchMode();
        if (isset($params['search_mode']) && is_scalar($params['search_mode']) && isset(SearchBox::$searchModes[(string) $params['search_mode']])) {
            $searchMode = (string) $params['search_mode'];
        }
        Logger::writeWithContext((string) ('search mode: '.SearchBox::$searchModes[$searchMode]['text']), (string) 'info', (bool) false);
        if ($searchMode == SearchBox::SEARCH_MODE_AND) {
            return $q;
        }

        return sprintf('"%s"', $q);
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    private function getSearchArea(array $params)
    {
        $searchArea = is_scalar($params['search_area'] ?? '') ? (string) ($params['search_area'] ?? '') : '';
        if (isset(self::$searchAreas[$searchArea])) {
            return $searchArea;
        }

        return self::SEARCH_AREA_TITLE;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    private function getSort(array $params): array
    {
        if (! isset($params['sort']) || ! isset($params['type'])) {
            // Use default
            return [];
        }
        if (isset($params['sort'], self::$sortFieldMaps[$params['sort']]) && isset($params['type']) && in_array($params['type'], ['asc', 'desc'])) {
            $sortField = self::$sortFieldMaps[$params['sort']];
        } else {
            $sortField = 'id';
        }
        if (isset($params['type']) && in_array($params['type'], ['desc', 'asc'])) {
            $sortType = $params['type'];
        } else {
            $sortType = 'desc';
        }
        // when searching, ignore promotion

        return ["$sortField:$sortType"];
    }

    /**
     * @return mixed
     */
    private function getPerPage(User $user)
    {
        if ($user->torrentsperpage) {
            $size = $user->torrentsperpage;
        } elseif (($sizeFromConfig = SiteConfig::current()->main->torrentsPerPage()) > 0) {
            $size = $sizeFromConfig;
        } else {
            $size = 100;
        }

        return intval(min($size, 200));
    }
}
