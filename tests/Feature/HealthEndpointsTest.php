<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * /health/* routing through LegacyRequestMiddleware and the diag gate.
 *
 * /health/live and /health/ready must stay reachable for unauthenticated
 * probes; /health/diag requires a sysop-class session.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class HealthEndpointsTest extends TestCase
{
    public function test_live_and_ready_are_reachable_unauthenticated(): void
    {
        $this->get('/health/live')->assertOk()->assertJson(['status' => 'ok']);
        $this->get('/health/ready')->assertOk()->assertJsonStructure(['status', 'checks']);
    }

    public function test_diag_redirects_guests_to_login(): void
    {
        $response = $this->get('/health/diag');
        $response->assertStatus(302);
    }

    public function test_diag_denies_regular_users(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/health/diag')->assertForbidden();
    }

    public function test_diag_returns_infrastructure_payload_for_sysop(): void
    {
        $sysop = User::factory()->create(['class' => 15]);
        $this->actingAs($sysop, 'nexus-web');

        $this->get('/health/diag')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'php',
                'laravel',
                'environment',
                'db_ping_ms',
                'redis_ping_ms',
                'meilisearch_ms',
                'scheduler_heartbeat_age',
                'horizon_masters',
                'disk_free_bytes',
                'memory_usage_bytes',
                'memory_peak_bytes',
                'time',
            ]);
    }
}
