<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Structural assertions for the Variant-A modern chrome (ADR 0014).
 *
 * These replace byte-exact legacy HTML pinning for migrated pages:
 * the shell must expose semantic landmarks and data-* markers, while
 * its visual output is free to evolve.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class ModernChromeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_renders_semantic_chrome_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/index');

        $response->assertOk();

        $html = (string) $response->getContent();

        // Semantic landmarks instead of the legacy frame/table chrome.
        $this->assertStringContainsString('data-chrome="modern"', $html);
        $this->assertStringContainsString('<header class="nxm-header" role="banner">', $html);
        $this->assertStringContainsString('<nav class="nxm-nav"', $html);
        $this->assertStringContainsString('<main id="main-content"', $html);
        $this->assertStringContainsString('role="contentinfo"', $html);
        $this->assertStringContainsString('class="skip-link"', $html);
        $this->assertStringContainsString('css/modern.css', $html);
    }

    public function test_index_marks_current_nav_item(): void
    {
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/index');
        $html = (string) $response->getContent();

        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertMatchesRegularExpression(
            '/<a href="index\.php"[^>]*aria-current="page"/',
            $html,
            'Home nav item must carry aria-current on /index',
        );
    }

    public function test_index_shows_user_bar_stats(): void
    {
        $user = User::factory()->create([
            'seedbonus' => 42.5,
            'invites' => 3,
        ]);

        $response = $this->withNexusCookie($user)->get('/index');
        $html = (string) $response->getContent();

        $this->assertStringContainsString('nxm-userbar', $html);
        $this->assertStringContainsString('logout.php', $html);
        $this->assertStringContainsString('usercp.php', $html);
        $this->assertStringContainsString('messages.php', $html);
        $this->assertStringContainsString('42.5', $html);
    }

    public function test_index_does_not_use_legacy_frame_chrome(): void
    {
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/index');
        $html = (string) $response->getContent();

        // The legacy shell wrapped content in Frame::mainOpen tables and
        // the themed stylesheet; the modern shell must not emit them.
        $this->assertStringNotContainsString('class="embedded"', $html);
        $this->assertStringNotContainsString('DomTT.css', $html);
        $this->assertStringNotContainsString('domTT.js', $html);
    }
}
