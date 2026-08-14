<?php

namespace App\Http\Controllers;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Models\Offer;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UploadRepository;
use App\Support\Category;
use App\Support\LegacyResponse;
use App\Support\SupportContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Nexus\Field\Field;

class TorrentUploadController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (SupportContext::getCache() === null) {
            return redirect('/upload.php?' . $request->getQueryString());
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto=' . urlencode($request->fullUrl()));
        }

        $currentUser = SupportContext::getUser() ?? $user->toLegacyArray();
        SupportContext::setUser($currentUser);

        if (empty(SupportContext::getGlobal('lang_upload')) || empty(SupportContext::getGlobal('lang_edit'))) {
            SupportContext::setServerValue('SCRIPT_NAME', '/upload.php');
            require base_path(\App\Support\Locale::scriptFilePath((string) "", (bool) false, (string) ""));
            SupportContext::setGlobal('lang_upload', $lang_upload ?? []);
            require base_path(\App\Support\Locale::scriptFilePath((string) 'edit.php', (bool) false, (string) ""));
            SupportContext::setGlobal('lang_edit', $lang_edit ?? []);
        }

        /** @var array<string, string> $lang_upload */
        $lang_upload = SupportContext::getGlobal('lang_upload') ?? [];
        /** @var array<string, string> $lang_edit */
        $lang_edit = SupportContext::getGlobal('lang_edit') ?? [];

        if ($currentUser['parked'] === 'yes') {
            LegacyResponse::abort($lang_upload['std_sorry'] ?? '', $lang_upload['std_unauthorized_to_upload'] ?? '', false);
        }

        if ($currentUser['uploadpos'] == 'no') {
            LegacyResponse::abort($lang_upload['std_sorry'] ?? '', $lang_upload['std_unauthorized_to_upload'] ?? '', false);
        }

        $enableoffer = SupportContext::getGlobal('enableoffer', 'no');
        $has_allowed_offer = 0;
        $offerRows = [];
        if ($enableoffer === 'yes') {
            $offerRows = Offer::query()
                ->where('allowed', 'allowed')
                ->where('userid', $currentUser['id'])
                ->orderBy('name')
                ->get()
                ->toArray();
            $has_allowed_offer = count($offerRows);
        }

        $uploadFreely = LegacyResponse::canUpload('torrents');
        $allowtorrents = $has_allowed_offer || $uploadFreely;
        if (! $allowtorrents) {
            LegacyResponse::abort($lang_upload['std_sorry'] ?? '', $lang_upload['std_please_offer'] ?? '', false);
        }

        $browsecatmode = (int) (SupportContext::getGlobal('browsecatmode') ?? 1);

        return view('torrents.upload', [
            'uploadFreely' => $uploadFreely,
            'allowtorrents' => $allowtorrents,
            'offerRows' => $offerRows,
            'torrentRep' => new TorrentRepository(),
            'searchBoxRep' => new SearchBoxRepository(),
            'tagRep' => new TagRepository(),
            'customField' => new Field(),
            'hitAndRunRep' => new HitAndRunRepository(),
            'pageTitle' => $lang_upload['head_upload'] ?? '',
            'cats' => Category::listByModeWithContext($browsecatmode),
        ]);
    }

    public function legacyStore(Request $request, UploadRepository $repository): RedirectResponse
    {
        try {
            $torrent = $repository->upload($request);
        } catch (TorrentAlreadyExistsException $e) {
            return redirect('details.php?id=' . $e->getTorrentId() . '&existed=1');
        }

        return redirect('details.php?id=' . $torrent->id . '&uploaded=1');
    }
}
