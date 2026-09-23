<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * The `throttle:` middleware honours `nexus.rate_limiting`: enabled by
 * default, fully bypassed when set to false (e2e stacks share one IP).
 * POST /login sits behind `throttle:login` (10/min per IP) — a cheap
 * named-limiter surface that needs no auth fixtures.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class HttpRateLimitingToggleTest extends TestCase
{
    public function test_named_limiter_throttles_by_default(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login');
        }

        $this->post('/login')->assertStatus(429);
    }

    public function test_named_limiter_bypassed_when_disabled(): void
    {
        config(['nexus.rate_limiting' => false]);

        for ($i = 0; $i < 15; $i++) {
            $this->assertNotSame(429, $this->post('/login')->getStatusCode());
        }
    }
}
