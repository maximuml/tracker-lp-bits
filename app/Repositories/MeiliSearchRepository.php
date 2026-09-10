<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\MeiliSearchRepositoryInterface;
use App\Exceptions\NexusException;
use App\Models\Torrent;
use App\Models\User;
use App\Services\MeiliSearchService;
use App\Support\Config;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

class MeiliSearchRepository extends BaseRepository implements MeiliSearchRepositoryInterface
{
    /** @var mixed */
    private static $client;

    public const INDEX_NAME = 'torrents';

    public const SEARCH_AREA_TITLE = '0';

    public const SEARCH_AREA_DESC = '1';

    public const SEARCH_AREA_OWNER = '3';

    /** @var array<int|string, mixed> */
    private static array $filterableAttributes = [
        'id', 'category', 'source', 'medium', 'codec', 'standard', 'processing', 'audiocodec', 'owner',
        'sp_state', 'visible', 'banned', 'approval_status', 'size', 'leechers', 'seeders', 'times_completed', 'added',
    ];

    /** @var array<int|string, mixed> */
    private static array $sortableAttributes = [
        'id', 'name', 'comments', 'added', 'size', 'leechers', 'seeders', 'times_completed', 'owner',
        'pos_state', 'anonymous',
    ];

    /** @var array<int|string, mixed> */
    private static array $intFields = [
        'id', 'category', 'source', 'medium', 'codec', 'standard', 'processing', 'audiocodec', 'owner',
        'sp_state', 'approval_status', 'size', 'leechers', 'seeders', 'times_completed', 'url', 'comments',
    ];

    /** @var array<int|string, mixed> */
    private static array $timestampFields = ['added'];

    /** @var array<int|string, mixed> */
    private static array $yesOrNoFields = ['visible', 'anonymous', 'banned'];

    public function __construct(
        private MeiliSearchService $searchService,
    ) {}

    public function getClient(): Client
    {
        if (self::$client === null) {
            $config = Config::get('nexus.meilisearch', null);
            $url = sprintf('%s://%s:%s', $config['scheme'], $config['host'], $config['port']);
            Logger::writeWithContext((string) ("get client with url: {$url}"), (string) 'info', (bool) false);
            self::$client = new Client($url, $config['master_key']);
        }

        return self::$client;
    }

    public function isEnabled(): bool
    {
        return SiteConfig::current()->meiliSearch->enabled();
    }

    /** @return  mixed */
    public function import()
    {
        if (! $this->isEnabled()) {
            return 0;
        }
        $client = $this->getClient();
        $stats = $client->stats();
        if (isset($stats['indexes'][self::INDEX_NAME])) {
            $doSwap = true;
            $indexName = self::INDEX_NAME.'_'.date('Ymd_His');
        } else {
            $doSwap = false;
            $indexName = self::INDEX_NAME;
        }
        Logger::writeWithContext((string) "indexName: {$indexName} will be created, doSwap: {$doSwap}", (string) 'info', (bool) false);
        $index = $this->createIndex($indexName);
        try {
            $total = $this->doImportFromDatabase(null, $index);
            if ($doSwap) {
                $swapResult = $client->swapIndexes([[self::INDEX_NAME, $indexName]]);
                $times = 0;
                while (true) {
                    if ($times == 3600) {
                        $msg = "total: $total, swap too long, times: $times, return false";
                        Logger::writeWithContext((string) $msg, (string) 'info', (bool) false);
                        throw new NexusException($msg);
                    }
                    sleep(1);
                    $task = $client->getTask($swapResult['taskUid']);
                    if ($task['status'] == 'succeeded') {
                        Logger::writeWithContext((string) "total: {$total}, swap success at times: {$times}", (string) 'info', (bool) false);
                        $client->deleteIndex($indexName);

                        return $total;
                    }
                    Logger::writeWithContext((string) "waiting swap success, times: {$times}", (string) 'info', (bool) false);
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
     * @return mixed
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
            'distinctAttribute' => 'id',
            'displayedAttributes' => $this->getRequiredFields(),
            'searchableAttributes' => $this->searchService->getSearchableAttributes(),
            'filterableAttributes' => self::$filterableAttributes,
            'sortableAttributes' => self::$sortableAttributes,
            'rankingRules' => [
                'words',
                'sort',
                'typo',
                'proximity',
                'attribute',
                'exactness',
            ],
        ];
        $index->updateSettings($settings);

        return $index;

    }

    /** @return  array<int|string, mixed> */
    public function getRequiredFields(): array
    {
        return array_values(array_unique(array_merge(
            self::$filterableAttributes, self::$sortableAttributes, $this->searchService->getSearchableAttributes()
        )));
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $index
     * @return mixed
     */
    public function doImportFromDatabase($id = null, $index = null)
    {
        if (! $this->isEnabled() && $index === null) {
            Logger::writeWithContext((string) 'Not enabled!', (string) 'info', (bool) false);

            return false;
        }
        $page = 1;
        $size = 1000;
        $rebuild = $index instanceof Indexes;
        if (! $rebuild) {
            $index = $this->getIndex();
        }
        $total = 0;
        $tasks = [];
        $columns = DB::getSchemaBuilder()->getColumnListing('torrents');
        $fields = array_values(array_intersect($this->getRequiredFields(), $columns));
        while (true) {
            $query = Torrent::query()->select($fields)->orderBy('id')->forPage($page, $size);
            if ($id) {
                $query->whereIn('id', Arr::wrap($id));
            }
            $torrents = $query->get();
            $count = $torrents->count();
            $total += $count;
            if ($count == 0) {
                Logger::writeWithContext((string) "page: {$page} no data...", (string) 'info', (bool) false);
                break;
            }
            Logger::writeWithContext((string) sprintf('importing page: %s with id: %s, %s records...', $page, $id, $count), (string) 'info', (bool) false);
            $data = $torrents->map->toSearchableArray()->all();
            $result = $index->updateDocuments($data);
            if (is_array($result) && isset($result['taskUid'])) {
                $tasks[] = $result['taskUid'];
            }
            Logger::writeWithContext((string) sprintf('import page: %s with id: %s, %s records success.', $page, $id, $count), (string) 'info', (bool) false);
            $page++;
        }
        if ($rebuild && ! empty($tasks)) {
            $this->getClient()->waitForTasks($tasks, 60000, 100);
        }

        return $total;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $user
     * @return mixed
     */
    public function search(array $params, $user)
    {
        return $this->searchService->search($params, $user);
    }

    /**
     * Fast autocomplete over MeiliSearch index for search-as-you-type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function autocomplete(string $query, int $limit, User $user): array
    {
        return $this->searchService->autocomplete($query, $limit, $user);
    }

    public function getIndex(): Indexes
    {
        return $this->getClient()->index(self::INDEX_NAME);
    }

    /**
     * @param  mixed  $field
     * @param  mixed  $value
     * @return mixed
     */
    public function formatValueForMeili($field, $value)
    {
        // Yes/no enums must be resolved before any numeric cast so that a value
        // like 'yes' is not accidentally run through intval() and stored as 0.
        if (in_array($field, self::$yesOrNoFields)) {
            if (is_bool($value)) {
                return $value ? 1 : 0;
            }

            return $value == 'yes' || $value == 1 ? 1 : 0;
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
     * @return mixed
     */
    public function deleteDocuments($id)
    {
        if ($this->isEnabled()) {
            return $this->getIndex()->deleteDocuments(Arr::wrap($id));
        }
    }
}
