<?php

namespace App\Http\Controllers;

use App\Repositories\OfferRepository;
use App\Services\Legacy\OfferService;
use App\Services\OfferPageService;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends LegacyController
{
    private OfferRepository $repository;

    private OfferService $offerService;

    private OfferPageService $pageService;

    public function __construct(OfferRepository $repository, OfferService $offerService, OfferPageService $pageService)
    {
        $this->repository = $repository;
        $this->offerService = $offerService;
        $this->pageService = $pageService;
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
    public function legacy(Request $request): View|RedirectResponse
    {
        if (app(CurrentUser::class)->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/offers.php'.($qs ? '?'.$qs : ''));
        }

        $actionRedirect = $this->offerService->handleActionPublic($request);
        if ($actionRedirect instanceof RedirectResponse) {
            return $actionRedirect;
        }

        $data = $this->pageService->build($request);

        return $this->legacyPage($request, 'offers', true, $data);
    }
}
