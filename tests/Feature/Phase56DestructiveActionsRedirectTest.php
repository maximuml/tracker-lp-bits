<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 5.6: verify that the legacy delacctadmin/deletedisabled/massmail/maxlogin
 * endpoints redirect to the Filament SystemActions page and LoginAttemptResource.
 */
final class Phase56DestructiveActionsRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
    }

    public function test_delacctadmin_redirects_to_system_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/delacctadmin');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/system-actions');
    }

    public function test_deletedisabled_redirects_to_system_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/deletedisabled');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/system-actions');
    }

    public function test_massmail_redirects_to_system_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/massmail');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/system-actions');
    }

    public function test_maxlogin_redirects_to_login_attempt_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/maxlogin');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/login-attempts');
    }
}
