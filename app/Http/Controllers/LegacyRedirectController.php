<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\LegacyPostRedirects;
use App\Support\PageResponses;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Permanent-redirect action for renamed legacy GET URIs.
 *
 * Registered via `Route::get(..., [LegacyRedirectController::class, 'get'])
 * ->defaults('redirect_to', '/web/<page>')`. Keeping this a controller (not a
 * route closure) makes the shim safe for `route:cache` — nested closure
 * factories cannot be serialized and 500'd every redirect in cached envs.
 */
final class LegacyRedirectController
{
    public function __construct(private readonly Container $container) {}

    public function get(Request $request): RedirectResponse
    {
        $target = (string) ($request->route('redirect_to') ?? '');

        $route = $request->route();
        if ($route !== null) {
            foreach ($route->parameters() as $key => $value) {
                if ($key !== 'redirect_to' && is_string($value)) {
                    $target = str_replace('{'.$key.'}', $value, $target);
                }
            }
        }

        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            $target .= '?'.$query;
        }

        return redirect()->to($target, 301);
    }

    /**
     * 308-forwarding action for legacy POST URIs whose actions were split
     * into dedicated /web/* endpoints. The per-route map lives in
     * LegacyPostRedirects — the dispatchers this replaced did the same
     * 308-replay inline inside each controller.
     */
    public function post(Request $request): RedirectResponse|Response
    {
        $route = $request->route();
        $routeUri = is_object($route) ? $route->uri() : trim($request->path(), '/');
        $entry = LegacyPostRedirects::entry($routeUri);
        if ($entry === null) {
            abort(404);
        }

        $requestClass = $entry['request'] ?? null;
        if (is_string($requestClass)) {
            $this->container->make($requestClass);
        }

        $qs = $request->getQueryString();
        $suffix = ($qs !== null && $qs !== '') ? '?'.$qs : '';

        $target = null;
        foreach ((array) ($entry['rules'] ?? []) as [$matchers, $uri]) {
            $matched = true;
            foreach ((array) $matchers as $key => $value) {
                $actual = str_starts_with((string) $key, 'query:')
                    ? $request->query(substr((string) $key, 6))
                    : $request->input(str_starts_with((string) $key, 'input:') ? substr((string) $key, 6) : (string) $key);
                if ((string) $actual !== (string) $value) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) {
                $target = (string) $uri;
                break;
            }
        }
        $target ??= isset($entry['target']) ? (string) $entry['target'] : null;

        if ($target !== null) {
            $target = (string) preg_replace_callback(
                '/\{input:([a-zA-Z0-9_]+)\}/',
                fn (array $m): string => (string) $request->input($m[1]),
                $target
            );

            return redirect()->to($target.$suffix, 308);
        }

        $abortText = $entry['abort'] ?? null;
        if (is_string($abortText)) {
            return response(PageResponses::captureAbort(__('functions.std_error'), $abortText));
        }

        return redirect((string) ($entry['default'] ?? '/'), 302);
    }
}
