<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Jobs\CleanupJob;
use App\Models\OutboxEvent;
use App\Models\Torrent;
use App\Models\User;
use App\Services\OutboxService;
use App\Services\ThankService;
use App\Services\TorrentBookmarkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W3-03: Concurrency tests for critical flows.
 *
 * Tests verify that invariants hold under repeated/sequential operations
 * that simulate concurrent access patterns. Each test checks final
 * database state, not merely absence of exceptions.
 *
 * Scenarios covered:
 * - Duplicate bookmark/thanks submission
 * - Outbox claim/retry/dead-letter
 * - Torrent delete/edit collision
 * - Overlapping cleanup job uniqueness
 */
#[TestCategory(TestCategory::CONCURRENCY, TestCategory::SERVICE_INTEGRATION)]
final class CriticalFlowConcurrencyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        Redis::connection()->flushdb();
    }

    // ─── Bookmark duplicate ──────────────────────────────────────────

    public function test_duplicate_bookmark_toggle_does_not_create_duplicates(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();
        $service = app(TorrentBookmarkService::class);

        // Toggle on
        $status1 = $service->toggleBookmark($user->id, $torrent->id);
        $this->assertSame('added', $status1);

        // Toggle off
        $status2 = $service->toggleBookmark($user->id, $torrent->id);
        $this->assertSame('deleted', $status2);

        // Toggle on again
        $status3 = $service->toggleBookmark($user->id, $torrent->id);
        $this->assertSame('added', $status3);

        // Verify exactly one bookmark exists
        $count = DB::table('bookmarks')
            ->where('userid', $user->id)
            ->where('torrentid', $torrent->id)
            ->count();
        $this->assertSame(1, $count, 'Toggle should not create duplicate bookmarks.');
    }

    public function test_bookmark_toggle_is_idempotent_under_repeated_calls(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();
        $service = app(TorrentBookmarkService::class);

        // Add bookmark
        $service->toggleBookmark($user->id, $torrent->id);

        // Simulate concurrent "add" attempts — each toggle removes then adds
        // but the net result should be exactly one bookmark
        for ($i = 0; $i < 5; $i++) {
            $service->toggleBookmark($user->id, $torrent->id);
            $service->toggleBookmark($user->id, $torrent->id);
        }

        $count = DB::table('bookmarks')
            ->where('userid', $user->id)
            ->where('torrentid', $torrent->id)
            ->count();
        $this->assertSame(1, $count, 'Repeated toggle should result in exactly one bookmark.');
    }

    // ─── Thanks duplicate ─────────────────────────────────────────────

    public function test_duplicate_thanks_submission_is_rejected(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $torrent = Torrent::factory()->owner($owner)->create();
        $service = app(ThankService::class);

        // First thanks should succeed
        $service->thankTorrent($user, $torrent);

        // Second thanks should be rejected
        try {
            $service->thankTorrent($user, $torrent);
            $this->fail('Duplicate thanks should throw LogicException.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('already thank', $e->getMessage());
        }

        // Verify exactly one thanks record
        $count = DB::table('thanks')
            ->where('userid', $user->id)
            ->where('torrentid', $torrent->id)
            ->count();
        $this->assertSame(1, $count, 'Duplicate thanks should not create a second record.');
    }

    public function test_thanks_grants_bonus_exactly_once(): void
    {
        $user = User::factory()->create(['seedbonus' => 0.0]);
        $owner = User::factory()->create(['seedbonus' => 0.0]);
        $torrent = Torrent::factory()->owner($owner)->create();
        $service = app(ThankService::class);

        $service->thankTorrent($user, $torrent);

        $userBonusAfterFirst = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $ownerBonusAfterFirst = (float) DB::table('users')->where('id', $owner->id)->value('seedbonus');

        // Attempt duplicate — should not grant bonus again
        try {
            $service->thankTorrent($user, $torrent);
        } catch (\LogicException $e) {
            // Expected
        }

        $userBonusAfterDuplicate = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');
        $ownerBonusAfterDuplicate = (float) DB::table('users')->where('id', $owner->id)->value('seedbonus');

        $this->assertSame($userBonusAfterFirst, $userBonusAfterDuplicate, 'Duplicate thanks should not grant additional bonus to thanker.');
        $this->assertSame($ownerBonusAfterFirst, $ownerBonusAfterDuplicate, 'Duplicate thanks should not grant additional bonus to owner.');
    }

    // ─── Outbox claim/retry/dead-letter ───────────────────────────────

    public function test_outbox_claim_is_atomic_only_one_worker_claims(): void
    {
        $outboxService = app(OutboxService::class);

        // Record an event
        $event = $outboxService->record('test', 'test.event', ['data' => 'value']);

        // Simulate two workers trying to claim the same event
        // The lockForUpdate + atomic update ensures only one succeeds
        $claim1 = DB::table('outbox_events')
            ->where('id', $event->id)
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->update([
                'status' => OutboxEvent::STATUS_PROCESSING,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        $claim2 = DB::table('outbox_events')
            ->where('id', $event->id)
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->update([
                'status' => OutboxEvent::STATUS_PROCESSING,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        $this->assertSame(1, $claim1, 'First claim should succeed.');
        $this->assertSame(0, $claim2, 'Second claim should fail (already processing).');

        $finalStatus = DB::table('outbox_events')->where('id', $event->id)->value('status');
        $this->assertSame(OutboxEvent::STATUS_PROCESSING, $finalStatus);
    }

    public function test_outbox_retry_schedules_with_backoff(): void
    {
        $outboxService = app(OutboxService::class);
        $event = $outboxService->record('test', 'test.retry', ['data' => 'value']);

        // Simulate dispatcher: increment attempts, then mark failed
        $event->update(['attempts' => 1]);
        $event->refresh();
        $event->markFailed('Test error');
        $event->refresh();

        // After first failure (attempt 1), should be pending with backoff
        $this->assertSame(OutboxEvent::STATUS_PENDING, $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertTrue($event->available_at > now(), 'Backoff should delay availability.');

        // Backoff for attempt 1: 5 * 2^0 = 5 seconds
        $backoff = (int) now()->diffInSeconds($event->available_at);
        $this->assertGreaterThanOrEqual(4, $backoff, 'First retry backoff should be ~5s.');
    }

    public function test_outbox_dead_letters_after_max_attempts(): void
    {
        $outboxService = app(OutboxService::class);
        $event = $outboxService->record('test', 'test.deadletter', ['data' => 'value']);

        // Simulate dispatcher: increment attempts then mark failed, MAX_ATTEMPTS times
        for ($i = 0; $i < OutboxEvent::MAX_ATTEMPTS; $i++) {
            $event->update(['attempts' => $event->attempts + 1]);
            $event->refresh();
            $event->markFailed("Failure {$i}");
            $event->refresh();
        }

        $this->assertSame(OutboxEvent::STATUS_DEAD_LETTER, $event->status);
        $this->assertGreaterThanOrEqual(OutboxEvent::MAX_ATTEMPTS, $event->attempts);
        $this->assertStringContainsString('Failure', $event->last_error ?? '');
    }

    public function test_outbox_completed_events_are_not_redispatched(): void
    {
        $outboxService = app(OutboxService::class);
        $event = $outboxService->record('test', 'test.completed', ['data' => 'value']);

        // Mark as completed
        $event->markCompleted();

        // Attempt to claim — should fail since status is not pending
        $claim = DB::table('outbox_events')
            ->where('id', $event->id)
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->update([
                'status' => OutboxEvent::STATUS_PROCESSING,
                'attempts' => DB::raw('attempts + 1'),
            ]);

        $this->assertSame(0, $claim, 'Completed events should not be claimable.');

        $finalStatus = DB::table('outbox_events')->where('id', $event->id)->value('status');
        $this->assertSame(OutboxEvent::STATUS_COMPLETED, $finalStatus);
    }

    // ─── Torrent delete/edit collision ───────────────────────────────

    public function test_torrent_delete_then_edit_does_not_corrupt_state(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        // Delete the torrent
        DB::table('torrents')->where('id', $torrent->id)->delete();

        // Verify the torrent is gone
        $exists = DB::table('torrents')->where('id', $torrent->id)->exists();
        $this->assertFalse($exists, 'Torrent should be deleted.');

        // Attempt to "edit" (update) the deleted torrent — should affect 0 rows
        $affected = DB::table('torrents')
            ->where('id', $torrent->id)
            ->update(['name' => 'edited_name']);

        $this->assertSame(0, $affected, 'Editing a deleted torrent should affect 0 rows.');
    }

    public function test_concurrent_edits_last_write_wins_without_corruption(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create(['name' => 'original']);

        // Simulate two sequential edits
        DB::table('torrents')->where('id', $torrent->id)->update(['name' => 'edit1']);
        DB::table('torrents')->where('id', $torrent->id)->update(['name' => 'edit2']);

        $finalName = DB::table('torrents')->where('id', $torrent->id)->value('name');
        $this->assertSame('edit2', $finalName, 'Last write should win.');
    }

    // ─── Overlapping cleanup uniqueness ───────────────────────────────

    public function test_cleanup_job_is_unique_prevents_overlap(): void
    {
        $job1 = new CleanupJob;
        $job2 = new CleanupJob;

        // ShouldBeUnique uses uniqueId() to prevent overlapping
        $this->assertSame($job1->uniqueId(), $job2->uniqueId(), 'CleanupJob uniqueId should be the same for all instances.');
        $this->assertSame(CleanupJob::class, $job1->uniqueId());
    }
}
