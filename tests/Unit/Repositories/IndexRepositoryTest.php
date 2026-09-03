<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\PeerSeeder;
use App\Enums\TorrentVisible;
use App\Models\News;
use App\Models\Peer;
use App\Models\PollAnswer;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\IndexRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for IndexRepository.
 *
 * Covers getLatestTorrents(), getTopUploaders(), getUserStats(),
 * touchLastHome(), getTorrentStats(), getClassStats(), getCurrentPoll(),
 * hasVoted(), getUserVote(), recordPollVote(), getPollResults(),
 * getLatestNews(), and getLatestForumPosts().
 *
 * Cache::flush() is called in setUp() since every method uses
 * Cache::remember().
 */
final class IndexRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private IndexRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('pollanswers')->delete();
        DB::table('polls')->delete();
        DB::table('news')->delete();
        DB::table('peers')->delete();
        DB::table('torrents')->delete();
        DB::table('posts')->delete();
        DB::table('topics')->delete();
        DB::table('forums')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new IndexRepository;
    }

    public function test_get_latest_torrents_returns_visible_torrents(): void
    {
        /** @var Torrent $visible */
        $visible = Torrent::factory()->create(['visible' => TorrentVisible::YES->value]);
        Torrent::factory()->create(['visible' => TorrentVisible::NO->value]);

        $result = $this->repository->getLatestTorrents(10);

        $this->assertCount(1, $result);
        $first = $result->first();
        $this->assertNotNull($first);
        $this->assertSame($visible->id, $first->id);
    }

    public function test_get_latest_torrents_respects_limit(): void
    {
        Torrent::factory()->count(3)->create(['visible' => TorrentVisible::YES->value]);

        $result = $this->repository->getLatestTorrents(2);

        $this->assertCount(2, $result);
    }

    public function test_get_latest_torrents_orders_by_id_desc(): void
    {
        /** @var Torrent $first */
        $first = Torrent::factory()->create(['visible' => TorrentVisible::YES->value]);
        /** @var Torrent $second */
        $second = Torrent::factory()->create(['visible' => TorrentVisible::YES->value]);

        $result = $this->repository->getLatestTorrents(10);

        $first = $result->first();
        $this->assertNotNull($first);
        $this->assertSame($second->id, $first->id);
    }

    public function test_get_top_uploaders_returns_users_with_torrents(): void
    {
        /** @var User $uploader */
        $uploader = User::factory()->create();
        Torrent::factory()->count(3)->create(['owner' => $uploader->id]);
        /** @var User $other */
        $other = User::factory()->create();
        Torrent::factory()->create(['owner' => $other->id]);

        $result = $this->repository->getTopUploaders(10);

        $this->assertGreaterThanOrEqual(1, $result->count());
        $topUser = $result->first();
        $this->assertNotNull($topUser);
        $this->assertSame($uploader->id, $topUser->id);
    }

    public function test_get_top_uploaders_respects_limit(): void
    {
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            /** @var User $user */
            Torrent::factory()->create(['owner' => $user->id]);
        }

        $result = $this->repository->getTopUploaders(2);

        $this->assertCount(2, $result);
    }

    public function test_get_user_stats_returns_array_with_keys(): void
    {
        User::factory()->create();

        $stats = $this->repository->getUserStats();

        $this->assertArrayHasKey('registered', $stats);
        $this->assertArrayHasKey('unverified', $stats);
        $this->assertArrayHasKey('totalonlinetoday', $stats);
        $this->assertArrayHasKey('vip', $stats);
        $this->assertArrayHasKey('donated', $stats);
        $this->assertArrayHasKey('warned', $stats);
        $this->assertArrayHasKey('disabled', $stats);
        $this->assertIsInt($stats['registered']);
    }

    public function test_touch_last_home_updates_column(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['last_home' => null]);

        $result = $this->repository->touchLastHome($user->id);

        $this->assertTrue($result);
        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->last_home);
    }

    public function test_touch_last_home_returns_false_when_not_found(): void
    {
        $this->assertFalse($this->repository->touchLastHome(999999));
    }

    public function test_get_torrent_stats_returns_array_with_keys(): void
    {
        $stats = $this->repository->getTorrentStats();

        $this->assertArrayHasKey('torrents', $stats);
        $this->assertArrayHasKey('dead', $stats);
        $this->assertArrayHasKey('seeders', $stats);
        $this->assertArrayHasKey('leechers', $stats);
        $this->assertArrayHasKey('peers', $stats);
        $this->assertArrayHasKey('ratio', $stats);
        $this->assertArrayHasKey('totaltorrentssize', $stats);
        $this->assertIsInt($stats['torrents']);
    }

    public function test_get_torrent_stats_counts_seeders_and_leechers(): void
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        Peer::factory()->create(['torrent' => $torrent->id, 'seeder' => PeerSeeder::YES->value]);
        Peer::factory()->create(['torrent' => $torrent->id, 'seeder' => PeerSeeder::NO->value]);

        $stats = $this->repository->getTorrentStats();

        $this->assertSame(1, $stats['seeders']);
        $this->assertSame(1, $stats['leechers']);
        $this->assertSame(2, $stats['peers']);
    }

    public function test_get_class_stats_returns_array_with_class_keys(): void
    {
        $stats = $this->repository->getClassStats();

        $this->assertArrayHasKey(UC_PEASANT, $stats);
        $this->assertArrayHasKey(UC_USER, $stats);
        $this->assertArrayHasKey(UC_NEXUS_MASTER, $stats);
        $this->assertIsInt($stats[UC_USER]);
    }

    public function test_get_current_poll_returns_null_when_no_polls(): void
    {
        $this->assertNull($this->repository->getCurrentPoll());
    }

    public function test_get_current_poll_returns_latest_poll(): void
    {
        $poll1 = $this->createPoll(['question' => 'Old Poll']);
        $poll2 = $this->createPoll(['question' => 'New Poll']);

        $result = $this->repository->getCurrentPoll();

        $this->assertNotNull($result);
        $this->assertSame($poll2, $result['id']);
        $this->assertSame('New Poll', $result['question']);
    }

    public function test_has_voted_returns_false_when_not_voted(): void
    {
        $pollId = $this->createPoll();

        $this->assertFalse($this->repository->hasVoted($pollId, 1));
    }

    public function test_has_voted_returns_true_when_voted(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $pollId = $this->createPoll();
        PollAnswer::create(['pollid' => $pollId, 'userid' => $user->id, 'selection' => 0]);

        $this->assertTrue($this->repository->hasVoted($pollId, $user->id));
    }

    public function test_get_user_vote_returns_null_when_not_voted(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $pollId = $this->createPoll();

        $this->assertNull($this->repository->getUserVote($pollId, $user->id));
    }

    public function test_get_user_vote_returns_selection_when_voted(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $pollId = $this->createPoll();
        PollAnswer::create(['pollid' => $pollId, 'userid' => $user->id, 'selection' => 2]);

        $this->assertSame(2, $this->repository->getUserVote($pollId, $user->id));
    }

    public function test_record_poll_vote_creates_answer(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $pollId = $this->createPoll();

        $result = $this->repository->recordPollVote($pollId, $user->id, 3);

        $this->assertTrue($result);
        $this->assertSame(1, DB::table('pollanswers')->where('pollid', $pollId)->where('userid', $user->id)->count());
        $this->assertSame(3, (int) DB::table('pollanswers')->where('pollid', $pollId)->where('userid', $user->id)->value('selection'));
    }

    public function test_record_poll_vote_clears_vote_cache(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $pollId = $this->createPoll();
        // Prime the cache
        $this->repository->hasVoted($pollId, $user->id);
        $this->repository->getUserVote($pollId, $user->id);

        $this->repository->recordPollVote($pollId, $user->id, 0);

        $this->assertTrue($this->repository->hasVoted($pollId, $user->id));
        $this->assertSame(0, $this->repository->getUserVote($pollId, $user->id));
    }

    public function test_get_poll_results_returns_sorted_items(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        /** @var User $user3 */
        $user3 = User::factory()->create();
        $pollId = $this->createPoll(['option0' => 'Option A', 'option1' => 'Option B']);
        PollAnswer::create(['pollid' => $pollId, 'userid' => $user1->id, 'selection' => 0]);
        PollAnswer::create(['pollid' => $pollId, 'userid' => $user2->id, 'selection' => 0]);
        PollAnswer::create(['pollid' => $pollId, 'userid' => $user3->id, 'selection' => 1]);

        $results = $this->repository->getPollResults($pollId);

        $this->assertNotEmpty($results);
        // Sorted by count desc — option 0 has 2 votes, option 1 has 1 vote.
        $this->assertSame(2, $results[0]['count']);
        $this->assertSame('Option A', $results[0]['option']);
        $this->assertSame(1, $results[1]['count']);
        $this->assertSame('Option B', $results[1]['option']);
    }

    public function test_get_poll_results_returns_empty_when_no_votes(): void
    {
        $pollId = $this->createPoll(['option0' => 'Option A', 'option1' => '']);

        $results = $this->repository->getPollResults($pollId);

        $this->assertCount(1, $results);
        $this->assertSame(0, $results[0]['count']);
    }

    public function test_get_latest_news_returns_ordered_news(): void
    {
        $this->createNews('Older', '2025-01-01 00:00:00');
        $this->createNews('Newer', '2025-06-01 00:00:00');

        $result = $this->repository->getLatestNews(10);

        $this->assertCount(2, $result);
        $this->assertSame('Newer', $result[0]['title']);
    }

    public function test_get_latest_news_respects_limit(): void
    {
        $this->createNews('A', '2025-01-01 00:00:00');
        $this->createNews('B', '2025-02-01 00:00:00');
        $this->createNews('C', '2025-03-01 00:00:00');

        $result = $this->repository->getLatestNews(2);

        $this->assertCount(2, $result);
    }

    public function test_get_latest_forum_posts_returns_posts_within_class(): void
    {
        /** @var User $topicUser */
        $topicUser = User::factory()->create();
        /** @var User $postUser */
        $postUser = User::factory()->create();
        $forumId = (int) DB::table('forums')->insertGetId([
            'name' => 'Test Forum',
            'description' => 'test',
            'minclassread' => 0,
            'minclasswrite' => 0,
            'postcount' => 0,
            'topiccount' => 0,
            'minclasscreate' => 0,
            'forid' => 0,
            'sort' => 0,
        ]);
        $topicId = (int) DB::table('topics')->insertGetId([
            'userid' => $topicUser->id,
            'subject' => 'Test Topic',
            'forumid' => $forumId,
            'firstpost' => 0,
            'lastpost' => 0,
        ]);
        DB::table('posts')->insertGetId([
            'topicid' => $topicId,
            'userid' => $postUser->id,
            'added' => now()->toDateTimeString(),
            'body' => 'hello',
            'ori_body' => 'hello',
        ]);

        $result = $this->repository->getLatestForumPosts(10, 0);

        $this->assertNotEmpty($result);
        $this->assertSame('Test Topic', $result[0]['subject']);
    }

    public function test_get_latest_forum_posts_filters_by_min_class_read(): void
    {
        /** @var User $topicUser */
        $topicUser = User::factory()->create();
        /** @var User $postUser */
        $postUser = User::factory()->create();
        $forumId = (int) DB::table('forums')->insertGetId([
            'name' => 'High Class Forum',
            'description' => 'restricted',
            'minclassread' => 10,
            'minclasswrite' => 10,
            'postcount' => 0,
            'topiccount' => 0,
            'minclasscreate' => 10,
            'forid' => 0,
            'sort' => 0,
        ]);
        $topicId = (int) DB::table('topics')->insertGetId([
            'userid' => $topicUser->id,
            'subject' => 'Restricted Topic',
            'forumid' => $forumId,
            'firstpost' => 0,
            'lastpost' => 0,
        ]);
        DB::table('posts')->insert([
            'topicid' => $topicId,
            'userid' => $postUser->id,
            'added' => now()->toDateTimeString(),
            'body' => 'secret',
            'ori_body' => 'secret',
        ]);

        $result = $this->repository->getLatestForumPosts(10, 0);

        $this->assertSame([], $result);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPoll(array $overrides = []): int
    {
        return (int) DB::table('polls')->insertGetId(array_merge([
            'added' => now()->toDateTimeString(),
            'question' => 'Test Poll',
            'option0' => 'Yes',
            'option1' => 'No',
        ], $overrides));
    }

    private function createNews(string $title, string $added): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        News::query()->create([
            'userid' => $user->id,
            'added' => $added,
            'title' => $title,
            'body' => 'body',
            'notify' => 0,
        ]);
    }
}
