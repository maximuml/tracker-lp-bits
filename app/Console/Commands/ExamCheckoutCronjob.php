<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\ExamRepository;
use App\Support\Logger;
use Illuminate\Console\Command;
use Nexus\Nexus;

class ExamCheckoutCronjob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:checkout_cronjob {--ignore-time-range}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checkout exam cronjob, options: --ignore-time-range';

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
        $examRep = app(ExamRepository::class);
        $ignoreTimeRange = $this->option('ignore-time-range');
        $this->info('ignore-time-range: '.var_export($ignoreTimeRange, true));
        $result = $examRep->cronjobCheckout($ignoreTimeRange);
        $log = sprintf('[%s], %s, result: %s', Nexus::instance()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
