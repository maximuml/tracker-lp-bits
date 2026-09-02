<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Auth\Permission;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Services\ForumIndexService;
use App\Services\ForumTopicViewService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for ForumTopicViewService.
 *
 * Covers buildViewTopic with invalid topicid, topic not found,
 * permission denied, and valid topic with posts.
 */
final class ForumTopicViewServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ForumTopicViewService $service;

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
        app(Globals::class)->set('CURLANGDIR', 'en');
        app(Globals::class)->set('lang_functions', [
            'text_prev' => 'Prev', 'text_next' => 'Next',
            'text_locked' => 'Locked', 'text_forums' => 'Forums',
            'there_is' => 'There is ', 'hits_on_this_topic' => ' views',
            'title_reply_directly' => 'Reply', 'text_by' => 'by',
            'text_at' => 'at', 'text_number' => '#', 'text_lou' => '',
            'text_back_to_top' => 'Top', 'text_view_all_posts' => 'All',
            'text_view_this_author_only' => 'Author', 'text_posts' => 'Posts',
            'text_ul' => 'UL', 'text_dl' => 'DL', 'text_ratio' => 'Ratio',
            'title_online' => 'Online', 'title_offline' => 'Offline',
            'title_send_message_to' => 'PM', 'title_report_this_post' => 'Report',
            'title_reply_with_quote' => 'Quote', 'title_delete_post' => 'Delete',
            'title_edit_post' => 'Edit', 'submit_sticky' => 'Sticky',
            'submit_unsticky' => 'Unsticky', 'submit_lock' => 'Lock',
            'submit_unlock' => 'Unlock', 'submit_delete_topic' => 'Delete Topic',
            'text_move_thread_to' => 'Move to', 'submit_move' => 'Move',
            'text_highlight_topic' => 'Highlight', 'select_color' => 'Color',
            'submit_change' => 'Change', 'text_quick_reply' => 'Quick Reply',
            'submit_add_reply' => 'Add Reply', 'text_add_reply' => 'Reply',
            'text_topic_locked_new_denied' => 'Locked', 'text_unpermitted_posting_here' => 'No post',
            'text_post_protected' => 'Protected', 'text_last_edited_by' => 'Edited by',
            'text_last_edit_at' => ' at ', 'std_error' => 'Error',
            'std_forum_error' => 'Forum Error', 'std_topic_not_found' => 'Topic not found',
            'std_unpermitted_viewing_topic' => 'No permission',
        ]);

        $indexService = new ForumIndexService;
        $this->service = new ForumTopicViewService($indexService);
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
        $repo->shouldReceive('isModeratorOfForum')->andReturn(false);
        $repo->shouldReceive('getModeratorArray')->andReturn([]);
        $repo->shouldReceive('getLastReadPosts')->andReturn(null);
        $this->app->instance(ForumRepository::class, $repo);

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
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function setUser(array $data = []): int
    {
        $defaults = [
            'id' => 999, 'username' => 'testuser', 'class' => 10,
            'enabled' => 'yes', 'donor' => 'no', 'leechwarn' => 'no',
            'warned' => 'no', 'forumpost' => 'yes', 'avatars' => 'yes',
            'signatures' => 'yes', 'clicktopic' => 'firstpage',
        ];
        $merged = array_merge($defaults, $data);

        $currentUser = new CurrentUser;
        $currentUser->set($merged);
        $this->app->instance(CurrentUser::class, $currentUser);

        $user = new User;
        $user->id = $merged['id'];
        $user->class = $merged['class'];
        $user->username = $merged['username'];
        $user->enabled = $merged['enabled'];
        $user->donor = $merged['donor'];
        $user->forumpost = $merged['forumpost'];
        auth()->login($user);

        return (int) $merged['id'];
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createDbUser(int $id, array $overrides = []): void
    {
        $data = array_merge([
            'id' => $id,
            'username' => 'user'.$id,
            'email' => 'user'.$id.'@test.com',
            'class' => 10,
            'enabled' => 1,
            'donor' => 0,
            'donoruntil' => null,
            'leechwarn' => 0,
            'warned' => 0,
            'title' => '',
            'avatar' => '',
            'signature' => '',
            'uploaded' => 0,
            'downloaded' => 0,
            'last_access' => '2024-01-01 00:00:00',
            'privacy' => 'normal',
            'passkey' => 'testpasskey'.$id,
            'passhash' => 'testhash',
            'secret' => 'testsecret',
            'auth_key' => 'testauthkey',
            'status' => 'confirmed',
            'added' => '2024-01-01 00:00:00',
            'parked' => 0,
            'clientselect' => 0,
            'showclienterror' => 0,
            'downloadpos' => 1,
        ], $overrides);

        DB::table('users')->insert($data);
    }

    // --- buildViewTopic: invalid topicid ---

    public function test_build_view_topic_with_invalid_id_aborts(): void
    {
        $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['topicid' => 0]);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
                ['std_forum_error' => 'Error', 'std_topic_not_found' => 'Not found'],
                ['id' => 1, 'username' => 'test', 'class' => 10],
                1,
                Request::create('/forums.php', 'GET', ['topicid' => 0]),
                10,
            ));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when topicid is invalid (0)');
    }

    // --- buildViewTopic: topic not found ---

    public function test_build_view_topic_not_found_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['topicid' => 999]);

        $repo->shouldReceive('getTopic')->with(999)->andReturn(null);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
                ['std_forum_error' => 'Error', 'std_topic_not_found' => 'Not found'],
                ['id' => 1, 'username' => 'test', 'class' => 10],
                1,
                Request::create('/forums.php', 'GET', ['topicid' => 999]),
                10,
            ));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when topic does not exist');
    }

    // --- buildViewTopic: permission denied ---

    public function test_build_view_topic_permission_denied_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser(['class' => 0]);
        $this->setRequest(['topicid' => 1]);

        $topic = new Topic;
        $topic->id = 1;
        $topic->userid = 1;
        $topic->subject = 'Test Topic';
        $topic->locked = false;
        $topic->forumid = 1;
        $topic->sticky = false;
        $topic->hlcolor = 0;
        $topic->views = 0;

        $repo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 50, 'minclasswrite' => 50, 'minclasscreate' => 50],
        ]);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
                ['std_error' => 'Error', 'std_unpermitted_viewing_topic' => 'No permission'],
                ['id' => 1, 'username' => 'test', 'class' => 0],
                1,
                Request::create('/forums.php', 'GET', ['topicid' => 1]),
                10,
            ));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when user class is below minclassread');
    }

    // --- buildViewTopic: valid topic with no posts ---

    public function test_build_view_topic_valid_no_posts_returns_html(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['topicid' => 1]);

        $topic = new Topic;
        $topic->id = 1;
        $topic->userid = 1;
        $topic->subject = 'Test Topic';
        $topic->locked = false;
        $topic->forumid = 1;
        $topic->sticky = false;
        $topic->hlcolor = 0;
        $topic->views = 5;

        $repo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $repo->shouldReceive('incrementTopicViews')->with(1)->andReturn(true);
        $repo->shouldReceive('countTopicPosts')->with(1, null)->andReturn(0);
        $repo->shouldReceive('getTopicPosts')->withAnyArgs()->andReturn(new EloquentCollection);
        $repo->shouldReceive('getUsersByIds')->andReturn(new EloquentCollection);
        $repo->shouldReceive('countUserPosts')->andReturn(0);
        $repo->shouldReceive('markPostRead')->andReturn(true);
        $repo->shouldReceive('getTopicById')->andReturn($topic);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
            ['text_forums' => 'Forums', 'text_prev' => 'Prev', 'text_next' => 'Next',
                'there_is' => 'There is ', 'hits_on_this_topic' => ' views',
                'text_locked' => 'Locked', 'title_reply_directly' => 'Reply',
                'text_by' => 'by', 'text_at' => 'at', 'text_number' => '#',
                'text_lou' => '', 'text_back_to_top' => 'Top',
                'text_view_all_posts' => 'All', 'text_view_this_author_only' => 'Author',
                'text_posts' => 'Posts', 'text_ul' => 'UL', 'text_dl' => 'DL',
                'text_ratio' => 'Ratio', 'title_online' => 'Online',
                'title_offline' => 'Offline', 'title_send_message_to' => 'PM',
                'title_report_this_post' => 'Report', 'title_reply_with_quote' => 'Quote',
                'title_delete_post' => 'Delete', 'title_edit_post' => 'Edit',
                'submit_sticky' => 'Sticky', 'submit_unsticky' => 'Unsticky',
                'submit_lock' => 'Lock', 'submit_unlock' => 'Unlock',
                'submit_delete_topic' => 'Delete Topic', 'text_move_thread_to' => 'Move to',
                'submit_move' => 'Move', 'text_highlight_topic' => 'Highlight',
                'select_color' => 'Color', 'submit_change' => 'Change',
                'text_quick_reply' => 'Quick Reply', 'submit_add_reply' => 'Add Reply',
                'text_add_reply' => 'Reply', 'text_unpermitted_posting_here' => 'No post',
                'std_forum_error' => 'Error', 'std_topic_not_found' => 'Not found',
                'std_error' => 'Error', 'std_unpermitted_viewing_topic' => 'No permission'],
            ['id' => 999, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes',
                'clicktopic' => 'firstpage', 'avatars' => 'yes', 'signatures' => 'yes',
                'last_catchup' => 0],
            999,
            Request::create('/forums.php', 'GET', ['topicid' => 1]),
            10,
        ));

        $this->assertArrayHasKey('html', $result);
        $this->assertSame(1, $result['topicid']);
        $this->assertSame(1, $result['forumid']);
        $this->assertStringContainsString('Test Topic', $result['html']);
    }

    // --- buildViewTopic: valid topic with posts ---

    public function test_build_view_topic_valid_with_posts_returns_html(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $userId = $this->setUser();
        $this->setRequest(['topicid' => 1]);

        // Create a real user in the DB for UserDisplay::row()
        $this->createDbUser($userId);

        $topic = new Topic;
        $topic->id = 1;
        $topic->setAttribute('userid', $userId);
        $topic->subject = 'Test Topic';
        $topic->locked = false;
        $topic->forumid = 1;
        $topic->sticky = false;
        $topic->hlcolor = 0;
        $topic->views = 5;

        $post = new Post;
        $post->id = 1;
        $post->topicid = 1;
        $post->setAttribute('userid', $userId);
        $post->added = Carbon::parse('2024-01-01 12:00:00');
        $post->body = 'Hello world';
        $post->editedby = 0;

        $user = new User;
        $user->id = $userId;
        $user->username = 'user'.$userId;
        $user->class = 10;
        $user->enabled = true;
        $user->donor = false;
        $user->leechwarn = false;
        $user->warned = false;
        $user->avatar = '';
        $user->signature = '';
        $user->uploaded = 0;
        $user->downloaded = 0;
        $user->last_access = '2024-01-01 00:00:00';
        $user->title = '';

        $userCollection = new EloquentCollection([$userId => $user]);

        $repo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $repo->shouldReceive('incrementTopicViews')->with(1)->andReturn(true);
        $repo->shouldReceive('countTopicPosts')->with(1, null)->andReturn(1);
        $repo->shouldReceive('getTopicPosts')->withAnyArgs()->andReturn(new EloquentCollection([$post]));
        $repo->shouldReceive('getUsersByIds')->andReturn($userCollection);
        $repo->shouldReceive('countUserPosts')->andReturn(0);
        $repo->shouldReceive('markPostRead')->andReturn(true);
        $repo->shouldReceive('getTopicById')->with(1)->andReturn($topic);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
            ['text_forums' => 'Forums', 'text_prev' => 'Prev', 'text_next' => 'Next',
                'there_is' => 'There is ', 'hits_on_this_topic' => ' views',
                'text_locked' => 'Locked', 'title_reply_directly' => 'Reply',
                'text_by' => 'by', 'text_at' => 'at', 'text_number' => '#',
                'text_lou' => '', 'text_back_to_top' => 'Top',
                'text_view_all_posts' => 'All', 'text_view_this_author_only' => 'Author',
                'text_posts' => 'Posts', 'text_ul' => 'UL', 'text_dl' => 'DL',
                'text_ratio' => 'Ratio', 'title_online' => 'Online',
                'title_offline' => 'Offline', 'title_send_message_to' => 'PM',
                'title_report_this_post' => 'Report', 'title_reply_with_quote' => 'Quote',
                'title_delete_post' => 'Delete', 'title_edit_post' => 'Edit',
                'submit_sticky' => 'Sticky', 'submit_unsticky' => 'Unsticky',
                'submit_lock' => 'Lock', 'submit_unlock' => 'Unlock',
                'submit_delete_topic' => 'Delete Topic', 'text_move_thread_to' => 'Move to',
                'submit_move' => 'Move', 'text_highlight_topic' => 'Highlight',
                'select_color' => 'Color', 'submit_change' => 'Change',
                'text_quick_reply' => 'Quick Reply', 'submit_add_reply' => 'Add Reply',
                'text_add_reply' => 'Reply', 'text_unpermitted_posting_here' => 'No post',
                'std_forum_error' => 'Error', 'std_topic_not_found' => 'Not found',
                'std_error' => 'Error', 'std_unpermitted_viewing_topic' => 'No permission'],
            ['id' => $userId, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes',
                'clicktopic' => 'firstpage', 'avatars' => 'yes', 'signatures' => 'yes',
                'last_catchup' => 0],
            $userId,
            Request::create('/forums.php', 'GET', ['topicid' => 1]),
            10,
        ));

        $this->assertArrayHasKey('html', $result);
        $this->assertSame(1, $result['topicid']);
        $this->assertSame(1, $result['forumid']);
        $this->assertStringContainsString('Test Topic', $result['html']);
        $this->assertStringContainsString('Hello world', $result['html']);
    }

    // --- buildViewTopic: locked topic ---

    public function test_build_view_topic_locked_shows_locked_indicator(): void
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $this->setUser();
        $this->setRequest(['topicid' => 1]);

        $topic = new Topic;
        $topic->id = 1;
        $topic->userid = 1;
        $topic->subject = 'Locked Topic';
        $topic->locked = true;
        $topic->forumid = 1;
        $topic->sticky = false;
        $topic->hlcolor = 0;
        $topic->views = 3;

        $repo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $repo->shouldReceive('incrementTopicViews')->with(1)->andReturn(true);
        $repo->shouldReceive('countTopicPosts')->with(1, null)->andReturn(0);
        $repo->shouldReceive('getTopicPosts')->withAnyArgs()->andReturn(new EloquentCollection);
        $repo->shouldReceive('getUsersByIds')->andReturn(new EloquentCollection);
        $repo->shouldReceive('countUserPosts')->andReturn(0);
        $repo->shouldReceive('markPostRead')->andReturn(true);
        $repo->shouldReceive('getTopicById')->andReturn($topic);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
            ['text_forums' => 'Forums', 'text_prev' => 'Prev', 'text_next' => 'Next',
                'there_is' => 'There is ', 'hits_on_this_topic' => ' views',
                'text_locked' => 'LOCKED', 'title_reply_directly' => 'Reply',
                'text_by' => 'by', 'text_at' => 'at', 'text_number' => '#',
                'text_lou' => '', 'text_back_to_top' => 'Top',
                'text_view_all_posts' => 'All', 'text_view_this_author_only' => 'Author',
                'text_posts' => 'Posts', 'text_ul' => 'UL', 'text_dl' => 'DL',
                'text_ratio' => 'Ratio', 'title_online' => 'Online',
                'title_offline' => 'Offline', 'title_send_message_to' => 'PM',
                'title_report_this_post' => 'Report', 'title_reply_with_quote' => 'Quote',
                'title_delete_post' => 'Delete', 'title_edit_post' => 'Edit',
                'submit_sticky' => 'Sticky', 'submit_unsticky' => 'Unsticky',
                'submit_lock' => 'Lock', 'submit_unlock' => 'Unlock',
                'submit_delete_topic' => 'Delete Topic', 'text_move_thread_to' => 'Move to',
                'submit_move' => 'Move', 'text_highlight_topic' => 'Highlight',
                'select_color' => 'Color', 'submit_change' => 'Change',
                'text_quick_reply' => 'Quick Reply', 'submit_add_reply' => 'Add Reply',
                'text_add_reply' => 'Reply', 'text_topic_locked_new_denied' => 'Topic is locked',
                'text_unpermitted_posting_here' => 'No post',
                'std_forum_error' => 'Error', 'std_topic_not_found' => 'Not found',
                'std_error' => 'Error', 'std_unpermitted_viewing_topic' => 'No permission'],
            ['id' => 999, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes',
                'clicktopic' => 'firstpage', 'avatars' => 'yes', 'signatures' => 'yes',
                'last_catchup' => 0],
            999,
            Request::create('/forums.php', 'GET', ['topicid' => 1]),
            10,
        ));

        $this->assertStringContainsString('LOCKED', $result['html']);
        $this->assertStringContainsString('Locked Topic', $result['html']);
    }
}
