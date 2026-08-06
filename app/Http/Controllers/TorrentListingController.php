<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\TorrentSearchRepository;
use App\Support\SupportContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TorrentListingController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (SupportContext::getCache() === null) {
            $path = $request->path();
            $entry = $path === 'special' ? 'special.php' : 'torrents.php';

            return redirect('/' . $entry . '?' . http_build_query($request->query->all()));
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto=' . urlencode($request->fullUrl()));
        }

        $currentUser = SupportContext::getUser() ?? $user->toArray();
        SupportContext::setUser($currentUser);
        $GLOBALS['CURUSER'] = $currentUser;

        $data = TorrentSearchRepository::getListingData($request->query->all());

        return view('torrents.index', $data);
    }
}
