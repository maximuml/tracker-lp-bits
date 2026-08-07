<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TorrentActionController extends LegacyController
{
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

    public function torrentrss(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'torrentrss', false);
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

    public function thanks(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'thanks', true);
    }

}
