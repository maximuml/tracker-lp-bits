<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\TokenRepository;
use App\Support\LegacyDb;
use App\Support\Logger;
use App\Support\RequestContext;
use Illuminate\Console\Command;

class DeleteExpiredToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:delete_expired_token {--uid=} {--days=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete user expired token, options: --uid, --days';

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
    public function handle(TokenRepository $tokenRepository)
    {
        $uid = $this->option('uid');
        $days = $this->option('days');
        if (! is_numeric($days)) {
            $days = 60;
        }
        $log = sprintf('uid: %s, days: %s', $uid, $days);
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        $result = $tokenRepository->deleteExpiredPersonalAccessTokens($uid !== null ? (int) $uid : null, (int) $days);
        $log = sprintf('[%s], %s, result: %s, query: %s', RequestContext::instance()->getRequestId(), __METHOD__, var_export($result, true), LegacyDb::lastQuery(false, 'json'));
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
