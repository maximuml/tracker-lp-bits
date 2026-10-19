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
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/web/torrents');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('data-chrome="modern"', $html);
        $this->assertStringContainsString('<main id="main-content"', $html);
        $this->assertStringContainsString('nxm-searchpanel', $html);
        $this->assertStringContainsString('<details class="nxm-filters" id="ksearchboxmain"', $html);
        $this->assertStringContainsString('<fieldset', $html);
        $this->assertStringContainsString('<legend', $html);
        $searchPosition = strpos($html, 'id="searchinput"');
        $categoryPosition = strpos($html, 'nxm-category');
        $this->assertIsInt($searchPosition);
        $this->assertIsInt($categoryPosition);
        $this->assertLessThan($categoryPosition, $searchPosition);
    }

    public function test_advanced_filters_open_the_details_and_update_the_badge(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $closed = $this->withNexusCookie($user)->get('/web/torrents');
        $closed->assertOk();
        $this->assertDoesNotMatchRegularExpression('/<details[^>]*id="ksearchboxmain"[^>]*\bopen(?:\s|>)/', (string) $closed->getContent());

        $filtered = $this->withNexusCookie($user)->get('/web/torrents?incldead=2&spstate=1');
        $filtered->assertOk();
        $html = (string) $filtered->getContent();
        $this->assertMatchesRegularExpression('/<details[^>]*id="ksearchboxmain"[^>]*\bopen(?:\s|>)/', $html);
        $this->assertStringContainsString('class="nxm-filters__count" aria-hidden="true">2</span>', $html);
    }

    public function test_valid_sort_parameters_are_hidden_and_filter_chips_have_no_links(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get('/web/torrents?sort=4&type=desc');
        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('type="hidden" name="sort" value="4"', $html);
        $this->assertStringContainsString('type="hidden" name="type" value="desc"', $html);

        $this->assertSame(1, preg_match('/<details[^>]*id="ksearchboxmain".*?<\/details>/s', $html, $filters));
        $filterHtml = $filters[0] ?? '';
        preg_match_all('/<fieldset\b[^>]*>.*?<\/fieldset>/si', $filterHtml, $fieldsets);
        foreach ($fieldsets[0] as $fieldset) {
            $this->assertDoesNotMatchRegularExpression('/<a\s+href=/i', $fieldset);
        }
        preg_match_all('/<label class="nx-chip"[^>]*>.*?<\/label>/si', $html, $chips);
        foreach ($chips[0] as $chip) {
            $this->assertDoesNotMatchRegularExpression('/<a\b/i', $chip);
        }
    }

    public function test_torrents_table_renders_prepared_rows(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Torrent $torrent */
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
        $response = $this->withNexusCookie($user)->get('/web/torrents?cat=1');
        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('nx-torrents', $html);
        $this->assertStringContainsString('data-nx="data"', $html);
        $this->assertStringContainsString('colhead', $html);
        $this->assertStringContainsString('nxm-nameblock', $html);
        $this->assertStringContainsString('/web/details/'.$torrent->id, $html);
        $this->assertStringContainsString('Structural Marker Torrent', $html);
    }

    public function test_torrents_empty_state_has_status_role(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // A search string that matches nothing forces the empty branch.
        $response = $this->withNexusCookie($user)->get('/web/torrents?search='.rawurlencode('zzz-no-such-torrent-marker'));
        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('nxm-empty', $html);
        $this->assertStringContainsString('role="status"', $html);
    }

    public function test_sorted_column_exposes_direction_and_short_labels(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        Torrent::factory()->create([
            'category' => 1,
            'owner' => $user->id,
            'visible' => 1,
            'banned' => 0,
            'medium' => 1,
            'codec' => 1,
            'standard' => 1,
            'processing' => 1,
            'audiocodec' => 1,
        ]);

        foreach (['asc' => 'ascending', 'desc' => 'descending'] as $type => $direction) {
            $response = $this->withNexusCookie($user)->get('/web/torrents?sort=4&type='.$type.'&cat=1');
            $response->assertOk();
            $html = (string) $response->getContent();
            $this->assertMatchesRegularExpression('/<th\b[^>]*class="[^"]*nxm-th--sorted[^"]*"[^>]*aria-sort="'.$direction.'"/', $html);
            $this->assertSame(1, preg_match_all('/<th\b[^>]*\baria-sort=/i', $html));
        }

        foreach (['S', 'L', 'Sn'] as $label) {
            $this->assertStringContainsString('nxm-th__label">'.$label.'</span>', $html);
        }
    }
}
