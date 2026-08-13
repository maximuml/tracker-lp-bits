<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentAjaxRepository;
use App\Support\Permissions;
use App\Support\SupportContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;
use Rhilip\Bencode\Bencode;

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
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            abort(404);
        }

        $torrent = Torrent::query()->find($id, ['id', 'name']);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);
        if (! Permissions::userCan(PermissionEnum::TORRENT_STRUCTURE->value, false, $currentUserId)) {
            abort(403);
        }

        $torrentDir = \App\Support\Config\SiteConfig::current()->main->torrentDir();
        $filePath = \App\Support\Path::resolve("{$torrentDir}/{$id}.torrent", \ROOT_PATH);
        if (! is_file($filePath) || ! is_readable($filePath)) {
            abort(404);
        }

        $dict = Bencode::load($filePath);

        return $this->legacyPage($request, 'torrent_info', true, [
            'torrentName' => (string) $torrent->name,
            'dict' => $dict,
        ]);
    }

    public function viewFileList(Request $request): Response|RedirectResponse
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return response('', 400, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $files = TorrentAjaxRepository::fileList($torrentId);

        return $this->legacyPageRaw($request, 'viewfilelist', false, ['files' => $files]);
    }

    public function viewPeerList(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'viewpeerlist', false);
    }

    public function viewSnatches(Request $request): View|RedirectResponse
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return redirect('/torrents.php');
        }

        return $this->legacyPage($request, 'viewsnatches', true, TorrentAjaxRepository::snatchList($torrentId));
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
        $searchstr = (string) $request->input('q', '');
        if ($searchstr === '') {
            return response(json_encode([], JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        return response(
            json_encode(TorrentAjaxRepository::searchSuggest($searchstr), JSON_UNESCAPED_UNICODE),
            200,
            ['Content-Type' => 'application/x-suggestions+json; charset=utf-8']
        );
    }

    public function autocompleteTorrents(Request $request): Response|RedirectResponse|JsonResponse
    {
        $query = (string) $request->input('q', '');
        if ($query === '') {
            return response()->json(['torrents' => []]);
        }

        $userId = (int) (SupportContext::getUser()['id'] ?? 0);
        $user = User::query()->find($userId);

        if ($user === null) {
            return response()->json(['torrents' => []]);
        }

        return response()->json(TorrentAjaxRepository::autocompleteTorrents($query, $user));
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
