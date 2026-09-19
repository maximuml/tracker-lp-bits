<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Repositories\OverforumRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicReadStateRepository;
use App\Repositories\TopicRepository;
use App\Services\ForumIndexService;
use App\Services\ForumListingService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Integration tests for ForumListingService.
 *
 * ADR 0021 (stage 3.1b): the service returns typed view models —
 * assertions check the view-model data, not generated markup.
 * Covers buildViewUnread (empty / with rows / truncation),
 * buildSearch (no keywords / no hits / hits + pagination) and
 * buildViewForum (invalid id / missing forum / empty / with rows).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumListingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ForumListingService $service;

    /** @var TopicRepository&MockInterface */
    private TopicRepository $topicRepo;

    /** @var PostRepository&MockInterface */
    private PostRepository $postRepo;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        app(Globals::class)->set('SITENAME', 'TestSite');
        app(Globals::class)->set('lang_functions', [
            'text_prev' => 'Prev', 'text_next' => 'Next',
            'submit_submit' => 'Submit', 'submit_preview' => 'Preview',
            'submit_edit' => 'Edit', 'text_tags' => 'Tags', 'text_smilies' => 'Smilies',
            'js_prompt_enter_url' => 'URL', 'js_prompt_enter_title' => 'Title',
            'js_prompt_error' => 'Error', 'js_prompt_enter_image_url' => 'Image URL',
            'js_prompt_enter_item' => 'Item', 'select_color' => 'Color',
            'select_font' => 'Font', 'select_size' => 'Size',
            'text_more_smilies' => 'More',
        ]);

        $indexService = new ForumIndexService(
            $this->app->make(CurrentUser::class),
            $this->app->make(Globals::class),
            $this->app->make(ForumRepository::class),
            new OverforumRepository,
            $this->app->make(LegacyRedisCache::class),
            $this->app->make(TopicRepository::class),
            $this->app->make(TopicReadStateRepository::class),
            $this->app->make(PostRepository::class),
        );
        $this->service = new ForumListingService(
            $indexService,
            $this->app->make(Globals::class),
            $this->app->make(LegacyRedisCache::class),
            $this->app->make(TopicRepository::class),
            $this->app->make(PostRepository::class),
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
        /** @var ForumRepository&MockInterface $repo */
        $repo = Mockery::mock(ForumRepository::class);
        $repo->shouldIgnoreMissing(false);
        $repo->shouldReceive('getModeratorArray')->andReturn([]);
        $this->app->instance(ForumRepository::class, $repo);

        /** @var TopicRepository&MockInterface $topicRepo */
        $topicRepo = Mockery::mock(TopicRepository::class);
        $topicRepo->shouldIgnoreMissing();
        $this->app->instance(TopicRepository::class, $topicRepo);
        $this->topicRepo = $topicRepo;

        /** @var PostRepository&MockInterface $postRepo */
        $postRepo = Mockery::mock(PostRepository::class);
        $postRepo->shouldIgnoreMissing();
        $this->app->instance(PostRepository::class, $postRepo);
        $this->postRepo = $postRepo;

        $this->rebuildService($repo);

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
        $this->rebuildService(null, $cache);
    }

    private function rebuildService(?ForumRepository $repo = null, ?LegacyRedisCache $cache = null): void
    {
        $forumRepo = $repo ?? $this->app->make(ForumRepository::class);
        $cacheInstance = $cache ?? $this->app->make(LegacyRedisCache::class);

        $indexService = new ForumIndexService(
            $this->app->make(CurrentUser::class),
            $this->app->make(Globals::class),
            $forumRepo,
            new OverforumRepository,
            $cacheInstance,
            $this->app->make(TopicRepository::class),
            $this->app->make(TopicReadStateRepository::class),
            $this->app->make(PostRepository::class),
        );
        $this->service = new ForumListingService(
            $indexService,
            $this->app->make(Globals::class),
            $cacheInstance,
            $this->app->make(TopicRepository::class),
            $this->app->make(PostRepository::class),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function setUser(array $data = []): void
    {
        $defaults = ['id' => 1, 'username' => 'testuser', 'class' => 10];
        $merged = array_merge($defaults, $data);

        $currentUser = $this->app->make(CurrentUser::class);
        $currentUser->set($merged);
        $this->app->instance(CurrentUser::class, $currentUser);

        // The legacy runtime flag is false in the test environment, so
        // UserDisplay::currentClass() uses auth()->user()->class — log in a
        // User model with the right class.
        $user = new User;
        $user->id = $merged['id'];
        $user->class = $merged['class'];
        $user->username = $merged['username'];
        auth()->login($user);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function setRequest(array $query = []): void
    {
        $request = Request::create('/forums.php', 'GET', $query);
        $this->app->instance('request', $request);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fakeTopic(array $attributes): Topic
    {
        $topic = new Topic;
        $topic->setRawAttributes(array_merge([
            'id' => 1,
            'forumid' => 1,
            'userid' => 1,
            'subject' => 'Test topic',
            'views' => 5,
            'locked' => 0,
            'sticky' => 0,
            'hlcolor' => 0,
            'firstpost' => 0,
            'lastpost' => 0,
        ], $attributes));

        return $topic;
    }

    private function callWithSuppressedErrors(callable $fn): mixed
    {
        set_error_handler(function (int $severity): bool {
            return true;
        }, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);

        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    // --- buildViewUnread ---

    public function test_build_view_unread_with_no_topics_returns_empty_list(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $this->topicRepo->shouldReceive('getUnreadTopics')->andReturn(new Collection);

        $vm = $this->service->buildViewUnread(['id' => 1, 'username' => 'test', 'class' => 10]);

        $this->assertSame('TestSite', $vm->siteName);
        $this->assertSame([], $vm->topics);
        $this->assertNull($vm->moreBeforePostId);
    }

    public function test_build_view_unread_lists_unread_topic_with_forum(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $this->topicRepo->shouldReceive('getUnreadTopics')->andReturn(new Collection([
            $this->fakeTopic(['id' => 7, 'forumid' => 1, 'subject' => 'Unread <b>topic</b>', 'lastpost' => 100, 'hlcolor' => 5]),
        ]));
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'forid' => 1, 'minclassread' => 0],
        ]);

        $vm = $this->service->buildViewUnread(['id' => 1, 'username' => 'test', 'class' => 10]);

        $this->assertCount(1, $vm->topics);
        $row = $vm->topics[0];
        $this->assertSame(7, $row->topicId);
        $this->assertSame(1, $row->forumId);
        $this->assertSame('Test Forum', $row->forumName);
        $this->assertSame(5, $row->hlcolor);
        // Subject arrives pre-escaped (SafeHtml marks escaping already done).
        $this->assertSame('Unread &lt;b&gt;topic&lt;/b&gt;', (string) $row->subject);
        $this->assertNull($vm->moreBeforePostId);
    }

    public function test_build_view_unread_skips_topics_below_minclassread(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $this->topicRepo->shouldReceive('getUnreadTopics')->andReturn(new Collection([
            $this->fakeTopic(['id' => 7, 'forumid' => 1, 'lastpost' => 100]),
        ]));
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Staff Forum', 'forid' => 1, 'minclassread' => 90],
        ]);

        $vm = $this->service->buildViewUnread(['id' => 1, 'username' => 'test', 'class' => 10]);

        $this->assertSame([], $vm->topics);
    }

    public function test_build_view_unread_sets_more_before_post_id_when_truncated(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        // 26 visible topics → the 26th exceeds the 25-result cap and its
        // lastpost becomes the beforepostid continuation cursor.
        $topics = [];
        for ($i = 1; $i <= 26; $i++) {
            $topics[] = $this->fakeTopic(['id' => $i, 'forumid' => 1, 'lastpost' => 1000 + $i]);
        }
        $this->topicRepo->shouldReceive('getUnreadTopics')->andReturn(new Collection($topics));
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'forid' => 1, 'minclassread' => 0],
        ]);

        $vm = $this->service->buildViewUnread(['id' => 1, 'username' => 'test', 'class' => 10]);

        $this->assertCount(25, $vm->topics);
        $this->assertSame(1026, $vm->moreBeforePostId);
    }

    // --- buildSearch ---

    public function test_build_search_with_no_keywords_is_not_searched(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $vm = $this->service->buildSearch(20);

        $this->assertFalse($vm->searched);
        $this->assertSame('', $vm->keywords);
        $this->assertSame(0, $vm->hits);
        $this->assertSame([], $vm->results);
        $this->assertStringContainsString('search_button.gif', $vm->imageUrl);
    }

    public function test_build_search_with_keywords_no_hits(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['keywords' => 'notfound']);

        $this->postRepo->shouldReceive('searchForumPosts')->once()->andReturn(['hits' => 0, 'rows' => new Collection]);

        $vm = $this->service->buildSearch(20);

        $this->assertTrue($vm->searched);
        $this->assertSame('notfound', $vm->keywords);
        $this->assertSame(0, $vm->hits);
        $this->assertSame([], $vm->results);
    }

    public function test_build_search_with_hits_returns_rows_and_pagination(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['keywords' => 'test']);

        $this->postRepo->shouldReceive('searchForumPosts')->andReturn([
            'hits' => 25,
            'rows' => new Collection([
                (object) [
                    'id' => 50, 'topicid' => 7, 'subject' => 'A test topic',
                    'hlcolor' => 0, 'forumid' => 3, 'forumname' => 'Forum Three',
                    'added' => '2026-09-01 12:00:00', 'userid' => 1,
                ],
            ]),
        ]);

        $vm = $this->service->buildSearch(20);

        $this->assertTrue($vm->searched);
        $this->assertSame(25, $vm->hits);
        $this->assertSame(2, $vm->pages); // 25 hits / 20 per page
        $this->assertCount(1, $vm->results);
        $row = $vm->results[0];
        $this->assertSame(50, $row->postId);
        $this->assertSame(7, $row->topicId);
        $this->assertSame(3, $row->forumId);
        $this->assertSame('Forum Three', $row->forumName);
        $this->assertSame('2026-09-01 12:00:00', $row->added);
        $this->assertStringContainsString('keywords=test', $vm->pagerHref());
    }

    // --- buildViewForum ---

    public function test_build_view_forum_with_invalid_id_aborts(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['forumid' => 0]);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service->buildViewForum(['id' => 1, 'username' => 'test', 'class' => 10], Request::create('/forums.php', 'GET', ['forumid' => 0]), 20, 10));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when forumid is invalid (0)');
    }

    public function test_build_view_forum_with_nonexistent_forum_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['forumid' => 999]);

        $repo->shouldReceive('getForumsList')->andReturn([]);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service->buildViewForum(['id' => 1, 'username' => 'test', 'class' => 10, 'ip' => '127.0.0.1'], Request::create('/forums.php', 'GET', ['forumid' => 999]), 20, 10));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when forum does not exist');
    }

    public function test_build_view_forum_with_valid_forum_no_topics(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['forumid' => 1]);

        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'forid' => 1, 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0, 'topiccount' => 0, 'postcount' => 0, 'description' => 'Test'],
        ]);
        $this->topicRepo->shouldReceive('getTopicsByForum')->andReturn(['count' => 0, 'rows' => new Collection]);

        $vm = $this->service->buildViewForum(
            ['id' => 1, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes'],
            Request::create('/forums.php', 'GET', ['forumid' => 1]),
            20,
            10,
        );

        $this->assertSame(1, $vm->forumId);
        $this->assertSame('Test Forum', $vm->forumName);
        $this->assertSame('TestSite', $vm->siteName);
        $this->assertTrue($vm->mayPost);
        $this->assertSame([], $vm->topics);
        $this->assertSame(1, $vm->pages);
    }

    public function test_build_view_forum_maps_topic_rows(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['forumid' => 1]);

        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'forid' => 1, 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $this->topicRepo->shouldReceive('getTopicsByForum')->andReturn([
            'count' => 1,
            'rows' => new Collection([
                $this->fakeTopic(['id' => 7, 'subject' => 'Pinned <i>topic</i>', 'sticky' => 1, 'hlcolor' => 9, 'views' => 1234]),
            ]),
        ]);
        $this->postRepo->shouldReceive('countTopicPosts')->andReturn(3);

        $vm = $this->service->buildViewForum(
            ['id' => 1, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes'],
            Request::create('/forums.php', 'GET', ['forumid' => 1]),
            20,
            10,
        );

        $this->assertCount(1, $vm->topics);
        $row = $vm->topics[0];
        $this->assertSame(7, $row->id);
        $this->assertSame(1, $row->forumId);
        $this->assertTrue($row->sticky);
        $this->assertSame(9, $row->hlcolor);
        $this->assertSame('read', $row->state); // no posts → lastpostread(0) >= lppostid(0)
        $this->assertSame(2, $row->replies); // posts-1
        $this->assertSame(1234, $row->views);
        $this->assertSame([], $row->visiblePages); // 3 posts / 10 per page → single page
        $this->assertNull($row->jumpToPostId);
        $this->assertNull($row->tooltipId); // tooltips off by default
        $this->assertSame('Pinned &lt;i&gt;topic&lt;/i&gt;', (string) $row->subject);
        $this->assertSame([], $vm->tooltips);
    }

    public function test_build_view_forum_multipage_topic_lists_visible_pages(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['forumid' => 1]);

        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'forid' => 1, 'minclassread' => 0],
        ]);
        $this->topicRepo->shouldReceive('getTopicsByForum')->andReturn([
            'count' => 1,
            'rows' => new Collection([$this->fakeTopic(['id' => 7])]),
        ]);
        $this->postRepo->shouldReceive('countTopicPosts')->andReturn(95); // 10 pages at 10/page

        $vm = $this->service->buildViewForum(
            ['id' => 1, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes'],
            Request::create('/forums.php', 'GET', ['forumid' => 1]),
            20,
            10,
        );

        // dotspace=4 → pages 1-4, gap, 7-10 shown (10 pages total)
        $this->assertSame([1, 2, 3, 4, '…', 7, 8, 9, 10], $vm->topics[0]->visiblePages);
    }

    public function test_build_view_forum_passes_sort_and_search_to_repository(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['forumid' => 1, 'sort' => 'firstpostasc', 'search' => 'abc']);

        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'forid' => 1, 'minclassread' => 0],
        ]);
        $this->topicRepo->shouldReceive('getTopicsByForum')
            ->twice()
            ->with(1, 'abc', 'firstpost', 'asc', Mockery::type('int'), Mockery::type('int'))
            ->andReturn(['count' => 0, 'rows' => new Collection]);

        $vm = $this->service->buildViewForum(
            ['id' => 1, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes'],
            Request::create('/forums.php', 'GET', ['forumid' => 1]),
            20,
            10,
        );

        $this->assertSame('abc', $vm->search);
        $this->assertSame('firstpostasc', $vm->sort);
        $this->assertSame('&search=abc', $vm->addParam());
        $this->assertStringContainsString('search=abc', $vm->pagerHref());
    }
}
