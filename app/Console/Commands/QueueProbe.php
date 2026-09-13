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
                            {--sleep=0 : Seconds the probe job stays busy before writing its marker}
                            {--wait : Poll until the marker lands (job actually ran)}
                            {--wait-started : Poll until the job reports running (it is in-flight)}
                            {--token= : Poll an existing probe job instead of dispatching a new one}
                            {--timeout=90 : Max seconds --wait/--wait-started polls before failing}';

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
        $token = (string) ($this->option('token') ?: '');
        if ($token === '') {
            $token = (string) Str::uuid();
            $sleep = max(0, (int) $this->option('sleep'));
            QueueProbeJob::dispatch($token, $sleep);
            $this->info("Probe dispatched (token={$token}, sleep={$sleep}s, queue=default)");
        }

        $expect = $this->option('wait-started') ? 'running' : 'done';
        if (! $this->option('wait') && ! $this->option('wait-started')) {
            $this->info("Not waiting — poll Cache key queue:probe:state:{$token} for 'done'.");

            return 0;
        }

        $timeout = max(1, (int) $this->option('timeout'));
        $deadline = time() + $timeout;
        while (time() < $deadline) {
            $state = Cache::get("queue:probe:state:{$token}");
            if ($state === $expect || ($expect === 'running' && $state === 'done')) {
                $this->info("PROBE_OK: job is {$expect}");

                return 0;
            }
            usleep(500000);
        }

        $this->error("PROBE_TIMEOUT: job never reached '{$expect}' within {$timeout}s");

        return 1;
    }
}
