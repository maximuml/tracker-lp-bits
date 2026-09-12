<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W1-01: Route inventory ratchet.
 *
 * Validates that all mutation routes (POST/PUT/PATCH/DELETE) in the
 * application have proper authentication guards and tracks which
 * routes still lack FormRequest validation.
 *
 * Pre-auth routes (login, signup, recover, confirm, passkey login,
 * tracker protocol) are excluded — they handle auth themselves.
 *
 * Framework routes (Horizon, Livewire, Filament) are excluded.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class RouteInventoryTest extends TestCase
{
    /**
     * Pre-auth mutation URIs that legitimately lack an auth guard.
     *
     * @var array<string, true>
     */
    private const PRE_AUTH_URIS = [
        'login' => true,
        'logout' => true,
        'signup' => true,
        'takesignup' => true,
        'recover' => true,
        'confirm_resend' => true,
        'api/v1/login' => true,
        'api/challenge' => true,
        // ajax.php — per-action guard inside UtilityController::ajax()
        // (passkey actions are guest-facing, the rest get a JSON 401 via
        // LegacyAuth::requireLoginFromContext)
        'ajax' => true,
        // Tracker protocol — uses passkey, not session/token guard
        'announce' => true,
        'announce.php' => true,
        'scrape' => true,
        'scrape.php' => true,
        // Cron — uses token middleware
        'cron' => true,
    ];

    /**
     * Known mutation routes without auth guard — legacy debt to fix in W1-02.
     * These routes are in routes/legacy/public.php (web group without auth).
     *
     * @var array<string, true>
     */
    private const KNOWN_MISSING_GUARD = [
        // All 6 routes fixed in W1-02 — auth.nexus middleware added to POST routes
    ];

    public function test_mutation_routes_have_authentication_guard(): void
    {
        $violations = [];
        /** @var Route[] $routes */
        $routes = RouteFacade::getRoutes()->getRoutes();

        foreach ($routes as $route) {
            $methods = $route->methods();
            $methods = array_filter($methods, fn ($m) => $m !== 'OPTIONS' && $m !== 'HEAD');

            $isMutation = false;
            foreach ($methods as $method) {
                if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    $isMutation = true;
                    break;
                }
            }

            if (! $isMutation) {
                continue;
            }

            $uri = $route->uri();
            $action = $route->getActionName();

            // Skip pre-auth routes
            if (array_key_exists($uri, self::PRE_AUTH_URIS)) {
                continue;
            }

            // Skip known missing-guard routes (legacy debt, tracked in KNOWN_MISSING_GUARD)
            if (array_key_exists($uri, self::KNOWN_MISSING_GUARD)) {
                continue;
            }

            // Skip framework routes
            if (str_starts_with($action, 'Laravel\\') ||
                str_starts_with($action, 'Livewire\\') ||
                str_starts_with($action, 'Filament\\') ||
                $action === 'Closure') {
                continue;
            }

            $middleware = implode(' ', $route->gatherMiddleware());

            // Check for auth guard
            $hasGuard = str_contains($middleware, 'auth.nexus') ||
                str_contains($middleware, 'auth:sanctum') ||
                str_contains($middleware, 'auth:');

            if (! $hasGuard) {
                $violations[] = sprintf(
                    '%s [%s] -> %s — mutation route without auth guard',
                    $uri,
                    implode('|', $methods),
                    $action,
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Found mutation routes without authentication guard:\n".
            implode("\n", $violations)."\n\n".
            'Add auth middleware or add to PRE_AUTH_URIS if the route handles auth itself.',
        );
    }

    public function test_mutation_route_count_does_not_increase(): void
    {
        $currentCount = $this->countAppMutationRoutes();

        // Baseline captured on 2026-09-07
        $this->assertLessThanOrEqual(
            155,
            $currentCount,
            sprintf(
                'App mutation route count increased from baseline 155 to %d. '.
                'Consider whether new mutation routes need FormRequest validation.',
                $currentCount,
            ),
        );
    }

    public function test_known_missing_guard_count_does_not_increase(): void
    {
        $actualMissing = $this->getMissingGuardUris();
        $actualCount = count($actualMissing);
        $baselineCount = count(self::KNOWN_MISSING_GUARD);

        $this->assertLessThanOrEqual(
            $baselineCount,
            $actualCount,
            sprintf(
                'Mutation routes without auth guard increased from baseline %d to %d. '.
                'Add auth middleware to new mutation routes.',
                $baselineCount,
                $actualCount,
            ),
        );
    }

    public function test_known_missing_guard_entries_still_exist(): void
    {
        $actualMissing = $this->getMissingGuardUris();
        $stale = [];

        foreach (array_keys(self::KNOWN_MISSING_GUARD) as $uri) {
            if (! in_array($uri, $actualMissing, true)) {
                $stale[] = $uri;
            }
        }

        $this->assertSame(
            [],
            $stale,
            "Routes fixed — remove from KNOWN_MISSING_GUARD:\n".
            implode("\n", $stale),
        );
    }

    /**
     * @return list<string>
     */
    private function getMissingGuardUris(): array
    {
        $missing = [];
        /** @var Route[] $routes */
        $routes = RouteFacade::getRoutes()->getRoutes();

        foreach ($routes as $route) {
            $methods = $route->methods();
            $methods = array_filter($methods, fn ($m) => $m !== 'OPTIONS' && $m !== 'HEAD');

            $isMutation = false;
            foreach ($methods as $method) {
                if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    $isMutation = true;
                    break;
                }
            }

            if (! $isMutation) {
                continue;
            }

            $uri = $route->uri();
            $action = $route->getActionName();

            if (array_key_exists($uri, self::PRE_AUTH_URIS)) {
                continue;
            }

            if (str_starts_with($action, 'Laravel\\') ||
                str_starts_with($action, 'Livewire\\') ||
                str_starts_with($action, 'Filament\\') ||
                $action === 'Closure') {
                continue;
            }

            $middleware = implode(' ', $route->gatherMiddleware());
            $hasGuard = str_contains($middleware, 'auth.nexus') ||
                str_contains($middleware, 'auth:sanctum') ||
                str_contains($middleware, 'auth:');

            if (! $hasGuard) {
                $missing[] = $uri;
            }
        }

        return $missing;
    }

    private function countAppMutationRoutes(): int
    {
        $count = 0;
        /** @var Route[] $routes */
        $routes = RouteFacade::getRoutes()->getRoutes();

        foreach ($routes as $route) {
            $methods = $route->methods();
            $methods = array_filter($methods, fn ($m) => $m !== 'OPTIONS' && $m !== 'HEAD');

            $isMutation = false;
            foreach ($methods as $method) {
                if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    $isMutation = true;
                    break;
                }
            }

            if (! $isMutation) {
                continue;
            }

            $action = $route->getActionName();

            // Skip framework routes
            if (str_starts_with($action, 'Laravel\\') ||
                str_starts_with($action, 'Livewire\\') ||
                str_starts_with($action, 'Filament\\') ||
                $action === 'Closure') {
                continue;
            }

            $count++;
        }

        return $count;
    }
}
