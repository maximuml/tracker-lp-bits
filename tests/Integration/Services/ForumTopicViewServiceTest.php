<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Auth\Permission;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Repositories\OverforumRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicReadStateRepository;
use App\Repositories\TopicRepository;
use App\Services\ForumIndexService;
use App\Services\ForumTopicViewService;
use App\Support\Cache\NexusCache;
use App\Support\CurrentUser;
use App\Support\PageState;
use App\ViewModels\Forum\ViewTopicViewModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Unit tests for ForumTopicViewService.
 *
 * Covers buildViewTopic with invalid topicid, topic not found,
 * permission denied, and valid topic with posts.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumTopicViewServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    private ForumTopicViewService $service;

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
        $this->initialObLevel = ob_get_level();
        $this->seedTestSettings(['SITENAME' => 'TestSite']);
        PageState::instance()->setLangDir('en');

        $indexService = new ForumIndexService(
            $this->app->make(CurrentUser::class),
            $this->app->make(ForumRepository::class),
            new OverforumRepository,
            $this->app->make(NexusCache::class),
            $this->app->make(TopicRepository::class),
            $this->app->make(TopicReadStateRepository::class),
            $this->app->make(PostRepository::class),
        );
        $this->service = new ForumTopicViewService(
            $indexService,
            $this->app->make(ForumRepository::class),
            $this->app->make(NexusCache::class),
            $this->app->make(TopicRepository::class),
            $this->app->make(TopicReadStateRepository::class),
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
        $repo->shouldReceive('isModeratorOfForum')->andReturn(false);
        $repo->shouldReceive('getModeratorArray')->andReturn([]);
        $this->app->instance(ForumRepository::class, $repo);

        /** @var TopicRepository&MockInterface $topicRepo */
        $topicRepo = Mockery::mock(TopicRepository::class);
        $topicRepo->shouldIgnoreMissing();
        $this->app->instance(TopicRepository::class, $topicRepo);
        $this->topicRepo = $topicRepo;

        /** @var TopicReadStateRepository&MockInterface $readStateRepo */
        $readStateRepo = Mockery::mock(TopicReadStateRepository::class);
        $readStateRepo->shouldIgnoreMissing();
        $readStateRepo->shouldReceive('getLastReadPosts')->andReturn(null);
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
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get')->andReturn(false);
        $cache->shouldReceive('put')->andReturn(true);
        $cache->shouldReceive('forget')->andReturn(true);
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
        $this->service = new ForumTopicViewService(
            $indexService,
            $forumRepo,
            $cacheInstance,
            $this->app->make(TopicRepository::class),
            $this->app->make(TopicReadStateRepository::class),
            $this->app->make(PostRepository::class),
        );
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
            'signatures' => 'yes', 'clicktopic' => 0,
        ];
        $merged = array_merge($defaults, $data);

        $currentUser = $this->app->make(CurrentUser::class);
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
            'privacy' => 1,
            'passkey' => 'testpasskey'.$id,
            'passhash' => 'testhash',
            'secret' => 'testsecret',
            'auth_key' => 'testauthkey',
            'status' => 1,
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

        $this->topicRepo->shouldReceive('getTopic')->with(999)->andReturn(null);

        $this->assertAbortContains(
            fn () => $this->service->buildViewTopic(
                ['id' => 1, 'username' => 'test', 'class' => 10],
                1,
                Request::create('/forums.php', 'GET', ['topicid' => 999]),
                10,
            ),
            (string) __('forums.std_topic_not_found'),
        );
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

        $this->topicRepo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 50, 'minclasswrite' => 50, 'minclasscreate' => 50],
        ]);

        $this->assertAbortContains(
            fn () => $this->service->buildViewTopic(
                ['id' => 1, 'username' => 'test', 'class' => 0],
                1,
                Request::create('/forums.php', 'GET', ['topicid' => 1]),
                10,
            ),
            (string) __('forums.std_unpermitted_viewing_topic'),
        );
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

        $this->topicRepo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $this->topicRepo->shouldReceive('incrementTopicViews')->with(1)->andReturn(true);
        $this->postRepo->shouldReceive('countTopicPosts')->with(1, null)->andReturn(0);
        $this->postRepo->shouldReceive('getTopicPosts')->withAnyArgs()->andReturn(new EloquentCollection);
        $repo->shouldReceive('getUsersByIds')->andReturn(new EloquentCollection);
        $this->postRepo->shouldReceive('countUserPosts')->andReturn(0);
        $this->readStateRepo->shouldReceive('markPostRead')->andReturn(true);
        $this->topicRepo->shouldReceive('getTopicById')->andReturn($topic);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
            ['id' => 999, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes',
                'clicktopic' => 0, 'avatars' => 'yes', 'signatures' => 'yes',
                'last_catchup' => 0],
            999,
            Request::create('/forums.php', 'GET', ['topicid' => 1]),
            10,
        ));

        $this->assertInstanceOf(ViewTopicViewModel::class, $result);
        $this->assertSame(1, $result->topicid);
        $this->assertSame(1, $result->forumid);
        $this->assertStringContainsString('Test Topic', (string) $result->subject);
        $this->assertSame([], $result->posts);
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

        $this->topicRepo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $this->topicRepo->shouldReceive('incrementTopicViews')->with(1)->andReturn(true);
        $this->postRepo->shouldReceive('countTopicPosts')->with(1, null)->andReturn(1);
        $this->postRepo->shouldReceive('getTopicPosts')->withAnyArgs()->andReturn(new EloquentCollection([$post]));
        $repo->shouldReceive('getUsersByIds')->andReturn($userCollection);
        $this->postRepo->shouldReceive('countUserPosts')->andReturn(0);
        $this->readStateRepo->shouldReceive('markPostRead')->andReturn(true);
        $this->topicRepo->shouldReceive('getTopicById')->with(1)->andReturn($topic);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
            ['id' => $userId, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes',
                'clicktopic' => 0, 'avatars' => 'yes', 'signatures' => 'yes',
                'last_catchup' => 0],
            $userId,
            Request::create('/forums.php', 'GET', ['topicid' => 1]),
            10,
        ));

        $this->assertInstanceOf(ViewTopicViewModel::class, $result);
        $this->assertSame(1, $result->topicid);
        $this->assertSame(1, $result->forumid);
        $this->assertStringContainsString('Test Topic', (string) $result->subject);
        $this->assertCount(1, $result->posts);
        $this->assertStringContainsString('Hello world', (string) $result->posts[0]->body);
        $this->assertSame(1, $result->posts[0]->number);
        $this->assertTrue($result->posts[0]->isLast);
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

        $this->topicRepo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $this->topicRepo->shouldReceive('incrementTopicViews')->with(1)->andReturn(true);
        $this->postRepo->shouldReceive('countTopicPosts')->with(1, null)->andReturn(0);
        $this->postRepo->shouldReceive('getTopicPosts')->withAnyArgs()->andReturn(new EloquentCollection);
        $repo->shouldReceive('getUsersByIds')->andReturn(new EloquentCollection);
        $this->postRepo->shouldReceive('countUserPosts')->andReturn(0);
        $this->readStateRepo->shouldReceive('markPostRead')->andReturn(true);
        $this->topicRepo->shouldReceive('getTopicById')->andReturn($topic);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
            ['id' => 999, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes',
                'clicktopic' => 0, 'avatars' => 'yes', 'signatures' => 'yes',
                'last_catchup' => 0],
            999,
            Request::create('/forums.php', 'GET', ['topicid' => 1]),
            10,
        ));

        $this->assertInstanceOf(ViewTopicViewModel::class, $result);
        $this->assertTrue($result->locked);
        $this->assertStringContainsString('Locked Topic', (string) $result->subject);
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $forumRow
     * @param  array<string, mixed>  $topicAttrs
     * @param  array<string, mixed>  $posterAttrs
     */
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

    private function viewTopicVm(array $curUser = [], array $forumRow = [], array $topicAttrs = [], array $posterAttrs = [], array $query = ['topicid' => 1]): ViewTopicViewModel
    {
        $repo = $this->mockForumRepo();
        $this->mockCache();
        $curUser = array_merge(['id' => 999, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes',
            'clicktopic' => 0, 'avatars' => 'yes', 'signatures' => 'yes', 'last_catchup' => 0], $curUser);
        $userId = $this->setUser($curUser);
        $this->setRequest($query);

        $topic = new Topic;
        $topic->id = 1;
        $topic->setAttribute('userid', $userId);
        $topic->subject = 'Test Topic';
        $topic->locked = false;
        $topic->forumid = 1;
        $topic->sticky = false;
        $topic->hlcolor = 0;
        $topic->views = 5;
        foreach ($topicAttrs as $key => $value) {
            $topic->setAttribute($key, $value);
        }

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
        foreach ($posterAttrs as $key => $value) {
            $user->setAttribute($key, $value);
        }

        $this->topicRepo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => array_merge(['id' => 1, 'name' => 'Test Forum', 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0], $forumRow),
        ]);
        $this->topicRepo->shouldReceive('incrementTopicViews')->with(1)->andReturn(true);
        $this->postRepo->shouldReceive('countTopicPosts')->with(1, isset($query['authorid']) ? (int) $query['authorid'] : null)->andReturn(1);
        $this->postRepo->shouldReceive('getTopicPosts')->withAnyArgs()->andReturn(new EloquentCollection([$post]));
        $repo->shouldReceive('getUsersByIds')->andReturn(new EloquentCollection([$userId => $user]));
        $this->postRepo->shouldReceive('countUserPosts')->andReturn(0);
        $this->readStateRepo->shouldReceive('markPostRead')->andReturn(true);
        $this->topicRepo->shouldReceive('getTopicById')->with(1)->andReturn($topic);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
            $curUser,
            (int) $curUser['id'],
            Request::create('/forums.php', 'GET', $query),
            10,
        ));

        $this->assertInstanceOf(ViewTopicViewModel::class, $result);

        return $result;
    }

    public function test_viewtopic_maypost_false_when_class_below_minwrite(): void
    {
        $vm = $this->viewTopicVm(['class' => 2], ['minclasswrite' => 5]);

        $this->assertFalse($vm->mayPost);
    }

    public function test_viewtopic_maypost_false_when_topic_locked(): void
    {
        $vm = $this->viewTopicVm([], [], ['locked' => true]);

        $this->assertFalse($vm->mayPost);
    }

    public function test_viewtopic_maypost_false_when_forumpost_disabled(): void
    {
        $vm = $this->viewTopicVm(['forumpost' => 'no']);

        $this->assertFalse($vm->mayPost);
        $this->assertNull($vm->quickReply);
        $this->assertNotNull($vm->deniedNotice);
    }

    public function test_viewtopic_maypost_true_when_class_equals_minwrite(): void
    {
        $vm = $this->viewTopicVm(['class' => 5], ['minclasswrite' => 5]);

        $this->assertTrue($vm->mayPost);
    }

    public function test_viewtopic_maypost_true_when_minwrite_key_null(): void
    {
        $vm = $this->viewTopicVm(['class' => 0], ['minclasswrite' => null]);

        $this->assertTrue($vm->mayPost);
        $this->assertNotNull($vm->quickReply);
        $this->assertNull($vm->deniedNotice);
    }

    public function test_viewtopic_maypost_true_for_mod_despite_locked(): void
    {
        $mod = User::factory()->create(['class' => 15]);
        DB::table('forummods')->insert(['forumid' => 1, 'userid' => $mod->id]);

        $vm = $this->viewTopicVm(['id' => $mod->id, 'class' => 15], ['minclasswrite' => 20], ['locked' => true]);

        $this->assertTrue($vm->mayPost);
    }

    public function test_viewtopic_hides_signature_when_pref_disabled(): void
    {
        $vm = $this->viewTopicVm(['signatures' => 'no'], [], [], ['signature' => 'my signature']);

        $this->assertNull($vm->posts[0]->signature);
    }

    public function test_viewtopic_uses_default_avatar_when_pref_disabled(): void
    {
        $vm = $this->viewTopicVm(['avatars' => 'no'], [], [], ['avatar' => 'pic/custom.png']);

        $this->assertStringContainsString('default_avatar', (string) $vm->posts[0]->avatarImage);
    }

    public function test_viewtopic_post_author_toggle_links_this_author_only(): void
    {
        $vm = $this->viewTopicVm();

        $this->assertSame(__('forums.text_view_this_author_only'), $vm->posts[0]->authorToggleLabel);
        $this->assertStringContainsString('topicid=1&authorid=', $vm->posts[0]->authorToggleUrl);
    }

    public function test_viewtopic_post_author_toggle_links_all_posts_when_filtered(): void
    {
        $vm = $this->viewTopicVm([], [], [], [], ['topicid' => 1, 'authorid' => 42]);

        $this->assertSame(__('forums.text_view_all_posts'), $vm->posts[0]->authorToggleLabel);
        $this->assertSame('?action=viewtopic&topicid=1', $vm->posts[0]->authorToggleUrl);
    }

    public function test_viewtopic_cache_key_and_ttl_contract(): void
    {
        $repo = $this->mockForumRepo();
        $curUser = ['id' => 999, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes',
            'clicktopic' => 0, 'avatars' => 'yes', 'signatures' => 'yes', 'last_catchup' => 0];
        $userId = $this->setUser($curUser);
        $this->setRequest(['topicid' => 1]);
        $this->createDbUser($userId);

        $puts = [];
        $getManys = [];
        $forgets = [];
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get')->andReturn(false);
        $cache->shouldReceive('getMany')->andReturnUsing(function (array $keys) use (&$getManys) {
            $getManys[] = $keys;

            return [];
        });
        $cache->shouldReceive('put')->andReturnUsing(function (string $key, $value, int $ttl) use (&$puts) {
            $puts[] = [$key, $value, $ttl];

            return true;
        });
        $cache->shouldReceive('forget')->andReturnUsing(function (string $key) use (&$forgets) {
            $forgets[] = $key;

            return true;
        });
        $this->rebuildService(null, $cache);

        $topic = new Topic;
        $topic->id = 1;
        $topic->setAttribute('userid', $userId);
        $topic->subject = 'Test Topic';
        $topic->locked = false;
        $topic->forumid = 1;
        $topic->sticky = false;
        $topic->hlcolor = 0;
        $topic->views = 5;

        $makePost = static function (int $id, int $authorId, string $body): Post {
            $post = new Post;
            $post->id = $id;
            $post->topicid = 1;
            $post->setAttribute('userid', $authorId);
            $post->added = Carbon::parse('2024-01-01 12:00:00');
            $post->body = $body;
            $post->editedby = 0;

            return $post;
        };
        $posts = new EloquentCollection([
            $makePost(1, $userId, 'same body'),
            $makePost(2, $userId, 'same body'),
            $makePost(3, 888, 'different body'),
        ]);

        $makeUser = static function (int $id): User {
            $user = new User;
            $user->id = $id;
            $user->username = 'user'.$id;
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

            return $user;
        };

        $this->topicRepo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $this->topicRepo->shouldReceive('incrementTopicViews')->with(1)->andReturn(true);
        $this->postRepo->shouldReceive('countTopicPosts')->with(1, null)->andReturn(7);
        $this->postRepo->shouldReceive('getTopicPosts')->withAnyArgs()->andReturn($posts);
        $repo->shouldReceive('getUsersByIds')->andReturn(new EloquentCollection([$userId => $makeUser($userId), 888 => $makeUser(888)]));
        $this->postRepo->shouldReceive('countUserPostsBatch')->with([$userId, 888])->andReturn([$userId => 42]);
        $this->readStateRepo->shouldReceive('markPostRead')->andReturn(true);
        $this->readStateRepo->shouldReceive('getLastReadPosts')->andReturn([1 => 0]);
        $this->topicRepo->shouldReceive('getTopicById')->with(1)->andReturn($topic);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
            $curUser,
            $userId,
            Request::create('/forums.php', 'GET', ['topicid' => 1]),
            10,
        ));

        $this->assertInstanceOf(ViewTopicViewModel::class, $result);
        $this->assertSame(42, $result->posts[0]->postCount);
        $this->assertSame(0, $result->posts[2]->postCount);
        $this->assertContains(['topic_1_post_count', 7, 3600], $puts);
        $this->assertContains(['user_'.$userId.'_post_count', 42, 3600], $puts);
        $this->assertContains(['user_888_post_count', 0, 3600], $puts);
        $fmtPuts = array_values(array_filter($puts, fn (array $p): bool => str_starts_with($p[0], 'fmt_post_')));
        $this->assertCount(2, $fmtPuts);
        foreach ($fmtPuts as $p) {
            $this->assertSame(86400, $p[2]);
            $this->assertIsString($p[1]);
        }
        $this->assertSame('fmt_post_'.md5('same body'), $fmtPuts[0][0]);
        $this->assertSame('fmt_post_'.md5('different body'), $fmtPuts[1][0]);
        $this->assertContains(['user_'.$userId.'_post_count', 'user_888_post_count'], $getManys);
        $this->assertContains(['fmt_post_'.md5('same body'), 'fmt_post_'.md5('different body')], $getManys);
        $this->assertContains('user_'.$userId.'_last_read_post_list', $forgets);
    }

    public function test_viewtopic_forgets_user_zero_key_when_curuser_id_missing(): void
    {
        $repo = $this->mockForumRepo();
        $curUser = ['username' => 'noid', 'class' => 10, 'forumpost' => 'yes',
            'clicktopic' => 0, 'avatars' => 'yes', 'signatures' => 'yes', 'last_catchup' => 0];
        $userId = $this->setUser($curUser + ['id' => 999]);
        $this->setRequest(['topicid' => 1]);
        $this->createDbUser($userId);

        $forgets = [];
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get')->andReturn(false);
        $cache->shouldReceive('getMany')->andReturn([]);
        $cache->shouldReceive('put')->andReturn(true);
        $cache->shouldReceive('forget')->andReturnUsing(function (string $key) use (&$forgets) {
            $forgets[] = $key;

            return true;
        });
        $this->rebuildService(null, $cache);

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

        $this->topicRepo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $this->topicRepo->shouldReceive('incrementTopicViews')->with(1)->andReturn(true);
        $this->postRepo->shouldReceive('countTopicPosts')->with(1, null)->andReturn(1);
        $this->postRepo->shouldReceive('getTopicPosts')->withAnyArgs()->andReturn(new EloquentCollection([$post]));
        $repo->shouldReceive('getUsersByIds')->andReturn(new EloquentCollection([$userId => $user]));
        $this->postRepo->shouldReceive('countUserPostsBatch')->with([$userId])->andReturn([$userId => 1]);
        $this->readStateRepo->shouldReceive('markPostRead')->andReturn(true);
        $this->readStateRepo->shouldReceive('getLastReadPosts')->andReturn([1 => 0]);
        $this->topicRepo->shouldReceive('getTopicById')->with(1)->andReturn($topic);

        $this->callWithSuppressedErrors(fn () => $this->service->buildViewTopic(
            $curUser,
            $userId,
            Request::create('/forums.php', 'GET', ['topicid' => 1]),
            10,
        ));

        $this->assertContains('user_0_last_read_post_list', $forgets);
    }

    public function test_viewtopic_runs_with_null_cache(): void
    {
        $repo = $this->mockForumRepo();
        $curUser = ['id' => 999, 'username' => 'test', 'class' => 10, 'forumpost' => 'yes',
            'clicktopic' => 0, 'avatars' => 'yes', 'signatures' => 'yes', 'last_catchup' => 0];
        $userId = $this->setUser($curUser);
        $this->setRequest(['topicid' => 1]);
        $this->createDbUser($userId);

        $indexService = new ForumIndexService(
            $this->app->make(CurrentUser::class),
            $repo,
            new OverforumRepository,
            $this->app->make(NexusCache::class),
            $this->app->make(TopicRepository::class),
            $this->app->make(TopicReadStateRepository::class),
            $this->app->make(PostRepository::class),
        );
        $service = new ForumTopicViewService(
            $indexService,
            $repo,
            null,
            $this->app->make(TopicRepository::class),
            $this->app->make(TopicReadStateRepository::class),
            $this->app->make(PostRepository::class),
        );

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

        $this->topicRepo->shouldReceive('getTopic')->with(1)->andReturn($topic);
        $repo->shouldReceive('getForumsList')->andReturn([
            1 => ['id' => 1, 'name' => 'Test Forum', 'minclassread' => 0, 'minclasswrite' => 0, 'minclasscreate' => 0],
        ]);
        $this->topicRepo->shouldReceive('incrementTopicViews')->with(1)->andReturn(true);
        $this->postRepo->shouldReceive('countTopicPosts')->with(1, null)->andReturn(1);
        $this->postRepo->shouldReceive('getTopicPosts')->withAnyArgs()->andReturn(new EloquentCollection([$post]));
        $repo->shouldReceive('getUsersByIds')->andReturn(new EloquentCollection);
        $this->readStateRepo->shouldReceive('markPostRead')->andReturn(true);
        $this->readStateRepo->shouldReceive('getLastReadPosts')->andReturn(null);
        $this->topicRepo->shouldReceive('getTopicById')->with(1)->andReturn($topic);

        $result = $this->callWithSuppressedErrors(fn () => $service->buildViewTopic(
            $curUser,
            $userId,
            Request::create('/forums.php', 'GET', ['topicid' => 1]),
            10,
        ));

        $this->assertInstanceOf(ViewTopicViewModel::class, $result);
        $this->assertCount(1, $result->posts);
        $this->assertStringContainsString('Hello world', (string) $result->posts[0]->body);
    }
}
