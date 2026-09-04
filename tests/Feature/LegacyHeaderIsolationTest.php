<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Listeners\ResetNexus;
use App\Support\LegacyHeaderBag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Cross-request isolation tests for the legacy header bag.
 *
 * Verifies that headers and status codes set via LegacyHeaderBag
 * do not leak from one request to the next under Octane-style
 * sequential request handling.
 */
final class LegacyHeaderIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetState();
    }

    protected function tearDown(): void
    {
        $this->resetState();
        parent::tearDown();
    }

    public function test_header_does_not_leak_between_requests(): void
    {
        $bag = app(LegacyHeaderBag::class);

        // Request A: set a Location header
        $bag->set('Location', '/index.php');
        $this->assertSame('/index.php', $bag->first('Location'));

        // End of request A → ResetNexus
        $this->dispatchResetNexus();

        // Request B: Location should be cleared
        $this->assertNull($bag->first('Location'));
    }

    public function test_status_code_does_not_leak_between_requests(): void
    {
        $bag = app(LegacyHeaderBag::class);

        // Request A: set a 503 status code
        $bag->setStatusCode(503);
        $this->assertSame(503, $bag->getStatusCode());

        // End of request A → ResetNexus
        $this->dispatchResetNexus();

        // Request B: status code should be cleared
        $this->assertNull($bag->getStatusCode());
    }

    public function test_content_type_header_does_not_leak_between_requests(): void
    {
        $bag = app(LegacyHeaderBag::class);

        // Request A: set Content-Type (e.g. from ImageCaptchaDriver)
        $bag->set('Content-Type', 'image/png');
        $this->assertSame('image/png', $bag->first('Content-Type'));

        // End of request A → ResetNexus
        $this->dispatchResetNexus();

        // Request B: Content-Type should be cleared
        $this->assertNull($bag->first('Content-Type'));
    }

    public function test_multiple_headers_cleared_between_requests(): void
    {
        $bag = app(LegacyHeaderBag::class);

        // Request A: set multiple headers + status
        $bag->set('Location', '/redirect');
        $bag->set('Content-Type', 'text/html');
        $bag->set('X-Custom', 'value');
        $bag->setStatusCode(302);

        // End of request A → ResetNexus
        $this->dispatchResetNexus();

        // Request B: all should be cleared
        $this->assertNull($bag->first('Location'));
        $this->assertNull($bag->first('Content-Type'));
        $this->assertNull($bag->first('X-Custom'));
        $this->assertNull($bag->getStatusCode());
    }

    public function test_alternating_requests_no_leak(): void
    {
        $bag = app(LegacyHeaderBag::class);

        // Simulate alternating requests with different headers
        $sequence = [
            ['Location', '/page-a.php', 302],
            ['Content-Type', 'image/png', 200],
            ['Location', '/page-b.php', 301],
            ['Content-Type', 'text/html', 200],
            ['Location', '/page-c.php', 302],
        ];

        foreach ($sequence as $i => [$header, $value, $status]) {
            // Start of request
            $bag->set($header, $value);
            $bag->setStatusCode($status);

            // Verify state is correct for this request
            $this->assertSame(
                $value,
                $bag->first($header),
                "Request $i: expected $header=$value"
            );
            $this->assertSame(
                $status,
                $bag->getStatusCode(),
                "Request $i: expected status $status"
            );

            // End of request → ResetNexus
            $this->dispatchResetNexus();

            // After reset, should be cleared
            $this->assertNull($bag->first($header), "Request $i: $header leaked after reset");
            $this->assertNull($bag->getStatusCode(), "Request $i: status leaked after reset");
        }
    }

    public function test_consecutive_resets_are_idempotent(): void
    {
        $bag = app(LegacyHeaderBag::class);

        $bag->set('Location', '/test');
        $bag->setStatusCode(302);

        $this->dispatchResetNexus();
        $this->dispatchResetNexus();
        $this->dispatchResetNexus();

        $this->assertNull($bag->first('Location'));
        $this->assertNull($bag->getStatusCode());
    }

    /**
     * Dispatch the ResetNexus listener as Octane would between requests.
     */
    private function dispatchResetNexus(): void
    {
        app(ResetNexus::class)->handle(null);
    }

    /**
     * Reset all state to simulate a fresh worker.
     */
    private function resetState(): void
    {
        app(LegacyHeaderBag::class)->flush();
    }
}
