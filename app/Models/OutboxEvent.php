<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * T-24: Transactional Outbox event.
 *
 * Each row represents a domain event that must be reliably published
 * to external systems. Written in the same DB transaction as the
 * domain change, then dispatched by the OutboxDispatcher.
 *
 * @property int $id
 * @property string $event_id UUID for idempotent publishing
 * @property string $aggregate_type e.g. "user", "torrent", "invite"
 * @property int|null $aggregate_id
 * @property string $event_type e.g. "purchase.completed", "user.registered"
 * @property array<string, mixed> $payload
 * @property string $status pending|processing|completed|dead_letter
 * @property int $attempts
 * @property Carbon $available_at
 * @property Carbon|null $completed_at
 * @property string|null $last_error
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class OutboxEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DEAD_LETTER = 'dead_letter';

    public const MAX_ATTEMPTS = 5;

    protected $table = 'outbox_events';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'available_at' => 'datetime',
        'completed_at' => 'datetime',
        'attempts' => 'integer',
        'aggregate_id' => 'integer',
    ];

    /**
     * Record a new outbox event (call within a DB transaction).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function record(
        string $aggregateType,
        string $eventType,
        array $payload,
        ?int $aggregateId = null,
        ?string $eventId = null,
    ): self {
        return self::query()->create([
            'event_id' => $eventId ?? Str::uuid()->toString(),
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
        ]);
    }

    /**
     * Mark as processing (atomic claim).
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as failed and schedule retry with exponential backoff.
     */
    public function markFailed(string $error): void
    {
        if ($this->attempts >= self::MAX_ATTEMPTS) {
            $this->update([
                'status' => self::STATUS_DEAD_LETTER,
                'last_error' => mb_substr($error, 0, 500),
            ]);

            return;
        }

        $backoff = $this->calculateBackoff();
        $this->update([
            'status' => self::STATUS_PENDING,
            'last_error' => mb_substr($error, 0, 500),
            'available_at' => now()->addSeconds($backoff),
        ]);
    }

    /**
     * Exponential backoff: 5s, 10s, 20s, 40s, 80s.
     */
    private function calculateBackoff(): int
    {
        return (int) (5 * (2 ** ($this->attempts - 1)));
    }

    /**
     * Whether this event is eligible for dispatch.
     */
    public function isEligible(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->available_at <= now();
    }
}
