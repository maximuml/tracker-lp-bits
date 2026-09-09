<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RecordHttpMetrics;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\Builders\ForumScenario;
use Tests\Builders\MessageScenario;
use Tests\Builders\TorrentListingScenario;
use Tests\Concerns\AssertsQueryCount;
use Tests\TestCase;

/**
 * W3-05: Realistic fixture tests with N+1 detection.
 *
 * Uses scenario builders to create realistic datasets (dozens of
 * related records) and runs query-budget tests against them to
 * detect N+1 regressions that would not appear with small datasets.
 *
 * The scenarios create:
 * - Torrent listing: 3 uploaders, 10 torrents each, 5 peers each
 * - Forum: 3 forums, 5 topics each, 10 posts each
 * - Messages: 5 users, 10 messages per conversation
 */
#[TestCategory(TestCategory::PERFORMANCE, TestCategory::SERVICE_INTEGRATION)]
final class RealisticFixtureTest extends TestCase
{
    use AssertsQueryCount;
    use DatabaseTransactions;

    public function test_torrent_listing_scenario_creates_expected_dataset(): void
    {
        $scenario = TorrentListingScenario::create()
            ->withUploaders(3)
            ->withTorrentsPerUploader(10)
            ->withPeersPerTorrent(5)
            ->withSnatchesPerTorrent(3)
            ->build();

        $this->assertSame(3, $scenario->uploaders->count());
        $this->assertSame(30, $scenario->torrentCount());
        $this->assertSame(150, $scenario->peerCount());
        $this->assertSame(90, $scenario->snatchCount());
    }

    public function test_forum_scenario_creates_expected_dataset(): void
    {
        $scenario = ForumScenario::create()
            ->withForums(3)
            ->withTopicsPerForum(5)
            ->withPostsPerTopic(10)
            ->withUsers(5)
            ->build();

        $this->assertSame(3, count($scenario->forums));
        $this->assertSame(15, $scenario->topicCount());
        $this->assertSame(150, $scenario->postCount());
        $this->assertSame(5, $scenario->users->count());
    }

    public function test_message_scenario_creates_expected_dataset(): void
    {
        $scenario = MessageScenario::create()
            ->withUsers(5)
            ->withMessagesPerConversation(10)
            ->build();

        $this->assertSame(5, $scenario->users->count());
        // 5 users → C(5,2) = 10 conversations × 10 messages = 100
        $this->assertSame(100, $scenario->messageCount());
    }

    public function test_torrent_listing_query_count_with_realistic_dataset(): void
    {
        // Create a realistic dataset
        TorrentListingScenario::create()
            ->withUploaders(3)
            ->withTorrentsPerUploader(10)
            ->withPeersPerTorrent(5)
            ->build();

        $user = User::factory()->create();

        // Query the torrents listing — should not have N+1 queries
        // with 30 torrents. Budget is generous to allow for framework
        // overhead but low enough to catch N+1 regressions.
        $this->assertQueryCountBelow(100, function () use ($user): void {
            $this->actingAs($user)
                ->withoutMiddleware(RecordHttpMetrics::class)
                ->getJson('/api/v1/torrents?per_page=30');
        });
    }

    public function test_forum_listing_query_count_with_realistic_dataset(): void
    {
        ForumScenario::create()
            ->withForums(3)
            ->withTopicsPerForum(5)
            ->withPostsPerTopic(10)
            ->build();

        $user = User::factory()->create();

        // Query the forums listing — should not have N+1 queries
        // with 15 topics across 3 forums.
        $this->assertQueryCountBelow(50, function () use ($user): void {
            $this->actingAs($user)
                ->withoutMiddleware(RecordHttpMetrics::class)
                ->getJson('/api/v1/forums');
        });
    }

    public function test_torrent_detail_query_count_with_realistic_dataset(): void
    {
        $scenario = TorrentListingScenario::create()
            ->withUploaders(2)
            ->withTorrentsPerUploader(5)
            ->withPeersPerTorrent(10)
            ->withSnatchesPerTorrent(5)
            ->build();

        $user = User::factory()->create();
        $torrent = $scenario->torrents[0];

        // Query a single torrent detail — should not have N+1 queries
        // even with many peers and snatches.
        $this->assertQueryCountBelow(50, function () use ($user, $torrent): void {
            $this->actingAs($user)
                ->withoutMiddleware(RecordHttpMetrics::class)
                ->getJson("/api/v1/detail/{$torrent->id}");
        });
    }
}
