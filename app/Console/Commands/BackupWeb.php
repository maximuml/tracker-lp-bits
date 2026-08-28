<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\ToolRepository;
use App\Support\Logger;
use Illuminate\Console\Command;
use Nexus\Nexus;

class BackupWeb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:web {--method=} {--transfer=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'BackupWeb web data, options: --method';

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
        $transfer = $this->option('transfer');
        $this->info("method: $method, transfer: $transfer");
        $rep = app(ToolRepository::class);
        $result = $rep->backupWeb($method, $transfer);
        $log = sprintf('[%s], %s, result: %s', Nexus::instance()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
