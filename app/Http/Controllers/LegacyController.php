<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

abstract class LegacyController extends Controller
{
    protected function legacyPage(Request $request, string $page, bool $auth = true): View|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! IN_NEXUS || ($auth && SupportContext::getUser() === null)) {
            $qs = $request->getQueryString();

            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        return view($page . '.index');
    }

    protected function legacyPageWithRedirect(Request $request, string $page, bool $auth = true): Response|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! IN_NEXUS || ($auth && SupportContext::getUser() === null)) {
            $qs = $request->getQueryString();

            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        $content = view($page . '.index')->render();

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

    protected function legacyPageRaw(Request $request, string $page, bool $auth = true): Response|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! IN_NEXUS || ($auth && SupportContext::getUser() === null)) {
            $qs = $request->getQueryString();

            return redirect('/' . $page . '.php' . ($qs ? '?' . $qs : ''));
        }

        $content = view($page . '.index')->render();

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
}
