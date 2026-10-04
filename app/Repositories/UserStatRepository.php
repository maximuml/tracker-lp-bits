<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\PasswordHasher;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Bulk user-table maintenance reads/writes for cron jobs and CLI
 * commands — seed-time/seed-bonus upserts, legacy-hash audit queries.
 * Reads stay on `toBase()` rows; no model hydration on the hot sweep
 * paths.
 */
final class UserStatRepository
{
    /**
     * Narrow column rows for the seed-bonus job — the job maps each row
     * to a plain array.
     *
     * @param  array<int, int>  $ids
     * @param  array<int, string>  $columns
     * @return Collection<int, array<string, mixed>>
     */
    public function listStatRowsByIds(array $ids, array $columns): Collection
    {
        /** @var Collection<int, array<string, mixed>> */
        return User::query()
            ->toBase()
            ->whereIn('id', $ids)
            ->select($columns)
            ->get()
            ->map(fn ($row) => (array) $row);
    }

    /**
     * Seed/leech time totals upsert — UpdateUserSeedingLeechingTime.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function upsertSeedTimes(array $rows): int
    {
        return User::query()->upsert($rows, ['id'], ['seedtime', 'leechtime', 'seed_time_updated_at']);
    }

    /**
     * Seed-bonus totals upsert — SeedBonusJob.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function upsertSeedBonus(array $rows): int
    {
        return User::query()->upsert($rows, ['id'], ['seed_points', 'seed_points_per_hour', 'seed_bonus_per_hour', 'seedbonus', 'seeding_torrent_count', 'seeding_torrent_size', 'seed_points_updated_at']);
    }

    public function countAll(): int
    {
        return (int) User::query()->count();
    }

    /**
     * Count users on a legacy (non-Argon2id) password hash.
     */
    public function countLegacyHash(bool $flaggedOnly = false): int
    {
        $query = User::query()->where(fn ($q) => $q
            ->where('passhash_algo', '!=', PasswordHasher::ALGO_ARGON2ID)
            ->orWhereNull('passhash_algo')
            ->orWhere('passhash_algo', ''));
        if ($flaggedOnly) {
            $query->where('must_change_password', 1);
        }

        return (int) $query->count();
    }

    /**
     * User counts grouped by effective hash algorithm (empty/NULL folds
     * into sha256) — users:legacy-hash-report.
     *
     * @return Collection<int, array{algo: string, total: int}>
     */
    public function listLegacyHashAlgoCounts(): Collection
    {
        /** @var Collection<int, array{algo: string, total: int}> */
        return User::query()
            ->toBase()
            ->selectRaw("COALESCE(NULLIF(passhash_algo, ''), '".PasswordHasher::ALGO_SHA256."') AS algo")
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('algo')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => (array) $row);
    }

    /**
     * Users on one hash algorithm for the report's --list mode.
     *
     * @return Collection<int, array{id: int, username: string, last_login: string|null}>
     */
    public function listLegacyHashUsers(string $algo, int $limit): Collection
    {
        $query = User::query()->toBase()->select(['id', 'username', 'last_login'])->orderBy('id');
        if ($algo === PasswordHasher::ALGO_SHA256) {
            $query->where(fn ($q) => $q->where('passhash_algo', $algo)->orWhereNull('passhash_algo')->orWhere('passhash_algo', ''));
        } else {
            $query->where('passhash_algo', $algo);
        }

        /** @var Collection<int, array{id: int, username: string, last_login: string|null}> */
        return $query->limit($limit)->get()->map(fn ($row) => (array) $row);
    }

    /**
     * Users on one hash algorithm — mirrors listLegacyHashUsers' filter.
     */
    public function countHashUsers(string $algo): int
    {
        $query = User::query()->toBase();
        if ($algo === PasswordHasher::ALGO_SHA256) {
            $query->where(fn ($q) => $q->where('passhash_algo', $algo)->orWhereNull('passhash_algo')->orWhere('passhash_algo', ''));
        } else {
            $query->where('passhash_algo', $algo);
        }

        return (int) $query->count();
    }

    /**
     * Flag every remaining legacy-hash user with must_change_password=1
     * — users:force-reset-legacy --apply.
     */
    public function flagLegacyHashUsers(): int
    {
        return (int) User::query()
            ->where(fn ($q) => $q
                ->where('passhash_algo', '!=', PasswordHasher::ALGO_ARGON2ID)
                ->orWhereNull('passhash_algo')
                ->orWhere('passhash_algo', ''))
            ->where('must_change_password', '!=', 1)
            ->update(['must_change_password' => 1]);
    }

    /**
     * Enabled+confirmed user ids in the given classes, paged —
     * bulk-message/increment sweeps.
     *
     * @param  array<int, int>  $classIds
     * @return Collection<int, int>
     */
    public function listActiveUserIdsByClasses(array $classIds, int $offset, int $limit): Collection
    {
        return User::query()
            ->whereIn('class', $classIds)
            ->where('enabled', true)
            ->where('status', UserStatus::CONFIRMED->value)
            ->offset($offset)
            ->limit($limit)
            ->pluck('id');
    }

    /**
     * Legacy-hash users ordered for the report cursor (md5/sha256/NULL
     * only — argon2id excluded; algo/day filters applied when given).
     *
     * @return LazyCollection<int, User>
     */
    public function cursorLegacyHashUsers(?string $algo, ?int $daysSinceLogin): LazyCollection
    {
        $query = User::query()
            ->select(['id', 'username', 'passhash_algo', 'last_login', 'class'])
            ->where(fn ($q) => $q
                ->whereIn('passhash_algo', [PasswordHasher::ALGO_MD5, PasswordHasher::ALGO_SHA256])
                ->orWhereNull('passhash_algo'));
        if ($algo !== null) {
            $query->where('passhash_algo', $algo);
        }
        if ($daysSinceLogin !== null) {
            $query->where(function ($q) use ($daysSinceLogin): void {
                $q->whereNull('last_login')
                    ->orWhere('last_login', '<', now()->subDays($daysSinceLogin));
            });
        }

        return $query->orderBy('passhash_algo')->orderBy('id')->cursor();
    }
}
