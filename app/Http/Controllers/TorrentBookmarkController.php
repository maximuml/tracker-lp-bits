<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TorrentBookmarkService;
use App\Support\CurrentUser;
use App\Support\LegacyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TorrentBookmarkController extends LegacyController
{
    public function __construct(
        private readonly TorrentBookmarkService $bookmarkService,
    ) {}

    public function bookmark(Request $request): Response
    {
        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/xml; charset=utf-8',
        ];

        $user = app(CurrentUser::class)->get();
        if ($user === null) {
            return response('failed', 200, $headers);
        }

        $torrentId = (int) $request->input('torrentid', 0);
        if ($torrentId <= 0) {
            return response('failed', 200, $headers);
        }

        $status = $this->bookmarkService->toggleBookmark((int) $user['id'], $torrentId);

        return response($status, 200, $headers);
    }

    public function thanks(Request $request): Response|RedirectResponse
    {
        if (app(CurrentUser::class)->get() === null) {
            return redirect('/thanks.php'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        }

        $curUser = app(CurrentUser::class)->get();

        if ($request->query('id') !== null) {
            LegacyResponse::abort('Party is over!', "This trick doesn't work anymore. You need to click the button!");
        }

        $torrentid = (int) $request->post('id');

        try {
            $this->bookmarkService->thankTorrent($curUser, $torrentid);
        } catch (\RuntimeException $e) {
            LegacyResponse::abort('Error', $e->getMessage());
        }

        return $this->legacyPageRaw($request, 'thanks', true, [
            'torrentid' => $torrentid,
            'message' => 'Thank you has been recorded.',
        ]);
    }
}
