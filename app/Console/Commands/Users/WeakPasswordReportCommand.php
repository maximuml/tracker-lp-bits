<?php

declare(strict_types=1);

namespace App\Console\Commands\Users;

use App\Models\User;
use App\Repositories\UserStatRepository;
use App\Support\PasswordHasher;
use Illuminate\Console\Command;
use Illuminate\Support\LazyCollection;
use Symfony\Component\Console\Helper\Table;

/**
 * Report users with legacy password hashes (md5 or sha256).
 *
 * W2-01: This is a read-only diagnostic step before retiring md5 support.
 * The next steps are:
 *   1. Force a transparent rehash on login (already active in NexusWebUserProvider
 *      and WebAuthService).
 *   2. Announce a migration window for any remaining md5 users.
 *   3. After the window, disable md5 verification and require a password reset.
 */
final class WeakPasswordReportCommand extends Command
{
    /** @var string */
    protected $signature = 'users:legacy-password-report
        {--days-since-login= : Only include users whose last login was more than N days ago}
        {--format=table : Output format: table or json}
        {--algo= : Filter by algorithm: md5 or sha256}';

    /** @var string */
    protected $description = 'Report users with legacy password hashes (md5 or sha256)';

    public function handle(UserStatRepository $userStatRepository): int
    {
        $algo = $this->option('algo') !== null ? (string) $this->option('algo') : null;
        $days = $this->option('days-since-login') !== null ? (int) $this->option('days-since-login') : null;

        /** @var LazyCollection<int, User> $users */
        $users = $userStatRepository->cursorLegacyHashUsers($algo, $days);

        $byAlgo = [
            PasswordHasher::ALGO_MD5 => 0,
            PasswordHasher::ALGO_SHA256 => 0,
            'null' => 0,
        ];
        $rows = [];

        foreach ($users as $user) {
            $algo = $user->passhash_algo ?? 'null';
            $byAlgo[$algo === 'null' ? 'null' : $algo]++;

            $rows[] = [
                $user->id,
                $user->username,
                $algo,
                $user->last_login?->toDateTimeString() ?? 'never',
                (string) $user->class,
            ];
        }

        if ($this->option('format') === 'json') {
            $json = json_encode([
                'summary' => $byAlgo,
                'total' => count($rows),
                'users' => $rows,
            ], JSON_PRETTY_PRINT);

            if ($json === false) {
                $this->error('Failed to encode report to JSON.');

                return self::FAILURE;
            }

            $this->line($json);

            return self::SUCCESS;
        }

        $this->info('Legacy password hash report');
        $this->newLine();
        $this->line('Summary:');
        foreach ($byAlgo as $algo => $count) {
            $this->line("  {$algo}: {$count}");
        }
        $this->line('  total: '.count($rows));
        $this->newLine();

        if ($rows !== []) {
            $table = new Table($this->output);
            $table->setHeaders(['id', 'username', 'algo', 'last_login', 'class']);
            $table->setRows($rows);
            $table->render();
        } else {
            $this->info('No legacy password hashes found.');
        }

        return self::SUCCESS;
    }
}
