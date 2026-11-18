<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Auth\ActorContext;
use App\Enums\TorrentListView;
use App\Models\User;
use App\Repositories\TorrentSearchRepository;
use App\Support\Cache;
use App\Support\Cache\NexusCache;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\RequestContext;
use App\Support\UserDisplay;
use App\ViewModels\TorrentListViewFactory;
use App\ViewModels\TorrentSearchPanelFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TorrentListingController extends Controller
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly TorrentSearchRepository $torrentSearchRepository,
        private readonly ?NexusCache $cache,
        private readonly TorrentListViewFactory $torrentListFactory,
        private readonly TorrentSearchPanelFactory $searchPanelFactory,
        private readonly RequestContext $requestContext,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($this->cache === null) {
            return redirect('/web/torrents?'.http_build_query($request->query->all()));
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login?returnto='.urlencode($request->fullUrl()));
        }

        $this->currentUser->promoteIfAbsent($user);
        $currentUser = ActorContext::current()->toLegacyArray();

        $viewMode = TorrentListView::resolve($request->query('view'), (string) ($user->torrents_view ?? 'list'));
        if ($request->query('view') !== null && $viewMode->value !== $user->torrents_view) {
            $user->torrents_view = $viewMode->value;
            $user->save();
            Cache::clearUser($user->id, (string) $user->passkey);
        }

        $data = $this->torrentSearchRepository->getListingData($request->query->all(), $this->requestContext->getScript());
        foreach (['pagertop', 'pagerbottom'] as $pagerKey) {
            $data[$pagerKey] = ($data[$pagerKey] ?? null) instanceof SafeHtml ? $data[$pagerKey] : SafeHtml::fromPlainText((string) ($data[$pagerKey] ?? ''));
        }
        $data['bookmarkedUsername'] = UserDisplay::username(ActorContext::current()->id);
        $data['listVm'] = $this->torrentListFactory->create(
            $data['rows'] ?? [],
            (int) ($data['sectiontype'] ?? 0),
            $viewMode,
        );
        $data['panelVm'] = $this->searchPanelFactory->create(
            (int) ($data['sectiontype'] ?? 0),
            (string) ($request->getQueryString() ?? ''),
            isset($data['CURUSER']['notifs']) ? (string) $data['CURUSER']['notifs'] : null,
            $data['hotSearches'] ?? [],
            $request->query->all(),
            (bool) ($data['showApprovalStatusFilter'] ?? false),
        );

        return view('torrents.index', $data);
    }
}
