<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 5.3: verify that the legacy bans/cheaters/iphistory/ipcheck/ipsearch
 * endpoints redirect to the Filament SecurityResource group.
 */
final class Phase53SecurityRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
    }

    public function test_bans_redirects_to_filament_ban_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/bans');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/security/bans');
    }

    public function test_cheaterbox_redirects_to_filament_cheater_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/cheaterbox');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/security/cheaters');
    }

    public function test_cheaters_redirects_to_filament_cheater_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/cheaters');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/security/cheaters');
    }

    public function test_ipcheck_redirects_to_filament_user_list(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/ipcheck');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users');
    }

    public function test_iphistory_with_id_redirects_to_filament_user_view(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/iphistory?id=42');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users/42');
    }

    public function test_iphistory_without_id_redirects_to_filament_user_list(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/iphistory');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users');
    }

    public function test_ipsearch_redirects_to_filament_user_list(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/ipsearch?ip=1.2.3.4');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users');
    }
}
