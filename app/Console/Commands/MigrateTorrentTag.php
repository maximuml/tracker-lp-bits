<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\TagRepository;
use App\Support\LegacyDb;
use App\Support\Logger;
use Illuminate\Console\Command;
use Nexus\Nexus;

class MigrateTorrentTag extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'torrent:migrate_tags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate exits torrent tags to new structure';

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
        $rep = app(TagRepository::class);
        $result = $rep->migrateTorrentTag();
        $log = sprintf('[%s], %s, result: %s, query: %s', Nexus::instance()->getRequestId(), __METHOD__, var_export($result, true), LegacyDb::lastQuery(false, 'json'));
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
