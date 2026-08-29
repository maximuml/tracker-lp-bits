<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\ExamRepository;
use App\Support\Logger;
use App\Support\RequestContext;
use Illuminate\Console\Command;

class ExamAssign extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:assign {--uid=} {--exam_id=} {--begin=} {--end=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign exam to user, options: --uid, --exam_id, --begin, --end';

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
        $uid = (int) $this->option('uid');
        $examId = (int) $this->option('exam_id');
        $begin = $this->option('begin');
        $end = $this->option('end');
        $this->info(sprintf('uid: %s, examId: %s, begin: %s, end: %s', $uid, $examId, $begin, $end));
        $result = $examRep->assignToUser($uid, $examId, $begin, $end);
        $log = sprintf('[%s], %s, result: %s', RequestContext::instance()->getRequestId(), __METHOD__, var_export($result, true));
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return 0;
    }
}
