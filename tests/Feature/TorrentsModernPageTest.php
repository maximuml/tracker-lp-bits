<?php

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Structural assertions for the modernized /torrents page
 * (Variant A, ADR 0014): the page must render under the modern
 * chrome, expose the search panel and the torrent table as
 * semantic landmarks with data-* markers, and must not contain
 * generated-HTML leftovers from SearchBox/TorrentTable.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class TorrentsModernPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_torrents_renders_modern_chrome_and_search_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/torrents');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('data-chrome="modern"', $html);
        $this->assertStringContainsString('<main id="main-content"', $html);
        $this->assertStringContainsString('nxm-searchpanel', $html);
        $this->assertStringContainsString('nxm-searchpanel__toggle', $html);
        // Search panel is built from fieldset/legend groups, not the
        // legacy generated <table> from SearchBox::buildCategoryTable.
        $this->assertStringContainsString('<fieldset', $html);
        $this->assertStringContainsString('<legend', $html);
    }

    public function test_torrents_table_renders_prepared_rows(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'name' => 'Structural Marker Torrent',
            'category' => 1,
            'owner' => $user->id,
            'visible' => 1,
            'banned' => 0,
            'anonymous' => 0,
            'source' => 1,
            'medium' => 1,
            'codec' => 1,
            'standard' => 1,
            'processing' => 1,
            'audiocodec' => 1,
        ]);

        // cat= pins the category filter deterministically — the default
        // listing is cached under 'category_list_mode_*' in Redis and may
        // be stale within a single test process.
        $response = $this->withNexusCookie($user)->get('/torrents?cat=1');
        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('nx-torrents', $html);
        $this->assertStringContainsString('data-nx="data"', $html);
        $this->assertStringContainsString('colhead', $html);
        $this->assertStringContainsString('nxm-nameblock', $html);
        $this->assertStringContainsString('details.php?id='.$torrent->id, $html);
        $this->assertStringContainsString('Structural Marker Torrent', $html);
    }

    public function test_torrents_empty_state_has_status_role(): void
    {
        $user = User::factory()->create();

        // A search string that matches nothing forces the empty branch.
        $response = $this->withNexusCookie($user)->get('/torrents?search='.rawurlencode('zzz-no-such-torrent-marker'));
        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('nxm-empty', $html);
        $this->assertStringContainsString('role="status"', $html);
    }
}
