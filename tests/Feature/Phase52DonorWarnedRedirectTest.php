<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 5.2: verify that the legacy donorlist/warned/nowarn endpoints redirect
 * to the Filament UserResource with appropriate filters.
 */
final class Phase52DonorWarnedRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
    }

    public function testDonorlistRedirectsToFilamentUserListWithDonorFilter(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/donorlist');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users?tableFilters[is_donating][value]=yes');
    }

    public function testWarnedRedirectsToFilamentUserListWithWarnedFilter(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/warned');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users?tableFilters[warned][value]=yes');
    }

    public function testNowarnPostRedirectsToFilamentUserListWithWarnedFilter(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->post('/nowarn', ['usernw' => [1, 2]]);

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users?tableFilters[warned][value]=yes');
    }
}
