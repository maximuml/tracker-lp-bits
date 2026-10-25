<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SetlistLookup;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Transport-layer tests — every external fetch must go through the Http
 * facade so `Http::fake()` intercepts it without network.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class SetlistLookupTest extends TestCase
{
    private const SETLIST_MARKDOWN = <<<'MD'
        Title: Linkin Park Setlist at Nürburgring

        # **[Linkin Park](https://www.setlist.fm/setlists/linkin-park.html) Setlist** at [Nürburgring, Germany](https://www.setlist.fm/venue/x.html)

        Jun 7 2024

        ## Setlist

        1. Song One
        2. Song Two
        MD;

    private const SETLIST_URL = 'https://www.setlist.fm/setlist/linkin-park/2024/rock-im-park-12345678.html';

    public function test_from_url_rejects_non_setlist_hosts_without_fetching(): void
    {
        Http::fake();

        $result = SetlistLookup::fromUrl('https://evil.example/x');

        $this->assertFalse($result['success']);
        $this->assertSame('Only setlist.fm URLs are supported.', $result['error']);
        Http::assertNothingSent();
    }

    public function test_from_url_fetches_through_jina_and_parses(): void
    {
        Http::fake(['*' => Http::response(self::SETLIST_MARKDOWN)]);

        $result = SetlistLookup::fromUrl(self::SETLIST_URL);

        $this->assertTrue($result['success']);
        $this->assertSame('Linkin Park', $result['data']['artist']);
        $this->assertSame('2024-06-07', $result['data']['date']);
        $this->assertSame(['Song One', 'Song Two'], array_column($result['data']['sets'][0]['songs'], 'name'));

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://r.jina.ai/http://www.setlist.fm/setlist/')
            && $r->hasHeader('User-Agent'));
        Http::assertSentCount(1);
    }

    public function test_from_url_surfaces_fetch_failure(): void
    {
        Http::fake(['*' => Http::response('Server Error', 500)]);

        $result = SetlistLookup::fromUrl(self::SETLIST_URL);

        $this->assertFalse($result['success']);
        $this->assertSame('Could not fetch setlist page.', $result['error']);
    }

    public function test_from_torrent_name_uses_pooled_fetches(): void
    {
        Http::fake([
            'linkinpedia.com/*' => Http::response(json_encode(['error' => ['code' => 'missingtitle']])),
            'r.jina.ai/*search*' => Http::response('[Linkin Park](https://www.setlist.fm/setlist/linkin-park/2024/rock-im-park-12345678.html)'),
            'r.jina.ai/*setlist*' => Http::response(self::SETLIST_MARKDOWN),
            '*' => Http::response('', 404),
        ]);

        $result = SetlistLookup::fromTorrentName('Linkin Park - Nürburg, Germany, Rock im Park (07.06.2024)');

        $this->assertTrue($result['success']);
        $this->assertSame('Linkin Park', $result['data']['artist']);

        // All five Linkinpedia suffix candidates went out in one batch.
        $this->assertSame(5, count(Http::recorded(
            fn (Request $r) => str_starts_with($r->url(), 'https://linkinpedia.com/'),
        )));
        $this->assertGreaterThan(0, count(Http::recorded(
            fn (Request $r) => str_contains($r->url(), 'search?query='),
        )));
    }

    public function test_from_torrent_name_returns_error_when_nothing_matches(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $result = SetlistLookup::fromTorrentName('Linkin Park - Nürburg, Germany, Rock im Park (07.06.2024)');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No setlist found', (string) $result['error']);
    }
}
