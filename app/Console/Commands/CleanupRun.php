<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

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

        $process = Process::fromShellCommandline("php {$script} {$arg}");
        $process->setTimeout(null);

        $begin = time();
        $exitCode = $process->run(function ($type, $buffer) {
            $this->output->write($buffer, false);
        });
        $cost = time() - $begin;

        $log = sprintf('[CLEANUP_RUN] DONE, cost time: %d seconds', $cost);
        do_log($log);
        $this->info($log);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
