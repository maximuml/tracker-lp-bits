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

    public function testBansRedirectsToFilamentBanResource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/bans');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/bans');
    }

    public function testCheaterboxRedirectsToFilamentCheaterResource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/cheaterbox');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/cheaters');
    }

    public function testCheatersRedirectsToFilamentCheaterResource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/cheaters');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/cheaters');
    }

    public function testIpcheckRedirectsToFilamentUserList(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/ipcheck');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users');
    }

    public function testIphistoryWithIdRedirectsToFilamentUserView(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/iphistory?id=42');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users/42');
    }

    public function testIphistoryWithoutIdRedirectsToFilamentUserList(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/iphistory');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users');
    }

    public function testIpsearchRedirectsToFilamentUserList(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/ipsearch?ip=1.2.3.4');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users');
    }
}
