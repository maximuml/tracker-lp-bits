<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\IndexPostRequest;
use App\Repositories\IndexRepository;
use App\Services\IndexPageService;
use App\Services\PollVoteService;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(
        private readonly IndexPageService $indexPageService,
        private readonly CurrentUser $currentUser,
        private readonly IndexRepository $indexRepository,
        private readonly PollVoteService $pollVoteService,
    ) {}

    public function legacy(Request $request): View|Response|RedirectResponse
    {
        $user = $this->currentUser->get();
        if ($user === null) {
            $qs = $request->getQueryString();

            return redirect('/web/index'.($qs ? '?'.$qs : ''));
        }

        $this->indexRepository->touchLastHome((int) $this->currentUser->id());

        $data = $this->indexPageService->build()->toArray();
        $this->indexPageService->appendAssets($data['curUser']);

        return view('index.index', $data);
    }

    public function legacyPost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/index/submit'.$suffix, 308);
    }

    public function indexSubmit(IndexPostRequest $request): View|Response|RedirectResponse
    {
        $user = $this->currentUser->get();
        if ($user === null) {
            $qs = $request->getQueryString();

            return redirect('/web/index'.($qs ? '?'.$qs : ''));
        }

        $this->indexRepository->touchLastHome((int) $this->currentUser->id());

        if (SiteConfig::current()->main->showPolls()) {
            return $this->handlePollVote($request);
        }

        $data = $this->indexPageService->build()->toArray();
        $this->indexPageService->appendAssets($data['curUser']);

        return view('index.index', $data);
    }

    private function handlePollVote(Request $request): RedirectResponse
    {
        $choice = $request->input('choice');
        $user = $this->currentUser->get();

        if ($choice === null || $choice === '' || (int) $choice != floor((float) $choice) || ! is_array($user)) {
            return redirect('/web/index');
        }

        $ok = $this->pollVoteService->vote($user, (int) $choice);

        return redirect($ok ? '/' : '/web/index');
    }
}
