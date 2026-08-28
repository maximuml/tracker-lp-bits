<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Repositories\TorrentAjaxRepository;
use App\Support\CurrentUser;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TorrentAjaxController extends LegacyController
{
    public function viewFileList(Request $request): Response|RedirectResponse
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return response('', 400, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $files = TorrentAjaxRepository::fileList($torrentId);

        return response()->view('viewfilelist.index', ['files' => $files], 200, [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    public function viewPeerList(Request $request): Response|RedirectResponse
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return response('', 400, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $curUser = app(CurrentUser::class)->get() ?? [];
        $currentUser = ! empty($curUser) ? User::query()->find((int) ($curUser['id'] ?? 0)) : null;

        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/html; charset=utf-8',
        ];

        return response()->view('viewpeerlist.index', TorrentAjaxRepository::peerList($torrentId, $currentUser), 200, $headers);
    }

    public function viewSnatches(Request $request): View|RedirectResponse|Response
    {
        $torrentId = (int) $request->input('id', 0);
        if ($torrentId <= 0) {
            return redirect('/torrents.php');
        }

        return $this->legacyPage($request, 'viewsnatches', true, TorrentAjaxRepository::snatchList($torrentId));
    }

    public function getUserTorrentListAjax(Request $request): Response|RedirectResponse
    {
        $targetUserId = (int) $request->input('userid', 0);
        $type = (string) $request->input('type', '');

        if ($targetUserId <= 0 || ! in_array($type, ['uploaded', 'seeding', 'leeching', 'completed', 'incomplete'], true)) {
            return response('', 400, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $curUser = app(CurrentUser::class)->get() ?? [];
        $currentUser = ! empty($curUser) ? User::query()->find((int) ($curUser['id'] ?? 0)) : null;

        if ($currentUser === null || (! Permissions::userCan(PermissionEnum::TORRENT_HISTORY->value, false, $currentUser->id) && $currentUser->id !== $targetUserId)) {
            return response('', 403, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $page = (int) $request->input('page', 0);

        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/html; charset=utf-8',
        ];

        return response()->view('getusertorrentlistajax.index', TorrentAjaxRepository::userTorrentList($targetUserId, $type, $page, $currentUser), 200, $headers);
    }

    public function searchSuggest(Request $request): Response|RedirectResponse
    {
        $searchstr = (string) $request->input('q', '');
        if ($searchstr === '') {
            return response((string) json_encode([], JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        return response(
            (string) json_encode(TorrentAjaxRepository::searchSuggest($searchstr), JSON_UNESCAPED_UNICODE),
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

        $userId = (int) (app(CurrentUser::class)->get()['id'] ?? 0);
        $user = User::query()->find($userId);

        if ($user === null) {
            return response()->json(['torrents' => []]);
        }

        return response()->json(TorrentAjaxRepository::autocompleteTorrents($query, $user));
    }
}
