<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 5.5: verify that the legacy stats/allagents endpoints redirect
 * to the Filament dashboard, which now hosts the equivalent widgets.
 */
final class Phase55DashboardWidgetsRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
    }

    public function test_stats_redirects_to_filament_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/stats');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp');
    }

    public function test_allagents_redirects_to_filament_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/allagents');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp');
    }
}
