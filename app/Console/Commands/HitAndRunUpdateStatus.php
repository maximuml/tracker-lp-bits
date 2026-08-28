<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\HitAndRunRepository;
use App\Support\Logger;
use Illuminate\Console\Command;
use Nexus\Nexus;

class HitAndRunUpdateStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:update_status {--uid=} {--torrent_id=}  {--ignore_time=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update H&R status, options: --uid, --torrent_id, --ignore_time';

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
        $uid = $this->option('uid');
        $torrentId = $this->option('torrent_id');
        $ignoreTime = $this->option('ignore_time');
        $rep = new HitAndRunRepository;
        $rep->cronjobUpdateStatus($uid, $torrentId, $ignoreTime);
        $log = sprintf(
            '[%s], %s, uid: %s, torrentId: %s, ignoreTime: %s',
            Nexus::instance()->getRequestId(), __METHOD__, $uid, $torrentId, $ignoreTime
        );
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
