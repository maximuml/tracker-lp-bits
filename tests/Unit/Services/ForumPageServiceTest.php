<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\ForumRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicReadStateRepository;
use App\Repositories\TopicRepository;
use App\Services\ForumPageService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for ForumPageService.
 *
 * Covers build() dispatching: default action (forums index),
 * catchup flag, unknown action abort, and data structure.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumPageServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ForumPageService $service;

    private int $initialObLevel;

    /** @var TopicRepository&MockInterface */
    private TopicRepository $topicRepo;

    /** @var TopicReadStateRepository&MockInterface */
    private TopicReadStateRepository $readStateRepo;

    /** @var PostRepository&MockInterface */
    private PostRepository $postRepo;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        if (! defined('IN_NEXUS')) {
            define('IN_NEXUS', true);
        }
        $this->initialObLevel = ob_get_level();
        Settings::saveBatch('basic', ['SITENAME' => 'TestSite']);
        Settings::saveBatch('main', ['postsperpage' => 10, 'topicsperpage' => 20]);
        Settings::resetCache();
        app(Globals::class)->set('CURLANGDIR', 'en');
        app(Globals::class)->set('showforumstats_main', 'no');
        app(Globals::class)->set('lang_forums', [
            'text_forums' => 'Forums', 'text_search' => 'Search',
            'text_view_unread' => 'Unread', 'text_catch_up' => 'Catch Up',
            'std_forum_error' => 'Error', 'std_unknown_action' => 'Unknown action',
        ]);
    }

    /**
     * Resolve a fresh `ForumPageService` from the container so that any
     * mocks bound in the test are injected.
     */
    private function service(): ForumPageService
    {
        return $this->app->make(ForumPageService::class);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }
        Mockery::close();
        parent::tearDown();
    }

    /** @return ForumRepository&MockInterface */
    private function mockForumRepo(): mixed
    {
        /** @var ForumRepository&MockInterface $repo */
        $repo = Mockery::mock(ForumRepository::class);
        $repo->shouldReceive('getModeratorArray')->andReturn([]);
        $repo->shouldReceive('updateUserForumAccess')->andReturn(true);
        $repo->shouldReceive('getOverforumsList')->andReturn([]);
        $repo->shouldReceive('getForumsList')->andReturn([]);
        $this->app->instance(ForumRepository::class, $repo);

        /** @var TopicRepository&MockInterface $topicRepo */
        $topicRepo = Mockery::mock(TopicRepository::class);
        $topicRepo->shouldIgnoreMissing();
        $this->app->instance(TopicRepository::class, $topicRepo);
        $this->topicRepo = $topicRepo;

        /** @var TopicReadStateRepository&MockInterface $readStateRepo */
        $readStateRepo = Mockery::mock(TopicReadStateRepository::class);
        $readStateRepo->shouldIgnoreMissing();
        $this->app->instance(TopicReadStateRepository::class, $readStateRepo);
        $this->readStateRepo = $readStateRepo;

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
        $cache->shouldReceive('cache_value')->andReturn(true);
        $cache->shouldReceive('delete_value')->andReturn(true);
        $this->app->instance(LegacyRedisCache::class, $cache);
        $this->rebuildService(null, $cache);
    }

    private function rebuildService(?ForumRepository $repo = null, ?LegacyRedisCache $cache = null): void
    {
        if ($repo !== null) {
            $this->app->instance(ForumRepository::class, $repo);
        }
        if ($cache !== null) {
            $this->app->instance(LegacyRedisCache::class, $cache);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function setUser(array $data = []): void
    {
        $defaults = [
            'id' => 999, 'username' => 'testuser', 'class' => 10,
            'forumpost' => 'yes', 'postsperpage' => 0, 'topicsperpage' => 0,
        ];
        $merged = array_merge($defaults, $data);

        $currentUser = new CurrentUser;
        $currentUser->set($merged);
        $this->app->instance(CurrentUser::class, $currentUser);

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

    // --- build: default action (forums index) ---

    public function test_build_default_action_returns_forums_index(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->build(Request::create('/forums.php', 'GET'))->toArray());

        $this->assertSame('forums', $result['action']);
        $this->assertSame(999, $result['userId']);
        $this->assertSame('TestSite', $result['sitename']);
        $this->assertSame(10, $result['postsperpage']);
        $this->assertSame(20, $result['topicsperpage']);
        $this->assertArrayHasKey('forums', $result);
        $this->assertArrayHasKey('html', $result['forums']);
    }

    // --- build: data structure ---

    public function test_build_returns_expected_data_keys(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->build(Request::create('/forums.php', 'GET'))->toArray());

        $this->assertArrayHasKey('lang', $result);
        $this->assertArrayHasKey('curUser', $result);
        $this->assertArrayHasKey('userId', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('sitename', $result);
        $this->assertArrayHasKey('postsperpage', $result);
        $this->assertArrayHasKey('topicsperpage', $result);
        $this->assertArrayHasKey('todayDate', $result);
    }

    // --- build: catchup flag ---

    public function test_build_with_catchup_flag_clears_read_posts(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $request = Request::create('/forums.php', 'GET', ['catchup' => 1]);
        $this->app->instance('request', $request);

        $this->readStateRepo->shouldReceive('clearReadPosts')->once()->andReturn(true);
        $this->postRepo->shouldReceive('getLastPostId')->once()->andReturn(0);

        $result = $this->service()->build($request)->toArray();

        $this->assertSame('forums', $result['action']);
    }

    // --- build: unknown action aborts ---

    public function test_build_unknown_action_aborts(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['action' => 'invalidaction']);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service()->build(Request::create('/forums.php', 'GET', ['action' => 'invalidaction'])));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when action is unknown');
    }

    // --- build: postsperpage from user settings ---

    public function test_build_uses_user_postsperpage_when_set(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser(['postsperpage' => 25, 'topicsperpage' => 30]);
        $this->setRequest();

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->build(Request::create('/forums.php', 'GET'))->toArray());

        $this->assertSame(25, $result['postsperpage']);
        $this->assertSame(30, $result['topicsperpage']);
    }

    // --- build: postsperpage from globals when user has none ---

    public function test_build_uses_globals_postsperpage_when_user_has_none(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        Settings::saveBatch('main', ['postsperpage' => 15, 'topicsperpage' => 25]);
        Settings::resetCache();

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->build(Request::create('/forums.php', 'GET'))->toArray());

        $this->assertSame(15, $result['postsperpage']);
        $this->assertSame(25, $result['topicsperpage']);
    }

    // --- build: postsperpage defaults when neither user nor globals set ---

    public function test_build_uses_default_postsperpage_when_neither_set(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        app(Globals::class)->set('forumpostsperpage', null);
        app(Globals::class)->set('forumtopicsperpage_main', null);

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->build(Request::create('/forums.php', 'GET'))->toArray());

        $this->assertSame(10, $result['postsperpage']);
        $this->assertSame(20, $result['topicsperpage']);
    }
}
