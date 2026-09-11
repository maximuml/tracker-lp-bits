<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\PasswordHasher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Modernization plan step 2.1 item 4: flag every user still on a legacy
 * password hash for a forced password change at next login
 * (`must_change_password` → RequirePasswordChange middleware).
 *
 * This is the "hard reset" tool and intentionally refuses to run while the
 * legacy share is above `--threshold` (default 10%) without `--force` —
 * per the plan, forced reset is the LAST step, after the transparent
 * rehash-on-login has drained most accounts.
 *
 * Usage:
 *   php artisan users:force-reset-legacy                  # dry-run
 *   php artisan users:force-reset-legacy --apply          # mark flagged
 *   php artisan users:force-reset-legacy --apply --force  # skip threshold guard
 */
final class UsersForceResetLegacyCommand extends Command
{
    protected $signature = 'users:force-reset-legacy
                            {--apply : Actually set must_change_password=1 (default: dry-run)}
                            {--threshold=10 : Refuse if legacy share is above this percent, unless --force}
                            {--force : Ignore the threshold guard}';

    protected $description = 'Flag remaining legacy-hash users for forced password change (step 2.1)';

    public function handle(): int
    {
        $total = (int) DB::table('users')->count();
        // Same legacy definition as users:legacy-hash-report —
        // non-argon2id, empty, or NULL algo.
        $legacyQuery = fn () => DB::table('users')->where(fn ($q) => $q
            ->where('passhash_algo', '!=', PasswordHasher::ALGO_ARGON2ID)
            ->orWhereNull('passhash_algo')
            ->orWhere('passhash_algo', ''));
        $legacy = (int) $legacyQuery()->count();
        $alreadyFlagged = (int) $legacyQuery()->where('must_change_password', 1)->count();
        $share = $total > 0 ? $legacy * 100 / $total : 0.0;

        $this->info(sprintf(
            'Users: %d total, %d legacy-hash (%.2f%%), %d already flagged.',
            $total, $legacy, $share, $alreadyFlagged
        ));

        $threshold = (float) $this->option('threshold');
        if (! $this->option('force') && $share > $threshold) {
            $this->error(sprintf(
                'Legacy share %.2f%% is above the %.0f%% threshold — refusing to flag %d users. '
               .'Wait for rehash-on-login to drain them, or rerun with --force.',
                $share, $threshold, $legacy
            ));

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->warn(sprintf('Dry-run: would set must_change_password=1 for %d users. Pass --apply to execute.', $legacy));

            return self::SUCCESS;
        }

        $updated = (int) $legacyQuery()->where('must_change_password', '!=', 1)->update(['must_change_password' => 1]);
        $this->info(sprintf('Flagged %d users (must_change_password=1).', $updated));

        return self::SUCCESS;
    }
}
