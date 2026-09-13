<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\QueueProbeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class QueueProbe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:probe
                            {--sleep=0 : Seconds the probe job sleeps before writing its marker}
                            {--wait : Poll until the marker lands (job actually ran)}
                            {--timeout=90 : Max seconds --wait polls before failing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a probe job through the real queue and optionally wait for its marker — verifies workers run jobs end-to-end.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $token = (string) Str::uuid();
        $sleep = max(0, (int) $this->option('sleep'));
        QueueProbeJob::dispatch($token, $sleep);
        $this->info("Probe dispatched (token={$token}, sleep={$sleep}s, queue=default)");

        if (! $this->option('wait')) {
            $this->info('Not waiting — poll Cache key '.QueueProbeJob::CACHE_KEY.' for the token.');

            return 0;
        }

        $timeout = max(1, (int) $this->option('timeout'));
        $deadline = time() + $timeout;
        while (time() < $deadline) {
            if (Cache::get(QueueProbeJob::CACHE_KEY) === $token) {
                $this->info("PROBE_OK: job completed within {$timeout}s budget");

                return 0;
            }
            usleep(500000);
        }

        $this->error("PROBE_TIMEOUT: marker not observed within {$timeout}s — jobs are not completing");

        return 1;
    }
}
