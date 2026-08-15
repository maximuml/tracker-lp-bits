<?php

namespace App\Http\Controllers;

use App\Repositories\OfferRepository;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OfferController extends LegacyController
{
    private OfferRepository $repository;

    public function __construct(OfferRepository $repository)
    {
        $this->repository = $repository;
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
    public function legacy(Request $request): Response|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/offers.php' . ($qs ? '?' . $qs : ''));
        }

        return $this->legacyPageRaw($request, 'offers');
    }
}
