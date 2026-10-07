<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\UserClass;
use App\Enums\UserTimeType;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Repositories\OverforumRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicReadStateRepository;
use App\Repositories\TopicRepository;
use App\Services\ForumIndexService;
use App\Services\ForumListingService;
use App\Support\Cache\NexusCache;
use App\Support\CurrentUser;
use App\Support\Settings;
use App\Support\Time;
use App\Support\UserDisplay;
use App\ViewModels\Forum\TopicListViewModel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        Settings::saveBatch('basic', ['SITENAME' => 'TestSite']);
        Settings::resetCache();

        $indexService = new ForumIndexService(
            $this->app->make(CurrentUser::class),
            $this->app->make(ForumRepository::class),
            new OverforumRepository,
            $this->app->make(NexusCache::class),
            $this->app->make(TopicRepository::class),
            $this->app->make(TopicReadStateRepository::class),
            $this->app->make(PostRepository::class),
        );
        $this->service = new ForumListingService(
            $indexService,
            $this->app->make(NexusCache::class),
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

    /** @param  array<string, mixed>|null  $getValues */
    private function mockCache(?array $getValues = null): void
    {
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get')->andReturn(false);
        $cache->shouldReceive('getMany')->andReturn($getValues ?? []);
        $cache->shouldReceive('forget')->andReturn(true);
        $cache->shouldReceive('put')->andReturn(true);
        $this->app->instance(NexusCache::class, $cache);
        $this->rebuildService(null, $cache);
    }

    private function rebuildService(?ForumRepository $repo = null, ?NexusCache $cache = null): void
    {
        $forumRepo = $repo ?? $this->app->make(ForumRepository::class);
        $cacheInstance = $cache ?? $this->app->make(NexusCache::class);

        $indexService = new ForumIndexService(
            $this->app->make(CurrentUser::class),
            $forumRepo,
            new OverforumRepository,
            $cacheInstance,
            $this->app->make(TopicRepository::class),
            $this->app->make(TopicReadStateRepository::class),
            $this->app->make(PostRepository::class),
        );
        $this->service = new ForumListingService(
            $indexService,
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

    private function assertAbortContains(callable $fn, string ...$needles): void
    {
        try {
            $this->callWithSuppressedErrors($fn);
            $this->fail('Expected abort');
        } catch (HttpResponseException $e) {
            $html = (string) $e->getResponse()->getContent();
            foreach ($needles as $needle) {
                $this->assertStringContainsString(e($needle), $html);
            }
        } catch (\Throwable $e) {
            $this->fail('Expected HttpResponseException, got '.$e::class);
        }
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

        $this->postRepo->shouldReceive('countForumSearchPosts')->once()->andReturn(0);
        $this->postRepo->shouldNotReceive('searchForumPosts');

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

        $this->postRepo->shouldReceive('countForumSearchPosts')->once()->andReturn(25);
        $this->postRepo->shouldReceive('searchForumPosts')->once()->andReturn(new Collection([
            (object) [
                'id' => 50, 'topicid' => 7, 'subject' => 'A test topic',
                'hlcolor' => 0, 'forumid' => 3, 'forumname' => 'Forum Three',
                'added' => '2026-09-01 12:00:00', 'userid' => 1,
            ],
        ]));

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

        $this->assertAbortContains(
            fn () => $this->service->buildViewForum(['id' => 1, 'username' => 'test', 'class' => 10, 'ip' => '127.0.0.1'], Request::create('/forums.php', 'GET', ['forumid' => 999]), 20, 10),
            (string) __('forums.std_forum_not_found'),
        );
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
        $this->postRepo->shouldReceive('countTopicPostsBatch')->with([7])->andReturn([7 => 3]);

        Settings::saveBatch('tweak', ['enabletooltip' => 'no']);
        Settings::resetCache();

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
        $this->postRepo->shouldReceive('countTopicPostsBatch')->with([7])->andReturn([7 => 95]); // 10 pages at 10/page

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

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $forumRow
     */
    /**
     * @param  array<string, mixed>  $topicAttrs
     * @param  array<string, mixed>|null  $postRows
     */
    private function viewForumVm(array $curUser = [], array $forumRow = [], array $topicAttrs = [], ?array $postRows = null): TopicListViewModel
    {
        $repo = $this->mockForumRepo();
        $this->mockCache($postRows);
        $curUser = array_merge([
            'id' => 1, 'username' => 'test', 'class' => 10,
            'forumpost' => 'yes', 'ip' => '127.0.0.1',
        ], $curUser);
        $this->setUser($curUser);
        $this->setRequest(['forumid' => 1]);

        $repo->shouldReceive('getForumsList')->andReturn([
            1 => array_merge([
                'id' => 1, 'name' => 'Test Forum', 'forid' => 1,
                'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0,
            ], $forumRow),
        ]);
        $this->topicRepo->shouldReceive('getTopicsByForum')->andReturn([
            'count' => 1,
            'rows' => new Collection([$this->fakeTopic(array_merge(['id' => 7, 'subject' => 'Topic'], $topicAttrs))]),
        ]);
        $this->postRepo->shouldReceive('countTopicPostsBatch')->with([7])->andReturn([7 => 3]);

        $vm = $this->service->buildViewForum(
            $curUser,
            Request::create('/forums.php', 'GET', ['forumid' => 1]),
            20,
            10,
        );

        $this->assertInstanceOf(TopicListViewModel::class, $vm);

        return $vm;
    }

    public function test_build_view_forum_tooltips_enabled_when_tweak_on_and_pref_not_no(): void
    {
        Settings::saveBatch('tweak', ['enabletooltip' => 'yes']);
        Settings::resetCache();

        $vm = $this->viewForumVm();

        $this->assertSame('lastpost_0', $vm->topics[0]->tooltipId);
        $this->assertCount(1, $vm->tooltips);
    }

    public function test_build_view_forum_tooltip_content_uses_at_time_by_default(): void
    {
        Settings::saveBatch('tweak', ['enabletooltip' => 'yes']);
        Settings::resetCache();

        $vm = $this->viewForumVm(
            curUser: ['timetype' => UserTimeType::TIMEADDED->value],
            topicAttrs: ['lastpost' => 100],
            postRows: ['post_100_content' => ['id' => 100, 'userid' => 5, 'added' => '2024-06-01 00:00:00', 'body' => 'lp body']],
        );

        $this->assertSame('lastpost_0', $vm->tooltips[0]['id']);
        $this->assertSame(
            __('forums.text_last_posted_by').UserDisplay::username(5).__('forums.text_at_time').'2024-06-01 00:00:00',
            (string) $vm->tooltips[0]['content'],
        );
    }

    public function test_build_view_forum_tooltip_content_formats_timealive(): void
    {
        Settings::saveBatch('tweak', ['enabletooltip' => 'yes']);
        Settings::resetCache();

        $vm = $this->viewForumVm(
            curUser: ['timetype' => UserTimeType::TIMEALIVE->value],
            topicAttrs: ['lastpost' => 100],
            postRows: ['post_100_content' => ['id' => 100, 'userid' => 5, 'added' => '2024-06-01 00:00:00', 'body' => 'lp body']],
        );

        $this->assertSame(
            __('forums.text_last_posted_by').UserDisplay::username(5).__('forums.text_blank').Time::format('2024-06-01 00:00:00', true, false, true),
            (string) $vm->tooltips[0]['content'],
        );
    }

    public function test_build_view_forum_tooltips_disabled_when_showlastpost_no(): void
    {
        Settings::saveBatch('tweak', ['enabletooltip' => 'yes']);
        Settings::resetCache();

        $vm = $this->viewForumVm(['showlastpost' => 'no']);

        $this->assertNull($vm->topics[0]->tooltipId);
        $this->assertSame([], $vm->tooltips);
    }

    public function test_build_view_forum_maypost_false_when_class_below_minwrite(): void
    {
        $vm = $this->viewForumVm(['class' => 2], ['minclasswrite' => 5]);

        $this->assertFalse($vm->mayPost);
    }

    public function test_build_view_forum_maypost_false_when_class_below_mincreate(): void
    {
        $vm = $this->viewForumVm(['class' => 2], ['minclasscreate' => 5]);

        $this->assertFalse($vm->mayPost);
    }

    public function test_build_view_forum_maypost_false_when_forumpost_disabled(): void
    {
        $vm = $this->viewForumVm(['forumpost' => 'no']);

        $this->assertFalse($vm->mayPost);
    }

    public function test_build_view_forum_maypost_true_at_minclass_boundary(): void
    {
        $vm = $this->viewForumVm(['class' => 5], ['minclasswrite' => 5, 'minclasscreate' => 5]);

        $this->assertTrue($vm->mayPost);
    }

    public function test_view_forum_cache_key_and_ttl_contract(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();
        $this->setRequest(['forumid' => 1]);
        Settings::saveBatch('tweak', ['enabletooltip' => 'yes']);
        Settings::resetCache();

        $puts = [];
        $getManys = [];
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get')->andReturn(false);
        $cache->shouldReceive('getMany')->andReturnUsing(function (array $keys) use (&$getManys) {
            $getManys[] = $keys;
            if ($keys === ['post_11_content', 'post_12_content', 'post_13_content']) {
                return [
                    'post_11_content' => ['id' => 11, 'userid' => 1, 'body' => 'Alpha body', 'added' => '2024-01-01 00:00:00', 'poster' => 'u'],
                    'post_12_content' => ['id' => 12, 'userid' => 1, 'body' => 'Alpha body', 'added' => '2024-01-02 00:00:00', 'poster' => 'u'],
                    'post_13_content' => ['id' => 13, 'userid' => 1, 'body' => 'Beta body', 'added' => '2024-01-03 00:00:00', 'poster' => 'u'],
                ];
            }

            return [];
        });
        $cache->shouldReceive('put')->andReturnUsing(function (string $key, $value, int $ttl) use (&$puts) {
            $puts[] = [$key, $value, $ttl];

            return true;
        });
        $cache->shouldReceive('forget')->andReturn(true);
        $this->app->instance(NexusCache::class, $cache);
        $this->rebuildService(null, $cache);

        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'forid' => 1, 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $this->topicRepo->shouldReceive('getTopicsByForum')->andReturn([
            'count' => 3,
            'rows' => new Collection([
                $this->fakeTopic(['id' => 7, 'lastpost' => 11, 'firstpost' => 11]),
                $this->fakeTopic(['id' => 8, 'lastpost' => 12, 'firstpost' => 0]),
                $this->fakeTopic(['id' => 9, 'lastpost' => 13, 'firstpost' => 0]),
            ]),
        ]);
        $this->postRepo->shouldReceive('countTopicPostsBatch')->with([7, 8, 9])->andReturn([7 => 3, 8 => 5]);

        $vm = $this->callWithSuppressedErrors(fn () => $this->service->buildViewForum(
            ['id' => 1, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes', 'showlastpost' => 'yes'],
            Request::create('/forums.php', 'GET', ['forumid' => 1]),
            20,
            10,
        ));

        $this->assertInstanceOf(TopicListViewModel::class, $vm);
        $this->assertSame(2, $vm->topics[0]->replies);
        $this->assertSame(4, $vm->topics[1]->replies);
        $this->assertContains(['topic_7_post_count', 'topic_8_post_count', 'topic_9_post_count'], $getManys);
        $this->assertContains(['post_11_content', 'post_12_content', 'post_13_content'], $getManys);
        $this->assertContains(['fmt_tt_'.md5('Alpha body'), 'fmt_tt_'.md5('Beta body')], $getManys);
        $this->assertContains(['topic_7_post_count', 3, 3600], $puts);
        $this->assertContains(['topic_8_post_count', 5, 3600], $puts);
        $this->assertContains(['topic_9_post_count', 0, 3600], $puts);

        $ttPuts = array_values(array_filter($puts, fn (array $p): bool => str_starts_with($p[0], 'fmt_tt_')));
        $this->assertCount(2, $ttPuts);
        foreach ($ttPuts as $p) {
            $this->assertSame(86400, $p[2]);
            $this->assertIsString($p[1]);
        }
        $this->assertSame('fmt_tt_'.md5('Alpha body'), $ttPuts[0][0]);
        $this->assertSame('fmt_tt_'.md5('Beta body'), $ttPuts[1][0]);
    }

    public function test_view_forum_runs_with_null_cache(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();
        $this->setRequest(['forumid' => 1]);
        Settings::saveBatch('tweak', ['enabletooltip' => 'yes']);
        Settings::resetCache();

        $user = new User;
        $user->id = 1;
        $user->class = UserClass::USER->value;
        auth()->login($user);

        $indexService = new ForumIndexService(
            $this->app->make(CurrentUser::class),
            $repo,
            new OverforumRepository,
            $this->app->make(NexusCache::class),
            $this->app->make(TopicRepository::class),
            $this->app->make(TopicReadStateRepository::class),
            $this->app->make(PostRepository::class),
        );
        $service = new ForumListingService(
            $indexService,
            null,
            $this->app->make(TopicRepository::class),
            $this->app->make(PostRepository::class),
        );

        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'forid' => 1, 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $this->postRepo->shouldReceive('countTopicPostsBatch')->with([7])->andReturn([7 => 3]);

        $dbUserId = (int) DB::table('users')->insertGetId([
            'username' => 'poster',
            'added' => '2024-01-01 00:00:00',
            'class' => UserClass::USER->value,
        ]);
        $dbTopicId = (int) DB::table('topics')->insertGetId([
            'forumid' => 1,
            'userid' => $dbUserId,
            'subject' => 'Real topic',
        ]);
        $postId = (int) DB::table('posts')->insertGetId([
            'topicid' => $dbTopicId,
            'userid' => $dbUserId,
            'added' => '2024-01-01 00:00:00',
            'body' => 'Real tooltip body',
            'ori_body' => 'Real tooltip body',
        ]);
        $this->topicRepo->shouldReceive('getTopicsByForum')->andReturn([
            'count' => 1,
            'rows' => new Collection([
                $this->fakeTopic(['id' => 7, 'lastpost' => $postId, 'firstpost' => 0]),
            ]),
        ]);

        $vm = $this->callWithSuppressedErrors(fn () => $service->buildViewForum(
            ['id' => 1, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes', 'showlastpost' => 'yes'],
            Request::create('/forums.php', 'GET', ['forumid' => 1]),
            20,
            10,
        ));

        $this->assertInstanceOf(TopicListViewModel::class, $vm);
        $this->assertSame(2, $vm->topics[0]->replies);
    }
}
