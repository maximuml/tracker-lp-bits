<?php

namespace App\Http\Controllers;

use App\Services\CleanupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ManagementController extends LegacyController
{
    public function report(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'report');
    }

    public function reports(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'reports');
    }

    public function bans(Request $request): View|RedirectResponse|Response
    {
        if ($request->isMethod('post')) {
            return $this->legacyPageWithRedirect($request, 'bans');
        }

        return $this->legacyPage($request, 'bans');
    }

    public function cheaterbox(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'cheaterbox');
    }

    public function cheaters(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'cheaters');
    }

    public function iphistory(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'iphistory');
    }

    public function ipcheck(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'ipcheck');
    }

    public function ipsearch(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'ipsearch');
    }

    public function modtask(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'modtask');
    }

    public function staff(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'staff');
    }

    public function staffbox(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'staffbox');
    }

    public function staffmess(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'staffmess');
    }

    public function takeStaffmess(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'takestaffmess');
    }

    public function contactstaff(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'contactstaff');
    }

    public function takecontact(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'takecontact');
    }

    public function modrules(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'modrules');
    }

    public function donorlist(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'donorlist');
    }

    public function stats(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'stats');
    }

    public function warned(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'warned');
    }

    public function nowarn(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'nowarn');
    }

    public function allagents(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'allagents');
    }

    public function checkuser(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'checkuser');
    }

    public function takeconfirm(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'takeconfirm');
    }

    public function userBanLog(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'user-ban-log');
    }

    public function clearCache(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'clearcache');
    }

    public function catmanage(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'catmanage');
    }

    public function fields(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'fields');
    }

    public function formats(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'formats');
    }

    public function videoformats(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'videoformats');
    }

    public function bonusLog(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'bonus-log', true);
    }

    public function medal(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'medal', true);
    }

    public function task(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'task', true);
    }

    public function uploaders(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'uploaders', true);
    }

    public function settings(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'settings', true);
    }

    public function freeleech(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'freeleech', true);
    }

    public function magic(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'magic', true);
    }

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

    public function users(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'users', true);
    }

    public function staffpanel(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'staffpanel', true);
    }

    public function docleanup(Request $request): Response
    {
        return \response(
            app(CleanupService::class)->runFull($request->boolean('forceall'), true),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    public function location(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'location', true);
    }

    public function mailtest(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'mailtest', true);
    }

    public function mysqlStats(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'mysql_stats', true);
    }

    public function reset(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'reset', true);
    }

    public function selfEnable(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'self-enable', true);
    }

    public function unco(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'unco', true);
    }

    public function adduser(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'adduser', true);
    }

    public function complains(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'complains', false);
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

    public function testip(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'testip', true);
    }

}
