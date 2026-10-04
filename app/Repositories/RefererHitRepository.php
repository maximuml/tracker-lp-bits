<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\RefererHit;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Aggregated external-referer hits (one row per host per day).
 */
class RefererHitRepository extends BaseRepository
{
    /**
     * Increment today's hit counter for the host, inserting the aggregate
     * row on first sight. A lost unique-key race folds into the existing
     * row instead of erroring.
     */
    public function recordHit(string $host, string $path): void
    {
        $now = now();
        $today = $now->toDateString();
        $path = mb_substr('/'.ltrim($path, '/'), 0, 255);

        $updated = RefererHit::query()
            ->where('host', $host)
            ->where('date', $today)
            ->update([
                'hits' => DB::raw('hits + 1'),
                'last_path' => $path,
                'last_seen_at' => $now,
            ]);

        if ($updated > 0) {
            return;
        }

        try {
            RefererHit::query()->create([
                'host' => $host,
                'date' => $today,
                'hits' => 1,
                'last_path' => $path,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
        } catch (Throwable) {
            RefererHit::query()
                ->where('host', $host)
                ->where('date', $today)
                ->increment('hits');
        }
    }
}
