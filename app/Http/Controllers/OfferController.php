<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Http\Requests\AllowOfferRequest;
use App\Http\Requests\DeleteOfferRequest;
use App\Http\Requests\FinishOfferRequest;
use App\Http\Requests\StoreOfferRequest;
use App\Http\Requests\UpdateOfferRequest;
use App\Services\OfferModerationService;
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
    private OfferRepositoryInterface $repository;

    private OfferService $offerService;

    private OfferPageService $pageService;

    private OfferVoteService $offerVoteService;

    public function __construct(
        OfferRepositoryInterface $repository,
        OfferService $offerService,
        OfferPageService $pageService,
        OfferVoteService $offerVoteService,
        private readonly OfferModerationService $offerModerationService,
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

            return redirect('/web/offers'.($qs ? '?'.$qs : ''));
        }

        if ($request->isMethod('post')) {
            $qs = $request->getQueryString();
            $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';
            foreach (['new_offer' => 'create', 'allow_offer' => 'allow', 'finish_offer' => 'finish', 'del_offer' => 'delete', 'take_off_edit' => 'edit'] as $marker => $verb) {
                if ($request->input($marker) !== null && $request->input($marker) !== '') {
                    return redirect()->to('/web/offers/'.$verb.$suffix, 308);
                }
            }
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

    public function store(StoreOfferRequest $request): RedirectResponse
    {
        return $this->offerService->handleCreate($request);
    }

    public function allow(AllowOfferRequest $request): RedirectResponse
    {
        return $this->offerModerationService->handleAllow($request);
    }

    public function finish(FinishOfferRequest $request): RedirectResponse
    {
        return $this->offerModerationService->handleFinish($request);
    }

    public function destroy(DeleteOfferRequest $request): RedirectResponse
    {
        return $this->offerService->handleDelete($request);
    }

    public function update(UpdateOfferRequest $request): RedirectResponse
    {
        return $this->offerService->handleEdit($request);
    }
}
