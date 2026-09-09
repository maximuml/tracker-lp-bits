<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\OutboxEvent;
use App\Services\OutboxDispatcher;
use App\Services\OutboxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * T-24: OutboxDispatcher tests.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OutboxDispatcherTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('outbox_events')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function test_dispatch_completes_pending_event(): void
    {
        $service = new OutboxService;
        $service->record('test', 'test.event', ['data' => 'value']);

        // Mock Redis publish
        Redis::shouldReceive('connection->publish')
            ->once()
            ->withArgs(function (string $channel, string $message): bool {
                return $channel === 'outbox:test'
                    && str_contains($message, '"event_type":"test.event"');
            });

        $dispatcher = new OutboxDispatcher;
        $count = $dispatcher->dispatch();

        $this->assertSame(1, $count);
        $this->assertSame(OutboxEvent::STATUS_COMPLETED, OutboxEvent::first()->status);
    }

    public function test_dispatch_skips_future_events(): void
    {
        $service = new OutboxService;
        $event = $service->record('test', 'test.event', []);
        $event->update(['available_at' => now()->addMinutes(10)]);

        $dispatcher = new OutboxDispatcher;
        $count = $dispatcher->dispatch();

        $this->assertSame(0, $count);
        $this->assertSame(OutboxEvent::STATUS_PENDING, OutboxEvent::first()->status);
    }

    public function test_dispatch_handles_publish_failure_with_retry(): void
    {
        $service = new OutboxService;
        $service->record('test', 'test.event', []);

        // Mock Redis publish to throw
        Redis::shouldReceive('connection->publish')
            ->once()
            ->andThrow(new \RuntimeException('Redis down'));

        $dispatcher = new OutboxDispatcher;
        $count = $dispatcher->dispatch();

        $this->assertSame(0, $count);
        $event = OutboxEvent::first();
        $this->assertSame(OutboxEvent::STATUS_PENDING, $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertSame('Redis down', $event->last_error);
    }

    public function test_dispatch_dead_letters_after_max_attempts(): void
    {
        $service = new OutboxService;
        $event = $service->record('test', 'test.event', []);
        $event->update(['attempts' => OutboxEvent::MAX_ATTEMPTS]);

        Redis::shouldReceive('connection->publish')
            ->once()
            ->andThrow(new \RuntimeException('Persistent failure'));

        $dispatcher = new OutboxDispatcher;
        $dispatcher->dispatch();

        $this->assertSame(OutboxEvent::STATUS_DEAD_LETTER, OutboxEvent::first()->status);
    }

    public function test_pending_count(): void
    {
        $service = new OutboxService;
        $service->record('test', 'test.event', []);
        $service->record('test', 'test.event2', []);

        $dispatcher = new OutboxDispatcher;
        $this->assertSame(2, $dispatcher->pendingCount());
    }

    public function test_dead_letter_count(): void
    {
        $service = new OutboxService;
        $event = $service->record('test', 'test.event', []);
        $event->update(['status' => OutboxEvent::STATUS_DEAD_LETTER]);

        $dispatcher = new OutboxDispatcher;
        $this->assertSame(1, $dispatcher->deadLetterCount());
    }

    public function test_oldest_pending_age(): void
    {
        $service = new OutboxService;
        $event = $service->record('test', 'test.event', []);
        // Set created_at to 60 seconds ago
        $event->update(['created_at' => now()->subSeconds(60)]);
        // Refresh so the model has the updated created_at
        $event->refresh();

        $dispatcher = new OutboxDispatcher;
        $age = $dispatcher->oldestPendingAge();

        // The age should be at least 59 seconds (allowing for timing variance)
        $this->assertGreaterThanOrEqual(59, $age);
    }

    public function test_average_latency(): void
    {
        $service = new OutboxService;
        $event = $service->record('test', 'test.event', []);
        $event->update([
            'status' => OutboxEvent::STATUS_COMPLETED,
            'created_at' => now()->subSeconds(30),
            'completed_at' => now(),
        ]);

        $dispatcher = new OutboxDispatcher;
        $latency = $dispatcher->averageLatency();

        $this->assertGreaterThan(0, $latency);
    }
}
