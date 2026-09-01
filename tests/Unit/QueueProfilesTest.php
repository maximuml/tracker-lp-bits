<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\AttendanceJob;
use App\Jobs\BuyTorrent;
use App\Jobs\CheckQueueFailedJobs;
use App\Jobs\CleanupJob;
use App\Jobs\FireEvent;
use App\Jobs\GenerateCoverThumbnail;
use App\Jobs\GenerateTemporaryInvite;
use App\Jobs\HrCheckJob;
use App\Jobs\LoadTorrentBoughtUsers;
use App\Jobs\SaveIpLogCacheToDB;
use App\Jobs\SeedBonusJob;
use App\Jobs\SendLoginNotify;
use App\Jobs\UpdateTorrentSeedersEtc;
use App\Jobs\UpdateUserDownloadPrivilege;
use App\Jobs\UpdateUserSeedingLeechingTime;
use Tests\TestCase;

/**
 * Wave 5 Step 20: queue profiles.
 *
 * Verifies that all ShouldQueue jobs are assigned to the correct
 * queue profile (tracker-critical, default, mail, search, maintenance)
 * matching the Horizon supervisor configuration.
 */
final class QueueProfilesTest extends TestCase
{
    /**
     * Tracker-critical jobs: BuyTorrent, FireEvent, UpdateTorrentSeedersEtc, GenerateCoverThumbnail.
     */
    public function test_tracker_critical_jobs_assigned_to_correct_queue(): void
    {
        $this->assertSame('tracker-critical', (new BuyTorrent(1, 1))->queue);
        $this->assertSame('tracker-critical', (new FireEvent('test', '1'))->queue);
        $this->assertSame('tracker-critical', (new UpdateTorrentSeedersEtc(1, 10, '1', 'key'))->queue);
        $this->assertSame('tracker-critical', (new GenerateCoverThumbnail('http://example.com/img.jpg', '/tmp/out.jpg'))->queue);
    }

    /**
     * Mail jobs: SendLoginNotify.
     */
    public function test_mail_jobs_assigned_to_mail_queue(): void
    {
        $this->assertSame('mail', (new SendLoginNotify(1))->queue);
    }

    /**
     * Maintenance jobs: CleanupJob, CheckQueueFailedJobs.
     */
    public function test_maintenance_jobs_assigned_to_maintenance_queue(): void
    {
        $this->assertSame('maintenance', (new CleanupJob)->queue);
        $this->assertSame('maintenance', (new CheckQueueFailedJobs)->queue);
    }

    /**
     * Default jobs: AttendanceJob, HrCheckJob, LoadTorrentBoughtUsers,
     * SeedBonusJob, UpdateUserSeedingLeechingTime, UpdateUserDownloadPrivilege,
     * GenerateTemporaryInvite, SaveIpLogCacheToDB.
     */
    public function test_default_jobs_assigned_to_default_queue(): void
    {
        $this->assertSame('default', (new AttendanceJob)->queue);
        $this->assertSame('default', (new HrCheckJob)->queue);
        $this->assertSame('default', (new LoadTorrentBoughtUsers(1))->queue);
        $this->assertSame('default', (new SeedBonusJob(0, 0, '', 'key'))->queue);
        $this->assertSame('default', (new UpdateUserSeedingLeechingTime(0, 0, '', 'key'))->queue);
        $this->assertSame('default', (new UpdateUserDownloadPrivilege(1, true, 'reason'))->queue);
        $this->assertSame('default', (new GenerateTemporaryInvite('key', 7, 1))->queue);
        $this->assertSame('default', (new SaveIpLogCacheToDB)->queue);
    }

    /**
     * Horizon config defines all 5 queue profiles.
     */
    public function test_horizon_config_defines_all_profiles(): void
    {
        $horizon = config('horizon');
        $this->assertIsArray($horizon);
        $this->assertArrayHasKey('tracker-critical', $horizon['defaults']);
        $this->assertArrayHasKey('default', $horizon['defaults']);
        $this->assertArrayHasKey('mail', $horizon['defaults']);
        $this->assertArrayHasKey('search', $horizon['defaults']);
        $this->assertArrayHasKey('maintenance', $horizon['defaults']);
    }

    /**
     * Horizon wait thresholds are configured for each queue.
     */
    public function test_horizon_wait_thresholds_configured(): void
    {
        $waits = config('horizon.waits');
        $this->assertIsArray($waits);
        $this->assertArrayHasKey('redis:tracker-critical', $waits);
        $this->assertArrayHasKey('redis:default', $waits);
        $this->assertArrayHasKey('redis:mail', $waits);
        $this->assertArrayHasKey('redis:search', $waits);
        $this->assertArrayHasKey('redis:maintenance', $waits);
    }

    /**
     * Tracker-critical queue has lower wait threshold than default.
     */
    public function test_tracker_critical_has_lower_wait_threshold(): void
    {
        $waits = config('horizon.waits');
        $this->assertLessThan(
            $waits['redis:default'],
            $waits['redis:tracker-critical'],
            'tracker-critical should have a lower wait threshold than default'
        );
    }

    /**
     * Each queue profile has tries, timeout, and backoff configured.
     */
    public function test_all_queue_profiles_have_tries_timeout_backoff(): void
    {
        $defaults = config('horizon.defaults');
        $this->assertIsArray($defaults);
        foreach (['tracker-critical', 'default', 'mail', 'search', 'maintenance'] as $profile) {
            $this->assertArrayHasKey('tries', $defaults[$profile], "$profile must have tries");
            $this->assertArrayHasKey('timeout', $defaults[$profile], "$profile must have timeout");
        }
    }

    /**
     * All ShouldQueue jobs have tries, timeout, and queue assignment.
     */
    public function test_all_jobs_have_tries_timeout_queue(): void
    {
        $jobInstances = [
            new AttendanceJob,
            new BuyTorrent(1, 1),
            new CheckQueueFailedJobs,
            new CleanupJob,
            new FireEvent('test', '1'),
            new GenerateCoverThumbnail('http://example.com/img.jpg', '/tmp/out.jpg'),
            new GenerateTemporaryInvite('key', 7, 1),
            new HrCheckJob,
            new LoadTorrentBoughtUsers(1),
            new SaveIpLogCacheToDB,
            new SeedBonusJob(0, 0, '', 'key'),
            new SendLoginNotify(1),
            new UpdateTorrentSeedersEtc(1, 10, '1', 'key'),
            new UpdateUserDownloadPrivilege(1, true, 'reason'),
            new UpdateUserSeedingLeechingTime(0, 0, '', 'key'),
        ];
        foreach ($jobInstances as $job) {
            $this->assertNotNull($job->queue, $job::class.' must have a queue assignment');
            $this->assertNotEmpty($job->queue, $job::class.' queue must not be empty');
        }
    }
}
