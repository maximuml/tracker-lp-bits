<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * T-24: Transactional Outbox dispatcher.
 *
 * Claims pending outbox events, publishes them (via Redis pub/sub or
 * external system), and handles retry/backoff/dead-letter.
 *
 * Designed to be called from a scheduled command or queue worker.
 */
final class OutboxDispatcher
{
    public function __construct() {}

    /**
     * Dispatch up to $batchSize pending events.
     */
    public function dispatch(int $batchSize = 50): int
    {
        $dispatched = 0;

        /** @var list<OutboxEvent> $events */
        $events = OutboxEvent::query()
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->limit($batchSize)
            ->lockForUpdate()
            ->get();

        foreach ($events as $event) {
            if ($this->publish($event)) {
                $dispatched++;
            }
        }

        return $dispatched;
    }

    /**
     * Publish a single outbox event with idempotent handling.
     */
    private function publish(OutboxEvent $event): bool
    {
        // Atomic claim: only proceed if we can transition pending→processing
        $claimed = DB::table('outbox_events')
            ->where('id', $event->id)
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->update([
                'status' => OutboxEvent::STATUS_PROCESSING,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return false; // Already claimed by another worker
        }

        // Refresh the model to get the updated attempts count
        $event->refresh();

        try {
            $this->publishToChannel($event);
            $event->markCompleted();

            return true;
        } catch (\Throwable $e) {
            Log::error('Outbox dispatch failed', [
                'event_id' => $event->event_id,
                'event_type' => $event->event_type,
                'attempt' => $event->attempts,
                'error' => $e->getMessage(),
            ]);

            $event->markFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Publish event to Redis pub/sub channel.
     *
     * The event_id is used as the idempotency key — consumers should
     * deduplicate based on this UUID.
     */
    private function publishToChannel(OutboxEvent $event): void
    {
        $channel = "outbox:{$event->aggregate_type}";

        $message = json_encode([
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'aggregate_type' => $event->aggregate_type,
            'aggregate_id' => $event->aggregate_id,
            'payload' => $event->payload,
            'occurred_at' => $event->created_at->toIso8601String(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        Redis::connection()->publish($channel, $message);
    }

    /**
     * Get pending count for metrics.
     */
    public function pendingCount(): int
    {
        return (int) OutboxEvent::query()
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->count();
    }

    /**
     * Get dead-letter count for metrics.
     */
    public function deadLetterCount(): int
    {
        return (int) OutboxEvent::query()
            ->where('status', OutboxEvent::STATUS_DEAD_LETTER)
            ->count();
    }

    /**
     * Get the age of the oldest pending event in seconds.
     */
    public function oldestPendingAge(): int
    {
        $oldest = OutboxEvent::query()
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->oldest('created_at')
            ->value('created_at');

        if ($oldest === null) {
            return 0;
        }

        return abs((int) now()->diffInSeconds($oldest));
    }

    /**
     * Get average processing latency (created_at → completed_at) in seconds.
     */
    public function averageLatency(): float
    {
        $avg = OutboxEvent::query()
            ->where('status', OutboxEvent::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_latency')
            ->value('avg_latency');

        return (float) ($avg ?? 0);
    }
}
