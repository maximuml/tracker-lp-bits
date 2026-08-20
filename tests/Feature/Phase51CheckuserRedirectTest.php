<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 5.1: verify that the legacy checkuser/takeconfirm endpoints redirect
 * to the Filament UserResource instead of rendering the old admin pages.
 */
final class Phase51CheckuserRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
    }

    public function testCheckuserWithIdRedirectsToFilamentUserView(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/checkuser?id=42');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users/42');
    }

    public function testCheckuserWithoutIdRedirectsToFilamentUserList(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/checkuser');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users');
    }

    public function testTakeconfirmPostWithIdRedirectsToFilamentUserView(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->post('/takeconfirm', ['id' => 99]);

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users/99');
    }

    public function testTakeconfirmPostWithoutIdRedirectsToFilamentUserList(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->post('/takeconfirm', []);

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users');
    }

    public function testCheckuserWithInvalidIdRedirectsToList(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/checkuser?id=0');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/users');
    }
}
