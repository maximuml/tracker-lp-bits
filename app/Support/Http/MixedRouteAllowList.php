<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * T-21: Allow-list for mixed-method routes (Route::match / Route::any).
 *
 * The architecture test `MixedRouteAllowListTest` enforces that no new
 * mixed-method routes are added without being registered here. Each entry
 * must include a justification for why GET and POST (or other method
 * combinations) share the same controller action.
 *
 * Categories:
 * - "protocol": BitTorrent protocol endpoints that accept any method
 * - "api": RESTful endpoints combining PUT+PATCH
 *
 * To reduce this list: split the controller action into separate
 * GET (display) and POST (submit) methods, then change the route to
 * Route::get() or Route::post() respectively.
 */
final class MixedRouteAllowList
{
    /**
     * @return array<string, array{category: string, reason: string}>
     */
    public static function entries(): array
    {
        return [
            // --- BitTorrent protocol (Route::any) ---
            'GET /announce' => ['category' => 'protocol', 'reason' => 'BitTorrent announce — clients may use GET or UDP-over-HTTP, any method accepted'],
            'GET /announce.php' => ['category' => 'protocol', 'reason' => 'Legacy .php alias for announce'],
            'GET /scrape' => ['category' => 'protocol', 'reason' => 'BitTorrent scrape — same as announce'],
            'GET /scrape.php' => ['category' => 'protocol', 'reason' => 'Legacy .php alias for scrape'],

            // --- api.php: RESTful (Route::apiResource update — PUT+PATCH combined) ---
            'PUT api/v1/messages/{message}' => ['category' => 'api', 'reason' => 'RESTful message update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/news/{news}' => ['category' => 'api', 'reason' => 'RESTful news update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/polls/{poll}' => ['category' => 'api', 'reason' => 'RESTful poll update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/forums/{forum}' => ['category' => 'api', 'reason' => 'RESTful forum update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/topics/{topic}' => ['category' => 'api', 'reason' => 'RESTful topic update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/topics/{topic}/posts/{post}' => ['category' => 'api', 'reason' => 'RESTful post update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/agent-allows/{agent_allow}' => ['category' => 'api', 'reason' => 'RESTful agent-allow update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/agent-denies/{agent_deny}' => ['category' => 'api', 'reason' => 'RESTful agent-deny update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/exams/{exam}' => ['category' => 'api', 'reason' => 'RESTful exam update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/medals/{medal}' => ['category' => 'api', 'reason' => 'RESTful medal update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/user-medals/{user_medal}' => ['category' => 'api', 'reason' => 'RESTful user-medal update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/tags/{tag}' => ['category' => 'api', 'reason' => 'RESTful tag update — PUT+PATCH combined for compatibility'],
            'PUT api/v1/hr/{hr}' => ['category' => 'api', 'reason' => 'RESTful H&R update — PUT+PATCH combined for compatibility'],
        ];
    }
}
