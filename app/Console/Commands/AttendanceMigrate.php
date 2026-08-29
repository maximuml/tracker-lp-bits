<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\AttendanceRepository;
use App\Support\LegacyDb;
use App\Support\Logger;
use App\Support\RequestContext;
use Illuminate\Console\Command;

class AttendanceMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate attendance from one time one record to one user one record.';

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
     */
    public function handle(): int
    {
        $rep = app(AttendanceRepository::class);
        $result = $rep->migrateAttendance();
        $log = sprintf('[%s], %s, result: %s, query: %s', RequestContext::instance()->getRequestId(), __METHOD__, var_export($result, true), LegacyDb::lastQuery(false, 'json'));
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
