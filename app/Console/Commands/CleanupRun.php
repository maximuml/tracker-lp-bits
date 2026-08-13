<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CleanupService;
use Illuminate\Console\Command;

final class CleanupRun extends Command
{
    /** @var string */
    protected $signature = 'cleanup:run {--force : Run all cleanup tasks regardless of last run time}';

    /** @var string */
    protected $description = 'Run periodic cleanup tasks (peers, visibility, forum/offers, users, dead torrents).';

    /**
     * @return int
     */
    public function handle(CleanupService $service): int
    {
        $lockFile = sprintf('%s/nexus_cleanup_cli.lock', sys_get_temp_dir());
        $fd = fopen($lockFile, 'c');
        if ($fd === false || ! flock($fd, LOCK_EX | LOCK_NB)) {
            $this->warn('Cleanup already running.');

            return Command::SUCCESS;
        }

        register_shutdown_function(function () use ($fd, $lockFile): void {
            flock($fd, LOCK_UN);
            fclose($fd);
            @unlink($lockFile);
        });

        $force = $this->option('force');

        $begin = time();
        $output = $service->runAll($force, true);
        $cost = time() - $begin;

        if ($output === false) {
            $this->warn('Cleanup not triggered.');

            return Command::SUCCESS;
        }

        $this->output->write((string) $output, false);

        $log = sprintf('[CLEANUP_RUN] DONE, cost time: %d seconds', $cost);
        \App\Support\Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
        $this->info($log);

        return Command::SUCCESS;
    }
}
