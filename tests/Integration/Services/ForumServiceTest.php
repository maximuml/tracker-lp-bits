<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Topic;
use App\Models\User;
use App\Policies\PostPolicy;
use App\Policies\TopicPolicy;
use App\Repositories\ForumRepository;
use App\Repositories\MessageRepository;
use App\Repositories\PostLookupRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicRepository;
use App\Services\ForumDataRepositories;
use App\Services\ForumModerationService;
use App\Services\ForumService;
use App\Support\Cache\NexusCache;
use App\Support\CurrentUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Unit tests for ForumService.
 *
 * Covers the legacy() action router, permission-denied paths for all
 * mutation handlers, and validation/redirect behaviour for handlePost,
 * handleMoveTopic, handleDeleteTopic, handleDeletePost, handleSetLocked,
 * handleHighlightTopic, and handleSetSticky.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumServiceTest extends TestCase
{
    use SeedsLegacySettings;

    private int $initialObLevel;

    /** @var TopicRepository&Mockery\MockInterface */
    private TopicRepository $topicRepo;

    /** @var PostRepository&Mockery\MockInterface */
    private PostRepository $postRepo;

    /** @var PostLookupRepository&Mockery\MockInterface */
    private PostLookupRepository $postLookupRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialObLevel = ob_get_level();
    }

    protected function tearDown(): void
    {
        // Clean up any output buffers left open by PageResponses::abort($die=false)
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }

        Mockery::close();
        parent::tearDown();
    }

    /**
     * Call the service while suppressing E_NOTICE/E_WARNING from the
     * legacy rendering system (PageRenderer, Html::stdhead) that is
     * triggered by PageResponses::abort()/permissionDenied().
     */
    private function callService(Request $request): mixed
    {
        set_error_handler(function (int $severity): bool {
            return true;
        }, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);

        try {
            return $this->service()->legacy($request);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Assert that calling the service with $request triggers an abort/guard.
     *
     * PageResponses::abort()/permissionDenied() throws HttpResponseException,
     * but the legacy rendering (Html::stdhead → PageRenderer) may also throw
     * TypeError or ErrorException when language/user data is incomplete in
     * the test environment. Any Throwable from the guard path indicates the
     * abort was triggered — which is what we're verifying.
     */
    private function assertServiceThrows(Request $request): void
    {
        $threw = false;
        try {
            $this->callService($request);
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected exception was not thrown');
    }

    /**
     * Like assertServiceThrows but pins the rendered abort body —
     * distinguishes a removed guard abort from the downstream aborts that
     * would otherwise mask it.
     */
    private function assertAbortContent(Request $request, string ...$needles): void
    {
        try {
            $this->callService($request);
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

    /** @return ForumRepository&Mockery\MockInterface */
    private function mockForumRepo(): mixed
    {
        /** @var ForumRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(ForumRepository::class);
        $repo->shouldIgnoreMissing(false);
        $repo->shouldReceive('getTopicIdByPost')->andReturn(null);
        $repo->shouldReceive('isModeratorOfTopic')->andReturn(false);
        $repo->shouldReceive('isModeratorOfForum')->andReturn(false);
        $this->app->instance(ForumRepository::class, $repo);

        /** @var TopicRepository&Mockery\MockInterface $topicRepo */
        $topicRepo = Mockery::mock(TopicRepository::class);
        $topicRepo->shouldIgnoreMissing();
        $this->app->instance(TopicRepository::class, $topicRepo);
        $this->topicRepo = $topicRepo;

        /** @var PostRepository&Mockery\MockInterface $postRepo */
        $postRepo = Mockery::mock(PostRepository::class);
        $postRepo->shouldIgnoreMissing();
        $this->app->instance(PostRepository::class, $postRepo);
        $this->postRepo = $postRepo;

        /** @var PostLookupRepository&Mockery\MockInterface $postLookupRepo */
        $postLookupRepo = Mockery::mock(PostLookupRepository::class);
        $postLookupRepo->shouldIgnoreMissing();
        $this->app->instance(PostLookupRepository::class, $postLookupRepo);
        $this->postLookupRepo = $postLookupRepo;

        return $repo;
    }

    private function service(): ForumService
    {
        return new ForumService(
            $this->app->make(ForumDataRepositories::class),
            $this->app->make(CurrentUser::class),
            $this->app->make(NexusCache::class),
            $this->app->make(TopicPolicy::class),
            $this->app->make(PostPolicy::class),
            $this->app->make(ForumModerationService::class),
            $this->app->make(MessageRepository::class),
        );
    }

    private function unauthenticatedUser(): void
    {
        $currentUser = new CurrentUser;
        $currentUser->set([]);
        $this->app->instance(CurrentUser::class, $currentUser);
    }

    /**
     * @param  array<string, mixed>  $userData
     */
    private function authenticatedUser(array $userData = []): void
    {
        $defaults = [
            'id' => 1,
            'username' => 'testuser',
            'class' => 'user',
            'forumpost' => true,
            'last_post' => '1970-01-01 00:00:00',
        ];
        $data = array_merge($defaults, $userData);

        $currentUser = new CurrentUser;
        $currentUser->set($data);
        $this->app->instance(CurrentUser::class, $currentUser);
    }

    /**
     * Authenticate via Laravel's Auth guard so UserDisplay::currentClass()
     * returns a valid class (needed when the legacy runtime flag is false,
     * which is the case in the test bootstrap).
     *
     * @param  array<string, mixed>  $userData
     */
    private function actingAsUser(array $userData = []): void
    {
        $this->authenticatedUser($userData);

        $user = new User;
        $user->id = $userData['id'] ?? 1;
        $user->class = $userData['class'] ?? 'user';
        $user->username = $userData['username'] ?? 'testuser';
        auth()->login($user);
    }

    /** @param  array<string, mixed>  $values */
    private function seedSettings(array $values = []): void
    {
        $this->seedTestSettings($values);
    }

    private function mockCache(): void
    {
        $cache = Mockery::mock(NexusCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get')->andReturn(false);
        $cache->shouldReceive('forget')->andReturn(true);
        $this->app->instance(NexusCache::class, $cache);
    }

    // ─── legacy() action router ───────────────────────────────────────

    public function test_legacy_returns_empty_array_for_unknown_action(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', ['action' => 'unknown']);

        $this->assertSame([], $this->callService($request));
    }

    public function test_legacy_returns_empty_array_for_empty_action(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'GET');

        $this->assertSame([], $this->callService($request));
    }

    public function test_legacy_returns_empty_array_for_read_only_action(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'GET', ['action' => 'viewforum']);

        $this->assertSame([], $this->callService($request));
    }

    public function test_legacy_routes_post_action(): void
    {
        $repo = $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $repo->shouldReceive('forumExists')->with(1)->andReturn(false);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'new',
            'id' => 1,
            'subject' => 'Test',
            'body' => 'Body',
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_legacy_routes_movetopic_action(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'movetopic',
            'forumid' => 2,
            'topicid' => 1,
        ]);

        $this->assertAbortContent($request, (string) __('forums.std_topic_not_found'));
    }

    public function test_legacy_routes_deletetopic_action(): void
    {
        $repo = $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        // W1-04: handleDeleteTopic now uses Topic model instead of repo
        $topic = Topic::factory()->create();
        $this->topicRepo->shouldReceive('getTopic')->andReturn($topic);
        $this->postRepo->shouldReceive('countTopicPosts')->with($topic->id)->andReturn(0);

        $request = Request::create('/forums.php', 'GET', [
            'action' => 'deletetopic',
            'topicid' => (string) $topic->id,
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_legacy_routes_deletepost_action(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'GET', [
            'action' => 'deletepost',
            'postid' => 1,
        ]);

        $this->assertAbortContent($request, (string) __('forums.std_post_not_found'));
    }

    public function test_moderation_entrypoints_are_public(): void
    {
        foreach (['moveTopic', 'deletePost', 'deleteTopic', 'setLocked', 'highlightTopic', 'setSticky'] as $method) {
            $this->assertTrue((new \ReflectionMethod(ForumModerationService::class, $method))->isPublic(), "$method must stay public");
        }
    }

    public function test_legacy_routes_setlocked_action(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'setlocked',
            'topicid' => 1,
            'locked' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_legacy_routes_hltopic_action(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php?action=hltopic&topicid=1', 'POST', [
            'color' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_legacy_routes_setsticky_action(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'setsticky',
            'topicid' => 1,
            'sticky' => 'yes',
        ]);

        $this->assertServiceThrows($request);
    }

    // ─── handlePost: validation paths (die=true → HttpResponseException) ──

    public function test_handle_post_new_topic_aborts_when_forum_not_found(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $repo->shouldReceive('forumExists')->with(999)->andReturn(false);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'new',
            'id' => 999,
            'subject' => 'Test subject',
            'body' => 'Test body',
        ]);

        $this->assertAbortContent($request, (string) __('forums.std_no_forum_id'));
    }

    public function test_handle_post_reply_aborts_when_topic_not_found(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $repo->shouldReceive('topicExists')->with(999)->andReturn(null);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'reply',
            'id' => 999,
            'body' => 'Test body',
        ]);

        $this->assertAbortContent($request, (string) __('forums.std_bad_topic_id'));
    }

    public function test_handle_post_edit_redirects_when_post_not_found(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $this->postLookupRepo->shouldReceive('getPostEditInfo')->with(999)->andReturn(null);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'edit',
            'id' => 999,
            'body' => 'Test body',
        ]);

        $result = $this->callService($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/forums', $result->getTargetUrl());
    }

    public function test_handle_post_aborts_when_subject_empty_for_new_topic(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $repo->shouldReceive('forumExists')->with(1)->andReturn(true);
        $repo->shouldReceive('getForumRow')->with(1)->andReturn([
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'new',
            'id' => 1,
            'subject' => '',
            'body' => 'Test body',
        ]);

        $this->assertAbortContent($request, (string) __('forums.std_must_enter_subject'));
    }

    public function test_handle_post_aborts_when_subject_too_long(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $repo->shouldReceive('forumExists')->with(1)->andReturn(true);
        $repo->shouldReceive('getForumRow')->with(1)->andReturn([
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'new',
            'id' => 1,
            'subject' => str_repeat('x', 120),
            'body' => 'Test body',
        ]);

        $this->assertAbortContent($request, (string) __('forums.std_subject_limited'));
    }

    public function test_handle_post_aborts_when_body_empty(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $repo->shouldReceive('forumExists')->with(1)->andReturn(true);
        $repo->shouldReceive('getForumRow')->with(1)->andReturn([
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'new',
            'id' => 1,
            'subject' => 'Valid subject',
            'body' => '',
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_handle_post_aborts_when_forumpost_disabled(): void
    {
        $this->mockForumRepo();
        $this->authenticatedUser(['forumpost' => false]);
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'new',
            'id' => 1,
            'subject' => 'Valid subject',
            'body' => 'Test body',
        ]);

        $this->assertAbortContent($request, (string) __('forums.std_sorry'));
    }

    public function test_handle_post_proceeds_when_forumpost_flag_absent(): void
    {
        $this->mockForumRepo();
        // null coalesces through `?? true` — the flag defaults to allowed
        $this->authenticatedUser(['forumpost' => null]);
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'unknown-kind',
            'id' => 1,
            'body' => 'Test body',
        ]);

        $this->assertInstanceOf(RedirectResponse::class, $this->callService($request));
    }

    public function test_handle_post_unknown_type_redirects_to_forums(): void
    {
        $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'invalid_type',
            'id' => 1,
            'subject' => 'Test',
            'body' => 'Test body',
        ]);

        $result = $this->callService($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/forums', $result->getTargetUrl());
    }

    public function test_handle_post_redirects_when_forum_row_not_found(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $repo->shouldReceive('forumExists')->with(1)->andReturn(true);
        $repo->shouldReceive('getForumRow')->with(1)->andReturn(null);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'new',
            'id' => 1,
            'subject' => 'Test subject',
            'body' => 'Test body',
        ]);

        $result = $this->callService($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/forums', $result->getTargetUrl());
    }

    public function test_handle_post_permission_denied_when_class_too_low(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser(['class' => 'user']);
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $repo->shouldReceive('forumExists')->with(1)->andReturn(true);
        $repo->shouldReceive('getForumRow')->with(1)->andReturn([
            'minclassread' => 50,
            'minclasswrite' => 50,
            'minclasscreate' => 50,
        ]);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'new',
            'id' => 1,
            'subject' => 'Test subject',
            'body' => 'Test body',
        ]);

        $this->assertServiceThrows($request);
    }

    // ─── handlePost: die=false paths (echo + continue) ────────────────

    public function test_handle_post_outputs_error_when_user_cannot_post(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser(['forumpost' => false]);
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        // abort() renders the error page (header + message + footer) and
        // throws HttpResponseException with the rendered HTML.
        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'new',
            'id' => 0,
            'subject' => 'Test',
            'body' => 'Test body',
        ]);

        $output = '';
        ob_start();
        try {
            $this->callService($request);
        } catch (HttpResponseException $e) {
            $output = $e->getResponse()->getContent();
        } catch (\Throwable) {
            // Other exceptions may occur in the test environment
        } finally {
            ob_end_clean();
        }

        // htmlstrip=false keeps the '(<a ...' inbox link raw — flipping it
        // escapes the tag to &lt;a entities. The bare href alone appears in
        // the page chrome, so the paren makes the needle body-specific.
        $this->assertStringContainsString('(<a href="/web/messages">', $output);
    }

    public function test_handle_post_reply_outputs_error_when_topic_locked(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $repo->shouldReceive('getForumRow')->with(1)->andReturn([
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);
        $this->topicRepo->shouldReceive('topicExists')->with(1)->andReturn(1);
        // W1-04: locked-topic check now uses Topic model + TopicPolicy
        DB::table('topics')->updateOrInsert(['id' => 1], Topic::factory()->raw(['locked' => true]));
        // Code continues after abort(die=false) to flood check and post creation
        $repo->shouldReceive('incrementForumPostCount')->with(1)->andReturn(true);
        $this->postRepo->shouldReceive('createPost')->andReturn(1);
        $this->topicRepo->shouldReceive('getTopicWithUser')->with(1)->andReturn(null);
        $this->topicRepo->shouldReceive('setTopicLastPost')->with(1, 1)->andReturn(true);
        $this->postRepo->shouldReceive('updateUserLastPost')->with(1, Mockery::any())->andReturn(true);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'reply',
            'id' => 1,
            'body' => 'Test body',
        ]);

        $output = '';
        ob_start();
        try {
            $this->callService($request);
        } catch (HttpResponseException $e) {
            $output = $e->getResponse()->getContent();
        } catch (\Throwable) {
            // May hit a later abort
        } finally {
            ob_end_clean();
        }

        $this->assertNotEmpty($output, 'Expected error output from locked topic abort()');
    }

    public function test_handle_post_reply_redirects_when_topic_locked_returns_null(): void
    {
        $repo = $this->mockForumRepo();
        $this->actingAsUser();
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $this->topicRepo->shouldReceive('topicExists')->with(1)->andReturn(1);
        $repo->shouldReceive('getForumRow')->with(1)->andReturn([
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);
        // W1-04: locked-topic check now uses Topic model; ensure topic is not locked
        DB::table('topics')->updateOrInsert(['id' => 1], Topic::factory()->raw(['locked' => false]));

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'reply',
            'id' => 1,
            'body' => 'Test body',
        ]);

        $result = $this->callService($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/forums', $result->getTargetUrl());
    }

    public function test_handle_post_flood_check_outputs_error_when_posting_too_fast(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser([
            'last_post' => date('Y-m-d H:i:s', time() - 3),
        ]);
        $this->seedSettings(['maxsubjectlength' => 100]);
        $this->mockCache();

        $this->topicRepo->shouldReceive('topicExists')->with(1)->andReturn(1);
        $repo->shouldReceive('getForumRow')->with(1)->andReturn([
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);
        // W1-04: locked-topic check now uses Topic model + TopicPolicy
        DB::table('topics')->updateOrInsert(['id' => 1], Topic::factory()->raw(['locked' => false]));
        $repo->shouldReceive('incrementForumPostCount')->with(1)->andReturn(true);
        $this->postRepo->shouldReceive('createPost')->andReturn(1);
        $this->topicRepo->shouldReceive('getTopicWithUser')->with(1)->andReturn(null);
        $this->topicRepo->shouldReceive('setTopicLastPost')->with(1, 1)->andReturn(true);
        $this->postRepo->shouldReceive('updateUserLastPost')->with(1, Mockery::any())->andReturn(true);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'reply',
            'id' => 1,
            'body' => 'Test body',
        ]);

        $output = '';
        ob_start();
        try {
            $this->callService($request);
        } catch (HttpResponseException $e) {
            $output = $e->getResponse()->getContent();
        } catch (\Throwable) {
            // May hit a later abort
        } finally {
            ob_end_clean();
        }

        $this->assertNotEmpty($output, 'Expected flood error output from abort()');
    }

    // ─── handlePost: creation flow ────────────────────────────────────

    public function test_handle_post_new_topic_aborts_when_create_topic_fails(): void
    {
        $repo = $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings([
            'maxsubjectlength' => 100,
            'starttopic_bonus' => 0,
        ]);
        $this->mockCache();

        $repo->shouldReceive('forumExists')->with(1)->andReturn(true);
        $repo->shouldReceive('getForumRow')->with(1)->andReturn([
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);
        $this->topicRepo->shouldReceive('createTopic')->with(1, 1, 'Test subject')->andReturn(0);
        $repo->shouldReceive('incrementForumTopicCount')->with(1)->andReturn(true);
        $repo->shouldReceive('incrementForumPostCount')->with(1)->andReturn(true);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'new',
            'id' => 1,
            'subject' => 'Test subject',
            'body' => 'Test body',
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_handle_post_reply_redirects_when_create_post_fails(): void
    {
        $repo = $this->mockForumRepo();
        $this->actingAsUser();
        $this->seedSettings([
            'maxsubjectlength' => 100,
            'makepost_bonus' => 0,
        ]);
        $this->mockCache();

        $this->topicRepo->shouldReceive('topicExists')->with(1)->andReturn(1);
        $repo->shouldReceive('getForumRow')->with(1)->andReturn([
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
        ]);
        // W1-04: locked-topic check now uses Topic model + TopicPolicy
        DB::table('topics')->updateOrInsert(['id' => 1], Topic::factory()->raw(['locked' => false]));
        $repo->shouldReceive('incrementForumPostCount')->with(1)->andReturn(true);
        $this->postRepo->shouldReceive('createPost')->with(1, 1, Mockery::any(), Mockery::any())->andReturn(0);

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'post',
            'type' => 'reply',
            'id' => 1,
            'body' => 'Test body',
        ]);

        $result = $this->callService($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/forums', $result->getTargetUrl());
    }

    // ─── handleDeleteTopic ────────────────────────────────────────────

    public function test_delete_topic_redirects_when_topic_not_found(): void
    {
        $repo = $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $repo->shouldReceive('getTopicForumAndUser')->with(999)->andReturn(null);

        $request = Request::create('/forums.php', 'GET', [
            'action' => 'deletetopic',
            'topicid' => 999,
        ]);

        $result = $this->callService($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/forums', $result->getTargetUrl());
    }

    public function test_delete_topic_permission_denied_for_unauthenticated(): void
    {
        $repo = $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        // W1-04: handleDeleteTopic now uses Topic model instead of repo
        $topic = Topic::factory()->create();
        $this->topicRepo->shouldReceive('getTopic')->andReturn($topic);
        $this->postRepo->shouldReceive('countTopicPosts')->with($topic->id)->andReturn(0);

        $request = Request::create('/forums.php', 'GET', [
            'action' => 'deletetopic',
            'topicid' => (string) $topic->id,
        ]);

        // 'Permission denied!' distinguishes the policy-deny abort from the
        // downstream sure-check confirm abort that a removed deny falls into.
        $this->assertAbortContent($request, 'Permission denied!');
    }

    // ─── handleDeletePost ─────────────────────────────────────────────

    public function test_delete_post_permission_denied_for_unauthenticated(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'GET', [
            'action' => 'deletepost',
            'postid' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    // ─── handleMoveTopic ──────────────────────────────────────────────

    public function test_move_topic_permission_denied_for_unauthenticated(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'movetopic',
            'forumid' => 2,
            'topicid' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_move_topic_permission_denied_with_invalid_ids(): void
    {
        $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'movetopic',
            'forumid' => 0,
            'topicid' => 0,
        ]);

        $this->assertServiceThrows($request);
    }

    // ─── handleSetLocked ──────────────────────────────────────────────

    public function test_set_locked_permission_denied_for_unauthenticated(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'setlocked',
            'topicid' => 1,
            'locked' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_set_locked_permission_denied_with_zero_topicid(): void
    {
        $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'setlocked',
            'topicid' => 0,
            'locked' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_set_locked_aborts_when_topic_missing(): void
    {
        $this->mockForumRepo();
        // actingAsUser logs a real User into Auth — removing the null-topic
        // guard falls into TopicPolicy::lock($user, null) → TypeError, not
        // the HttpResponseException the permission-denied path produces.
        $this->actingAsUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'setlocked',
            'topicid' => 1,
            'locked' => 1,
        ]);

        $this->assertAbortContent($request, 'Permission denied!');
    }

    // ─── handleSetSticky ──────────────────────────────────────────────

    public function test_set_sticky_permission_denied_for_unauthenticated(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'setsticky',
            'topicid' => 1,
            'sticky' => 'yes',
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_set_sticky_permission_denied_with_zero_topicid(): void
    {
        $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'setsticky',
            'topicid' => 0,
            'sticky' => 'yes',
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_set_sticky_aborts_when_topic_missing(): void
    {
        $this->mockForumRepo();
        // Same null-topic guard pin as setLocked — authenticated user so the
        // removed guard falls into TopicPolicy::sticky($user, null).
        $this->actingAsUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php', 'POST', [
            'action' => 'setsticky',
            'topicid' => 1,
            'sticky' => 'yes',
        ]);

        $this->assertAbortContent($request, 'Permission denied!');
    }

    // ─── handleHighlightTopic ─────────────────────────────────────────

    public function test_highlight_topic_permission_denied_for_unauthenticated(): void
    {
        $this->mockForumRepo();
        $this->unauthenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php?action=hltopic&topicid=1', 'POST', [
            'color' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_highlight_topic_permission_denied_with_zero_topicid(): void
    {
        $this->mockForumRepo();
        $this->authenticatedUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php?action=hltopic&topicid=0', 'POST', [
            'color' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    public function test_highlight_topic_aborts_when_topic_missing(): void
    {
        $this->mockForumRepo();
        // Same null-topic guard pin — authenticated user so the removed
        // guard falls into TopicPolicy::highlight($user, null).
        $this->actingAsUser();
        $this->seedSettings();
        $this->mockCache();

        $request = Request::create('/forums.php?action=hltopic&topicid=1', 'POST', [
            'color' => 1,
        ]);

        $this->assertAbortContent($request, 'Permission denied!');
    }
}
