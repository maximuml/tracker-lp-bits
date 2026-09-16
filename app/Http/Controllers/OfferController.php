<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\OfferRepository;
use App\Services\OfferPageService;
use App\Services\OfferService;
use App\Services\OfferVoteService;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class OfferController extends LegacyController
{
    private OfferRepository $repository;

    private OfferService $offerService;

    private OfferPageService $pageService;

    private OfferVoteService $offerVoteService;

    public function __construct(
        OfferRepository $repository,
        OfferService $offerService,
        OfferPageService $pageService,
        OfferVoteService $offerVoteService,
        private readonly CurrentUser $currentUser,
    ) {
        $this->repository = $repository;
        $this->offerService = $offerService;
        $this->pageService = $pageService;
        $this->offerVoteService = $offerVoteService;
    }

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        return $this->success($this->repository->list($request));
    }

    /**
     * Serve the legacy offers.php page from a Laravel view.
     */
    public function legacyAction(Request $request): View|RedirectResponse|Response
    {
        return $this->legacy($request);
    }

    public function legacy(Request $request): View|RedirectResponse|Response
    {
        if ($this->currentUser->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/offers.php'.($qs ? '?'.$qs : ''));
        }

        $voteResponse = $this->offerVoteService->handleVote($request);
        if ($voteResponse instanceof Response) {
            return $voteResponse;
        }

        $actionRedirect = $this->offerService->handleActionPublic($request);
        if ($actionRedirect instanceof RedirectResponse) {
            return $actionRedirect;
        }

        $data = $this->pageService->build($request)->toArray();

        return $this->legacyPage($request, 'offers', true, $data);
    }
}
