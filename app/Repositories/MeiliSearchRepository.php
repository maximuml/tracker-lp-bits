<?php
namespace App\Repositories;

use App\Auth\Permission;
use App\Exceptions\NexusException;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Nexus\Database\NexusDB;

class MeiliSearchRepository extends BaseRepository
{
    /** @var  mixed */
    private static $client;

    const INDEX_NAME = 'torrents';

    const SEARCH_AREA_TITLE = '0';
    const SEARCH_AREA_DESC = '1';
    const SEARCH_AREA_OWNER = '3';

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $searchAreas = [
        self::SEARCH_AREA_TITLE => ['text' => 'title'],
        self::SEARCH_AREA_DESC => ['text' => 'desc'],
        self::SEARCH_AREA_OWNER => ['text' => 'owner'],
    ];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $queryFieldToTorrentFieldMaps = [
        'cat' => 'category',
        'source' => 'source',
        'medium' => 'medium',
        'codec' => 'codec',
        'audiocodec' => 'audiocodec',
        'standard' => 'standard',
        'processing' => 'processing',
    ];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $sortFieldMaps = [
        '1' => 'name',
//        '2' => 'numfiles',
        '3' => 'comments',
        '4' => 'added',
        '5' => 'size',
        '6' => 'times_completed',
        '7' => 'seeders',
        '8' => 'leechers',
        '9' => 'owner',
    ];


    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $filterableAttributes = [
        "id", "category", "source", "medium", "codec", "standard", "processing", "audiocodec", "owner",
        "sp_state", "visible", "banned", "approval_status", "size", "leechers", "seeders", "times_completed", "added",
    ];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $sortableAttributes = [
        "id", "name", "comments", "added", "size", "leechers", "seeders", "times_completed", "owner",
        "pos_state", "anonymous"
    ];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $intFields = [
        "id", "category", "source", "medium", "codec", "standard", "processing", "audiocodec", "owner",
        "sp_state", "approval_status", "size", "leechers", "seeders", "times_completed", "url", "comments",
    ];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $timestampFields = ['added'];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $yesOrNoFields = ['visible', 'anonymous', 'banned'];



    public function getClient(): Client
    {
        if (is_null(self::$client)) {
            $config = \App\Support\Config::get('nexus.meilisearch', null);
            $url = sprintf('%s://%s:%s', $config['scheme'], $config['host'], $config['port']);
            \App\Support\Logger::writeWithContext((string) ("get client with url: {$url}, master key: " . $config['master_key']), (string) 'info', (bool) false);
            self::$client = new Client($url, $config['master_key']);
        }
        return self::$client;
    }

    public static function isEnabled(): bool
    {
        return \App\Support\Config\SiteConfig::current()->meiliSearch->enabled();
    }

    /** @return  mixed */
    public function import()
    {
        if (!$this->isEnabled()) {
            return 0;
        }
        $client = $this->getClient();
        $stats = $client->stats();
        if (isset($stats['indexes'][self::INDEX_NAME])) {
            $doSwap = true;
            $indexName = self::INDEX_NAME . "_" . date('Ymd_His');
        } else {
            $doSwap = false;
            $indexName = self::INDEX_NAME;
        }
        \App\Support\Logger::writeWithContext((string) "indexName: {$indexName} will be created, doSwap: {$doSwap}", (string) 'info', (bool) false);
        $index = $this->createIndex($indexName);
        try {
            $total = $this->doImportFromDatabase(null, $index);
            if ($doSwap) {
                $swapResult = $client->swapIndexes([[self::INDEX_NAME, $indexName]]);
                $times = 0;
                while (true) {
                    if ($times == 3600) {
                        $msg = "total: $total, swap too long, times: $times, return false";
                        \App\Support\Logger::writeWithContext((string) $msg, (string) 'info', (bool) false);
                        throw new NexusException($msg);
                    }
                    sleep(1);
                    $task = $client->getTask($swapResult['taskUid']);
                    if ($task['status'] == 'succeeded') {
                        \App\Support\Logger::writeWithContext((string) "total: {$total}, swap success at times: {$times}", (string) 'info', (bool) false);
                        $client->deleteIndex($indexName);
                        return $total;
                    }
                    \App\Support\Logger::writeWithContext((string) "waiting swap success, times: {$times}", (string) 'info', (bool) false);
                    $times++;
                }
            }
            return $total;
        } catch (\Exception $exception) {
            $client->deleteIndex($indexName);
            throw $exception;
        }
    }

