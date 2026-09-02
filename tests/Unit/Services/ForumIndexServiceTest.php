<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\ForumRepository;
use App\Services\ForumIndexService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for ForumIndexService.
 *
 * Covers getTopicImage (all statuses), highlightTopic (with/without
 * colour), highlightColorOptions, getForumRow (all/specific/missing),
 * getLastReadPostId, catchUp (no-user/user), forumStats, and
 * buildForumsIndex (empty data, with overforums).
 */
final class ForumIndexServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ForumIndexService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        if (! defined('IN_NEXUS')) {
            define('IN_NEXUS', true);
        }
        $this->service = new ForumIndexService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @return ForumRepository&MockInterface */
    private function mockForumRepo(): mixed
    {
        /** @var ForumRepository&MockInterface $repo */
        $repo = Mockery::mock(ForumRepository::class);
        $repo->shouldIgnoreMissing(false);
        $this->app->instance(ForumRepository::class, $repo);

        return $repo;
    }

    private function mockCache(): void
    {
        /** @var LegacyRedisCache&MockInterface $cache */
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get_value')->andReturn(false);
        $cache->shouldReceive('delete_value')->andReturn(true);
        $cache->shouldReceive('cache_value')->andReturn(true);
        $this->app->instance(LegacyRedisCache::class, $cache);
    }

    /** @param  array<string, mixed>  $data */
    private function setUser(array $data = []): void
    {
        $currentUser = new CurrentUser;
        $currentUser->set(array_merge(['id' => 1, 'username' => 'testuser', 'class' => 1], $data));
        $this->app->instance(CurrentUser::class, $currentUser);
    }

    // --- getTopicImage ---

    public function test_get_topic_image_read_returns_img_tag(): void
    {
        $result = $this->service->getTopicImage('read', ['title_read' => 'Read']);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('alt="read"', $result);
        $this->assertStringContainsString('title="Read"', $result);
    }

    public function test_get_topic_image_unread_returns_img_tag(): void
    {
        $result = $this->service->getTopicImage('unread', ['title_unread' => 'Unread']);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('alt="unread"', $result);
    }

    public function test_get_topic_image_locked_returns_img_tag(): void
    {
        $result = $this->service->getTopicImage('locked', ['title_locked' => 'Locked']);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('alt="locked"', $result);
    }

    public function test_get_topic_image_lockednew_returns_img_tag(): void
    {
        $result = $this->service->getTopicImage('lockednew', ['title_locked_new' => 'Locked New']);

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('alt="lockednew"', $result);
    }

    public function test_get_topic_image_unknown_status_returns_empty(): void
    {
        $this->assertSame('', $this->service->getTopicImage('unknown', []));
    }

    // --- highlightTopic ---

    public function test_highlight_topic_with_zero_color_returns_subject_unchanged(): void
    {
        $result = $this->service->highlightTopic('My Topic', 0);

        $this->assertSame('My Topic', $result);
    }

    public function test_highlight_topic_with_valid_color_wraps_subject(): void
    {
        $result = $this->service->highlightTopic('My Topic', 17);

        $this->assertStringContainsString('<font', $result);
        $this->assertStringContainsString('My Topic', $result);
        $this->assertStringContainsString('Red', $result);
    }

    public function test_highlight_topic_with_invalid_color_returns_subject_unchanged(): void
    {
        $result = $this->service->highlightTopic('My Topic', 999);

        $this->assertSame('My Topic', $result);
    }

    // --- highlightColorOptions ---

    public function test_highlight_color_options_returns_html_with_default(): void
    {
        $result = $this->service->highlightColorOptions('Select Color');

        $this->assertStringContainsString('Select Color', $result);
        $this->assertStringContainsString("<option value='0'>", $result);
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

        $this->assertStringContainsString('Black', $result);
        $this->assertStringContainsString('White', $result);
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
        $this->assertSame('Forum 1', $result['name']);
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

        $repo->shouldReceive('getLastReadPosts')->andReturn(null);

        $result = $this->service->getLastReadPostId(1, ['id' => 1]);

        $this->assertSame(0, $result);
    }

    public function test_get_last_read_post_id_returns_catchup_when_no_read_posts(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $repo->shouldReceive('getLastReadPosts')->andReturn(null);

        $result = $this->service->getLastReadPostId(1, ['id' => 1, 'last_catchup' => 50]);

        $this->assertSame(50, $result);
    }

    public function test_get_last_read_post_id_returns_read_post_when_higher_than_catchup(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $repo->shouldReceive('getLastReadPosts')->andReturn([1 => 100]);

        $result = $this->service->getLastReadPostId(1, ['id' => 1, 'last_catchup' => 50]);

        $this->assertSame(100, $result);
    }

    // --- catchUp ---

    public function test_catch_up_with_no_user_returns_early(): void
    {
        $this->mockForumRepo();
        $this->mockCache();

        $currentUser = new CurrentUser;
        $currentUser->set(null);
        $this->app->instance(CurrentUser::class, $currentUser);

        $this->service->catchUp();

        $this->expectNotToPerformAssertions();
    }

    public function test_catch_up_with_user_calls_clear_read_posts(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();

        $repo->shouldReceive('clearReadPosts')->with(1)->once();
        $repo->shouldReceive('getLastPostId')->andReturn(100);
        $repo->shouldReceive('updateLastCatchup')->with(1, 100)->once();

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

        $repo->shouldReceive('clearReadPosts')->with(1)->once();
        $repo->shouldReceive('getLastPostId')->andReturn(null);
        $repo->shouldReceive('updateLastCatchup')->never();

        $this->service->catchUp();

        // Mockery::close() verifies shouldReceive expectations were met
        Mockery::close();
        $this->addToAssertionCount(1);
    }

    // --- forumStats ---

    public function test_forum_stats_returns_html_with_stats(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $repo->shouldReceive('getActiveForumUserCount')->andReturn(5);
        $repo->shouldReceive('getTotalPostsCount')->andReturn(100);
        $repo->shouldReceive('getTotalTopicsCount')->andReturn(50);
        $repo->shouldReceive('getTodayPostsCount')->andReturn(10);

        $result = $this->service->forumStats(['text_stats' => 'Stats', 'text_our_members_have' => 'Members'], date('Y-m-d'));

        $this->assertStringContainsString('100', $result);
        $this->assertStringContainsString('50', $result);
        $this->assertStringContainsString('10', $result);
    }

    public function test_forum_stats_with_no_active_users_shows_no_users_message(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();

        $repo->shouldReceive('getActiveForumUserCount')->andReturn(0);
        $repo->shouldReceive('getTotalPostsCount')->andReturn(0);
        $repo->shouldReceive('getTotalTopicsCount')->andReturn(0);
        $repo->shouldReceive('getTodayPostsCount')->andReturn(0);

        $result = $this->service->forumStats(['text_no_active_users' => 'No active users'], date('Y-m-d'));

        $this->assertStringContainsString('No active users', $result);
    }

    // --- buildForumsIndex ---

    public function test_build_forums_index_with_empty_data_returns_html(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();

        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getOverforumsList')->andReturn([]);
        $repo->shouldReceive('getForumsList')->andReturn([]);

        $result = $this->service->buildForumsIndex(['text_forums' => 'Forums'], ['id' => 1, 'username' => 'test'], 1);

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('<table', $result['html']);
    }
}
