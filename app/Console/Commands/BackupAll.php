<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\ToolRepository;
use App\Support\Logger;
use App\Support\RequestContext;
use Illuminate\Console\Command;

class BackupAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:all {--method=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup all data, include web root and database. options: --method';

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
     * @return int
     */
    public function handle()
    {
        $method = $this->option('method');
        $this->info("method: $method");
        $rep = app(ToolRepository::class);
        $result = $rep->backupAll($method);
        $log = sprintf(
            '[%s], %s, result: %s',
            RequestContext::instance()->getRequestId(), __METHOD__, var_export($result, true)
        );
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
