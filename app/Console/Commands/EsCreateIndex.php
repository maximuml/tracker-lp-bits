<?php

namespace App\Console\Commands;

use App\Repositories\SearchRepository;
use App\Support\Logger;
use Illuminate\Console\Command;
use Nexus\Nexus;

class EsCreateIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:create_index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create index in Elasticsearch';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rep = new SearchRepository;
        $result = $rep->createIndex();
        $log = sprintf("[%s], %s, result: \n%s", Nexus::instance()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
