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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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

    /**
     * Name of the cache key holding the temp index currently being
     * rebuilt. While set, document writes/deletes are mirrored to it so
     * changes racing with the rebuild are not lost by the swap.
     */
    public const REBUILD_INDEX_CACHE_KEY = 'meilisearch:rebuild_index';

    /**
     * Cache key holding the index name that held the pre-swap production
     * data — the rollback copy kept until the next successful rebuild.
     */
    public const PREV_INDEX_CACHE_KEY = 'meilisearch:prev_index';

    /** Temp index naming: INDEX_NAME . '_rebuild_' . Ymd_His */
    private const REBUILD_INDEX_PATTERN = '/^torrents_rebuild_\d{8}_\d{6}$/';

    /** Leftover temp indexes from crashed runs are pruned after this age. */
    private const STALE_REBUILD_TTL = 86400;

    /** Seconds between task-status polls while waiting for swap. */
    private const SWAP_POLL_INTERVAL = 1;

    /** Swap is a metadata operation — anything longer means trouble. */
    private const SWAP_TIMEOUT = 120;

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
        $doSwap = isset($stats['indexes'][self::INDEX_NAME]);
        $indexName = $doSwap
            ? self::INDEX_NAME.'_rebuild_'.date('Ymd_His')
            : self::INDEX_NAME;
        $startedAt = microtime(true);
        Logger::writeWithContext("indexName: {$indexName} will be created, doSwap: ".var_export($doSwap, true), 'info');

        $this->pruneStaleRebuildIndexes($client);
        try {
            $index = $this->createIndex($indexName);
            if ($doSwap) {
                // Dual-write window opens before the first batch is sent
                // so every concurrent change lands in the temp index too.
                $this->setRebuildIndex($indexName);
            }
            $total = $this->doImportFromDatabase(null, $index);
            if (! $doSwap) {
                $this->recordRebuildMetrics($startedAt, $total);

                return $total;
            }

            $this->assertIndexReady($index, $total);
            $this->swapAndWait($client, $indexName);
            $this->clearRebuildIndex();
            $this->rotateRollbackIndex($client, $indexName);
            $this->recordRebuildMetrics($startedAt, $total);

            return $total;
        } catch (\Throwable $exception) {
            $this->clearRebuildIndex();
            $this->recordRebuildMetrics($startedAt, null, $exception);
            // Only ever delete the temp index this attempt created —
            // the live index and the rollback copy stay untouched.
            try {
                $client->deleteIndex($indexName);
            } catch (\Throwable) {
            }
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
        $task = $client->createIndex($indexName, $params);
        $this->assertTaskSucceeded($client, (int) $task['taskUid'], 'createIndex');

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
        $task = $index->updateSettings($settings);
        $this->assertTaskSucceeded($client, (int) $task['taskUid'], 'updateSettings');

        return $index;
    }

    /**
     * A task that ends failed/canceled must abort the rebuild — the
     * alternative is swapping in a half-built index.
     */
    private function assertTaskSucceeded(Client $client, int $taskUid, string $what): void
    {
        $task = $client->waitForTask($taskUid, 120000, 100);
        $status = (string) ($task['status'] ?? '');
        if ($status !== 'succeeded') {
            $error = $task['error'] ?? [];
            throw new NexusException(sprintf(
                '%s task %d ended %s: %s %s',
                $what,
                $taskUid,
                $status !== '' ? $status : 'unknown',
                (string) ($error['code'] ?? ''),
                (string) ($error['message'] ?? ''),
            ));
        }
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
            Logger::writeWithContext('Not enabled!', 'info');

            return false;
        }
        $size = 1000;
        $rebuild = $index instanceof Indexes;
        if (! $rebuild) {
            $index = $this->getIndex();
        }
        $total = 0;
        $tasks = [];
        $columns = DB::getSchemaBuilder()->getColumnListing('torrents');
        $fields = array_values(array_intersect($this->getRequiredFields(), $columns));

        if ($id) {
            // Incremental path: explicit id set, no rebuild semantics.
            Torrent::query()->select($fields)->whereIn('id', Arr::wrap($id))->orderBy('id')
                ->chunkById($size, function ($torrents) use ($index, &$total, &$tasks, $id) {
                    $total += $torrents->count();
                    $this->importBatch($index, $torrents, $tasks, (string) $id);
                });
        } else {
            // Rebuild path: keyset pagination — OFFSET on a live table
            // shifts under concurrent writes and rescans ever-growing
            // ranges on large tables.
            $lastId = 0;
            while (true) {
                $torrents = Torrent::query()->select($fields)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($size)
                    ->get();
                $count = $torrents->count();
                $last = $torrents->last();
                if ($count === 0 || $last === null) {
                    break;
                }
                $lastId = (int) $last->id;
                $total += $count;
                $this->importBatch($index, $torrents, $tasks, "after id {$lastId}");
            }
        }

        if ($rebuild) {
            $this->awaitImportTasks($tasks, $total);
        }

        return $total;
    }

    /**
     * @param  Collection<int, Torrent>  $torrents
     * @param  list<int>  $tasks
     */
    private function importBatch(Indexes $index, $torrents, array &$tasks, string $context): void
    {
        Logger::writeWithContext(sprintf('importing %d records (%s)...', $torrents->count(), $context), 'info');
        $data = $torrents->map->toSearchableArray()->all();
        $result = $index->updateDocuments($data);
        if (is_array($result) && isset($result['taskUid'])) {
            $tasks[] = (int) $result['taskUid'];
        }
    }

    /**
     * Wait for every batch task and verify each one actually succeeded.
     * A failed/canceled batch must fail the rebuild loudly instead of
     * being counted as imported.
     *
     * @param  list<int>  $tasks
     */
    private function awaitImportTasks(array $tasks, int $total): void
    {
        if ($tasks === []) {
            return;
        }
        $client = $this->getClient();
        // Timeout scales with volume: ~20 ms per doc plus a 2-minute floor.
        $timeoutMs = max(120000, $total * 20);
        $finished = $client->waitForTasks($tasks, $timeoutMs, 100);

        $failed = [];
        foreach ($finished as $task) {
            $status = (string) ($task['status'] ?? '');
            if ($status !== 'succeeded') {
                $error = $task['error'] ?? [];
                $failed[] = sprintf(
                    'task %d %s (%s %s)',
                    (int) ($task['uid'] ?? 0),
                    $status !== '' ? $status : 'unknown',
                    (string) ($error['code'] ?? ''),
                    (string) ($error['message'] ?? ''),
                );
            }
        }
        if ($failed !== []) {
            throw new NexusException('import failed: '.implode('; ', $failed));
        }
    }

    /**
     * Pre-swap validation: the temp index must hold every document we
     * sent (dual-write mirrors can only add more) and have the required
     * settings applied.
     */
    private function assertIndexReady(Indexes $index, int $sent): void
    {
        $stats = $index->stats();
        $documents = (int) ($stats['numberOfDocuments'] ?? 0);
        if ($documents < $sent) {
            throw new NexusException(sprintf(
                'import incomplete: sent %d documents but index holds %d',
                $sent,
                $documents,
            ));
        }
        if ($sent === 0 && $this->sourceTorrentCount() > 0) {
            // An empty index over a non-empty source means the import
            // silently produced nothing — not a legitimately empty table.
            throw new NexusException('import produced 0 documents over a non-empty torrents table');
        }
        $settings = $index->getSettings();
        $filterable = $settings['filterableAttributes'] ?? [];
        if (! is_array($filterable) || ! in_array('id', $filterable, true)) {
            throw new NexusException('index settings not applied: filterableAttributes missing');
        }
    }

    /**
     * Snapshot-side source count used by pre-swap validation. Separated
     * so tests can exercise the empty-source branch without wiping the
     * shared table.
     */
    protected function sourceTorrentCount(): int
    {
        return Torrent::query()->count();
    }

    private function swapAndWait(Client $client, string $indexName): void
    {
        $swapResult = $client->swapIndexes([[self::INDEX_NAME, $indexName]]);
        $taskUid = (int) $swapResult['taskUid'];
        $deadline = time() + self::SWAP_TIMEOUT;
        while (true) {
            sleep(self::SWAP_POLL_INTERVAL);
            $task = $client->getTask($taskUid);
            $status = (string) ($task['status'] ?? '');
            if ($status === 'succeeded') {
                return;
            }
            if ($status === 'failed' || $status === 'canceled') {
                $error = $task['error'] ?? [];
                throw new NexusException(sprintf(
                    'swap task %d %s: %s %s',
                    $taskUid,
                    $status,
                    (string) ($error['code'] ?? ''),
                    (string) ($error['message'] ?? ''),
                ));
            }
            if (time() > $deadline) {
                throw new NexusException("swap task {$taskUid} did not finish within ".self::SWAP_TIMEOUT.'s');
            }
        }
    }

    /**
     * After a successful swap the pre-swap data lives under $indexName —
     * keep it as the rollback copy and drop the older rollback index.
     */
    private function rotateRollbackIndex(Client $client, string $indexName): void
    {
        $previous = $this->prevIndexName();
        if ($previous !== null && $previous !== $indexName) {
            try {
                $client->deleteIndex($previous);
            } catch (\Throwable $e) {
                Logger::writeWithContext('failed to delete previous rollback index '.$previous.': '.$e->getMessage(), 'warning');
            }
        }
        try {
            Cache::put(self::PREV_INDEX_CACHE_KEY, $indexName, now()->addDays(7));
        } catch (\Throwable) {
        }
    }

    /**
     * Delete leftover rebuild artifacts older than STALE_REBUILD_TTL —
     * never the live index, never the current rollback copy.
     */
    private function pruneStaleRebuildIndexes(Client $client): void
    {
        try {
            $prev = $this->prevIndexName();
            $now = time();
            foreach ($client->getIndexes()->getResults() as $indexInfo) {
                $name = (string) $indexInfo->getUid();
                if (! preg_match(self::REBUILD_INDEX_PATTERN, $name)) {
                    continue;
                }
                if ($name === $prev) {
                    continue;
                }
                $stamp = \DateTimeImmutable::createFromFormat('Ymd_His', substr($name, -15));
                if ($stamp === false || ($now - $stamp->getTimestamp()) < self::STALE_REBUILD_TTL) {
                    continue;
                }
                $client->deleteIndex($name);
            }
        } catch (\Throwable $e) {
            Logger::writeWithContext('rebuild index pruning failed: '.$e->getMessage(), 'warning');
        }
    }

    public function rebuildIndexName(): ?string
    {
        try {
            $name = Cache::get(self::REBUILD_INDEX_CACHE_KEY);
        } catch (\Throwable) {
            return null;
        }

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function setRebuildIndex(string $indexName): void
    {
        try {
            Cache::put(self::REBUILD_INDEX_CACHE_KEY, $indexName, now()->addHours(4));
        } catch (\Throwable) {
        }
    }

    private function clearRebuildIndex(): void
    {
        try {
            Cache::forget(self::REBUILD_INDEX_CACHE_KEY);
        } catch (\Throwable) {
        }
    }

    private function prevIndexName(): ?string
    {
        try {
            $name = Cache::get(self::PREV_INDEX_CACHE_KEY);
        } catch (\Throwable) {
            return null;
        }

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Mirror one torrent document into the index currently being rebuilt.
     * Called from the live-sync listener so writes racing the rebuild are
     * not lost by the swap.
     */
    public function mirrorToRebuildIndex(Torrent $torrent): void
    {
        $name = $this->rebuildIndexName();
        if ($name === null) {
            return;
        }
        try {
            $this->getClient()->index($name)->updateDocuments([$torrent->toSearchableArray()]);
        } catch (\Throwable $e) {
            Logger::writeWithContext('rebuild mirror write failed for torrent '.$torrent->id.': '.$e->getMessage(), 'warning');
        }
    }

    /**
     * Mirror a delete into the index currently being rebuilt.
     *
     * @param  array<int|string, mixed>  $ids
     */
    public function mirrorDeleteToRebuildIndex(array $ids): void
    {
        $name = $this->rebuildIndexName();
        if ($name === null) {
            return;
        }
        try {
            $this->getClient()->index($name)->deleteDocuments($ids);
        } catch (\Throwable $e) {
            Logger::writeWithContext('rebuild mirror delete failed: '.$e->getMessage(), 'warning');
        }
    }

    private function recordRebuildMetrics(float $startedAt, ?int $total, ?\Throwable $failure = null): void
    {
        try {
            $redis = Redis::connection();
            $redis->set('metrics:meili_rebuild_duration', (string) round(microtime(true) - $startedAt, 3));
            if ($total !== null) {
                $redis->set('metrics:meili_rebuild_documents', (string) $total);
                $redis->set('metrics:meili_rebuild_last_success', (string) time());
            } else {
                $redis->incr('metrics:meili_rebuild_failed');
                Logger::writeWithContext('rebuild failed: '.($failure?->getMessage() ?? ''), 'error');
            }
        } catch (\Throwable) {
        }
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
            $this->mirrorDeleteToRebuildIndex(Arr::wrap($id));

            return $this->getIndex()->deleteDocuments(Arr::wrap($id));
        }
    }
}
