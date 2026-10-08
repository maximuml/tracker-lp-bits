<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CurrentUser;
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
        if ($auth && CurrentUser::instance()->get() === null) {
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
