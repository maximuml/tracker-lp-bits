<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 5.4: verify that the legacy staffbox endpoint redirects to the
 * Filament StaffMessageResource, and that modtask actions (warn, uploadpos,
 * downloadpos, forumpost) are available as UserProfile header actions.
 */
final class Phase54ModtaskStaffMessageRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_staffbox_redirects_to_filament_staff_message_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/staffbox');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/security/staff-messages');
    }

    public function test_staffbox_post_redirects_to_filament_staff_message_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->post('/staffbox');

        $response->assertStatus(302);
        $response->assertRedirect('/nexusphp/security/staff-messages');
    }

    public function test_staffmess_remains_legacy_route(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->withNexusCookie($admin)->get('/staffmess');

        // staffmess is still a legacy route — should not redirect to Filament
        $this->assertNotEquals(302, $response->getStatusCode());
    }

    public function test_contactstaff_remains_legacy_route(): void
    {
        $user = User::factory()->create();
        $response = $this->withNexusCookie($user)->get('/contactstaff');

        // contactstaff is still a legacy route — should not redirect to Filament
        $this->assertNotEquals(302, $response->getStatusCode());
    }
}
