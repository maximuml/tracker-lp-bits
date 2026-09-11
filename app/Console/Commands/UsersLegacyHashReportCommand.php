<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\PasswordHasher;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Modernization plan step 2.1: report how many users are still on legacy
 * password hashes (`sha256`, `md5`) vs the modern `argon2id`.
 *
 * Rehash-on-login is already transparent (see PasswordHasher::needsRehash),
 * so the remaining share tells us how many accounts need the forced-reset
 * path before the md5 branch can be removed from PasswordHasher::verify.
 *
 * Usage:
 *   php artisan users:legacy-hash-report
 *   php artisan users:legacy-hash-report --list=md5
 */
final class UsersLegacyHashReportCommand extends Command
{
    protected $signature = 'users:legacy-hash-report
                            {--list= : List up to 100 user IDs/usernames for the given passhash_algo}';

    protected $description = 'Report user counts per password hash algorithm (step 2.1)';

    private const LIST_LIMIT = 100;

    public function handle(): int
    {
        $rows = DB::table('users')
            ->selectRaw("COALESCE(NULLIF(passhash_algo, ''), '".PasswordHasher::ALGO_SHA256."') AS algo")
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('algo')
            ->orderByDesc('total')
            ->get();

        $total = (int) $rows->sum('total');
        $legacy = (int) $rows->where('algo', '!=', PasswordHasher::ALGO_ARGON2ID)->sum('total');

        $this->table(
            ['passhash_algo', 'users', '%'],
            $rows->map(fn ($row) => [
                (string) $row->algo,
                (int) $row->total,
                $total > 0 ? number_format((int) $row->total * 100 / $total, 2) : '0.00',
            ])->all()
        );

        $this->info(sprintf(
            'Total users: %d. Legacy (non-%s): %d (%.2f%%).',
            $total,
            PasswordHasher::ALGO_ARGON2ID,
            $legacy,
            $total > 0 ? $legacy * 100 / $total : 0.0
        ));

        if ($legacy > 0) {
            $this->line('Accounts on legacy hashes migrate to argon2id on next successful login;');
            $this->line('the rest need a forced reset before the legacy verify branches can be removed.');
        }

        $listAlgo = $this->option('list');
        if (is_string($listAlgo) && $listAlgo !== '') {
            $this->listUsers($listAlgo);
        }

        return self::SUCCESS;
    }

    private function listUsers(string $algo): void
    {
        $query = DB::table('users')->select(['id', 'username', 'last_login'])->orderBy('id');
        if ($algo === PasswordHasher::ALGO_SHA256) {
            $query->where(fn (Builder $q) => $q->where('passhash_algo', $algo)->orWhereNull('passhash_algo')->orWhere('passhash_algo', ''));
        } else {
            $query->where('passhash_algo', $algo);
        }

        $count = (clone $query)->count();
        $users = $query->limit(self::LIST_LIMIT)->get();

        $this->table(
            ['id', 'username', 'last_login'],
            $users->map(fn ($u) => [$u->id, $u->username, $u->last_login ?? 'never'])->all()
        );

        if ($count > self::LIST_LIMIT) {
            $this->warn(sprintf('Showing %d of %d users with passhash_algo=%s.', self::LIST_LIMIT, $count, $algo));
        }
    }
}
