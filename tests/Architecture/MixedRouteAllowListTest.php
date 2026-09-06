<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Support\Http\MixedRouteAllowList;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * T-21: Enforces that all mixed-method routes (Route::match / Route::any)
 * are registered in the MixedRouteAllowList.
 *
 * This prevents new mixed-method routes from being added without explicit
 * justification. The allow-list documents why each route needs both GET
 * and POST (or other method combinations) in the same controller action.
 *
 * To fix a failure:
 * 1. If the route is GET-only or POST-only, change it to Route::get() or
 *    Route::post() respectively.
 * 2. If the route genuinely needs both methods, add it to
 *    MixedRouteAllowList::entries() with a justification.
 */
final class MixedRouteAllowListTest extends TestCase
{
    public function test_all_mixed_method_routes_are_in_allow_list(): void
    {
        $allowList = MixedRouteAllowList::entries();
        $allowedKeys = array_keys($allowList);

        $violations = [];
        /** @var \Illuminate\Routing\Route[] $routes */
        $routes = Route::getRoutes()->getRoutes();

        foreach ($routes as $route) {
            $methods = $route->methods();
            // Skip OPTIONS (CORS preflight) and HEAD (auto-added for GET)
            $methods = array_filter($methods, fn ($m) => $m !== 'OPTIONS' && $m !== 'HEAD');

            // A "mixed" route has more than one method AND is not a simple
            // GET-only or single-method route. We specifically flag:
            // - Routes with both GET and POST
            // - Route::any (all methods)
            // - Routes with PUT+PATCH (RESTful combination)
            if (count($methods) <= 1) {
                continue;
            }

            // Check if this is a genuinely mixed route
            $isMixed = false;
            $methodKey = '';

            if (in_array('GET', $methods, true) && in_array('POST', $methods, true)) {
                $isMixed = true;
                $methodKey = 'GET '.$route->uri();
            } elseif (in_array('PUT', $methods, true) && in_array('PATCH', $methods, true)) {
                $isMixed = true;
                $methodKey = 'PUT '.$route->uri();
            } elseif (count($methods) >= 3) {
                // Route::any or similar — treat as mixed
                $isMixed = true;
                $methodKey = 'GET '.$route->uri();
            }

            if (! $isMixed) {
                continue;
            }

            // Check if the route URI (without leading slash) is in the allow-list
            $uri = ltrim($route->uri(), '/');

            // Try matching with and without leading slash
            $keyWithSlash = 'GET /'.$uri;
            $keyWithoutSlash = 'GET '.$uri;

            // For API routes, the URI may not have a leading slash
            $found = in_array($keyWithSlash, $allowedKeys, true)
                || in_array($keyWithoutSlash, $allowedKeys, true)
                || in_array('PUT '.$uri, $allowedKeys, true);

            if (! $found) {
                $violations[] = sprintf(
                    '%s [%s] — methods: %s. Add to MixedRouteAllowList or split into separate GET/POST routes.',
                    $route->getName() ?: '(unnamed)',
                    $uri,
                    implode('|', $methods),
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Found mixed-method routes not in MixedRouteAllowList:\n".
            implode("\n", $violations)."\n\n".
            'Either split the route to Route::get()/Route::post() or add it to '.
            'app/Support/Http/MixedRouteAllowList.php with a justification.',
        );
    }

    public function test_allow_list_entries_have_required_fields(): void
    {
        $entries = MixedRouteAllowList::entries();

        foreach ($entries as $key => $entry) {
            $this->assertArrayHasKey(
                'category',
                $entry,
                "Allow-list entry '{$key}' missing 'category' field",
            );
            $this->assertArrayHasKey(
                'reason',
                $entry,
                "Allow-list entry '{$key}' missing 'reason' field",
            );
            $this->assertNotEmpty(
                $entry['reason'],
                "Allow-list entry '{$key}' has empty 'reason' field",
            );
        }
    }
}
