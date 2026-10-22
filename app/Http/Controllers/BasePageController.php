<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Auth\ActorContext;
use App\Support\PageResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

abstract class BasePageController extends Controller
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderPage(Request $request, string $page, bool $auth = true, array $data = []): View|RedirectResponse
    {
        // Ambient-seam read: this base-class helper predates per-child
        // DI and cannot take a typed ActorContext — 37 children override
        // __construct without calling parent, and service-location is
        // ratcheted to zero in controllers. The ambient user singleton
        // stays the contract here like in SiteAccess / guards / middleware.
        if ($auth && ActorContext::current()->isGuest()) {
            $qs = $request->getQueryString();

            return redirect('/'.$page.($qs ? '?'.$qs : ''));
        }

        /** @var view-string $viewName */
        $viewName = $this->viewName($page);
        $view = view()->make($viewName, $data);

        /** @var View $view */
        return $view;
    }

    protected function abortResponse(string $heading, string $text, bool $htmlstrip = true): Response
    {
        return response(PageResponses::captureAbort($heading, $text, $htmlstrip));
    }

    private function viewName(string $page): string
    {
        return $page.'.index';
    }
}
