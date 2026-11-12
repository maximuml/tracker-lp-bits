<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Cache\NexusCache;
use Illuminate\Http\Request;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression tests for the UI-01/UI-02/UI-03 fixes:
 *  - `<title>` must not contain double-escaped entities (the
 *    two-argument @section stores e($val); the composer decodes it
 *    so the single {{ }} escape renders correctly).
 *  - Query-only hrefs (`?action=...`) are anchored to the current
 *    request path instead of resolving against the document URL.
 *  - `userhistory` comment links must use `?` as the query
 *    separator (was `&tocomm=1` — a 404).
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class UiLinksAndTitleTest extends TestCase
{
    public function test_details_title_is_single_escaped(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create([
            'name' => 'Amp & "Quoted"',
        ]);
        app(NexusCache::class)->redis?->flushDB();

        $response = $this->withNexusCookie($user)->get('/web/details/'.$torrent->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('&quot;', $html);
        $this->assertStringNotContainsString('&amp;quot;', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function test_details_torrent_url_row_offers_copy_button(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();
        app(NexusCache::class)->redis?->flushDB();

        $response = $this->withNexusCookie($user)->get('/web/details/'.$torrent->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('data-copy-value="', $html);
        $this->assertStringContainsString('/download?downhash=', $html);
        $this->assertStringContainsString('Copy torrent link', $html);
        $this->assertStringContainsString('data-copy-live', $html);
        $this->assertStringNotContainsString('Right click to view', $html);
    }

    public function test_details_torrent_url_row_hidden_when_download_denied(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create(['downloadpos' => false]);
        $torrent = Torrent::factory()->owner($owner)->create();
        app(NexusCache::class)->redis?->flushDB();

        $response = $this->withNexusCookie($viewer)->get('/web/details/'.$torrent->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringNotContainsString('data-nx-copy', $html);
        $this->assertStringNotContainsString('data-copy-value', $html);
        $this->assertStringNotContainsString('Torrent URL', $html);
    }

    public function test_details_torrent_url_row_visible_to_owner_without_downloadpos(): void
    {
        $owner = User::factory()->create(['downloadpos' => false]);
        $torrent = Torrent::factory()->owner($owner)->create();
        app(NexusCache::class)->redis?->flushDB();

        $response = $this->withNexusCookie($owner)->get('/web/details/'.$torrent->id);

        $response->assertOk();
        $this->assertStringContainsString('data-copy-value="', (string) $response->getContent());
    }

    public function test_forum_pager_normalizes_query_only_href(): void
    {
        $this->app->instance('request', Request::create('/forums', 'GET'));

        $html = (string) view('components.forum.pager', [
            'page' => 0,
            'pages' => 3,
            'href' => '?action=viewforum&forumid=1&',
            'items' => [1, 2, 3],
        ])->render();

        $this->assertStringContainsString('href="/forums?action=viewforum', $html);
        $this->assertStringNotContainsString('href="?action=', $html);
    }

    public function test_userhistory_comment_link_uses_query_separator(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();
        Comment::factory()->author($user)->torrent($torrent)->create();
        app(NexusCache::class)->redis?->flushDB();

        $response = $this->withNexusCookie($user)
            ->get('/web/userhistory?id='.$user->id.'&action=viewcomments');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString(
            '/web/details/'.$torrent->id.'?tocomm=1&hit=1',
            $html,
        );
        $this->assertStringNotContainsString('&tocomm=1', $html);
    }
}
