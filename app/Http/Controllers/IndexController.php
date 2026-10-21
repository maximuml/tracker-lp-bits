<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Auth\ActorContext;
use App\Http\Requests\IndexPostRequest;
use App\Repositories\IndexRepository;
use App\Services\IndexPageService;
use App\Services\PollVoteService;
use App\Support\Config\SiteConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(
        private readonly IndexPageService $indexPageService,
        private readonly ActorContext $actor,
        private readonly IndexRepository $indexRepository,
        private readonly PollVoteService $pollVoteService,
    ) {}

    public function legacy(Request $request): View|Response|RedirectResponse
    {
        if ($this->actor->isGuest()) {
            $qs = $request->getQueryString();

            return redirect('/web/index'.($qs ? '?'.$qs : ''));
        }

        $this->indexRepository->touchLastHome($this->actor->id);

        $data = $this->indexPageService->build()->toArray();
        $this->indexPageService->appendAssets($this->actor);

        return view('index.index', $data);
    }

    public function indexSubmit(IndexPostRequest $request): View|Response|RedirectResponse
    {
        if ($this->actor->isGuest()) {
            $qs = $request->getQueryString();

            return redirect('/web/index'.($qs ? '?'.$qs : ''));
        }

        $this->indexRepository->touchLastHome($this->actor->id);

        if (SiteConfig::current()->main->showPolls()) {
            return $this->handlePollVote($request);
        }

        $data = $this->indexPageService->build()->toArray();
        $this->indexPageService->appendAssets($this->actor);

        return view('index.index', $data);
    }

    private function handlePollVote(Request $request): RedirectResponse
    {
        $choice = $request->input('choice');

        if ($choice === null || $choice === '' || (int) $choice != floor((float) $choice) || $this->actor->isGuest()) {
            return redirect('/web/index');
        }

        $ok = $this->pollVoteService->vote($this->actor->toLegacyArray(), (int) $choice);

        return redirect($ok ? '/' : '/web/index');
    }
}
