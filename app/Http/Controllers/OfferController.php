<?php

namespace App\Http\Controllers;

use App\Repositories\OfferRepository;
use App\Services\Legacy\OfferService;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfferController extends LegacyController
{
    private OfferRepository $repository;

    private OfferService $offerService;

    public function __construct(OfferRepository $repository, OfferService $offerService)
    {
        $this->repository = $repository;
        $this->offerService = $offerService;
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
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/offers.php' . ($qs ? '?' . $qs : ''));
        }

        $result = $this->offerService->legacy($request);
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return $this->legacyPage($request, 'offers', true, $result);
    }
}
