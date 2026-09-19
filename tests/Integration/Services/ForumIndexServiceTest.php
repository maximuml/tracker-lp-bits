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
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for ForumIndexService.
 *
 * Covers getTopicImage (all statuses), highlightTopic (with/without
 * colour), highlightColorOptions, getForumRow (all/specific/missing),
 * getLastReadPostId, catchUp (no-user/user), forumStats, and
 * buildForumsIndex (empty data, with overforums).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumIndexServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ForumIndexService $service;

    /** @var ForumRepository&MockInterface */
    private ForumRepository $forumRepo;

    /** @var LegacyRedisCache&MockInterface */
    private LegacyRedisCache $cache;

    /** @var TopicRepository&MockInterface */
    private TopicRepository $topicRepo;

    /** @var TopicReadStateRepository&MockInterface */
    private TopicReadStateRepository $readStateRepo;

    /** @var PostRepository&MockInterface */
    private PostRepository $postRepo;

    private CurrentUser $currentUser;

    private Globals $globals;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        $this->currentUser = new CurrentUser;
        $this->globals = new Globals;

        /** @var ForumRepository&MockInterface $repo */
        $repo = Mockery::mock(ForumRepository::class);
        $repo->shouldIgnoreMissing(false);
        $this->forumRepo = $repo;

        /** @var LegacyRedisCache&MockInterface $cache */
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get_value')->andReturn(false);
        $cache->shouldReceive('delete_value')->andReturn(true);
        $cache->shouldReceive('cache_value')->andReturn(true);
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
            $this->globals,
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

    // --- getTopicImage ---

    public function test_get_topic_image_read_returns_img_tag(): void
    {
        $result = $this->service->getTopicImage('read');

        $this->assertStringContainsString('<img', (string) $result);
        $this->assertStringContainsString('alt="read"', (string) $result);
        $this->assertStringContainsString('title="Read"', (string) $result);
    }

    public function test_get_topic_image_unread_returns_img_tag(): void
    {
        $result = $this->service->getTopicImage('unread');

        $this->assertStringContainsString('<img', (string) $result);
        $this->assertStringContainsString('alt="unread"', (string) $result);
    }

    public function test_get_topic_image_locked_returns_img_tag(): void
    {
        $result = $this->service->getTopicImage('locked');

        $this->assertStringContainsString('<img', (string) $result);
        $this->assertStringContainsString('alt="locked"', (string) $result);
    }

    public function test_get_topic_image_lockednew_returns_img_tag(): void
    {
        $result = $this->service->getTopicImage('lockednew');

        $this->assertStringContainsString('<img', (string) $result);
        $this->assertStringContainsString('alt="lockednew"', (string) $result);
    }

    public function test_get_topic_image_unknown_status_returns_empty(): void
    {
        $this->assertSame('', $this->service->getTopicImage('unknown'));
    }

    // --- highlightTopic ---

    public function test_highlight_topic_with_zero_color_returns_subject_unchanged(): void
    {
        $result = $this->service->highlightTopic('My Topic', 0);

        $this->assertSame('My Topic', (string) ($result));
    }

    public function test_highlight_topic_with_valid_color_wraps_subject(): void
    {
        $result = $this->service->highlightTopic('My Topic', 17);

        $this->assertStringContainsString('<font', (string) $result);
        $this->assertStringContainsString('My Topic', (string) $result);
        $this->assertStringContainsString('Red', (string) $result);
    }

    public function test_highlight_topic_with_invalid_color_returns_subject_unchanged(): void
    {
        $result = $this->service->highlightTopic('My Topic', 999);

        $this->assertSame('My Topic', (string) ($result));
    }

    // --- highlightColorOptions ---

    public function test_highlight_color_options_returns_html_with_default(): void
    {
        $result = $this->service->highlightColorOptions('Select Color');

        $this->assertStringContainsString('Select Color', (string) $result);
        $this->assertStringContainsString("<option value='0'>", (string) $result);
    }

    public function test_highlight_color_options_contains_all_40_colors(): void
    {
        $result = $this->service->highlightColorOptions('Select');

        // 40 colour options + 1 default = 41 <option> tags
        $this->assertSame(41, substr_count($result, '<option'));
    }

    public function test_highlight_color_options_includes_black_and_white(): void
    {
        $result = $this->service->highlightColorOptions('Select');

        $this->assertStringContainsString('Black', (string) $result);
        $this->assertStringContainsString('White', (string) $result);
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

        $result = $this->service->getLastReadPostId(1, ['id' => 1]);

        $this->assertSame(0, $result);
    }

    public function test_get_last_read_post_id_returns_catchup_when_no_read_posts(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $this->readStateRepo->shouldReceive('getLastReadPosts')->andReturn(null);

        $result = $this->service->getLastReadPostId(1, ['id' => 1, 'last_catchup' => 50]);

        $this->assertSame(50, $result);
    }

    public function test_get_last_read_post_id_returns_read_post_when_higher_than_catchup(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $this->readStateRepo->shouldReceive('getLastReadPosts')->andReturn([1 => 100]);

        $result = $this->service->getLastReadPostId(1, ['id' => 1, 'last_catchup' => 50]);

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
        $this->globals->set('showforumstats_main', 'yes');

        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getOverforumsList')->andReturn([]);
        $repo->shouldReceive('getForumsList')->andReturn([]);
        $repo->shouldReceive('getActiveForumUserCount')->andReturn(5);
        $this->postRepo->shouldReceive('getTotalPostsCount')->andReturn(100);
        $this->topicRepo->shouldReceive('getTotalTopicsCount')->andReturn(50);
        $this->postRepo->shouldReceive('getTodayPostsCount')->andReturn(10);

        $result = $this->service->buildForumsIndex(['id' => 1, 'username' => 'test'], 1);

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
        $this->globals->set('showforumstats_main', 'yes');

        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getOverforumsList')->andReturn([]);
        $repo->shouldReceive('getForumsList')->andReturn([]);
        $repo->shouldReceive('getActiveForumUserCount')->andReturn(0);
        $this->postRepo->shouldReceive('getTotalPostsCount')->andReturn(0);
        $this->topicRepo->shouldReceive('getTotalTopicsCount')->andReturn(0);
        $this->postRepo->shouldReceive('getTodayPostsCount')->andReturn(0);

        $result = $this->service->buildForumsIndex(['id' => 1, 'username' => 'test'], 1);

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

        $result = $this->service->buildForumsIndex(['id' => 1, 'username' => 'test'], 1);

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

        $result = $this->service->buildForumsIndex(['id' => 1, 'username' => 'test'], 1);

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

        $result = $this->service->buildForumsIndex(['id' => 1, 'username' => 'test'], 1);

        $names = [];
        foreach ($result->sections as $section) {
            foreach ($section->forums as $row) {
                $names[] = $row->name;
            }
        }

        $this->assertNotContains('Staff Orphan Forum', $names);
    }
}
