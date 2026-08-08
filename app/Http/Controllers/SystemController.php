<?php

namespace App\Http\Controllers;

use App\Services\CleanupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SystemController extends LegacyController
{
    public function delacctadmin(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'delacctadmin', true);

    }

    public function deletedisabled(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'deletedisabled', true);

    }

    public function massmail(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'massmail', true);

    }

    public function takeamountupload(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takeamountupload', true);

    }

    public function takeinvite(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takeinvite', true);

    }

    public function takeupdate(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takeupdate', true);

    }

    public function docleanup(Request $request): Response
    {

        return \response(
            app(CleanupService::class)->runFull($request->boolean('forceall'), true),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );

    }

    public function mailtest(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'mailtest', true);

    }

    public function mysqlStats(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'mysql_stats', true);

    }

    public function cron(Request $request): Response
    {

        return \response(
            app(CleanupService::class)->triggerCron(),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );

    }

    public function incrementBulk(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'increment-bulk', true);

    }

    public function maxlogin(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'maxlogin', true);

    }

    public function setlistLookup(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageRaw($request, 'setlist_lookup', true);

    }

    public function takeIncrementBulk(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'take-increment-bulk', true);

    }

}