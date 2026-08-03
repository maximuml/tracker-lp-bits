<?php

namespace App\Console\Commands;

use App\Support\Environment;
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
    public function handle()
    {
        $force = $this->option('force');
        $script = base_path('include/cleanup_cli.php');
        $arg = $force ? '1' : '0';
        $command = "php $script $arg";

        $begin = time();

        try {
            $output = Environment::run($command, 'array', false, true);
        } catch (\RuntimeException $e) {
            $cost = time() - $begin;
            $this->error('Cleanup worker failed after ' . $cost . ' seconds:');
            foreach (explode("\n", trim($e->getMessage())) as $line) {
                $this->error($line);
            }

            return Command::FAILURE;
        }

        $cost = time() - $begin;

        foreach ($output as $line) {
            $this->line($line);
        }

        $log = sprintf('[CLEANUP_RUN] DONE, cost time: %d seconds', $cost);
        do_log($log);
        $this->info($log);

        return Command::SUCCESS;
    }
}
