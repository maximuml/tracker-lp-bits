<?php

namespace App\Http\Controllers;

use App\Support\CurrentUser;
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

        $headers = headers_list();
        $status = http_response_code();
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');
                $statusCode = is_int($status) && $status >= 300 && $status < 400 ? $status : 302;

                return redirect($url, $statusCode);
            }
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

        $headers = headers_list();
        $responseHeaders = [];
        $status = http_response_code();
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');

                $statusCode = is_int($status) && $status >= 300 && $status < 400 ? $status : 302;

                return redirect($url, $statusCode);
            }

            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                $responseHeaders[$name] = ($responseHeaders[$name] ?? '') !== '' ? $responseHeaders[$name].', '.$value : $value;
                header_remove($name);
            }
        }

        $responseStatus = is_int($status) && $status >= 100 ? $status : 200;

        return response($content, $responseStatus, $responseHeaders);
    }

    protected function legacyAbortResponse(string $heading, string $text, bool $htmlstrip = true): Response
    {
        ob_start();
        LegacyResponse::abort($heading, $text, $htmlstrip, true, true, false);

        return response((string) ob_get_clean());
    }
}
