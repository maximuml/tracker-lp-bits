<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 1.2: verify that the /cron endpoint is protected by the
 * CronToken middleware. Access is granted for loopback requests or
 * when a valid CRON_TOKEN query parameter is supplied; all other
 * requests receive 403 Forbidden.
 */
final class Phase12CronTokenTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    public function test_cron_allows_loopback_without_token(): void
    {
        // Test requests come from 127.0.0.1 by default
        $response = $this->get('/cron');

        // The cron endpoint runs cleanup — it may return any 200-level
        // response. The key assertion is that it is NOT 403.
        $this->assertNotSame(403, $response->status(), 'Loopback request should not be blocked by CronToken middleware.');
    }

    public function test_cron_blocks_non_loopback_without_token(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->get('/cron');

        $response->assertStatus(403);
    }

    public function test_cron_blocks_non_loopback_with_wrong_token(): void
    {
        config(['app.cron_token' => 'secret-token-value']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->get('/cron?token=wrong-token');

        $response->assertStatus(403);
    }

    public function test_cron_allows_non_loopback_with_valid_token(): void
    {
        config(['app.cron_token' => 'secret-token-value']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->get('/cron?token=secret-token-value');

        $this->assertNotSame(403, $response->status(), 'Request with valid token should not be blocked.');
    }

    public function test_cron_blocks_non_loopback_when_token_config_empty(): void
    {
        config(['app.cron_token' => '']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->get('/cron?token=anything');

        $response->assertStatus(403);
    }
}
