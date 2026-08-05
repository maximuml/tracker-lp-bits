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

        $currentUser = SupportContext::getUser() ?? $user->toArray();
        SupportContext::setUser($currentUser);
        $GLOBALS['CURUSER'] = $currentUser;

        if (empty(SupportContext::getGlobal('lang_upload')) || empty(SupportContext::getGlobal('lang_edit'))) {
            SupportContext::setServerValue('SCRIPT_NAME', '/upload.php');
            global $lang_upload, $lang_edit;
            require_once base_path(get_langfile_path());
            require_once base_path(get_langfile_path('edit.php'));
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
        if ($enableoffer === 'yes') {
            $has_allowed_offer = (int) Offer::query()
                ->where('allowed', 'allowed')
                ->where('userid', $currentUser['id'])
                ->count();
        }

        $allowtorrents = $has_allowed_offer || LegacyResponse::canUpload('torrents');
        $allowspecial = LegacyResponse::canUpload('music');
        if (! $allowtorrents && ! $allowspecial) {
            LegacyResponse::abort($lang_upload['std_sorry'] ?? '', $lang_upload['std_please_offer'] ?? '', false);
        }

        return view('torrents.upload', [
            'allowtorrents' => $allowtorrents,
            'allowspecial' => $allowspecial,
            'allowtwosec' => ($allowtorrents && $allowspecial),
            'settingMain' => get_setting('main'),
            'torrentRep' => new TorrentRepository(),
            'searchBoxRep' => new SearchBoxRepository(),
            'tagRep' => new TagRepository(),
            'customField' => new Field(),
            'hitAndRunRep' => new HitAndRunRepository(),
            'pageTitle' => $lang_upload['head_upload'] ?? '',
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