    /**
     * @param  mixed  $indexName
     * @return  mixed
     */
    private function createIndex($indexName)
    {
        $client = $this->getClient();
        $params = [
            'primaryKey' => 'id',
        ];
        $client->createIndex($indexName, $params);
        $index = $client->index($indexName);
        $settings = [
            "distinctAttribute" => "id",
            "displayedAttributes" => self::getRequiredFields(),
            "searchableAttributes" => self::getSearchableAttributes(),
            "filterableAttributes" => self::$filterableAttributes,
            "sortableAttributes" => self::$sortableAttributes,
            "rankingRules" => [
                "words",
                "sort",
                "typo",
                "proximity",
                "attribute",
                "exactness"
            ],
        ];
        $index->updateSettings($settings);

        return $index;

    }

    /** @return  array<int|string, mixed> */
    public static function getRequiredFields(): array
    {
        return array_values(array_unique(array_merge(
            self::$filterableAttributes, self::$sortableAttributes, self::getSearchableAttributes()
        )));
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $index
     * @return  mixed
     */
    public function doImportFromDatabase($id = null, $index = null)
    {
        if (!$this->isEnabled() && $index === null) {
            \App\Support\Logger::writeWithContext((string) "Not enabled!", (string) 'info', (bool) false);
            return false;
        }
        $page = 1;
        $size = 1000;
        $rebuild = $index instanceof Indexes;
        if (!$rebuild) {
            $index = $this->getIndex();
        }
        $total = 0;
        $tasks = [];
        $columns = DB::getSchemaBuilder()->getColumnListing('torrents');
        $fields = array_values(array_intersect($this->getRequiredFields(), $columns));
        while (true) {
            $query = Torrent::query()->select($fields)->orderBy('id')->forPage($page, $size);
            if ($id) {
                $query->whereIn("id", Arr::wrap($id));
            }
            $torrents = $query->get();
            $count = $torrents->count();
            $total += $count;
            if ($count == 0) {
                \App\Support\Logger::writeWithContext((string) "page: {$page} no data...", (string) 'info', (bool) false);
                break;
            }
            \App\Support\Logger::writeWithContext((string) sprintf('importing page: %s with id: %s, %s records...', $page, $id, $count), (string) 'info', (bool) false);
            $data = $torrents->map->toSearchableArray()->all();
            $result = $index->updateDocuments($data);
            if (is_array($result) && isset($result['taskUid'])) {
                $tasks[] = $result['taskUid'];
            }
            \App\Support\Logger::writeWithContext((string) sprintf('import page: %s with id: %s, %s records success.', $page, $id, $count), (string) 'info', (bool) false);
            $page++;
        }
        if ($rebuild && !empty($tasks)) {
            $this->getClient()->waitForTasks($tasks, 60000, 100);
        }
        return $total;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $user
     * @return  mixed
     */
    public function search(array $params, $user)
    {
        $results = ['total' => 0, 'list' => []];
        if (!$this->isEnabled()) {
            \App\Support\Logger::writeWithContext((string) "Not enabled!", (string) 'info', (bool) false);
            return $results;
        }
        $filters = [];
        //think about search area
        $searchArea = $this->getSearchArea($params);
        $searchQuery = is_scalar($params['search'] ?? '') ? (string) ($params['search'] ?? '') : '';
        if ($searchArea == self::SEARCH_AREA_OWNER) {
            $searchOwner = User::query()->where('username', trim($searchQuery))->first(['id']);
            if (!$searchOwner) {
                //No user match, no results
                return $results;
            } else {
                $filters[] = "owner = " . $searchOwner->id;
            }
        }
        if (!($user instanceof User)) {
            $user = User::query()->findOrFail(intval($user));
        }
        $filters = array_merge($filters, $this->getFilters($params, $user));
        $query = $this->getQuery($params);
        $page = isset($params['page']) && is_numeric($params['page']) ? (int) $params['page'] : 0;
        $perPage = $this->getPerPage($user);

        $options = [
            'filter' => implode(' AND ', $filters),
            'attributesToRetrieve' => $this->getAttributesToRetrieve(),
        ];
        $sort = $this->getSort($params);
        if (!empty($sort)) {
            $options['sort'] = $sort;
        }

        $paginator = Torrent::search($query)->options($options)->paginate($perPage, 'page', $page + 1);
        $torrents = new \Illuminate\Database\Eloquent\Collection($paginator->items());
        $total = $paginator->total();
        \App\Support\Logger::writeWithContext((string) ("search params: " . \App\Support\Json::encode($options) . ", page: " . ($page + 1) . ", perPage: {$perPage}, total: {$total}"), (string) 'info', (bool) false);
        if ($total > 0) {
            $torrents->load('basic_category');
            $list = [];
            foreach ($torrents as $torrent) {
                assert($torrent instanceof Torrent);
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
     * @param  string  $query
     * @param  int  $limit
     * @param  User  $user
     * @return  array<int, array<string, mixed>>
     */
    public function autocomplete(string $query, int $limit, User $user): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $params = ['mode' => SearchBox::listAuthorizedSectionId()];
        if (!Permission::canViewBannedTorrent()) {
            $params['banned'] = 'no';
        }
        if (!\App\Support\Config\SiteConfig::current()->torrent->approvalStatusNoneVisible() && !Permission::canApproveTorrent()) {
            $params['approval_status'] = Torrent::APPROVAL_STATUS_ALLOW;
        }
        $filters = $this->getFilters($params, $user);

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

    /**
     * @param  array<int|string, mixed>  $params
     * @param  \App\Models\User  $user
     * @return  array<int|string, mixed>
     */
    private function getFilters(array $params, User $user): array
    {
        $filters = [];
        $taxonomies = [];
        $categoryIdArr = [];
        //[cat401][cat404][sou1][med1][cod1][sta2][sta3][pro2][tea2][aud2][incldead=0][spstate=3][inclbookmarked=2]
        $userSetting = (string) $user->notifs;
        //cat401=1&source2=1&medium10=1&codec2=1&audiocodec2=1&standard3=1&processing2=1&incldead=2&spstate=1&inclbookmarked=0&approval_status=&size_begin=&size_end=&seeders_begin=&seeders_end=&leechers_begin=&leechers_end=&times_completed_begin=&times_completed_end=&added_begin=&added_end=&search=a+b&search_area=0&search_mode=2
        $queryString = http_build_query($params);
        //section
        if (!empty($params['mode'])) {
            $categoryIdArr = Category::query()->whereIn('mode', Arr::wrap($params['mode']))->pluck('id')->toArray();
        }
        foreach (self::$queryFieldToTorrentFieldMaps as $queryField => $torrentField) {
            if (isset($params[$queryField]) && $params[$queryField] !== '') {
                $taxonomies[$torrentField][] = $params[$queryField];
                \App\Support\Logger::writeWithContext((string) "{$torrentField} from params through {$queryField}: {$params[$queryField]}", (string) 'info', (bool) false);
            } elseif (preg_match_all("/{$queryField}(\d+)=/", $queryString, $matches)) {
                if (count($matches) == 2 && !empty($matches[1])) {
                    foreach ($matches[1] as $match) {
                        $taxonomies[$torrentField][] = $match;
                        \App\Support\Logger::writeWithContext((string) "{$torrentField} from params through {$queryField}: {$match}", (string) 'info', (bool) false);
                    }
                }
            } else {
                //get user setting
                $pattern = sprintf("/\[%s([\d]+)\]/", substr((string) $queryField, 0, 3));
                if (preg_match($pattern, $userSetting, $matches)) {
                    if (count($matches) == 2 && !empty($matches[1])) {
                        $match = $matches[1];
                        $taxonomies[$torrentField][] = $match;
                        \App\Support\Logger::writeWithContext((string) "{$torrentField} from user setting through {$queryField}: {$match}", (string) 'info', (bool) false);
                    }
                }
            }
        }
        if (empty($taxonomies['category']) && !empty($categoryIdArr)) {
            //Restricted to the category of the specified section
            $taxonomies['category'] = $categoryIdArr;
        }
        foreach ($taxonomies as $key => $values) {
            if (!empty($values)) {
                $filters[] = sprintf("%s IN [%s]", $key, implode(', ', array_map('intval', $values)));
            }
        }

        $includeDead = 1;
        if (isset($params['incldead'])) {
            $includeDead = (int)$params['incldead'];
        } elseif (preg_match("/\[incldead=(\d+)\]/", $userSetting, $matches)) {
            $includeDead = $matches[1];
        }
        if ($includeDead == 1) {
            //active torrent
            $filters[] = "visible = 1";
            \App\Support\Logger::writeWithContext((string) "visible = yes through incldead: {$includeDead}", (string) 'info', (bool) false);
        } elseif ($includeDead == 2) {
            //dead torrent
            $filters[] = "visible = 0";
            \App\Support\Logger::writeWithContext((string) "visible = no through incldead: {$includeDead}", (string) 'info', (bool) false);
        }

        $includeBookmarked = 0;
        if (isset($params['inclbookmarked'])) {
            $includeBookmarked = (int)$params['inclbookmarked'];
        } elseif (preg_match("/\[inclbookmarked=(\d+)\]/", $userSetting, $matches)) {
            $includeBookmarked = $matches[1];
        }
        if ($includeBookmarked > 0) {
            $userBookmarkedTorrentIdStr = Bookmark::query()->where('userid', $user->id)->pluck('torrentid')->implode(',');
            if ($includeBookmarked == 1) {
                //only bookmark
                $filters[] = "id IN [$userBookmarkedTorrentIdStr]";
                \App\Support\Logger::writeWithContext((string) "bookmark through inclbookmarked: {$includeBookmarked}", (string) 'info', (bool) false);
            } elseif ($includeBookmarked == 2) {
                //only not bookmark
                $filters[] = "id NOT IN [$userBookmarkedTorrentIdStr]";
                \App\Support\Logger::writeWithContext((string) "bookmark through inclbookmarked: {$includeBookmarked}", (string) 'info', (bool) false);
            }
        }

        $spState = 0;
        if (isset($params['spstate'])) {
            $spState = (int)$params['spstate'];
            \App\Support\Logger::writeWithContext((string) "spstate from params", (string) 'info', (bool) false);
        } elseif (preg_match("/\[spstate=(\d+)\]/", $userSetting, $matches)) {
            $spState = $matches[1];
            \App\Support\Logger::writeWithContext((string) "spstate from user setting", (string) 'info', (bool) false);
        }
        if ($spState > 0) {
            $filters[] = "sp_state = $spState";
            \App\Support\Logger::writeWithContext((string) "sp_state = {$spState} through spstate: {$spState}", (string) 'info', (bool) false);
        }

        if (isset($params['approval_status']) && is_numeric($params['approval_status'])) {
            $filters[] = "approval_status = " . (int) $params['approval_status'];
            \App\Support\Logger::writeWithContext((string) "approval_status = {$params['approval_status']} through approval_status: {$params['approval_status']}", (string) 'info', (bool) false);
        }

        //size
        if (!empty($params['size_begin'])) {
            $atomicValue = intval($params['size_begin']) * 1024 * 1024 * 1024;
            $filters[] = "size >= $atomicValue";
            \App\Support\Logger::writeWithContext((string) "size >= {$atomicValue} through size_begin: {$atomicValue}", (string) 'info', (bool) false);
        }
        if (!empty($params['size_end'])) {
            $atomicValue = intval($params['size_end']) * 1024 * 1024 * 1024;
            $filters[] = "size <= $atomicValue";
            \App\Support\Logger::writeWithContext((string) "size <= {$atomicValue} through size_end: {$atomicValue}", (string) 'info', (bool) false);
        }


        //seeders
        if (!empty($params['seeders_begin'])) {
            $atomicValue = intval($params['seeders_begin']);
            $filters[] = "seeders >= $atomicValue";
            \App\Support\Logger::writeWithContext((string) "seeders >= {$atomicValue} through seeders_begin: {$atomicValue}", (string) 'info', (bool) false);
        }
        if (!empty($params['seeders_end'])) {
            $atomicValue = intval($params['seeders_end']);
            $filters[] = "seeders <= $atomicValue";
            \App\Support\Logger::writeWithContext((string) "seeders <= {$atomicValue} through seeders_end: {$atomicValue}", (string) 'info', (bool) false);
        }

        //leechers
        if (!empty($params['leechers_begin'])) {
            $atomicValue = intval($params['leechers_begin']);
            $filters[] = "leechers >= $atomicValue";
            \App\Support\Logger::writeWithContext((string) "leechers >= {$atomicValue} through leechers_begin: {$atomicValue}", (string) 'info', (bool) false);
        }
        if (!empty($params['leechers_end'])) {
            $atomicValue = intval($params['leechers_end']);
            $filters[] = "leechers <= $atomicValue";
            \App\Support\Logger::writeWithContext((string) "leechers <= {$atomicValue} through leechers_end: {$atomicValue}", (string) 'info', (bool) false);
        }


        //times_completed
        if (!empty($params['times_completed_begin'])) {
            $atomicValue = intval($params['times_completed_begin']);
            $filters[] = "times_completed >= $atomicValue";
            \App\Support\Logger::writeWithContext((string) "times_completed >= {$atomicValue} through times_completed_begin: {$atomicValue}", (string) 'info', (bool) false);
        }
        if (!empty($params['times_completed_end'])) {
            $atomicValue = intval($params['times_completed_end']);
            $filters[] = "times_completed <= $atomicValue";
            \App\Support\Logger::writeWithContext((string) "times_completed <= {$atomicValue} through times_completed_end: {$atomicValue}", (string) 'info', (bool) false);
        }

        //added
        if (!empty($params['added_begin'])) {
            $atomicValue = $params['added_begin'];
            $filters[] = "added >= " . strtotime($atomicValue);
            \App\Support\Logger::writeWithContext((string) "added >= {$atomicValue} through added_begin: {$atomicValue}", (string) 'info', (bool) false);
        }
        if (!empty($params['added_end'])) {
            $atomicValue = Carbon::parse($params['added_end'])->endOfDay()->toDateTimeString();
            $filters[] = "added <= " . strtotime($atomicValue);
            \App\Support\Logger::writeWithContext((string) "added <= {$atomicValue} through added_end: {$atomicValue}", (string) 'info', (bool) false);
        }

        //permission see banned
        if (isset($params['banned']) && in_array($params['banned'], ['yes', 'no'])) {
            if ($params['banned'] == 'yes') {
                $filters[] = "banned = 1";
            } else {
                $filters[] = "banned = 0";
            }
        }

        \App\Support\Logger::writeWithContext((string) ("[GET_FILTERS]: " . json_encode($filters)), (string) 'info', (bool) false);
        return $filters;
    }

    /** @param  array<int|string, mixed>  $params */
    private function getQuery(array $params): string
    {
        $q = trim(is_scalar($params['search'] ?? '') ? (string) ($params['search'] ?? '') : '');
        $searchMode = SearchBox::getDefaultSearchMode();
        if (isset($params['search_mode']) && is_scalar($params['search_mode']) && isset(SearchBox::$searchModes[(string) $params['search_mode']])) {
            $searchMode = (string) $params['search_mode'];
        }
        \App\Support\Logger::writeWithContext((string) ("search mode: " . SearchBox::$searchModes[$searchMode]['text']), (string) 'info', (bool) false);
        if ($searchMode == SearchBox::SEARCH_MODE_AND) {
            return $q;
        }
        return sprintf('"%s"', $q);
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return  mixed
     */
    private function getSearchArea(array $params)
    {
        $searchArea = is_scalar($params['search_area'] ?? '') ? (string) ($params['search_area'] ?? '') : '';
        if (isset(self::$searchAreas[$searchArea])) {
            return $searchArea;
        }
        return self::SEARCH_AREA_TITLE;
    }

    public function getIndex(): \Meilisearch\Endpoints\Indexes
    {
        return $this->getClient()->index(self::INDEX_NAME);
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return  array<int|string, mixed>
     */
    private function getSort(array $params): array
    {
        if (!isset($params['sort']) || !isset($params['type'])) {
            //Use default
            return [];
        }
        if (isset($params['sort'], self::$sortFieldMaps[$params['sort']]) && isset($params['type']) && in_array($params['type'], ['asc', 'desc'])) {
            $sortField = self::$sortFieldMaps[$params['sort']];
        } else {
            $sortField = "id";
        }
        if (isset($params['type']) && in_array($params['type'], ['desc', 'asc'])) {
            $sortType = $params['type'];
        } else {
            $sortType = "desc";
        }
        //when searching, ignore promotion
//        if ($sortField == "id") {
//            return ["pos_state:desc", "$sortField:$sortType"];
//        } else {
//            return ["pos_state:desc", "$sortField:$sortType", "id:desc"];
//        }

        return ["$sortField:$sortType"];
    }

    /**
     * @param  \App\Models\User  $user
     * @return  mixed
     */
    private function getPerPage(User $user)
    {
        if ($user->torrentsperpage) {
            $size = $user->torrentsperpage;
        } elseif (($sizeFromConfig = \App\Support\Config\SiteConfig::current()->main->torrentsPerPage()) > 0) {
            $size = $sizeFromConfig;
        } else {
            $size = 100;
        }
        return intval(min($size, 200));
    }

    /**
     * @param  mixed  $field
     * @param  mixed  $value
     * @return  mixed
     */
    public static function formatValueForMeili($field, $value)
    {
        // Yes/no enums must be resolved before any numeric cast so that a value
        // like 'yes' is not accidentally run through intval() and stored as 0.
        if (in_array($field, self::$yesOrNoFields)) {
            return $value == 'yes' ? 1 : 0;
        }
        if (in_array($field, self::$intFields)) {
            return intval($value);
        }
        if (in_array($field, self::$timestampFields)) {
            return strtotime((string) $value);
        }
        return strval($value);
    }

    /**
     * @param  mixed  $id
     * @return  mixed
     */
    public function deleteDocuments($id)
    {
        if ($this->isEnabled()) {
            return $this->getIndex()->deleteDocuments(Arr::wrap($id));
        }
    }

    /** @return  array<int|string, mixed> */
    private static function getAttributesToRetrieve(): array
    {
        if (\App\Support\Env::get("APP_ENV", null) == 'production') {
            return ['id'];
        }
        return ['*'];
    }

    /** @return  array<int|string, mixed> */
    private static function getSearchableAttributes(): array
    {
        $attributes = ["name", "url"];
        if (\App\Support\Config\SiteConfig::current()->meiliSearch->searchDescription()) {
            $attributes[] = "descr";
        }
        return $attributes;
    }




}
