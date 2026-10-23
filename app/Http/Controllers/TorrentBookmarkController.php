<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Auth\ActorContext;
use App\Http\Requests\BookmarkSubmitRequest;
use App\Http\Requests\ThanksTorrentRequest;
use App\Services\TorrentBookmarkService;
use App\Support\PageResponses;
use App\Support\SafeExceptionMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TorrentBookmarkController extends BasePageController
{
    public function __construct(
        private readonly TorrentBookmarkService $bookmarkService,
        private readonly ActorContext $actor,
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

        if ($this->actor->isGuest()) {
            return response('failed', 200, $headers);
        }

        $torrentId = (int) $request->input('torrentid', 0);
        if ($torrentId <= 0) {
            return response('failed', 200, $headers);
        }

        $status = $this->bookmarkService->toggleBookmark((int) $this->actor->id, $torrentId);

        return response($status, 200, $headers);
    }

    public function bookmarkSubmit(BookmarkSubmitRequest $request): Response
    {
        return $this->bookmark($request);
    }

    public function thanksSubmit(ThanksTorrentRequest $request): Response|RedirectResponse|View
    {
        if ($this->actor->isGuest()) {
            return redirect('/thanks'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        }

        $actor = $this->actor->toLegacyArray();

        if ($request->query('id') !== null) {
            PageResponses::abort('Party is over!', "This trick doesn't work anymore. You need to click the button!");
        }

        $torrentid = (int) $request->post('id');

        try {
            $this->bookmarkService->thankTorrent($actor, $torrentid);
        } catch (\RuntimeException $e) {
            PageResponses::abort('Error', SafeExceptionMessage::message($e));
        }

        return $this->renderPage($request, 'thanks', true, [
            'torrentid' => $torrentid,
            'message' => 'Thank you has been recorded.',
        ]);
    }
}
