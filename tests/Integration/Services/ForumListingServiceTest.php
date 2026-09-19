<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

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
use App\Support\LegacyRuntime;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for ForumListingService.
 *
 * Covers buildViewUnread (no topics, with topics), buildSearch
 * (no keywords, no hits, with hits), and buildViewForum
 * (invalid ID, nonexistent forum, valid forum with no topics).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumListingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ForumListingService $service;

    private int $initialObLevel;

    /** @var TopicRepository&MockInterface */
    private TopicRepository $topicRepo;

    /** @var PostRepository&MockInterface */
    private PostRepository $postRepo;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        app(LegacyRuntime::class)->markLegacy();
        $this->initialObLevel = ob_get_level();
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

    public function test_build_view_unread_with_no_topics_returns_nothing_found(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $this->topicRepo->shouldReceive('getUnreadTopics')->andReturn(new Collection);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewUnread(['id' => 1, 'username' => 'test', 'class' => 10]));

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('Nothing found', (string) $result['html']);
    }

    public function test_build_view_unread_returns_html_structure(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $this->topicRepo->shouldReceive('getUnreadTopics')->andReturn(new Collection);
        $repo->shouldReceive('getForumsList')->andReturn([]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewUnread(['id' => 1, 'username' => 'test', 'class' => 10]));

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('<h1', (string) $result['html']);
    }

    // --- buildSearch ---

    public function test_build_search_with_no_keywords_returns_form(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildSearch(20));

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('search_form', (string) $result['html']);
        $this->assertStringContainsString('by keyword', (string) $result['html']);
    }

    public function test_build_search_with_keywords_no_hits_returns_form_with_error(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['keywords' => 'notfound']);

        $this->postRepo->shouldReceive('searchForumPosts')->andReturn(['hits' => 0, 'rows' => new Collection]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildSearch(20));

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('Nothing found', (string) $result['html']);
    }

    public function test_build_search_with_keywords_and_hits_returns_results(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['keywords' => 'test']);

        $this->postRepo->shouldReceive('searchForumPosts')->andReturn(['hits' => 1, 'rows' => new Collection]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildSearch(20));

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('Found', (string) $result['html']);
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

    public function test_build_view_forum_with_valid_forum_no_topics_returns_no_topics(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['forumid' => 1]);

        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'forid' => 1, 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0, 'topiccount' => 0, 'postcount' => 0, 'description' => 'Test'],
        ]);
        $this->topicRepo->shouldReceive('getTopicsByForum')->andReturn(['count' => 0, 'rows' => new Collection]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewForum(
            ['id' => 1, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes'],
            Request::create('/forums.php', 'GET', ['forumid' => 1]),
            20,
            10,
        ));

        $this->assertArrayHasKey('html', $result);
        $this->assertSame(1, $result['forumid']);
        $this->assertSame('Test Forum', (string) ($result['forumname']));
        $this->assertStringContainsString('No topics found', (string) $result['html']);
    }
}
