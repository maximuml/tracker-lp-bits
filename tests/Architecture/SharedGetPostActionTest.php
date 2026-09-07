<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * W0-05: Ratchet on shared GET/POST actions.
 *
 * Detects routes where the same URI is registered separately for
 * GET and POST but points to the same controller@action. This is a
 * legacy pattern that mixes rendering and mutation in one method.
 *
 * Standard RESTful PUT+PATCH combinations are excluded — they are
 * a normal API pattern, not legacy debt.
 *
 * To fix a failure:
 * 1. Split the shared action into separate GET (query/display) and
 *    POST (command/mutation) methods.
 * 2. If it cannot be split yet, add the URI to BASELINE_URIS below
 *    with a justification comment.
 */
final class SharedGetPostActionTest extends TestCase
{
    /**
     * Baseline: URIs where GET and POST share the same controller action.
     *
     * These are legacy actions that need to be split in Wave 1.
     * The count must only decrease — never add new entries without
     * an architectural decision.
     *
     * Captured on 2026-09-07.
     *
     * @var array<string, true>
     */
    private const BASELINE_URIS = [
        // Auth
        'recover' => true,
        // API
        'api/v1/usercp/settings' => true,
        // Info pages
        'faq' => true,
        'donate' => true,
        'donated' => true,
        'bitbucketlog' => true,
        'news' => true,
        // Support
        'complains' => true,
        // Bonus
        'freeleech' => true,
        // Friends/messages
        'friends' => true,
        // RSS
        'getrss' => true,
        // Polls
        'makepoll' => true,
        'polloverview' => true,
        // Attendance
        'attendance' => true,
        // Moderation
        'modtask' => true,
        'modrules' => true,
        'staffmess' => true,
        'contactstaff' => true,
        // Admin tools
        'bans' => true,
        'staffbox' => true,
        'user-ban-log' => true,
        'clearcache' => true,
        'delacctadmin' => true,
        'massmail' => true,
        'location' => true,
        'maxlogin' => true,
        'testip' => true,
        // User admin
        'reset' => true,
        'self-enable' => true,
        'unco' => true,
        'adduser' => true,
        // FAQ management
        'faqmanage' => true,
        'faqactions' => true,
        // Legacy pages
        'log' => true,
        'index' => true,
        // Tracker protocol
        'announce' => true,
        'announce.php' => true,
        'scrape' => true,
        'scrape.php' => true,
    ];

    public function test_no_new_shared_get_post_actions(): void
    {
        $violations = [];
        /** @var array<string, array<int, \Illuminate\Routing\Route>> $byUri */
        $byUri = [];

        /** @var \Illuminate\Routing\Route[] $routes */
        $routes = Route::getRoutes()->getRoutes();

        foreach ($routes as $route) {
            $methods = $route->methods();
            $methods = array_filter($methods, fn ($m) => $m !== 'OPTIONS' && $m !== 'HEAD');

            $uri = $route->uri();
            $action = $route->getActionName();

            // Only track GET and POST routes
            foreach ($methods as $method) {
                if ($method === 'GET' || $method === 'POST') {
                    $byUri[$uri][$method] = $action;
                }
            }
        }

        foreach ($byUri as $uri => $methodActions) {
            $getAction = $methodActions['GET'] ?? null;
            $postAction = $methodActions['POST'] ?? null;

            if ($getAction === null || $postAction === null) {
                continue;
            }

            // Same action handles both GET and POST — legacy pattern
            if ($getAction === $postAction) {
                if (! array_key_exists($uri, self::BASELINE_URIS)) {
                    $violations[] = sprintf(
                        '%s — GET and POST share action %s. Split into separate query and command methods.',
                        $uri,
                        $getAction,
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Found new routes where GET and POST share the same controller action:\n".
            implode("\n", $violations)."\n\n".
            'Split the shared action into separate GET (display) and POST (mutation) methods, '.
            'or add the URI to BASELINE_URIS with a justification.',
        );
    }

    public function test_shared_get_post_action_count_does_not_increase(): void
    {
        $currentUris = $this->getSharedGetPostActionUris();
        $currentCount = count($currentUris);
        $baselineCount = count(self::BASELINE_URIS);

        $this->assertLessThanOrEqual(
            $baselineCount,
            $currentCount,
            sprintf(
                'Shared GET/POST action count increased from baseline %d to %d. '.
                'Split shared actions into separate GET/POST methods.',
                $baselineCount,
                $currentCount,
            ),
        );
    }

    public function test_baseline_uris_still_exist(): void
    {
        $currentUris = $this->getSharedGetPostActionUris();
        $stale = [];

        foreach (array_keys(self::BASELINE_URIS) as $uri) {
            if (! in_array($uri, $currentUris, true)) {
                $stale[] = $uri;
            }
        }

        $this->assertSame(
            [],
            $stale,
            "Baseline URIs no longer have shared GET/POST actions — remove from BASELINE_URIS:\n".
            implode("\n", $stale),
        );
    }

    /**
     * @return list<string>
     */
    private function getSharedGetPostActionUris(): array
    {
        $byUri = [];
        /** @var \Illuminate\Routing\Route[] $routes */
        $routes = Route::getRoutes()->getRoutes();

        foreach ($routes as $route) {
            $methods = $route->methods();
            $methods = array_filter($methods, fn ($m) => $m !== 'OPTIONS' && $m !== 'HEAD');

            $uri = $route->uri();
            $action = $route->getActionName();

            foreach ($methods as $method) {
                if ($method === 'GET' || $method === 'POST') {
                    $byUri[$uri][$method] = $action;
                }
            }
        }

        $shared = [];
        foreach ($byUri as $uri => $methodActions) {
            $getAction = $methodActions['GET'] ?? null;
            $postAction = $methodActions['POST'] ?? null;

            if ($getAction !== null && $postAction !== null && $getAction === $postAction) {
                $shared[] = $uri;
            }
        }

        return $shared;
    }
}
