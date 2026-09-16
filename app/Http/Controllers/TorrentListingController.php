<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\TorrentSearchRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TorrentListingController extends Controller
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly TorrentSearchRepository $torrentSearchRepository,
        private readonly ?LegacyRedisCache $legacyRedisCache,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($this->legacyRedisCache === null) {
            return redirect('/torrents.php?'.http_build_query($request->query->all()));
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = $this->currentUser->get() ?? $user->toLegacyArray();
        $this->currentUser->set($currentUser);

        $data = $this->torrentSearchRepository->getListingData($request->query->all());

        return view('torrents.index', $data);
    }
}
