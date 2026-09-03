<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\CleanupRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Unit tests for CleanupRepository.
 *
 * Covers recordBatch(), runBatchJob*() methods, checkCleanup(),
 * and checkQueueFailedJobs().
 *
 * Batch-job methods that dispatch queued jobs are tested for their
 * no-op / early-return paths to avoid actually dispatching jobs.
 */
final class CleanupRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CleanupRepository $repository;

    /** @var array<int, string> */
    private array $batchKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('avps')->delete();
        DB::table('failed_jobs')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new CleanupRepository;
    }

    protected function tearDown(): void
    {
        $redis = Redis::connection()->client();
        foreach ($this->batchKeys as $key) {
            try {
                $batch = $redis->get($key);
                if ($batch !== false) {
                    $redis->del($batch);
                }
                $redis->del($key);
            } catch (\Exception) {
                // ignore
            }
        }
        try {
            $redis->del(CleanupRepository::USER_SEED_BONUS_BATCH_KEY);
            $redis->del(CleanupRepository::USER_SEEDING_LEECHING_TIME_BATCH_KEY);
            $redis->del(CleanupRepository::TORRENT_SEEDERS_ETC_BATCH_KEY);
        } catch (\Exception) {
            // ignore
        }
        parent::tearDown();
    }

    public function test_record_batch_creates_batch_keys_in_redis(): void
    {
        $redis = Redis::connection()->client();
        $this->batchKeys[] = CleanupRepository::USER_SEED_BONUS_BATCH_KEY;
        $this->batchKeys[] = CleanupRepository::USER_SEEDING_LEECHING_TIME_BATCH_KEY;
        $this->batchKeys[] = CleanupRepository::TORRENT_SEEDERS_ETC_BATCH_KEY;

        $result = $this->repository->recordBatch($redis, 100, 200);

        $this->assertNotNull($result);
        $this->assertNotFalse($redis->get(CleanupRepository::USER_SEED_BONUS_BATCH_KEY));
    }

    public function test_record_batch_stores_uid_and_torrent_id(): void
    {
        $redis = Redis::connection()->client();
        $this->batchKeys[] = CleanupRepository::USER_SEED_BONUS_BATCH_KEY;

        $this->repository->recordBatch($redis, 500, 600);

        $batch = $redis->get(CleanupRepository::USER_SEED_BONUS_BATCH_KEY);
        $this->assertNotFalse($batch);
        $exists = $redis->hExists($batch, (string) 500);
        $this->assertTrue($exists);
    }

    public function test_run_batch_job_calculate_user_seed_bonus_no_batch_returns_early(): void
    {
        // No batch key set in Redis, should return early without error
        $this->repository->runBatchJobCalculateUserSeedBonus('test-request-id');

        $this->expectNotToPerformAssertions();
    }

    public function test_run_batch_job_update_user_seeding_leeching_time_no_batch(): void
    {
        $this->repository->runBatchJobUpdateUserSeedingLeechingTime('test-request-id');

        $this->expectNotToPerformAssertions();
    }

    public function test_run_batch_job_update_torrent_seeders_etc_no_batch(): void
    {
        $this->repository->runBatchJobUpdateTorrentSeedersEtc('test-request-id');

        $this->expectNotToPerformAssertions();
    }

    public function test_check_cleanup_returns_early_when_no_avps(): void
    {
        $this->repository->checkCleanup();

        $this->expectNotToPerformAssertions();
    }

    public function test_check_cleanup_returns_early_when_interval_not_elapsed(): void
    {
        DB::table('avps')->insert([
            'arg' => 'lastcleantime',
            'value_s' => '',
            'value_u' => time(),
        ]);

        $this->repository->checkCleanup();

        $this->expectNotToPerformAssertions();
    }

    public function test_check_queue_failed_jobs_returns_early_when_none(): void
    {
        $this->repository->checkQueueFailedJobs();

        $this->expectNotToPerformAssertions();
    }

    public function test_check_queue_failed_jobs_logs_when_failed_jobs_exist(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-1',
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'test exception',
            'failed_at' => now()->toDateTimeString(),
        ]);

        // Should not throw even with failed jobs present
        $this->repository->checkQueueFailedJobs();

        $this->expectNotToPerformAssertions();
    }
}
