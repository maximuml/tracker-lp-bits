<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OutboxEvent;
use App\Repositories\OutboxEventRepository;
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
    public function __construct(private readonly OutboxEventRepository $outboxEventRepository) {}

    /**
     * Dispatch up to $batchSize pending events.
     */
    public function dispatch(int $batchSize = 50): int
    {
        $dispatched = 0;

        $events = $this->outboxEventRepository->listPendingForDispatch($batchSize);

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
        $claimed = $this->outboxEventRepository->claimPending((int) $event->id);

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
        return $this->outboxEventRepository->countPending();
    }

    /**
     * Get dead-letter count for metrics.
     */
    public function deadLetterCount(): int
    {
        return $this->outboxEventRepository->countDeadLetter();
    }
}
