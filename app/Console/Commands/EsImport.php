<?php

namespace App\Console\Commands;

use App\Repositories\SearchRepository;
use App\Support\Logger;
use Illuminate\Console\Command;
use Nexus\Nexus;

class EsImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:import {--torrent_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import torrent to Elasticsearch, options: --torrent_id';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rep = new SearchRepository;
        $torrentId = $this->option('torrent_id');
        $this->info("torrent_id: $torrentId");
        $result = $rep->import($torrentId);
        $log = sprintf("[%s], %s, result: \n%s", Nexus::instance()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
