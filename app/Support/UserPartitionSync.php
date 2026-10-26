<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserPreference;
use App\Models\UserSeedStats;
use Illuminate\Contracts\Database\Query\Expression;

/**
 * Mirrors `users` writes into the vertical partition tables
 * (user_preferences / user_activity / user_seed_stats) for update
 * paths that bypass Eloquent model events — query-builder
 * `->update()` and `DB::table()` calls do not fire
 * UserPartitionObserver, so without this the partitions go stale.
 *
 * Single implementation shared by the observer (model writes) and
 * the repository call sites (builder writes).
 */
final class UserPartitionSync
{
    /**
     * Sync every partitioned column present in $fields for the user.
     * Non-partitioned keys and query expressions are skipped.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function sync(int $userId, array $fields): void
    {
        $grouped = [];
        foreach ($fields as $column => $value) {
            $table = User::$partitionedColumns[$column] ?? null;
            if ($table !== null && ! $value instanceof Expression) {
                $grouped[$table][$column] = $value;
            }
        }

        foreach ($grouped as $table => $data) {
            match ($table) {
                'user_preferences' => UserPreference::query()->updateOrCreate(['user_id' => $userId], $data),
                'user_activity' => UserActivity::query()->updateOrCreate(['user_id' => $userId], $data),
                'user_seed_stats' => UserSeedStats::query()->updateOrCreate(['user_id' => $userId], $data),
                default => null,
            };
        }
    }
}
