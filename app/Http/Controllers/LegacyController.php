<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
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
        if ($auth && SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        return view($page . '.index', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function legacyPageWithRedirect(Request $request, string $page, bool $auth = true, array $data = []): Response|RedirectResponse
    {
        if ($auth && SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        $content = view($page . '.index', $data)->render();

        $headers = headers_list();
        $status = http_response_code();
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');

                return redirect($url, ($status >= 300 && $status < 400) ? $status : 302);
            }
        }

        return response($content);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function legacyPageRaw(Request $request, string $page, bool $auth = true, array $data = []): Response|RedirectResponse
    {
        if ($auth && SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        $content = view($page . '.index', $data)->render();

        $headers = headers_list();
        $responseHeaders = [];
        $status = http_response_code();
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');

                return redirect($url, ($status >= 300 && $status < 400) ? $status : 302);
            }

            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                $responseHeaders[$name] = ($responseHeaders[$name] ?? '') !== '' ? $responseHeaders[$name] . ', ' . $value : $value;
                header_remove($name);
            }
        }

        $responseStatus = ($status >= 100) ? $status : 200;

        return response($content, $responseStatus, $responseHeaders);
    }

    protected function legacyAbortResponse(string $heading, string $text, bool $htmlstrip = true): Response
    {
        ob_start();
        \App\Support\LegacyResponse::abort($heading, $text, $htmlstrip, true, true, false);
        return response((string) ob_get_clean());
    }
}
