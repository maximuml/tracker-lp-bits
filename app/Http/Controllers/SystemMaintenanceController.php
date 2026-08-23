<?php

namespace App\Http\Controllers;

use App\Repositories\MysqlStatsRepository;
use App\Services\CleanupService;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SystemMaintenanceController extends LegacyController
{
    public function docleanup(Request $request): Response
    {

        return \response(
            app(CleanupService::class)->runFull($request->boolean('forceall'), true),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );

    }

    public function mailtest(Request $request): View|RedirectResponse|Response
    {

        return $this->legacyPage($request, 'mailtest', true);

    }

    public function mysqlStats(Request $request): View|RedirectResponse|Response
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/mysql_stats.php'.($qs ? '?'.$qs : ''));
        }

        if (UserDisplay::currentClass() < UC_SYSOP) {
            abort(403);
        }

        return $this->legacyPage($request, 'mysql_stats', true, MysqlStatsRepository::status());
    }

    public function cron(Request $request): Response
    {

        return \response(
            app(CleanupService::class)->triggerCron(),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );

    }
}
