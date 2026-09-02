<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\ForumRepository;
use App\Services\ForumComposeService;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for ForumComposeService.
 *
 * Covers buildComposeFrame (new, reply, quote, edit, unknown),
 * checkWhetherExist (forum/topic/post not found, invalid ID),
 * buildNewTopic, and buildReply.
 */
final class ForumComposeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ForumComposeService $service;

    private int $initialObLevel;

    /** @var array<string, string> */
    private const LANG_FUNCTIONS = [
        'js_prompt_enter_url' => 'Enter URL',
        'js_prompt_enter_title' => 'Enter title',
        'js_prompt_error' => 'Error',
        'js_prompt_enter_image_url' => 'Enter image URL',
        'js_prompt_enter_item' => 'Enter item',
        'select_color' => 'Color',
        'select_font' => 'Font',
        'select_size' => 'Size',
        'text_more_smilies' => 'More smilies',
        'submit_preview' => 'Preview',
        'submit_edit' => 'Edit',
        'submit_submit' => 'Submit',
        'text_tags' => 'Tags',
        'text_smilies' => 'Smilies',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        if (! defined('IN_NEXUS')) {
            define('IN_NEXUS', true);
        }
        $this->initialObLevel = ob_get_level();
        app(Globals::class)->set('maxsubjectlength', 100);
        app(Globals::class)->set('lang_functions', self::LANG_FUNCTIONS);
        app(Globals::class)->set('enableattach_attachment', 'yes');
        $this->service = new ForumComposeService;
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
        $this->app->instance(ForumRepository::class, $repo);

        return $repo;
    }

    /** @param  array<string, mixed>  $data */
    private function setUser(array $data = []): void
    {
        $currentUser = new CurrentUser;
        $currentUser->set(array_merge(['id' => 1, 'username' => 'testuser', 'class' => 1], $data));
        $this->app->instance(CurrentUser::class, $currentUser);
    }

    /** @param  array<string, mixed>  $query */
    private function setRequest(array $query = []): void
    {
        $request = Request::create('/forums.php', 'GET', $query);
        $this->app->instance('request', $request);
    }

    /**
     * Call a callable while suppressing E_NOTICE/E_WARNING from the
     * legacy rendering system.
     */
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

    // --- buildComposeFrame: unknown type ---

    public function test_build_compose_frame_unknown_type_returns_empty(): void
    {
        $this->mockForumRepo();
        $this->setUser();

        $result = $this->service->buildComposeFrame(1, 'invalid_type', []);

        $this->assertSame(['title' => '', 'body' => ''], $result);
    }

    // --- buildComposeFrame: quote with post not found ---

    public function test_build_compose_frame_quote_nonexistent_post_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('getPostForQuote')->with(999)->andReturn(null);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service->buildComposeFrame(999, 'quote', ['std_error' => 'Error', 'std_no_post_id' => 'No post']));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when quote post not found');
    }

    // --- buildComposeFrame: edit with post not found ---

    public function test_build_compose_frame_edit_nonexistent_post_returns_empty(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('getPostForEdit')->with(999)->andReturn(null);

        $result = $this->service->buildComposeFrame(999, 'edit', []);

        $this->assertSame(['title' => '', 'body' => ''], $result);
    }

    // --- buildComposeFrame: new topic (golden path) ---

    public function test_build_compose_frame_new_topic_returns_title_and_body(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('getForumName')->with(1)->andReturn('Test Forum');

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildComposeFrame(1, 'new', ['text_new_topic_in' => 'New topic in', 'text_forum' => 'Forum']));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertStringContainsString('Test Forum', $result['title']);
        $this->assertStringContainsString('<form', $result['body']);
    }

    // --- buildComposeFrame: reply (golden path) ---

    public function test_build_compose_frame_reply_returns_title_and_body(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('getTopicSubject')->with(1)->andReturn('Test Topic');

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildComposeFrame(1, 'reply', ['text_reply_to_topic' => 'Reply to']));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertStringContainsString('Test Topic', $result['title']);
        $this->assertStringContainsString('<form', $result['body']);
    }

    // --- buildComposeFrame: quote (golden path) ---

    public function test_build_compose_frame_quote_with_post_returns_title_and_body(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('getPostForQuote')->with(1)->andReturn([
            'topicid' => 5,
            'topic_subject' => 'Quoted Topic',
            'username' => 'poster',
            'body' => 'Quoted text',
        ]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildComposeFrame(1, 'quote', ['text_reply_to_topic' => 'Reply to']));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertStringContainsString('Quoted Topic', $result['title']);
        $this->assertStringContainsString('[quote=', $result['body']);
    }

    // --- buildComposeFrame: edit (golden path) ---

    public function test_build_compose_frame_edit_with_post_returns_title_and_body(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('getPostForEdit')->with(1)->andReturn([
            'topicid' => 5,
            'topic_subject' => 'Edit Topic',
            'body' => 'Edit text',
            'is_first_post' => true,
        ]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildComposeFrame(1, 'edit', ['text_edit_post' => 'Edit Post']));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertStringContainsString('Edit Post', $result['title']);
    }

    // --- checkWhetherExist ---

    public function test_check_whether_exist_forum_not_found_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('forumExists')->with(999)->andReturn(false);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service->checkWhetherExist(999, 'forum', ['std_error' => 'Error', 'std_no_forum_id' => 'No forum']));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when forum not found');
    }

    public function test_check_whether_exist_topic_not_found_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('topicExists')->with(999)->andReturn(null);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service->checkWhetherExist(999, 'topic', ['std_error' => 'Error', 'std_bad_topic_id' => 'Bad topic']));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when topic not found');
    }

    public function test_check_whether_exist_post_not_found_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('postExists')->with(999)->andReturn(null);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service->checkWhetherExist(999, 'post', ['std_error' => 'Error', 'std_no_post_id' => 'No post']));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when post not found');
    }

    public function test_check_whether_exist_invalid_id_aborts(): void
    {
        $this->mockForumRepo();
        $this->setUser();

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service->checkWhetherExist(0, 'forum', ['std_error' => 'Error']));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when ID is invalid (0)');
    }

    public function test_check_whether_exist_forum_found_does_not_abort(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('forumExists')->with(1)->andReturn(true);

        $this->callWithSuppressedErrors(fn () => $this->service->checkWhetherExist(1, 'forum', []));

        $this->expectNotToPerformAssertions();
    }

    // --- buildNewTopic ---

    public function test_build_new_topic_with_valid_forumid_returns_compose_frame(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();
        $this->setRequest(['forumid' => 1]);

        $repo->shouldReceive('forumExists')->with(1)->andReturn(true);
        $repo->shouldReceive('getForumName')->with(1)->andReturn('Test Forum');

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildNewTopic(['text_new_topic_in' => 'New topic in', 'text_forum' => 'Forum'], Request::create('/forums.php', 'GET', ['forumid' => 1])));

        $this->assertIsArray($result);
        $this->assertStringContainsString('Test Forum', $result['title']);
    }

    // --- buildReply ---

    public function test_build_reply_with_valid_topicid_returns_compose_frame(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();
        $this->setRequest(['topicid' => 1]);

        $repo->shouldReceive('topicExists')->with(1)->andReturn(1);
        $repo->shouldReceive('forumExists')->with(1)->andReturn(true);
        $repo->shouldReceive('getTopicSubject')->with(1)->andReturn('Test Topic');

        $result = $this->callWithSuppressedErrors(fn () => $this->service->buildReply(['text_reply_to_topic' => 'Reply to'], Request::create('/forums.php', 'GET', ['topicid' => 1])));

        $this->assertIsArray($result);
        $this->assertStringContainsString('Test Topic', $result['title']);
    }
}
