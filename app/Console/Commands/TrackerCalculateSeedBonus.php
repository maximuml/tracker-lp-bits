<?php

namespace App\Console\Commands;

use App\Support\Bonus;
use App\Support\Logger;
use Illuminate\Console\Command;
use Nexus\Nexus;

class TrackerCalculateSeedBonus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:calculate_seed_bonus {uid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate user seed bonus.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        $result = Bonus::calculateForUser($uid);
        $log = sprintf(
            "[%s], %s, uid: %s, result: \n%s",
            Nexus::instance()->getRequestId(), __METHOD__, $uid, var_export($result, true)
        );
        $this->info($log);
        Logger::writeWithContext($log);

        return 0;
    }
}
