<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\OutboxEvent;
use App\Services\OutboxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * T-24: Transactional Outbox tests.
 */
final class OutboxServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('outbox_events')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_record_creates_pending_event(): void
    {
        $service = new OutboxService;

        $event = $service->record(
            aggregateType: 'user',
            eventType: 'user.registered',
            payload: ['user_id' => 1, 'username' => 'test'],
            aggregateId: 1,
        );

        $this->assertSame(OutboxEvent::STATUS_PENDING, $event->status);
        $this->assertSame(0, $event->attempts);
        $this->assertSame('user', $event->aggregate_type);
        $this->assertSame('user.registered', $event->event_type);
        $this->assertSame(1, $event->aggregate_id);
        $this->assertSame(['user_id' => 1, 'username' => 'test'], $event->payload);
        $this->assertNotEmpty($event->event_id);
    }

    public function test_record_purchase_completed(): void
    {
        $service = new OutboxService;

        $event = $service->recordPurchaseCompleted(
            userId: 10,
            torrentId: 20,
            purchaseData: ['price' => 100, 'channel' => 'Web'],
        );

        $this->assertSame('torrent', $event->aggregate_type);
        $this->assertSame('purchase.completed', $event->event_type);
        $this->assertSame(20, $event->aggregate_id);
        $this->assertSame(10, $event->payload['user_id']);
        $this->assertSame(20, $event->payload['torrent_id']);
        $this->assertSame(100, $event->payload['price']);
    }

    public function test_record_user_registered(): void
    {
        $service = new OutboxService;

        $event = $service->recordUserRegistered(
            userId: 5,
            userData: ['username' => 'newuser', 'email' => 'test@test.com'],
        );

        $this->assertSame('user', $event->aggregate_type);
        $this->assertSame('user.registered', $event->event_type);
        $this->assertSame(5, $event->aggregate_id);
        $this->assertSame('newuser', $event->payload['username']);
    }

    public function test_record_password_reset(): void
    {
        $service = new OutboxService;

        $event = $service->recordPasswordReset(userId: 7);

        $this->assertSame('user', $event->aggregate_type);
        $this->assertSame('password.reset', $event->event_type);
        $this->assertSame(7, $event->aggregate_id);
    }

    public function test_record_invite_consumed(): void
    {
        $service = new OutboxService;

        $event = $service->recordInviteConsumed(
            inviteId: 100,
            inviterId: 1,
            inviteeId: 2,
        );

        $this->assertSame('invite', $event->aggregate_type);
        $this->assertSame('invite.consumed', $event->event_type);
        $this->assertSame(100, $event->aggregate_id);
        $this->assertSame(1, $event->payload['inviter_id']);
        $this->assertSame(2, $event->payload['invitee_id']);
    }

    public function test_record_moderation_action(): void
    {
        $service = new OutboxService;

        $event = $service->recordModerationAction(
            moderatorId: 1,
            action: 'disable',
            targetUserId: 2,
            actionData: ['reason' => 'spam'],
        );

        $this->assertSame('moderation', $event->aggregate_type);
        $this->assertSame('moderation.action', $event->event_type);
        $this->assertSame(2, $event->aggregate_id);
        $this->assertSame('disable', $event->payload['action']);
        $this->assertSame('spam', $event->payload['reason']);
    }

    public function test_event_id_is_unique(): void
    {
        $service = new OutboxService;

        $event1 = $service->record('test', 'test.event', []);
        $event2 = $service->record('test', 'test.event', []);

        $this->assertNotEquals($event1->event_id, $event2->event_id);
    }

    public function test_mark_completed_sets_status_and_timestamp(): void
    {
        $service = new OutboxService;
        $event = $service->record('test', 'test.event', []);

        $event->markCompleted();

        $this->assertSame(OutboxEvent::STATUS_COMPLETED, $event->fresh()->status);
        $this->assertNotNull($event->fresh()->completed_at);
    }

    public function test_mark_failed_increments_attempts_and_schedules_retry(): void
    {
        $service = new OutboxService;
        $event = $service->record('test', 'test.event', []);

        $event->markFailed('Connection refused');
        $fresh = $event->fresh();

        $this->assertSame(OutboxEvent::STATUS_PENDING, $fresh->status);
        $this->assertSame('Connection refused', $fresh->last_error);
        $this->assertTrue($fresh->available_at > now());
    }

    public function test_mark_failed_dead_letters_after_max_attempts(): void
    {
        $service = new OutboxService;
        $event = $service->record('test', 'test.event', []);

        // Simulate max attempts
        $event->update(['attempts' => OutboxEvent::MAX_ATTEMPTS]);
        $event->markFailed('Persistent failure');

        $this->assertSame(OutboxEvent::STATUS_DEAD_LETTER, $event->fresh()->status);
    }

    public function test_is_eligible_for_pending_and_available(): void
    {
        $service = new OutboxService;
        $event = $service->record('test', 'test.event', []);

        $this->assertTrue($event->isEligible());

        $event->update(['available_at' => now()->addMinutes(5)]);
        $this->assertFalse($event->fresh()->isEligible());
    }
}
