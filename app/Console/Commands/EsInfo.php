<?php

namespace App\Console\Commands;

use App\Repositories\SearchRepository;
use App\Support\Logger;
use Illuminate\Console\Command;
use Nexus\Nexus;

class EsInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show Elasticsearch info';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rep = new SearchRepository;
        $result = $rep->getEsInfo();
        $log = sprintf("[%s], %s, result: \n%s", Nexus::instance()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
