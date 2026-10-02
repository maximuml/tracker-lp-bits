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

        ];
    }
}
