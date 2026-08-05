<?php

namespace App\Http\Controllers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class LegacyPagesController extends Controller
{
    public function friends(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'friends');
    }

    public function messages(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'messages');
    }

    public function getrss(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'getrss');
    }

    public function sendmessage(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'sendmessage');
    }

    public function userhistory(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'userhistory');
    }

    public function invite(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'invite');
    }

    public function news(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'news');
    }

    public function makepoll(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'makepoll');
    }

    public function polloverview(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'polloverview');
    }

    public function attendance(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'attendance');
    }

    public function takeMessage(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'takemessage');
    }

    public function deletemessage(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'deletemessage');
    }

    public function report(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'report');
    }

    public function reports(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'reports');
    }

    public function bans(Request $request): View|RedirectResponse|Response
    {
        if ($request->isMethod('post')) {
            return $this->legacyWithRedirect($request, 'bans');
        }

        return $this->legacy($request, 'bans');
    }

    public function cheaterbox(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'cheaterbox');
    }

    public function cheaters(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'cheaters');
    }

    public function iphistory(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'iphistory');
    }

    public function ipcheck(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'ipcheck');
    }

    public function ipsearch(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'ipsearch');
    }

    public function modtask(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'modtask');
    }

    public function staff(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'staff');
    }

    public function staffbox(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'staffbox');
    }

    public function staffmess(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'staffmess');
    }

    public function takeStaffmess(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'takestaffmess');
    }

    public function contactstaff(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'contactstaff');
    }

    public function takecontact(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'takecontact');
    }

    public function modrules(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'modrules');
    }

    public function donorlist(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'donorlist');
    }

    public function stats(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'stats');
    }

    public function warned(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'warned');
    }

    public function nowarn(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'nowarn');
    }

    public function allagents(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'allagents');
    }

    public function checkuser(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'checkuser');
    }

    public function takeconfirm(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'takeconfirm');
    }

    public function userBanLog(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'user-ban-log');
    }

    private function legacy(Request $request, string $page): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! isset($GLOBALS['CURUSER'])) {
            $qs = $request->getQueryString();
            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        return view($page . '.index');
    }

    private function legacyWithRedirect(Request $request, string $page): Response|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! isset($GLOBALS['CURUSER'])) {
            $qs = $request->getQueryString();

            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        $content = view($page . '.index')->render();

        $headers = headers_list();
        $status = http_response_code();
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');

                return redirect($url, ($status >= 300 && $status < 400) ? $status : 302);
            }
        }

        return response($content);
    }
}
