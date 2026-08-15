<?php

namespace App\Http\Controllers;

use App\Repositories\UsercpRepository;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UsercpController extends LegacyController
{
    private UsercpRepository $repository;

    public function __construct(UsercpRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->success($this->repository->settings());
    }

    /**
     * Serve the legacy usercp.php page from a Laravel view.
     */
    public function legacy(Request $request): Response|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/usercp.php' . ($qs ? '?' . $qs : ''));
        }

        return $this->legacyPageRaw($request, 'usercp');
    }
}
