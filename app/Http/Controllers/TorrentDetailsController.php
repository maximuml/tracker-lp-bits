<?php

namespace App\Http\Controllers;

use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentDetailRepository;
use App\Support\SupportContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Nexus\Field\Field;

class TorrentDetailsController extends Controller
{
    public function show(Request $request, int $id): View|RedirectResponse
    {
        if ($id <= 0) {
            abort(404);
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto=' . urlencode($request->fullUrl()));
        }

        $torrent = Torrent::query()->find($id);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        Gate::forUser($user)->authorize('view', $torrent);

        if (SupportContext::getCache() === null) {
            $query = $request->query->all();
            unset($query['id']);

            return redirect('/details.php?id=' . $id . ($query ? '&' . http_build_query($query) : ''));
        }

        $row = TorrentDetailRepository::getTorrent($id);
        if (empty($row)) {
            \App\Support\Logger::writeWithContext((string) "TorrentDetailsRepository getTorrent empty: {$id}", (string) 'info', (bool) false);
            error_log("TorrentDetailsRepository getTorrent empty: $id");
            abort(404);
        }

        $row = \App\Support\Hooks::applyFilter('torrent_detail', $row);

        $currentUser = SupportContext::getUser() ?? $user->toLegacyArray();
        SupportContext::setUser($currentUser);

        if (empty(SupportContext::getGlobal('lang_functions')) || empty(SupportContext::getGlobal('lang_details'))) {
            SupportContext::setServerValue('SCRIPT_NAME', '/details.php');
            require base_path(\App\Support\Locale::scriptFilePath((string) 'functions.php', (bool) false, (string) ""));
            SupportContext::setGlobal('lang_functions', $lang_functions ?? []);
            require base_path(\App\Support\Locale::scriptFilePath((string) "", (bool) false, (string) ""));
            SupportContext::setGlobal('lang_details', $lang_details ?? []);
        }

        $langDetails = SupportContext::getGlobal('lang_details') ?? [];
        $headTitle = empty($request->input('cmtpage'))
            ? ($langDetails['head_details_for_torrent'] ?? '') . '"' . $row['name'] . '"'
            : ($langDetails['head_comments_for_torrent'] ?? '') . '"' . $row['name'] . '"';

        return view('torrent.details', [
            'torrentId' => $id,
            'torrentRow' => $row,
            'user' => $user,
            'currentUser' => $currentUser,
            'customField' => new Field(),
            'headTitle' => $headTitle,
        ]);
    }
}
