<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class LegacyPagesController extends LegacyController
{
    public function getrss(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'getrss');
    }

    public function userhistory(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'userhistory');
    }

    public function invite(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'invite');
    }

    public function news(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'news');
    }

    public function makepoll(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'makepoll');
    }

    public function polloverview(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'polloverview');
    }

    public function attendance(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'attendance');
    }

    public function bookmark(Request $request): Response|RedirectResponse
    {
        $torrentId = (int) $request->input('torrentid', 0);
        if ($torrentId <= 0) {
            return redirect('/torrents.php');
        }

        return $this->legacyPageRaw($request, 'bookmark', false);
    }

    public function fastDelete(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'fastdelete');
    }

    public function torrentInfo(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'torrent_info');
    }

    public function viewFileList(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'viewfilelist', false);
    }

    public function viewPeerList(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'viewpeerlist', false);
    }

    public function viewSnatches(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'viewsnatches');
    }

    public function takeFlush(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'takeflush');
    }

    public function takeReseed(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'takereseed');
    }

    public function aboutNexus(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'aboutnexus', false);
    }

    public function rules(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'rules', false);
    }

    public function userAgreement(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'useragreement', false);
    }

    public function faq(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'faq', false);
    }

    public function donate(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'donate', false);
    }

    public function donated(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'donated');
    }

    public function faqManage(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'faqmanage');
    }

    public function faqActions(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'faqactions');
    }

    public function search(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'search');
    }

    public function usersearch(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'usersearch');
    }

    public function getUserTorrentListAjax(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'getusertorrentlistajax', false);
    }

    public function searchSuggest(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'searchsuggest', false);
    }

    public function autocompleteTorrents(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'autocomplete_torrents');
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

    public function attachment(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'attachment', true);
    }

    public function getattachment(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'getattachment', true);
    }

    public function image(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'image', false);
    }

    public function torrentrss(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'torrentrss', false);
    }

    public function page(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'page', false);
    }

    public function tags(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'tags', false);
    }

    public function suggest(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'suggest', false);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'preview', true);
    }

    public function moresmilies(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'moresmilies', true);
    }

    public function smilies(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'smilies', true);
    }

    public function opensearch(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'opensearch', false);
    }

    public function bitbucketlog(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'bitbucketlog', true);
    }

    public function confirmemail(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'confirmemail', false);
    }

    public function delete(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'delete', true);
    }

    public function downloadnotice(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'downloadnotice', true);
    }

    public function emailGateway(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'email-gateway', false);
    }

    public function ok(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'ok', false);
    }

    public function thanks(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'thanks', true);
    }

}
