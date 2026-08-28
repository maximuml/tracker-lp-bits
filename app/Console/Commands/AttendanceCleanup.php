<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\AttendanceRepository;
use App\Support\Logger;
use Illuminate\Console\Command;
use Nexus\Nexus;

class AttendanceCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup attendance data.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $rep = new AttendanceRepository;
        $result = $rep->cleanup();
        $log = sprintf(
            '[%s], %s, result: %s',
            Nexus::instance()->getRequestId(), __METHOD__, var_export($result, true)
        );
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
    }
}
