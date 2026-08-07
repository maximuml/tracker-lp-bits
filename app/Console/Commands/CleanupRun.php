<?php

namespace App\Console\Commands;

use App\Services\CleanupService;
use Illuminate\Console\Command;

class CleanupRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:run {--force : Run all cleanup tasks regardless of last run time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run legacy periodic cleanup tasks (autoclean / docleanup).';

    /**
     * Execute the console command.
     * @return int
     */
    public function handle(CleanupService $service)
    {
        $lockFile = sprintf('%s/nexus_cleanup_cli.lock', sys_get_temp_dir());
        $fd = fopen($lockFile, 'c');
        if ($fd === false || !flock($fd, LOCK_EX | LOCK_NB)) {
            $this->warn('Cleanup already running.');

            return Command::SUCCESS;
        }

        register_shutdown_function(function () use ($fd, $lockFile) {
            flock($fd, LOCK_UN);
            fclose($fd);
            @unlink($lockFile);
        });

        $force = $this->option('force');

        $begin = time();
        $output = $service->runFull($force, true);
        $cost = time() - $begin;

        $this->output->write($output, false);

        $log = sprintf('[CLEANUP_RUN] DONE, cost time: %d seconds', $cost);
        do_log($log);
        $this->info($log);

        return Command::SUCCESS;
    }
}
