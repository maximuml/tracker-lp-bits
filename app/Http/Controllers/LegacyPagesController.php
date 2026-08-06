<?php

namespace App\Http\Controllers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
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

    public function bookmark(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'bookmark', false);
    }

    public function fastDelete(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'fastdelete');
    }

    public function torrentInfo(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'torrent_info');
    }

    public function viewFileList(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'viewfilelist', false);
    }

    public function viewPeerList(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'viewpeerlist', false);
    }

    public function viewSnatches(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'viewsnatches');
    }

    public function takeFlush(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'takeflush');
    }

    public function takeReseed(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'takereseed');
    }

    public function clearCache(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'clearcache');
    }

    public function aboutNexus(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'aboutnexus', false);
    }

    public function rules(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'rules', false);
    }

    public function userAgreement(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'useragreement', false);
    }

    public function faq(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'faq', false);
    }

    public function donate(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'donate', false);
    }

    public function donated(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'donated');
    }

    public function faqManage(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'faqmanage');
    }

    public function faqActions(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'faqactions');
    }

    public function search(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'search');
    }

    public function usersearch(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'usersearch');
    }

    public function getUserTorrentListAjax(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'getusertorrentlistajax', false);
    }

    public function searchSuggest(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'searchsuggest', false);
    }

    public function autocompleteTorrents(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'autocomplete_torrents');
    }

    public function ajax(Request $request): JsonResponse|RedirectResponse
    {
        if (! defined('IN_NEXUS')) {
            $qs = $request->getQueryString();

            return redirect('/ajax.php' . ($qs ? '?' . $qs : ''));
        }

        $action = (string) $request->input('action', '');
        $params = $request->input('params', []);

        $passkeyActions = ['getPasskeyGetArgs', 'processPasskeyGet'];
        if (! in_array($action, $passkeyActions, true)) {
            loggedinorreturn();
        }

        if (! class_exists('AjaxInterface')) {
            view('ajax._ajax_legacy')->render();
        }

        try {
            $callable = ['AjaxInterface', $action];
            if (! is_callable($callable)) {
                do_log("hacking attempt made by " . ($GLOBALS['CURUSER']['username'] ?? 'guest') . ",uid " . ($GLOBALS['CURUSER']['id'] ?? 0), 'error');
                throw new \RuntimeException("Invalid action: {$action}");
            }

            $result = call_user_func($callable, $params);

            return response()->json(success($result));
        } catch (\Throwable $exception) {
            do_log($exception->getMessage() . $exception->getTraceAsString(), 'error');

            return response()->json(fail($exception->getMessage(), $request->all()));
        }
    }

    private function legacy(Request $request, string $page, bool $auth = true): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ($auth && ! isset($GLOBALS['CURUSER']))) {
            $qs = $request->getQueryString();
            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        return view($page . '.index');
    }

    private function legacyWithRedirect(Request $request, string $page, bool $auth = true): Response|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ($auth && ! isset($GLOBALS['CURUSER']))) {
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

    private function legacyRaw(Request $request, string $page, bool $auth = true): Response|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ($auth && ! isset($GLOBALS['CURUSER']))) {
            $qs = $request->getQueryString();

            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        $content = view($page . '.index')->render();

        $headers = headers_list();
        $responseHeaders = [];
        $status = http_response_code();
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');

                return redirect($url, ($status >= 300 && $status < 400) ? $status : 302);
            }

            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[trim($parts[0])] = trim($parts[1]);
            }
        }

        return response($content, 200, $responseHeaders);
    }
}
