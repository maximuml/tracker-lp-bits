<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

final class OutboxEventRepository
{
    /**
     * Pending events due for dispatch, locked for update inside the
     * caller's transaction.
     *
     * @return EloquentCollection<int, OutboxEvent>
     */
    public function listPendingForDispatch(int $limit): EloquentCollection
    {
        /** @var EloquentCollection<int, OutboxEvent> */
        return OutboxEvent::query()
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->limit($limit)
            ->lockForUpdate()
            ->get();
    }

    /**
     * Atomic pending → processing claim (0 when another worker won).
     */
    public function claimPending(int $id): int
    {
        return OutboxEvent::query()
            ->where('id', $id)
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->update([
                'status' => OutboxEvent::STATUS_PROCESSING,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);
    }

    public function countPending(): int
    {
        return (int) OutboxEvent::query()
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->count();
    }

    public function countDeadLetter(): int
    {
        return (int) OutboxEvent::query()
            ->where('status', OutboxEvent::STATUS_DEAD_LETTER)
            ->count();
    }

    /**
     * Single-pass aggregate for /metrics: pending/dead-letter counts,
     * oldest pending age, average dispatch latency.
     *
     * @return array{pending: int|string|null, dead_letter: int|string|null, oldest_pending: string|null, avg_latency: int|float|string|null}|null
     */
    public function collectStatusStats(): ?array
    {
        $row = OutboxEvent::query()->toBase()
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dead_letter, '.
                'MIN(CASE WHEN status = ? THEN created_at END) as oldest_pending, '.
                'AVG(CASE WHEN status = ? AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, completed_at) END) as avg_latency',
            )
            ->addBinding([
                OutboxEvent::STATUS_PENDING,
                OutboxEvent::STATUS_DEAD_LETTER,
                OutboxEvent::STATUS_PENDING,
                OutboxEvent::STATUS_COMPLETED,
            ], 'select')
            ->first();

        /** @var array{pending: int|string|null, dead_letter: int|string|null, oldest_pending: string|null, avg_latency: int|float|string|null}|null */
        return $row === null ? null : (array) $row;
    }
}
