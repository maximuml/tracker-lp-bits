<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\UserClass;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Repositories\OverforumRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicReadStateRepository;
use App\Repositories\TopicRepository;
use App\Services\ForumIndexService;
use App\Support\Cache\NexusCache;
use App\Support\CurrentUser;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\TestCase;

/**
 * Unit tests for ForumIndexService.
 *
 * Covers highlightColorOptions, getForumRow (all/specific/missing),
 * getLastReadPostId, catchUp (no-user/user), forumStats, and
 * buildForumsIndex (empty data, with overforums).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumIndexServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesCurrentUser;

    private ForumIndexService $service;

    /** @var ForumRepository&MockInterface */
    private ForumRepository $forumRepo;

    /** @var NexusCache&MockInterface */
    private NexusCache $cache;

    /** @var TopicRepository&MockInterface */
    private TopicRepository $topicRepo;

    /** @var TopicReadStateRepository&MockInterface */
    private TopicReadStateRepository $readStateRepo;

    /** @var PostRepository&MockInterface */
    private PostRepository $postRepo;

    private CurrentUser $currentUser;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        $this->currentUser = new CurrentUser;

        /** @var ForumRepository&MockInterface $repo */
        $repo = Mockery::mock(ForumRepository::class);
        $repo->shouldIgnoreMissing(false);
        $this->forumRepo = $repo;

        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get')->andReturn(false);
        $cache->shouldReceive('forget')->andReturn(true);
        $cache->shouldReceive('put')->andReturn(true);
        $this->cache = $cache;

        /** @var TopicRepository&MockInterface $topicRepo */
        $topicRepo = Mockery::mock(TopicRepository::class);
        $topicRepo->shouldIgnoreMissing();
        $this->topicRepo = $topicRepo;

        /** @var TopicReadStateRepository&MockInterface $readStateRepo */
        $readStateRepo = Mockery::mock(TopicReadStateRepository::class);
        $readStateRepo->shouldIgnoreMissing();
        $this->readStateRepo = $readStateRepo;

        /** @var PostRepository&MockInterface $postRepo */
        $postRepo = Mockery::mock(PostRepository::class);
        $postRepo->shouldIgnoreMissing();
        $this->postRepo = $postRepo;

        $this->service = new ForumIndexService(
            $this->currentUser,
            $this->forumRepo,
            new OverforumRepository,
            $this->cache,
            $this->topicRepo,
            $this->readStateRepo,
            $this->postRepo,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @return ForumRepository&MockInterface */
    private function mockForumRepo(): mixed
    {
        return $this->forumRepo;
    }

    private function mockCache(): void {}

    /** @param  array<string, mixed>  $data */
    private function setUser(array $data = []): void
    {
        $this->currentUser->set(array_merge(['id' => 1, 'username' => 'testuser', 'class' => 1], $data));
    }

    // --- highlightColorOptions ---

    public function test_highlight_color_options_returns_default_first(): void
    {
        $result = $this->service->highlightColorOptions('Select Color');

        $this->assertSame(['value' => 0, 'label' => 'Select Color'], $result[0]);
    }

    public function test_highlight_color_options_contains_all_40_colors(): void
    {
        $result = $this->service->highlightColorOptions('Select');

        // 40 colour options + 1 default
        $this->assertCount(41, $result);
    }

    public function test_highlight_color_options_includes_black_and_white(): void
    {
        $result = $this->service->highlightColorOptions('Select');

        $labels = array_column($result, 'label');
        $this->assertContains('Black', $labels);
        $this->assertContains('White', $labels);
    }

    // --- getForumRow ---

    public function test_get_forum_row_returns_all_when_forumid_zero(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $forums = [1 => ['id' => 1, 'name' => 'Forum 1'], 2 => ['id' => 2, 'name' => 'Forum 2']];
        $repo->shouldReceive('getForumsList')->andReturn($forums);

        $result = $this->service->getForumRow(0);

        $this->assertSame($forums, $result);
    }

    public function test_get_forum_row_returns_specific_forum(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $forums = [1 => ['id' => 1, 'name' => 'Forum 1'], 2 => ['id' => 2, 'name' => 'Forum 2']];
        $repo->shouldReceive('getForumsList')->andReturn($forums);

        $result = $this->service->getForumRow(1);

        $this->assertNotNull($result);
        $this->assertSame('Forum 1', (string) ($result['name']));
    }

    public function test_get_forum_row_returns_null_for_missing_forum(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $repo->shouldReceive('getForumsList')->andReturn([1 => ['id' => 1, 'name' => 'Forum 1']]);

        $result = $this->service->getForumRow(999);

        $this->assertNull($result);
    }

    // --- getLastReadPostId ---

    public function test_get_last_read_post_id_returns_zero_with_no_data(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $this->readStateRepo->shouldReceive('getLastReadPosts')->andReturn(null);

        $result = $this->service->getLastReadPostId(1, $this->curUser(['id' => 1]));

        $this->assertSame(0, $result);
    }

    public function test_get_last_read_post_id_returns_catchup_when_no_read_posts(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $this->readStateRepo->shouldReceive('getLastReadPosts')->andReturn(null);

        $result = $this->service->getLastReadPostId(1, $this->curUser(['id' => 1, 'last_catchup' => 50]));

        $this->assertSame(50, $result);
    }

    public function test_get_last_read_post_id_returns_read_post_when_higher_than_catchup(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $this->readStateRepo->shouldReceive('getLastReadPosts')->andReturn([1 => 100]);

        $result = $this->service->getLastReadPostId(1, $this->curUser(['id' => 1, 'last_catchup' => 50]));

        $this->assertSame(100, $result);
    }

    // --- catchUp ---

    public function test_catch_up_with_no_user_returns_early(): void
    {
        $this->mockForumRepo();
        $this->mockCache();

        $this->currentUser->set(null);

        $this->service->catchUp();

        $this->expectNotToPerformAssertions();
    }

    public function test_catch_up_with_user_calls_clear_read_posts(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();

        $this->readStateRepo->shouldReceive('clearReadPosts')->with(1)->once();
        $this->postRepo->shouldReceive('getLastPostId')->andReturn(100);
        $this->postRepo->shouldReceive('updateLastCatchup')->with(1, 100)->once();

        $this->service->catchUp();

        // Mockery::close() verifies shouldReceive expectations were met
        Mockery::close();
        $this->addToAssertionCount(1);
    }

    public function test_catch_up_with_no_last_post_skips_update(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();

        $this->readStateRepo->shouldReceive('clearReadPosts')->with(1)->once();
        $this->postRepo->shouldReceive('getLastPostId')->andReturn(null);
        $this->postRepo->shouldReceive('updateLastCatchup')->never();

        $this->service->catchUp();

        // Mockery::close() verifies shouldReceive expectations were met
        Mockery::close();
        $this->addToAssertionCount(1);
    }

    // --- stats (via buildForumsIndex, ADR 0021) ---

    public function test_forum_stats_returns_view_model_with_counts(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        Settings::saveBatch('main', ['showforumstats' => 'yes']);
        Settings::resetCache();

        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getOverforumsList')->andReturn([]);
        $repo->shouldReceive('getForumsList')->andReturn([]);
        $repo->shouldReceive('getActiveForumUserCount')->andReturn(5);
        $this->postRepo->shouldReceive('getTotalPostsCount')->andReturn(100);
        $this->topicRepo->shouldReceive('getTotalTopicsCount')->andReturn(50);
        $this->postRepo->shouldReceive('getTodayPostsCount')->andReturn(10);

        $result = $this->service->buildForumsIndex($this->curUser(['id' => 1, 'username' => 'test']), 1);

        $this->assertNotNull($result->stats);
        $this->assertSame(100, $result->stats->posts);
        $this->assertSame(50, $result->stats->topics);
        $this->assertSame(10, $result->stats->todayPosts);
        $this->assertSame(5, $result->stats->activeUsers);
    }

    public function test_forum_stats_with_no_active_users_reports_zero(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        Settings::saveBatch('main', ['showforumstats' => 'yes']);
        Settings::resetCache();

        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getOverforumsList')->andReturn([]);
        $repo->shouldReceive('getForumsList')->andReturn([]);
        $repo->shouldReceive('getActiveForumUserCount')->andReturn(0);
        $this->postRepo->shouldReceive('getTotalPostsCount')->andReturn(0);
        $this->topicRepo->shouldReceive('getTotalTopicsCount')->andReturn(0);
        $this->postRepo->shouldReceive('getTodayPostsCount')->andReturn(0);

        $result = $this->service->buildForumsIndex($this->curUser(['id' => 1, 'username' => 'test']), 1);

        $this->assertNotNull($result->stats);
        $this->assertSame(0, $result->stats->activeUsers);

        $html = view('components.forum.stats', ['stats' => $result->stats])->render();
        $this->assertStringContainsString('no active user', $html);
    }

    // --- buildForumsIndex ---

    public function test_build_forums_index_with_empty_data_returns_view_model(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();

        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getOverforumsList')->andReturn([]);
        $repo->shouldReceive('getForumsList')->andReturn([]);

        $result = $this->service->buildForumsIndex($this->curUser(['id' => 1, 'username' => 'test']), 1);

        $this->assertSame([], $result->sections);
        $this->assertFalse($result->canManageForums);
    }

    public function test_build_forums_index_renders_orphan_forums_in_their_own_group(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();
        $user = new User;
        $user->id = 1;
        $user->class = UserClass::USER->value;
        auth()->login($user);

        $overforumId = (int) DB::table('overforums')->min('id');

        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getForumsList')->andReturn([
            5 => ['id' => 5, 'name' => 'Grouped Forum', 'description' => 'belongs to an overforum', 'forid' => $overforumId, 'minclassread' => 0, 'topiccount' => 2, 'postcount' => 4],
            7 => ['id' => 7, 'name' => 'Orphan Forum', 'description' => 'no matching overforum', 'forid' => 999, 'minclassread' => 0, 'topiccount' => 3, 'postcount' => 9],
        ]);

        $result = $this->service->buildForumsIndex($this->curUser(['id' => 1, 'username' => 'test']), 1);

        $sectionByForumId = [];
        foreach ($result->sections as $i => $section) {
            foreach ($section->forums as $row) {
                $sectionByForumId[$row->id] = $i;
            }
        }

        $this->assertArrayHasKey(5, $sectionByForumId);
        $this->assertArrayHasKey(7, $sectionByForumId);
    }

    public function test_build_forums_index_hides_orphan_forums_below_minclassread(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser(['class' => 1]);
        $user = new User;
        $user->id = 1;
        $user->class = UserClass::USER->value;
        auth()->login($user);

        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getForumsList')->andReturn([
            7 => ['id' => 7, 'name' => 'Staff Orphan Forum', 'description' => '', 'forid' => 999, 'minclassread' => 10, 'topiccount' => 0, 'postcount' => 0],
        ]);

        $result = $this->service->buildForumsIndex($this->curUser(['id' => 1, 'username' => 'test']), 1);

        $names = [];
        foreach ($result->sections as $section) {
            foreach ($section->forums as $row) {
                $names[] = $row->name;
            }
        }

        $this->assertNotContains('Staff Orphan Forum', $names);
    }

    // --- cache key/TTL contract ---

    public function test_build_forums_index_cache_keys_and_ttls(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();
        $user = new User;
        $user->id = 1;
        $user->class = UserClass::USER->value;
        auth()->login($user);
        Settings::saveBatch('main', ['showforumstats' => 'yes']);
        Settings::resetCache();

        $puts = [];
        $gets = [];
        $getManys = [];
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get')->andReturnUsing(function (string $key) use (&$gets) {
            $gets[] = $key;

            return false;
        });
        $cache->shouldReceive('getMany')->andReturnUsing(function (array $keys) use (&$getManys) {
            $getManys[] = $keys;
            if ($keys === ['post_9_content', 'post_11_content']) {
                return [
                    'post_9_content' => ['id' => 9, 'userid' => 1, 'added' => '2024-01-01 00:00:00', 'body' => 'x'],
                    'post_11_content' => ['id' => 11, 'userid' => 1, 'added' => '2024-01-02 00:00:00', 'body' => 'y'],
                ];
            }

            return [
                'forum_5_last_replied_topic_content' => ['id' => 2, 'subject' => 'Replied', 'lastpost' => 9, 'hlcolor' => 0],
                'forum_6_last_replied_topic_content' => ['id' => 3, 'subject' => 'Dup', 'lastpost' => 9, 'hlcolor' => 0],
                'forum_7_last_replied_topic_content' => ['id' => 4, 'subject' => 'Other', 'lastpost' => 11, 'hlcolor' => 0],
            ];
        });
        $cache->shouldReceive('put')->andReturnUsing(function (string $key, $value, int $ttl) use (&$puts) {
            $puts[] = [$key, $ttl];

            return true;
        });
        $cache->shouldReceive('forget')->andReturn(true);

        $service = new ForumIndexService(
            $this->currentUser,
            $this->forumRepo,
            new OverforumRepository,
            $cache,
            $this->topicRepo,
            $this->readStateRepo,
            $this->postRepo,
        );

        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getForumsList')->andReturn([
            5 => ['id' => 5, 'name' => 'Replied Forum', 'description' => '', 'forid' => 999, 'minclassread' => 0, 'topiccount' => 1, 'postcount' => 2],
            6 => ['id' => 6, 'name' => 'Dup Forum', 'description' => '', 'forid' => 999, 'minclassread' => 0, 'topiccount' => 1, 'postcount' => 2],
            7 => ['id' => 7, 'name' => 'Other Forum', 'description' => '', 'forid' => 999, 'minclassread' => 0, 'topiccount' => 1, 'postcount' => 2],
            8 => ['id' => 8, 'name' => 'Quiet Forum', 'description' => '', 'forid' => 999, 'minclassread' => 0, 'topiccount' => 0, 'postcount' => 0],
        ]);
        $this->topicRepo->shouldReceive('getLastTopicByForum')->with(5)->never();
        $this->topicRepo->shouldReceive('getLastTopicByForum')->with(6)->never();
        $this->topicRepo->shouldReceive('getLastTopicByForum')->with(7)->never();
        $this->topicRepo->shouldReceive('getLastTopicByForum')->with(8)->andReturn(null);
        $this->postRepo->shouldReceive('getForumTodayPostCount')->andReturn(0);
        $repo->shouldReceive('getActiveForumUserCount')->andReturn(3);
        $this->postRepo->shouldReceive('getTotalPostsCount')->andReturn(100);
        $this->topicRepo->shouldReceive('getTotalTopicsCount')->andReturn(50);
        $this->postRepo->shouldReceive('getTodayPostsCount')->andReturn(10);
        $this->readStateRepo->shouldReceive('getLastReadPosts')->with(1)->andReturn([2 => 3]);

        $result = $service->buildForumsIndex($this->curUser(['id' => 1, 'username' => 'test']), 1);

        $today = date('Y-m-d');
        foreach ([
            ['overforums_list', 86400],
            ['forums_list', 86400],
            ['forum_8_last_replied_topic_content', 900],
            ['forum_5_post_'.$today.'_count', 1800],
            ['forum_6_post_'.$today.'_count', 1800],
            ['forum_7_post_'.$today.'_count', 1800],
            ['forum_8_post_'.$today.'_count', 1800],
            ['active_forum_user_count', 300],
            ['total_posts_count', 96400],
            ['total_topics_count', 96500],
            ['today_'.$today.'_posts_count', 700],
            ['user_1_last_read_post_list', 900],
        ] as $expected) {
            $this->assertContains($expected, $puts);
        }

        $this->assertContains('user_1_last_read_post_list', $gets);
        $this->assertContains(
            [
                'forum_5_last_replied_topic_content', 'forum_5_post_'.$today.'_count',
                'forum_6_last_replied_topic_content', 'forum_6_post_'.$today.'_count',
                'forum_7_last_replied_topic_content', 'forum_7_post_'.$today.'_count',
                'forum_8_last_replied_topic_content', 'forum_8_post_'.$today.'_count',
            ],
            $getManys,
        );
        $this->assertContains(['post_9_content', 'post_11_content'], $getManys);

        $rows = [];
        foreach ($result->sections as $section) {
            foreach ($section->forums as $forumRow) {
                $rows[$forumRow->id] = $forumRow;
            }
        }
        $this->assertTrue($rows[5]->hasUnread);
        $this->assertSame(2, $rows[5]->lastPost->topicId);
        $this->assertSame('2024-01-01 00:00:00', $rows[5]->lastPost->date);
    }

    public function test_last_read_post_id_uses_user_zero_key_when_id_missing(): void
    {
        $puts = [];
        $gets = [];
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get')->andReturnUsing(function (string $key) use (&$gets) {
            $gets[] = $key;

            return false;
        });
        $cache->shouldReceive('put')->andReturnUsing(function (string $key, $value, int $ttl) use (&$puts) {
            $puts[] = [$key, $value, $ttl];

            return true;
        });

        $service = new ForumIndexService(
            $this->currentUser,
            $this->forumRepo,
            new OverforumRepository,
            $cache,
            $this->topicRepo,
            $this->readStateRepo,
            $this->postRepo,
        );

        $this->readStateRepo->shouldReceive('getLastReadPosts')->with(0)->once()->andReturn(null);
        $this->readStateRepo->shouldReceive('getLastReadPosts')->with(0)->once()->andReturn([7 => 0]);
        $this->readStateRepo->shouldReceive('getLastReadPosts')->with(5)->andReturn(null);

        $this->assertSame(0, $service->getLastReadPostId(2, $this->curUser(['username' => 'noid'])));
        $this->assertContains('user_0_last_read_post_list', $gets);
        $this->assertContains(['user_0_last_read_post_list', 'no record', 900], $puts);

        $service2 = new ForumIndexService(
            $this->currentUser,
            $this->forumRepo,
            new OverforumRepository,
            $cache,
            $this->topicRepo,
            $this->readStateRepo,
            $this->postRepo,
        );
        $this->assertSame(0, $service2->getLastReadPostId(7, $this->curUser(['username' => 'noid'])));
        $this->assertContains(['user_0_last_read_post_list', [7 => 0], 900], $puts);

        $service3 = new ForumIndexService(
            $this->currentUser,
            $this->forumRepo,
            new OverforumRepository,
            $cache,
            $this->topicRepo,
            $this->readStateRepo,
            $this->postRepo,
        );
        $this->assertSame(0, $service3->getLastReadPostId(2, $this->curUser(['id' => 5])));
        $this->assertContains(['user_5_last_read_post_list', 'no record', 900], $puts);
    }

    public function test_build_forums_index_reads_stats_and_lists_from_cache(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();
        $user = new User;
        $user->id = 1;
        $user->class = UserClass::USER->value;
        auth()->login($user);
        Settings::saveBatch('main', ['showforumstats' => 'yes']);
        Settings::resetCache();

        $today = date('Y-m-d');
        $values = [
            'overforums_list' => [['id' => 1, 'name' => 'Over', 'minclassview' => 0]],
            'forums_list' => [
                5 => ['id' => 5, 'name' => 'F', 'forid' => 1, 'minclassread' => 0, 'topiccount' => 0, 'postcount' => 0, 'description' => ''],
            ],
            'active_forum_user_count' => 2,
            'total_posts_count' => 50,
            'total_topics_count' => 8,
            'today_'.$today.'_posts_count' => 4,
        ];
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get')->andReturnUsing(static fn (string $key) => $values[$key] ?? false);
        $cache->shouldReceive('put')->andReturn(true);
        $cache->shouldReceive('forget')->andReturn(true);

        $service = new ForumIndexService(
            $this->currentUser,
            $this->forumRepo,
            new OverforumRepository,
            $cache,
            $this->topicRepo,
            $this->readStateRepo,
            $this->postRepo,
        );

        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getForumsList')->never();
        $repo->shouldReceive('getActiveForumUserCount')->never();
        $this->topicRepo->shouldReceive('getLastTopicByForum')->andReturn(null);
        $this->postRepo->shouldReceive('getForumTodayPostCount')->andReturn(0);
        $this->postRepo->shouldReceive('getTotalPostsCount')->never();
        $this->topicRepo->shouldReceive('getTotalTopicsCount')->never();
        $this->postRepo->shouldReceive('getTodayPostsCount')->never();

        $result = $service->buildForumsIndex($this->curUser(['id' => 1, 'username' => 'test']), 1);

        $this->assertSame(2, $result->stats->activeUsers);
        $this->assertSame(50, $result->stats->posts);
        $this->assertSame(8, $result->stats->topics);
        $this->assertSame(4, $result->stats->todayPosts);
    }

    public function test_catch_up_forgets_user_last_read_post_list_cache_key(): void
    {
        $this->setUser(['id' => 7]);

        $forgets = [];
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('forget')->andReturnUsing(function (string $key) use (&$forgets) {
            $forgets[] = $key;

            return true;
        });

        $service = new ForumIndexService(
            $this->currentUser,
            $this->forumRepo,
            new OverforumRepository,
            $cache,
            $this->topicRepo,
            $this->readStateRepo,
            $this->postRepo,
        );

        $this->readStateRepo->shouldReceive('clearReadPosts')->with(7)->once();
        $this->postRepo->shouldReceive('getLastPostId')->andReturn(0);

        $service->catchUp();

        $this->assertSame(['user_7_last_read_post_list'], $forgets);
    }
}
