<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Contracts\Repositories\MeiliSearchRepositoryInterface;
use App\Exceptions\NexusException;
use App\Models\Torrent;
use App\Repositories\MeiliSearchRepository;
use App\Services\MeiliSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Meilisearch\Client;
use Meilisearch\Contracts\IndexesResults;
use Meilisearch\Endpoints\Indexes;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Rebuild-orchestration coverage for MeiliSearchRepository::import().
 * The Meili HTTP client is mocked; the database reads are real.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class MeiliSearchRebuildTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Cache::forget(MeiliSearchRepository::REBUILD_INDEX_CACHE_KEY);
        Cache::forget(MeiliSearchRepository::PREV_INDEX_CACHE_KEY);
        Mockery::close();
        parent::tearDown();
    }

    /** @return MeiliSearchRepository&MockInterface */
    private function repository(Client&MockInterface $client): MeiliSearchRepository
    {
        $repo = Mockery::mock(MeiliSearchRepository::class, [app(MeiliSearchService::class)])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $repo->shouldReceive('getClient')->andReturn($client);
        $repo->shouldReceive('isEnabled')->andReturn(true);

        return $repo;
    }

    private function clientWithLiveIndex(): Client&MockInterface
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('stats')->andReturn(['indexes' => [MeiliSearchRepository::INDEX_NAME => []]]);
        $client->shouldReceive('getIndexes')->andReturn(
            new IndexesResults(['results' => [], 'offset' => 0, 'limit' => 20])
        );

        return $client;
    }

    /** @return Indexes&MockInterface */
    private function readyIndex(int $documents): Indexes
    {
        $index = Mockery::mock(Indexes::class);
        $index->shouldReceive('stats')->andReturn(['numberOfDocuments' => $documents]);
        $index->shouldReceive('getSettings')->andReturn(['filterableAttributes' => ['id', 'category']]);

        return $index;
    }

    /**
     * Wire a client+index for a rebuild that gets as far as the batch
     * phase; returns the mocked temp index for further expectations.
     *
     * @return array{0: Client&MockInterface, 1: Indexes&MockInterface}
     */
    private function rebuildFixture(int $documentsInIndex): array
    {
        $client = $this->clientWithLiveIndex();
        $client->shouldReceive('createIndex')->andReturn(['taskUid' => 1]);
        $client->shouldReceive('waitForTask')->andReturn(['status' => 'succeeded']);

        $index = $this->readyIndex($documentsInIndex);
        $index->shouldReceive('updateSettings')->andReturn(['taskUid' => 2]);
        $client->shouldReceive('index')->andReturn($index);

        return [$client, $index];
    }

    public function test_failed_batch_task_aborts_before_swap_and_cleans_temp_index(): void
    {
        Torrent::factory()->create();
        [$client, $index] = $this->rebuildFixture(0);
        $index->shouldReceive('updateDocuments')->andReturn(['taskUid' => 10]);
        $client->shouldReceive('waitForTasks')->andReturn([
            ['uid' => 10, 'status' => 'failed', 'error' => ['code' => 'index_primary_key_not_found', 'message' => 'boom']],
        ]);
        $client->shouldNotReceive('swapIndexes');
        $client->shouldReceive('deleteIndex')->once()->with(Mockery::pattern('/^torrents_rebuild_\d{8}_\d{6}$/'));

        try {
            $this->repository($client)->import();
            $this->fail('expected NexusException');
        } catch (NexusException $e) {
            $this->assertStringContainsString('task 10', $e->getMessage());
            $this->assertStringContainsString('index_primary_key_not_found', $e->getMessage());
        }
        $this->assertNull(Cache::get(MeiliSearchRepository::REBUILD_INDEX_CACHE_KEY));
    }

    public function test_canceled_batch_task_aborts_before_swap(): void
    {
        Torrent::factory()->create();
        [$client, $index] = $this->rebuildFixture(0);
        $index->shouldReceive('updateDocuments')->andReturn(['taskUid' => 10]);
        $client->shouldReceive('waitForTasks')->andReturn([
            ['uid' => 10, 'status' => 'canceled'],
        ]);
        $client->shouldNotReceive('swapIndexes');
        $client->shouldReceive('deleteIndex')->once();

        $this->expectException(NexusException::class);
        $this->expectExceptionMessageMatches('/task 10 canceled/');
        $this->repository($client)->import();
    }

    public function test_failed_create_index_task_aborts_immediately(): void
    {
        $client = $this->clientWithLiveIndex();
        $client->shouldReceive('createIndex')->andReturn(['taskUid' => 1]);
        $client->shouldReceive('waitForTask')->with(1, 120000, 100)->andReturn([
            'status' => 'failed', 'error' => ['code' => 'index_creation_failed', 'message' => 'nope'],
        ]);
        $client->shouldNotReceive('swapIndexes');
        $client->shouldNotReceive('index');
        $client->shouldReceive('deleteIndex')->once();

        $this->expectException(NexusException::class);
        $this->expectExceptionMessageMatches('/createIndex task 1 ended failed.*index_creation_failed/');
        $this->repository($client)->import();
    }

    public function test_failed_settings_task_aborts_immediately(): void
    {
        $client = $this->clientWithLiveIndex();
        $client->shouldReceive('createIndex')->andReturn(['taskUid' => 1]);
        $client->shouldReceive('waitForTask')->with(1, 120000, 100)->andReturn(['status' => 'succeeded']);

        $index = $this->readyIndex(0);
        $index->shouldReceive('updateSettings')->andReturn(['taskUid' => 2]);
        $client->shouldReceive('waitForTask')->with(2, 120000, 100)->andReturn(['status' => 'canceled']);
        $client->shouldReceive('index')->andReturn($index);
        $index->shouldNotReceive('updateDocuments');
        $client->shouldNotReceive('swapIndexes');
        $client->shouldReceive('deleteIndex')->once();

        $this->expectException(NexusException::class);
        $this->expectExceptionMessageMatches('/updateSettings task 2 ended canceled/');
        $this->repository($client)->import();
    }

    public function test_document_count_shortfall_aborts_before_swap(): void
    {
        Torrent::factory()->create();
        $sent = Torrent::query()->count();
        [$client, $index] = $this->rebuildFixture($sent - 1);
        $index->shouldReceive('updateDocuments')->andReturn(['taskUid' => 10]);
        $client->shouldReceive('waitForTasks')->andReturn([['uid' => 10, 'status' => 'succeeded']]);
        $client->shouldNotReceive('swapIndexes');
        $client->shouldReceive('deleteIndex')->once();

        $this->expectException(NexusException::class);
        $this->expectExceptionMessageMatches('/import incomplete/');
        $this->repository($client)->import();
    }

    public function test_missing_settings_abort_before_swap(): void
    {
        Torrent::factory()->create();
        $sent = Torrent::query()->count();
        $client = $this->clientWithLiveIndex();
        $client->shouldReceive('createIndex')->andReturn(['taskUid' => 1]);
        $client->shouldReceive('waitForTask')->andReturn(['status' => 'succeeded']);
        $index = Mockery::mock(Indexes::class);
        $index->shouldReceive('updateSettings')->andReturn(['taskUid' => 2]);
        $index->shouldReceive('updateDocuments')->andReturn(['taskUid' => 10]);
        $index->shouldReceive('stats')->andReturn(['numberOfDocuments' => $sent]);
        $index->shouldReceive('getSettings')->andReturn(['filterableAttributes' => []]);
        $client->shouldReceive('index')->andReturn($index);
        $client->shouldReceive('waitForTasks')->andReturn([['uid' => 10, 'status' => 'succeeded']]);
        $client->shouldNotReceive('swapIndexes');
        $client->shouldReceive('deleteIndex')->once();

        $this->expectException(NexusException::class);
        $this->expectExceptionMessageMatches('/settings not applied/');
        $this->repository($client)->import();
    }

    public function test_failed_swap_task_fails_fast_without_long_poll(): void
    {
        Torrent::factory()->create();
        $sent = Torrent::query()->count();
        [$client, $index] = $this->rebuildFixture($sent);
        $index->shouldReceive('updateDocuments')->andReturn(['taskUid' => 10]);
        $client->shouldReceive('waitForTasks')->andReturn([['uid' => 10, 'status' => 'succeeded']]);
        $client->shouldReceive('swapIndexes')->once()->andReturn(['taskUid' => 20]);
        $client->shouldReceive('getTask')->with(20)->once()->andReturn([
            'status' => 'failed', 'error' => ['code' => 'index_not_found', 'message' => 'gone'],
        ]);
        $client->shouldReceive('deleteIndex')->once();

        $started = microtime(true);
        try {
            $this->repository($client)->import();
            $this->fail('expected NexusException');
        } catch (NexusException $e) {
            $this->assertStringContainsString('swap task 20 failed', $e->getMessage());
        }
        $this->assertLessThan(10, microtime(true) - $started, 'failed swap must abort fast, not poll for an hour');
    }

    public function test_successful_rebuild_swaps_and_records_rollback_copy(): void
    {
        Torrent::factory()->create();
        $sent = Torrent::query()->count();
        [$client, $index] = $this->rebuildFixture($sent);
        $index->shouldReceive('updateDocuments')->andReturn(['taskUid' => 10]);
        $client->shouldReceive('waitForTasks')->andReturn([['uid' => 10, 'status' => 'succeeded']]);
        $client->shouldReceive('swapIndexes')->once()->andReturn(['taskUid' => 20]);
        $client->shouldReceive('getTask')->with(20)->once()->andReturn(['status' => 'succeeded']);
        // Success path must not delete the just-swapped (rollback) index.
        $client->shouldNotReceive('deleteIndex');

        $total = $this->repository($client)->import();
        $this->assertSame($sent, $total);
        $prev = Cache::get(MeiliSearchRepository::PREV_INDEX_CACHE_KEY);
        $this->assertMatchesRegularExpression('/^torrents_rebuild_\d{8}_\d{6}$/', (string) $prev);
        $this->assertNull(Cache::get(MeiliSearchRepository::REBUILD_INDEX_CACHE_KEY));
    }

    public function test_next_rebuild_rotates_the_previous_rollback_copy(): void
    {
        Torrent::factory()->create();
        $sent = Torrent::query()->count();
        Cache::put(MeiliSearchRepository::PREV_INDEX_CACHE_KEY, 'torrents_rebuild_20000101_000000', now()->addDay());

        [$client, $index] = $this->rebuildFixture($sent);
        $index->shouldReceive('updateDocuments')->andReturn(['taskUid' => 10]);
        $client->shouldReceive('waitForTasks')->andReturn([['uid' => 10, 'status' => 'succeeded']]);
        $client->shouldReceive('swapIndexes')->once()->andReturn(['taskUid' => 20]);
        $client->shouldReceive('getTask')->with(20)->andReturn(['status' => 'succeeded']);
        // Exactly one older rollback copy is deleted — never the new one.
        $client->shouldReceive('deleteIndex')->once()->with('torrents_rebuild_20000101_000000');

        $this->repository($client)->import();
        $this->assertNotSame(
            'torrents_rebuild_20000101_000000',
            Cache::get(MeiliSearchRepository::PREV_INDEX_CACHE_KEY)
        );
    }

    public function test_empty_source_database_swaps_cleanly(): void
    {
        [$client, $index] = $this->rebuildFixture(0);
        $index->shouldNotReceive('updateDocuments');
        $client->shouldReceive('swapIndexes')->once()->andReturn(['taskUid' => 20]);
        $client->shouldReceive('getTask')->with(20)->andReturn(['status' => 'succeeded']);

        $repo = $this->repository($client);
        $repo->shouldReceive('doImportFromDatabase')->andReturn(0);
        $repo->shouldReceive('sourceTorrentCount')->andReturn(0);

        $this->assertSame(0, $repo->import());
    }

    public function test_zero_sent_over_non_empty_source_aborts(): void
    {
        Torrent::factory()->create();
        [$client, $index] = $this->rebuildFixture(0);
        $index->shouldNotReceive('updateDocuments');
        $client->shouldNotReceive('swapIndexes');
        $client->shouldReceive('deleteIndex')->once();

        $repo = $this->repository($client);
        $repo->shouldReceive('doImportFromDatabase')->andReturn(0);

        $this->expectException(NexusException::class);
        $this->expectExceptionMessageMatches('/produced 0 documents over a non-empty/');
        $repo->import();
    }

    public function test_import_command_returns_failure_on_rebuild_error(): void
    {
        $repo = Mockery::mock(MeiliSearchRepositoryInterface::class);
        $repo->shouldReceive('import')->andThrow(new NexusException('task 5 failed'));
        app()->instance(MeiliSearchRepositoryInterface::class, $repo);

        $this->artisan('meilisearch:import')->assertExitCode(1);
    }

    public function test_mirror_writes_go_to_rebuild_index_only_while_flagged(): void
    {
        $client = Mockery::mock(Client::class);
        $index = Mockery::mock(Indexes::class);
        $index->shouldReceive('updateDocuments')->once();
        $index->shouldReceive('deleteDocuments')->once()->with([1, 2]);
        $client->shouldReceive('index')->twice()->with('torrents_rebuild_20260101_000000')->andReturn($index);

        $repo = $this->repository($client);
        $this->assertNull($repo->rebuildIndexName());
        $repo->mirrorToRebuildIndex(Torrent::factory()->create());

        Cache::put(MeiliSearchRepository::REBUILD_INDEX_CACHE_KEY, 'torrents_rebuild_20260101_000000', now()->addHour());
        $repo->mirrorToRebuildIndex(Torrent::factory()->create());
        $repo->mirrorDeleteToRebuildIndex([1, 2]);
    }
}
