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
 * but render inside the semantic `layouts.app` shell instead of the
 * legacy `PageRenderer::headerHtml` chrome.
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

        $response = $this->withNexusCookie($user)->get('/web/details/'.$torrent->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertModernChrome($html);
        $this->assertStringContainsString('overflow-x-auto', $html);
        $this->assertStringContainsString('/download?id='.$torrent->id, $html);
    }

    public function test_web_routes_mark_their_matching_navigation_item_current(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $browseMode = (int) SearchBox::getBrowseMode();
        SearchBox::find($browseMode) ?? SearchBox::factory()->create(['id' => $browseMode]);
        $category = Category::where('mode', $browseMode)->first()
            ?? Category::factory()->mode($browseMode)->create();
        $torrent = Torrent::factory()->owner($user)->category($category->id)->create();

        $routes = [
            '/web/index' => '/web/index',
            '/web/torrents' => '/web/torrents',
            '/web/details/'.$torrent->id => '/web/torrents',
            '/web/upload' => '/web/upload',
            '/forums' => '/forums',
        ];

        foreach ($routes as $uri => $expectedHref) {
            $response = $this->withNexusCookie($user)->get($uri);

            $response->assertOk();
            $this->assertSame(
                [$expectedHref],
                $this->currentNavHrefs((string) $response->getContent()),
                "{$uri} should mark {$expectedHref} current"
            );
        }
    }

    public function test_personal_web_routes_do_not_mark_home_current(): void
    {
        $user = User::factory()->create(['class' => 1]);

        foreach ([
            '/web/messages',
            '/web/sendmessage?receiver='.$user->id,
            '/web/mybonus',
        ] as $uri) {
            $response = $this->withNexusCookie($user)->get($uri);

            $response->assertOk();
            $this->assertSame(
                [],
                $this->currentNavHrefs((string) $response->getContent()),
                "{$uri} should not mark a navigation item current"
            );
        }
    }

    /**
     * @return list<string>
     */
    private function currentNavHrefs(string $html): array
    {
        $this->assertSame(1, preg_match('/<nav class="nxm-nav"[^>]*>(.*?)<\/nav>/s', $html, $nav));
        preg_match_all('/<a\b[^>]*>/s', $nav[1], $links);

        $hrefs = [];
        foreach ($links[0] as $link) {
            if (preg_match('/\baria-current="page"/', $link) === 1
                && preg_match('/\bhref="([^"]+)"/', $link, $href) === 1) {
                $hrefs[] = $href[1];
            }
        }

        return $hrefs;
    }
}
