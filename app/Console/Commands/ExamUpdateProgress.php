<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\ExamRepository;
use App\Support\RequestContext;
use Illuminate\Console\Command;

class ExamUpdateProgress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:update_progress {--uid=} {--bulk=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exam progress. options: --uid, --bulk';

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
        $bulk = $this->option('bulk');
        $examRep = app(ExamRepository::class);
        $log = "uid: $uid, bulk: $bulk";
        $this->info($log);
        if (is_numeric($uid) && $uid) {
            $log .= ', do updateProgress';
            $result = $examRep->updateProgress($uid);
        } elseif ($bulk) {
            $result = $examRep->updateProgressBulk();
            $log .= ', do updateProgressBulk';
        } else {
            $this->error('specific uid or bulk.');

            return 0;
        }
        $this->info(RequestContext::instance()->getRequestId().", $log, result: ".var_export($result, true));

        return 0;
    }
}
