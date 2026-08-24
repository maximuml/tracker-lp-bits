<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Smoke-test the legacy pages that were drained from procedural
 * *_content.php partials into typed *PageService classes (Sprints 7-14).
 *
 * Each test authenticates as a user with sufficient privileges and
 * asserts that the page renders with HTTP 200 and contains expected
 * content markers. This catches regressions where a migrated Blade
 * partial is missing a variable or a service method throws.
 */
final class DrainedPageSmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
    }

    // -----------------------------------------------------------------------
    // Sprint 7 — usercp_content.php → UsercpPageService
    // -----------------------------------------------------------------------

    public function test_usercp_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $response = $this->withNexusCookie($user)->get('/usercp');

        $response->assertStatus(200);
        $response->assertSeeText('User CP', false);
    }

    // -----------------------------------------------------------------------
    // Sprint 8 — messages_content.php → MessagePageService
    // -----------------------------------------------------------------------

    public function test_messages_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $response = $this->withNexusCookie($user)->get('/messages');

        $response->assertStatus(200);
    }

    // -----------------------------------------------------------------------
    // Sprint 9 — offers_content.php → OfferPageService
    // -----------------------------------------------------------------------

    public function test_offers_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $response = $this->withNexusCookie($user)->get('/offers');

        $response->assertStatus(200);
    }

    // -----------------------------------------------------------------------
    // Sprint 10 — my_bonus_content.php → BonusPageService
    // -----------------------------------------------------------------------

    public function test_mybonus_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $response = $this->withNexusCookie($user)->get('/mybonus');

        $response->assertStatus(200);
    }

    // -----------------------------------------------------------------------
    // Sprint 12 — forum_forums_content.php → ForumPageService
    // -----------------------------------------------------------------------

    public function test_forums_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $response = $this->withNexusCookie($user)->get('/forums');

        $response->assertStatus(200);
    }

    // -----------------------------------------------------------------------
    // Sprint 13 — usersearch_content.php → UsersearchPageService
    // -----------------------------------------------------------------------

    public function test_usersearch_page_renders_for_moderator(): void
    {
        $mod = User::factory()->class(intval(User::CLASS_MODERATOR))->create();
        $response = $this->withNexusCookie($mod)->get('/usersearch');

        $response->assertStatus(200);
        $response->assertSeeText('Administrative User Search', false);
    }

    public function test_usersearch_page_denies_non_moderator(): void
    {
        $user = User::factory()->create();
        $response = $this->withNexusCookie($user)->get('/usersearch');

        // Non-moderators get a permission-denied message in the response body
        $response->assertSeeText('Permission denied', false);
    }

    // -----------------------------------------------------------------------
    // Sprint 14 — index_content.php → IndexPageService
    // -----------------------------------------------------------------------

    public function test_index_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $response = $this->withNexusCookie($user)->get('/index');

        $response->assertStatus(200);
    }

    public function test_index_page_contains_disclaimer_section(): void
    {
        $user = User::factory()->create();
        $response = $this->withNexusCookie($user)->get('/index');

        $response->assertStatus(200);
        $response->assertSeeText('Disclaimer', false);
    }

    public function test_index_page_contains_browser_note(): void
    {
        $user = User::factory()->create();
        $response = $this->withNexusCookie($user)->get('/index');

        $response->assertStatus(200);
        // The browser note is rendered at the bottom of the index page
        $response->assertSee('nexus.png', false);
    }
}
