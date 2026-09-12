<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W3-02: Data-driven HTTP contract tests.
 *
 * Validates that route definitions conform to HTTP contracts:
 * - Mutation routes use explicit HTTP methods (POST/PUT/PATCH/DELETE)
 * - API routes return JSON responses for unauthenticated requests
 * - Legacy .php aliases mirror their non-.php counterparts
 * - Unauthenticated requests to protected routes are rejected
 * - Wrong HTTP method on mutation-only routes returns 405
 *
 * Each contract iterates over the live route table, so adding a new
 * route automatically extends the test coverage. A change to route
 * method, middleware, or response contract produces a clear, focused
 * assertion failure identifying the offending route.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class HttpContractTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Pre-auth URIs that legitimately lack an auth guard.
     *
     * These routes handle authentication themselves (login, signup,
     * passkey, tracker protocol, cron, public pages).
     */
    private const PRE_AUTH_URIS = [
        'login', 'logout', 'signup', 'takesignup', 'recover', 'confirm_resend',
        'api/v1/login', 'api/challenge',
        'announce', 'announce.php', 'scrape', 'scrape.php',
        'cron', 'cron.php',
        'aboutnexus', 'faq', 'rules', 'staffmessages', 'contactstaff',
        'news', 'topten',
        'faq.php', 'rules.php', 'staffmessages.php', 'contactstaff.php',
        'news.php', 'topten.php',
        'confirmemail',
        // Passkey-auth routes — use passkey, not session/token guard
        'api/pieces-hash',
        // ajax.php — per-action guard inside UtilityController::ajax()
        // (passkey actions are guest-facing, the rest get a JSON 401 via
        // LegacyAuth::requireLoginFromContext)
        'ajax',
    ];

    /**
     * Framework route prefixes to exclude (Horizon, Filament, Livewire).
     */
    private const EXCLUDED_PREFIXES = [
        'horizon', 'livewire', 'panel', 'api/v1/health', '_ignition',
        'nexusphp',
    ];

    /**
     * Mutation HTTP methods that should NOT use GET.
     */
    private const MUTATION_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Routes that are allowed to use GET for mutations (legacy compatibility).
     */
    private const GET_MUTATION_ALLOWLIST = [
        'takeedit', 'takeupload', 'takesettings', 'takeusercp', 'takemessage',
        'takecontactstaff', 'takeresend', 'takedeleteuser', 'takeflush',
        'takeinvite', 'takebonus', 'takesnlist', 'takerecover', 'takesignup',
        'takelogin', 'takelang', 'takestaffmessage', 'taketopten', 'takefaq',
        'takerules', 'takeaboutnexus', 'takepoll', 'takeforummanage',
        'takedonate', 'confirmemail', 'confirm_resend', 'takemodtask',
        'takeexam', 'takeagentallow', 'takeagentdeny', 'takeshoutbox',
        'takesub', 'takecomment', 'takeoffer', 'takevote', 'takeviewnfo',
    ];

    public function test_mutation_routes_have_auth_middleware(): void
    {
        $violations = [];

        foreach ($this->collectRoutes() as $entry) {
            if (! in_array($entry['method'], self::MUTATION_METHODS, true)) {
                continue;
            }
            if (in_array($entry['uri'], self::GET_MUTATION_ALLOWLIST, true)) {
                continue;
            }
            if (in_array($entry['uri'], self::PRE_AUTH_URIS, true)) {
                continue;
            }

            $route = $this->findRoute($entry['uri'], $entry['method']);
            if ($route === null) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            if (! $this->hasAuthMiddleware($middleware)) {
                $violations[] = sprintf(
                    '%s %s — middleware: [%s]',
                    $entry['method'],
                    $entry['uri'],
                    implode(', ', $middleware),
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            sprintf(
                "%d mutation route(s) lack auth middleware:\n  %s\n"
                .'All mutation routes must have auth.nexus, auth:sanctum, or equivalent.',
                count($violations),
                implode("\n  ", $violations),
            ),
        );
    }

    public function test_api_routes_return_json_for_unauthenticated(): void
    {
        $violations = [];

        foreach ($this->collectRoutes() as $entry) {
            if (! str_starts_with($entry['uri'], 'api/')) {
                continue;
            }
            if (in_array($entry['uri'], self::PRE_AUTH_URIS, true)) {
                continue;
            }

            // Skip routes with parameters — they need valid IDs to match.
            if (str_contains($entry['uri'], '{')) {
                continue;
            }

            try {
                $response = $this->{$this->methodToCall($entry['method'])}(
                    "/{$entry['uri']}",
                    [],
                    ['Accept' => 'application/json'],
                );

                $status = $response->status();
            } catch (\Throwable $e) {
                $violations[] = sprintf(
                    '%s %s threw %s: %s',
                    $entry['method'],
                    $entry['uri'],
                    $e::class,
                    $e->getMessage(),
                );

                continue;
            }

            if (! in_array($status, [401, 403, 302, 422], true)) {
                $violations[] = sprintf(
                    '%s %s returned %d (expected 401/403/302/422)',
                    $entry['method'],
                    $entry['uri'],
                    $status,
                );

                continue;
            }

            // If 401/403, response should be JSON.
            if (in_array($status, [401, 403], true)) {
                $contentType = $response->headers->get('Content-Type', '');
                if (! str_contains($contentType, 'application/json')) {
                    $violations[] = sprintf(
                        '%s %s returned %d with non-JSON content type: %s',
                        $entry['method'],
                        $entry['uri'],
                        $status,
                        $contentType,
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            sprintf(
                "%d API route contract violation(s):\n  %s",
                count($violations),
                implode("\n  ", $violations),
            ),
        );
    }

    public function test_get_on_mutation_only_routes_returns_405_or_auth_error(): void
    {
        $violations = [];

        foreach ($this->collectMutationOnlyUris() as $uri) {
            if (in_array($uri, self::GET_MUTATION_ALLOWLIST, true)) {
                continue;
            }
            if (in_array($uri, self::PRE_AUTH_URIS, true)) {
                continue;
            }

            // Skip routes with parameters — they need valid IDs to match.
            if (str_contains($uri, '{')) {
                continue;
            }

            $response = $this->get("/{$uri}");
            $status = $response->status();

            if (! in_array($status, [405, 401, 403, 302], true)) {
                $violations[] = sprintf(
                    '%s returned %d (expected 405/401/403/302)',
                    $uri,
                    $status,
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            sprintf(
                "%d mutation-only route(s) accepted GET:\n  %s",
                count($violations),
                implode("\n  ", $violations),
            ),
        );
    }

    public function test_legacy_php_aliases_mirror_canonical_routes(): void
    {
        $violations = [];

        foreach ($this->collectRoutes() as $entry) {
            if (! str_ends_with($entry['uri'], '.php')) {
                continue;
            }

            $canonicalUri = substr($entry['uri'], 0, -4);
            $aliasRoute = $this->findRoute($entry['uri'], $entry['method']);
            $canonicalRoute = $this->findRoute($canonicalUri, $entry['method']);

            if ($aliasRoute === null) {
                continue;
            }

            if ($canonicalRoute === null) {
                $violations[] = sprintf(
                    '%s %s has no canonical counterpart at %s',
                    $entry['method'],
                    $entry['uri'],
                    $canonicalUri,
                );

                continue;
            }

            if ($aliasRoute->getActionName() !== $canonicalRoute->getActionName()) {
                $violations[] = sprintf(
                    '%s %s → %s does not match canonical %s → %s',
                    $entry['method'],
                    $entry['uri'],
                    $aliasRoute->getActionName(),
                    $canonicalUri,
                    $canonicalRoute->getActionName(),
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            sprintf(
                "%d legacy alias contract violation(s):\n  %s",
                count($violations),
                implode("\n  ", $violations),
            ),
        );
    }

    public function test_all_routes_have_explicit_http_methods(): void
    {
        $violations = [];

        foreach ($this->collectUniqueUris() as $uri) {
            $route = $this->findFirstRoute($uri);
            if ($route === null) {
                continue;
            }

            $methods = array_diff($route->methods(), ['HEAD']);
            if (empty($methods)) {
                $violations[] = $uri;
            }
        }

        $this->assertSame(
            [],
            $violations,
            sprintf(
                "%d route(s) have no explicit HTTP methods:\n  %s",
                count($violations),
                implode("\n  ", $violations),
            ),
        );
    }

    public function test_mutation_routes_do_not_use_get(): void
    {
        $violations = [];

        foreach ($this->collectRoutes() as $entry) {
            // Look for GET routes with mutation-like names.
            if ($entry['method'] !== 'GET') {
                continue;
            }

            $uri = $entry['uri'];

            // Skip allowlisted legacy take* routes.
            if (in_array($uri, self::GET_MUTATION_ALLOWLIST, true)) {
                continue;
            }

            // Skip non-mutation GET routes (reads, views, listings).
            if (! $this->looksLikeMutation($uri)) {
                continue;
            }

            // Check if POST/PUT/PATCH/DELETE also exists for this URI.
            $hasMutationMethod = false;
            foreach (self::MUTATION_METHODS as $mutationMethod) {
                if ($this->findRoute($uri, $mutationMethod) !== null) {
                    $hasMutationMethod = true;
                    break;
                }
            }

            if (! $hasMutationMethod) {
                $violations[] = sprintf(
                    'GET %s looks like a mutation but has no POST/PUT/PATCH/DELETE method',
                    $uri,
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            sprintf(
                "%d mutation-like route(s) only support GET:\n  %s\n"
                .'Add explicit POST/PUT/PATCH/DELETE methods for state changes.',
                count($violations),
                implode("\n  ", $violations),
            ),
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * @return list<array{uri: string, method: string}>
     */
    private function collectRoutes(): array
    {
        $routes = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();
            if ($this->isExcluded($uri)) {
                continue;
            }
            $methods = array_diff($route->methods(), ['HEAD']);
            foreach ($methods as $method) {
                $routes[] = ['uri' => $uri, 'method' => $method];
            }
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    private function collectUniqueUris(): array
    {
        $seen = [];
        foreach ($this->collectRoutes() as $entry) {
            if (! isset($seen[$entry['uri']])) {
                $seen[$entry['uri']] = true;
            }
        }

        return array_keys($seen);
    }

    /**
     * @return list<string>
     */
    private function collectMutationOnlyUris(): array
    {
        $uris = [];
        $uriMethods = [];

        foreach ($this->collectRoutes() as $entry) {
            $uriMethods[$entry['uri']][] = $entry['method'];
        }

        foreach ($uriMethods as $uri => $methods) {
            $hasGet = in_array('GET', $methods, true);
            $hasMutation = false;
            foreach (self::MUTATION_METHODS as $m) {
                if (in_array($m, $methods, true)) {
                    $hasMutation = true;
                    break;
                }
            }
            if ($hasMutation && ! $hasGet) {
                $uris[] = $uri;
            }
        }

        return $uris;
    }

    private function isExcluded(string $uri): bool
    {
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function findRoute(string $uri, string $method): ?Route
    {
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }

    private function findFirstRoute(string $uri): ?Route
    {
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $uri) {
                return $route;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $middleware
     */
    private function hasAuthMiddleware(array $middleware): bool
    {
        foreach ($middleware as $mw) {
            if (str_contains($mw, 'auth.nexus') || str_contains($mw, 'auth:sanctum') || str_contains($mw, 'auth:') || $mw === 'auth') {
                return true;
            }
        }

        return false;
    }

    private function methodToCall(string $method): string
    {
        return match (strtolower($method)) {
            'get' => 'get',
            'post' => 'post',
            'put' => 'put',
            'patch' => 'patch',
            'delete' => 'delete',
            'options' => 'options',
            default => 'get',
        };
    }

    private function looksLikeMutation(string $uri): bool
    {
        // Heuristic: URIs starting with "take" are legacy mutation handlers.
        if (str_starts_with($uri, 'take')) {
            return true;
        }

        // Exclude form display pages (create, edit, confirm) — these are
        // GET-only form views, not actual mutations. The actual mutations
        // use POST/PUT/PATCH/DELETE on store/update/destroy endpoints.
        if (preg_match('#/(create|edit|confirm)$#', $uri)) {
            return false;
        }
        if ($uri === 'edit' || $uri === 'confirm' || $uri === 'comment/add') {
            return false;
        }

        // Check for mutation-like action verbs in the URI path.
        return (bool) preg_match('/\b(delete|destroy|remove|store|update|submit|approve|reject|ban|unban|kick|warn|promote|demote|reset|flush|move|sticky|lock|pin|unpin|close|reopen|claim|vote|donate|buy|spend|send|forward|reply|merge|split)\b/i', $uri);
    }
}
