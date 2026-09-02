<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\ForumRepository;
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
use Tests\TestCase;

/**
 * Unit tests for ForumListingService.
 *
 * Covers buildViewUnread (no topics, with topics), buildSearch
 * (no keywords, no hits, with hits), and buildViewForum
 * (invalid ID, nonexistent forum, valid forum with no topics).
 */
final class ForumListingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ForumListingService $service;

    private int $initialObLevel;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        if (! defined('IN_NEXUS')) {
            define('IN_NEXUS', true);
        }
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

        $indexService = new ForumIndexService;
        $this->service = new ForumListingService($indexService);
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function setUser(array $data = []): void
    {
        $defaults = ['id' => 1, 'username' => 'testuser', 'class' => 10];
        $merged = array_merge($defaults, $data);

        $currentUser = new CurrentUser;
        $currentUser->set($merged);
        $this->app->instance(CurrentUser::class, $currentUser);

        // IN_NEXUS is false in the test environment, so UserDisplay::currentClass()
        // uses auth()->user()->class — log in a User model with the right class.
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

        $repo->shouldReceive('getUnreadTopics')->andReturn(new Collection);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewUnread(['text_nothing_found' => 'Nothing found', 'text_forums' => 'Forums'], ['id' => 1, 'username' => 'test', 'class' => 10]));

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('Nothing found', $result['html']);
    }

    public function test_build_view_unread_returns_html_structure(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $repo->shouldReceive('getUnreadTopics')->andReturn(new Collection);
        $repo->shouldReceive('getForumsList')->andReturn([]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewUnread(['text_forums' => 'Forums', 'text_nothing_found' => 'Nothing'], ['id' => 1, 'username' => 'test', 'class' => 10]));

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('<h1', $result['html']);
    }

    // --- buildSearch ---

    public function test_build_search_with_no_keywords_returns_form(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest();

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildSearch(['text_search_on_forum' => 'Search', 'text_by_keyword' => 'Keyword'], 20));

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('search_form', $result['html']);
        $this->assertStringContainsString('Keyword', $result['html']);
    }

    public function test_build_search_with_keywords_no_hits_returns_form_with_error(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['keywords' => 'notfound']);

        $repo->shouldReceive('searchForumPosts')->andReturn(['hits' => 0, 'rows' => new Collection]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildSearch(['text_search_on_forum' => 'Search', 'text_nothing_found' => 'Nothing found', 'text_by_keyword' => 'Keyword'], 20));

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('Nothing found', $result['html']);
    }

    public function test_build_search_with_keywords_and_hits_returns_results(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['keywords' => 'test']);

        $repo->shouldReceive('searchForumPosts')->andReturn(['hits' => 1, 'rows' => new Collection]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildSearch(['text_search_on_forum' => 'Search', 'text_found' => 'Found ', 'text_num_posts' => ' posts', 'text_by_keyword' => 'Keyword'], 20));

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('Found', $result['html']);
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
            $this->callWithSuppressedErrors(fn () => $this->service->buildViewForum(['std_forum_error' => 'Error'], ['id' => 1, 'username' => 'test', 'class' => 10], Request::create('/forums.php', 'GET', ['forumid' => 0]), 20, 10));
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
            $this->callWithSuppressedErrors(fn () => $this->service->buildViewForum(['std_forum_error' => 'Error', 'std_forum_not_found' => 'Not found'], ['id' => 1, 'username' => 'test', 'class' => 10, 'ip' => '127.0.0.1'], Request::create('/forums.php', 'GET', ['forumid' => 999]), 20, 10));
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
        $repo->shouldReceive('getTopicsByForum')->andReturn(['count' => 0, 'rows' => new Collection]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewForum(
            ['text_forums' => 'Forums', 'text_no_topics_found' => 'No topics found', 'col_topic' => 'Topic', 'col_author' => 'Author', 'col_replies' => 'Replies', 'col_views' => 'Views', 'col_last_post' => 'Last Post', 'text_fast_search' => 'Search', 'text_go' => 'Go', 'text_order' => 'Order'],
            ['id' => 1, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes'],
            Request::create('/forums.php', 'GET', ['forumid' => 1]),
            20,
            10,
        ));

        $this->assertArrayHasKey('html', $result);
        $this->assertSame(1, $result['forumid']);
        $this->assertSame('Test Forum', $result['forumname']);
        $this->assertStringContainsString('No topics found', $result['html']);
    }
}
