<?php

namespace App\Http\Controllers;

use App\Services\CleanupService;
use App\Support\SupportContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $torrentId = (int) $request->input('torrentid', 0);
        if ($torrentId <= 0) {
            return redirect('/torrents.php');
        }

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
        if (! defined('IN_NEXUS') || ! IN_NEXUS) {
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
                $currentUser = SupportContext::getUser() ?? [];
                do_log("hacking attempt made by " . ($currentUser['username'] ?? 'guest') . ",uid " . ($currentUser['id'] ?? 0), 'error');
                throw new \RuntimeException("Invalid action: {$action}");
            }

            $result = call_user_func($callable, $params);

            return response()->json(success($result));
        } catch (\Throwable $exception) {
            do_log($exception->getMessage() . $exception->getTraceAsString(), 'error');

            return response()->json(fail($exception->getMessage(), $request->all()));
        }
    }

    public function catmanage(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'catmanage');
    }

    public function forummanage(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'forummanage');
    }

    public function moforums(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'moforums');
    }

    public function fields(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'fields');
    }

    public function formats(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'formats');
    }

    public function videoformats(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'videoformats');
    }

    public function shoutbox(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'shoutbox', false);
    }

    public function attachment(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'attachment', true);
    }

    public function getattachment(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'getattachment', true);
    }

    public function image(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'image', false);
    }

    public function shoutboxHistory(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'shoutbox_history', true);
    }

    public function shoutboxSse(Request $request): SymfonyResponse
    {
        if (SupportContext::getUser() === null) {
            return new SymfonyResponse('', 403);
        }

        $context = SupportContext::getGlobalsForView();

        $callback = function () use ($context) {
            extract($context, EXTR_SKIP);
            $scriptFile = resource_path('views/shoutbox_sse/_shoutbox_sse_legacy.php');
            if (is_file($scriptFile)) {
                require $scriptFile;
            }
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function latestcomments(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'latestcomments', true);
    }

    public function bonusLog(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'bonus-log', true);
    }

    public function medal(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'medal', true);
    }

    public function task(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'task', true);
    }

    public function torrentrss(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'torrentrss', false);
    }

    public function uploaders(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'uploaders', true);
    }

    public function settings(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'settings', true);
    }

    public function freeleech(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'freeleech', true);
    }

    public function magic(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'magic', true);
    }

    public function delacctadmin(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'delacctadmin', true);
    }

    public function deletedisabled(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'deletedisabled', true);
    }

    public function massmail(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'massmail', true);
    }

    public function takeamountupload(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'takeamountupload', true);
    }

    public function takeinvite(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'takeinvite', true);
    }

    public function takeupdate(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'takeupdate', true);
    }

    public function users(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'users', true);
    }

    public function staffpanel(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'staffpanel', true);
    }

    public function docleanup(Request $request): Response
    {
        return \response(
            app(CleanupService::class)->runFull($request->boolean('forceall'), true),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    public function page(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'page', false);
    }

    public function location(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'location', true);
    }

    public function tags(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'tags', false);
    }

    public function suggest(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'suggest', false);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'preview', true);
    }

    public function moresmilies(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'moresmilies', true);
    }

    public function smilies(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'smilies', true);
    }

    public function opensearch(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'opensearch', false);
    }

    public function mailtest(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'mailtest', true);
    }

    public function mysqlStats(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'mysql_stats', true);
    }

    public function reset(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'reset', true);
    }

    public function selfEnable(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'self-enable', true);
    }

    public function unco(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'unco', true);
    }

    public function adduser(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'adduser', true);
    }

    public function bitbucketlog(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'bitbucketlog', true);
    }

    public function complains(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'complains', false);
    }

    public function confirmemail(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'confirmemail', false);
    }

    public function cron(Request $request): Response
    {
        return \response(
            app(CleanupService::class)->triggerCron(),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }

    public function delete(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'delete', true);
    }

    public function downloadnotice(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'downloadnotice', true);
    }

    public function emailGateway(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'email-gateway', false);
    }

    public function incrementBulk(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'increment-bulk', true);
    }

    public function maxlogin(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'maxlogin', true);
    }

    public function ok(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'ok', false);
    }

    public function setlistLookup(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'setlist_lookup', true);
    }

    public function takeIncrementBulk(Request $request): Response|RedirectResponse
    {
        return $this->legacyWithRedirect($request, 'take-increment-bulk', true);
    }

    public function testip(Request $request): View|RedirectResponse
    {
        return $this->legacy($request, 'testip', true);
    }

    public function thanks(Request $request): Response|RedirectResponse
    {
        return $this->legacyRaw($request, 'thanks', true);
    }

    private function legacy(Request $request, string $page, bool $auth = true): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! IN_NEXUS || ($auth && SupportContext::getUser() === null)) {
            $qs = $request->getQueryString();
            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        return view($page . '.index');
    }


    private function legacyWithRedirect(Request $request, string $page, bool $auth = true): Response|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! IN_NEXUS || ($auth && SupportContext::getUser() === null)) {
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
        if (! defined('IN_NEXUS') || ! IN_NEXUS || ($auth && SupportContext::getUser() === null)) {
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
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                $responseHeaders[$name] = ($responseHeaders[$name] ?? '') !== '' ? $responseHeaders[$name] . ', ' . $value : $value;
                header_remove($name);
            }
        }

        $responseStatus = ($status >= 100) ? $status : 200;

        return response($content, $responseStatus, $responseHeaders);
    }
}
