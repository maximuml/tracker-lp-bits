<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CurrentUser;
use App\Support\LegacyHeaderBag;
use App\Support\LegacyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

abstract class LegacyController extends Controller
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function legacyPage(Request $request, string $page, bool $auth = true, array $data = []): View|RedirectResponse
    {
        if ($auth && app(CurrentUser::class)->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/'.$page.'.php'.($qs ? '?'.$qs : ''));
        }

        $view = view()->make($page.'.index', $data);

        /** @var View $view */
        return $view;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function legacyPageWithRedirect(Request $request, string $page, bool $auth = true, array $data = []): Response|RedirectResponse
    {
        if ($auth && app(CurrentUser::class)->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/'.$page.'.php'.($qs ? '?'.$qs : ''));
        }

        $content = view()->make($page.'.index', $data)->render();

        // T-11: Read from the per-request LegacyHeaderBag instead of SAPI
        // globals (headers_list/http_response_code/header_remove) that
        // leak state across Octane worker requests.
        $headerBag = app(LegacyHeaderBag::class);
        $status = $headerBag->getStatusCode();

        // Check for a Location header (redirect)
        $location = $headerBag->first('Location');
        if ($location !== null) {
            $headerBag->remove('Location');
            $statusCode = $status !== null && $status >= 300 && $status < 400 ? $status : 302;

            return redirect($location, $statusCode);
        }

        return response($content);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function legacyPageRaw(Request $request, string $page, bool $auth = true, array $data = []): Response|RedirectResponse
    {
        if ($auth && app(CurrentUser::class)->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/'.$page.'.php'.($qs ? '?'.$qs : ''));
        }

        $content = view()->make($page.'.index', $data)->render();

        // T-11: Read from the per-request LegacyHeaderBag instead of SAPI
        // globals (headers_list/http_response_code/header_remove) that
        // leak state across Octane worker requests.
        $headerBag = app(LegacyHeaderBag::class);
        $status = $headerBag->getStatusCode();

        // Check for a Location header (redirect)
        $location = $headerBag->first('Location');
        if ($location !== null) {
            $headerBag->remove('Location');
            $statusCode = $status !== null && $status >= 300 && $status < 400 ? $status : 302;

            return redirect($location, $statusCode);
        }

        // Collect remaining headers for the response
        $responseHeaders = $headerBag->toResponseHeaders();
        $headerBag->flush();

        $responseStatus = $status !== null && $status >= 100 ? $status : 200;

        return response($content, $responseStatus, $responseHeaders);
    }

    protected function legacyAbortResponse(string $heading, string $text, bool $htmlstrip = true): Response
    {
        ob_start();
        LegacyResponse::abort($heading, $text, $htmlstrip, true, true, false);

        return response((string) ob_get_clean());
    }
}
