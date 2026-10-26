<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TorrentApprovalStatus;
use App\Enums\UserPrivacy;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Peer;
use App\Models\SearchBox;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * API privacy regressions: anonymous uploads and STRONG-privacy users
 * must not be deanonymized through the REST resources, and viewer-scope
 * filters (banned / unapproved / anonymous owner listing) apply to the
 * torrent list the same way the web UI applies them.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TorrentApiPrivacyTest extends TestCase
{
    use DatabaseTransactions;

    private function seedBrowseCategory(): Category
    {
        $browseMode = (int) SearchBox::getBrowseMode();
        SearchBox::find($browseMode) ?? SearchBox::factory()->create(['id' => $browseMode]);

        return Category::where('mode', $browseMode)->first()
            ?? Category::factory()->mode($browseMode)->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeTorrent(User $owner, Category $category, array $attributes = []): Torrent
    {
        return Torrent::factory()->owner($owner)->category($category->id)->create(array_merge([
            'approval_status' => TorrentApprovalStatus::ALLOW->value,
        ], $attributes));
    }

    public function test_anonymous_torrent_owner_is_hidden_in_list(): void
    {
        $category = $this->seedBrowseCategory();
        $owner = User::factory()->create();
        $this->makeTorrent($owner, $category, ['anonymous' => 1, 'name' => 'Anon Release One']);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer, ['torrent:list']);

        $response = $this->getJson('/api/v1/torrents?includes=user')
            ->assertStatus(200);

        $row = collect($response->json('data.data'))->firstWhere('name', 'Anon Release One');
        $this->assertNotNull($row);
        $this->assertNull($row['user']);
    }

    public function test_anonymous_torrent_owner_visible_to_self(): void
    {
        $category = $this->seedBrowseCategory();
        $owner = User::factory()->create();
        $this->makeTorrent($owner, $category, ['anonymous' => 1, 'name' => 'Anon Release Two']);

        Sanctum::actingAs($owner, ['torrent:list']);

        $response = $this->getJson('/api/v1/torrents?includes=user')
            ->assertStatus(200);

        $row = collect($response->json('data.data'))->firstWhere('name', 'Anon Release Two');
        $this->assertNotNull($row);
        $this->assertNotNull($row['user']);
        $this->assertSame($owner->id, $row['user']['id']);
    }

    public function test_owner_filter_does_not_enumerate_anonymous_releases(): void
    {
        $category = $this->seedBrowseCategory();
        $uploader = User::factory()->create();
        $this->makeTorrent($uploader, $category, ['anonymous' => 1, 'name' => 'Secret Anon Drop']);
        $this->makeTorrent($uploader, $category, ['anonymous' => 0, 'name' => 'Public Drop']);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer, ['torrent:list']);

        $response = $this->getJson('/api/v1/torrents?'.http_build_query(['filter' => ['owner' => $uploader->id]]))
            ->assertStatus(200);

        $names = collect($response->json('data.data'))->pluck('name');
        $this->assertContains('Public Drop', $names);
        $this->assertNotContains('Secret Anon Drop', $names);
    }

    public function test_owner_filter_returns_own_anonymous_releases(): void
    {
        $category = $this->seedBrowseCategory();
        $uploader = User::factory()->create();
        $this->makeTorrent($uploader, $category, ['anonymous' => 1, 'name' => 'My Own Anon Drop']);

        Sanctum::actingAs($uploader, ['torrent:list']);

        $response = $this->getJson('/api/v1/torrents?'.http_build_query(['filter' => ['owner' => $uploader->id]]))
            ->assertStatus(200);

        $this->assertContains('My Own Anon Drop', collect($response->json('data.data'))->pluck('name'));
    }

    public function test_banned_torrent_absent_from_list_for_regular_user(): void
    {
        $category = $this->seedBrowseCategory();
        $owner = User::factory()->create();
        $this->makeTorrent($owner, $category, ['name' => 'Banned Release', 'banned' => 1]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer, ['torrent:list']);

        $response = $this->getJson('/api/v1/torrents')->assertStatus(200);

        $this->assertNotContains('Banned Release', collect($response->json('data.data'))->pluck('name'));
    }

    public function test_banned_torrent_detail_denied_for_regular_user(): void
    {
        $category = $this->seedBrowseCategory();
        $owner = User::factory()->create();
        $torrent = $this->makeTorrent($owner, $category, ['banned' => 1]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer, ['torrent:view']);

        $this->getJson('/api/v1/detail/'.$torrent->id)->assertStatus(403);
    }

    public function test_anonymous_comment_author_is_hidden(): void
    {
        $category = $this->seedBrowseCategory();
        $owner = User::factory()->create();
        $torrent = $this->makeTorrent($owner, $category);
        $author = User::factory()->create();
        Comment::factory()->author($author)->torrent($torrent)->create(['anonymous' => true]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer, ['torrent:view']);

        $response = $this->getJson('/api/v1/comments?parent_id='.$torrent->id.'&type=torrent')
            ->assertStatus(200);

        $row = collect($response->json('data.data'))->first();
        $this->assertNotNull($row);
        $this->assertNull($row['create_user']);
    }

    public function test_strong_privacy_peer_user_is_hidden(): void
    {
        $category = $this->seedBrowseCategory();
        $owner = User::factory()->create();
        $torrent = $this->makeTorrent($owner, $category);
        $privatePeer = User::factory()->create(['privacy' => UserPrivacy::STRONG->value]);
        Peer::factory()->create(['torrent' => $torrent->id, 'userid' => $privatePeer->id, 'seeder' => 1]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer, ['peer:list']);

        $response = $this->getJson('/api/v1/peers?torrent_id='.$torrent->id)
            ->assertStatus(200);

        $seeders = $response->json('data.seeder_list.data') ?? $response->json('data.seeder_list');
        $this->assertNotEmpty($seeders);
        $this->assertNull($seeders[0]['user']);
    }

    public function test_strong_privacy_peer_visible_to_self(): void
    {
        $category = $this->seedBrowseCategory();
        $owner = User::factory()->create();
        $torrent = $this->makeTorrent($owner, $category);
        $privatePeer = User::factory()->create(['privacy' => UserPrivacy::STRONG->value]);
        Peer::factory()->create(['torrent' => $torrent->id, 'userid' => $privatePeer->id, 'seeder' => 1]);

        Sanctum::actingAs($privatePeer, ['peer:list']);

        $response = $this->getJson('/api/v1/peers?torrent_id='.$torrent->id)
            ->assertStatus(200);

        $seeders = $response->json('data.seeder_list.data') ?? $response->json('data.seeder_list');
        $this->assertNotEmpty($seeders);
        $this->assertSame($privatePeer->id, $seeders[0]['user']['id']);
    }

    public function test_strong_privacy_snatch_user_is_hidden(): void
    {
        $category = $this->seedBrowseCategory();
        $owner = User::factory()->create();
        $torrent = $this->makeTorrent($owner, $category);
        $privateUser = User::factory()->create(['privacy' => UserPrivacy::STRONG->value]);
        Snatch::factory()->finished()->create(['torrentid' => $torrent->id, 'userid' => $privateUser->id]);

        $viewer = User::factory()->create();
        Sanctum::actingAs($viewer, ['snatch:list']);

        $response = $this->getJson('/api/v1/snatches?torrent_id='.$torrent->id)
            ->assertStatus(200);

        $rows = $response->json('data.data') ?? $response->json('data');
        $this->assertNotEmpty($rows);
        $this->assertNull($rows[0]['user']);
    }
}
