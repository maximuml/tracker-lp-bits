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
}
