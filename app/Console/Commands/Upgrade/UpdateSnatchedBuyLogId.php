<?php

declare(strict_types=1);

namespace App\Console\Commands\Upgrade;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateSnatchedBuyLogId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upgrade:migrate_snatched_buy_log_id';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        DB::statement('UPDATE snatched INNER JOIN torrent_buy_logs ON snatched.userid = torrent_buy_logs.uid AND snatched.torrentid = torrent_buy_logs.torrent_id SET snatched.buy_log_id = torrent_buy_logs.id');
    }
}
