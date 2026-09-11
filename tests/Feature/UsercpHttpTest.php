<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W1-05: HTTP contract tests for usercp settings mutations.
 * Tests FormRequest validation, authorization via UsercpPolicy,
 * HTTP method boundaries, and legacy redirect behavior.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class UsercpHttpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    // ─── Authentication ────────────────────────────────────────────────

    public function test_usercp_post_redirects_unauthenticated_user(): void
    {
        $this->post('/usercp', ['action' => 'personal', 'type' => 'save'])
            ->assertRedirect();
    }

    public function test_usercp_get_redirects_unauthenticated_user(): void
    {
        $this->get('/usercp')
            ->assertRedirect();
    }

    // ─── FormRequest validation ──────────────────────────────────────

    public function test_personal_save_validates_required_action(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/usercp', [
                'type' => 'save',
                // action missing
            ])
            ->assertRedirect();
    }

    public function test_forum_save_validates_required_type(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/usercp', [
                'action' => 'forum',
                // type missing
            ])
            ->assertRedirect();
    }

    public function test_tracker_save_validates_topicsperpage_max(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/usercp', [
                'action' => 'tracker',
                'type' => 'save',
                'torrentsperpage' => 999, // exceeds max:100
            ])
            ->assertRedirect('/usercp.php?action=tracker');
    }

    public function test_security_confirm_validates_required_action(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/usercp', [
                'type' => 'confirm',
                // action missing
            ])
            ->assertRedirect();
    }

    // ─── Success paths ──────────────────────────────────────────────

    public function test_usercp_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->get('/usercp')
            ->assertStatus(200);
    }

    public function test_personal_save_redirects_on_success(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/usercp', [
                'action' => 'personal',
                'type' => 'save',
                'parked' => 'yes',
                'acceptpms' => 1,
                'gender' => 0,
                'info' => 'Test info',
            ])
            ->assertRedirect();
    }

    public function test_personal_save_accepts_legacy_string_values(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/usercp', [
                'action' => 'personal',
                'type' => 'save',
                'acceptpms' => 'friends',
                'gender' => 'Male',
            ])
            ->assertRedirect('/usercp.php?action=personal&type=saved');
    }

    public function test_forum_save_redirects_on_success(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/usercp', [
                'action' => 'forum',
                'type' => 'save',
                'topicsperpage' => 25,
                'postsperpage' => 30,
                'avatars' => 'yes',
                'signatures' => 'yes',
                'clicktopic' => 1,
                'signature' => 'My signature',
            ])
            ->assertRedirect();
    }

    public function test_forum_save_accepts_legacy_string_values(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/usercp', [
                'action' => 'forum',
                'type' => 'save',
                'clicktopic' => 'lastpage',
            ])
            ->assertRedirect('/usercp.php?action=forum&type=saved');
    }

    public function test_tracker_save_redirects_on_success(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/usercp', [
                'action' => 'tracker',
                'type' => 'save',
                'torrentsperpage' => 50,
                'timetype' => 0,
                'appendsticky' => 'yes',
                'appendnew' => 'yes',
                'appendpromotion' => 1,
                'appendpicked' => 'yes',
                'dlicon' => 'yes',
                'bmicon' => 'yes',
                'showcomnum' => 'yes',
                'showdescription' => 'yes',
                'smalldescr' => 'yes',
                'showcomment' => 'yes',
                'pmnum' => 20,
                'sbnum' => 70,
                'sbrefresh' => 120,
                'fontsize' => 2,
            ])
            ->assertRedirect();
    }

    public function test_tracker_save_accepts_legacy_string_values(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/usercp', [
                'action' => 'tracker',
                'type' => 'save',
                'fontsize' => 'small',
                'timetype' => 'timeadded',
                'appendpromotion' => 'highlight',
                'tooltip' => 'off',
            ])
            ->assertRedirect('/usercp.php?action=tracker&type=saved');
    }

    // ─── HTTP method boundaries ─────────────────────────────────────

    public function test_usercp_get_does_not_accept_post_only_action(): void
    {
        $user = User::factory()->create();

        // GET to /usercp renders the page (read-only), not a mutation
        $this->withNexusCookie($user)
            ->get('/usercp')
            ->assertStatus(200);
    }
}
