<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
}
