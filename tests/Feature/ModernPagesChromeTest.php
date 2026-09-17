<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Structural assertions for the remaining Variant-A page chrome swaps
 * (ADR 0014): /details, /forums and /usercp keep their section markup
 * but render inside the semantic `layouts.modern` shell instead of the
 * legacy `PageLayout::headerHtml`/`Frame` chrome.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class ModernPagesChromeTest extends TestCase
{
    use DatabaseTransactions;

    private function assertModernChrome(string $html): void
    {
        $this->assertStringContainsString('data-chrome="modern"', $html);
        $this->assertStringContainsString('<header class="nxm-header" role="banner">', $html);
        $this->assertStringContainsString('<main id="main-content"', $html);
        $this->assertStringContainsString('role="contentinfo"', $html);
        $this->assertStringContainsString('class="skip-link"', $html);
    }

    public function test_usercp_renders_under_modern_chrome(): void
    {
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/usercp');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertModernChrome($html);
        // Section content still renders inside the modern shell.
        $this->assertStringContainsString('nx-fgrid', $html);
    }

    public function test_forums_renders_under_modern_chrome(): void
    {
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/forums');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertModernChrome($html);
    }

    public function test_details_renders_under_modern_chrome(): void
    {
        $user = User::factory()->create();
        $browseMode = (int) SearchBox::getBrowseMode();
        SearchBox::find($browseMode) ?? SearchBox::factory()->create(['id' => $browseMode]);
        $category = Category::where('mode', $browseMode)->first()
            ?? Category::factory()->mode($browseMode)->create();
        $torrent = Torrent::factory()->owner($user)->category($category->id)->create();

        $response = $this->withNexusCookie($user)->get('/details?id='.$torrent->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertModernChrome($html);
        $this->assertStringContainsString('data-nx="data"', $html);
        $this->assertStringContainsString('download.php?id='.$torrent->id, $html);
    }
}
