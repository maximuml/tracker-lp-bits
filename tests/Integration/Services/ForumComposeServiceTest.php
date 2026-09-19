<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Repositories\PostLookupRepository;
use App\Repositories\TopicRepository;
use App\Services\ForumComposeService;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\ViewModels\Forum\ForumComposeViewModel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for ForumComposeService.
 *
 * Covers buildComposeFrame (new, reply, quote, edit, unknown),
 * checkWhetherExist (forum/topic/post not found, invalid ID),
 * buildNewTopic, and buildReply.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
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
        $this->initialObLevel = ob_get_level();
        app(Globals::class)->set('maxsubjectlength', 100);
        app(Globals::class)->set('lang_functions', self::LANG_FUNCTIONS);
        app(Globals::class)->set('enableattach_attachment', 'yes');
    }

    /**
     * Resolve a fresh `ForumComposeService` from the container so that any
     * mocks bound in the test are injected.
     */
    private function service(): ForumComposeService
    {
        return $this->app->make(ForumComposeService::class);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }
        Mockery::close();
        parent::tearDown();
    }

    /** @return ForumRepositoryInterface&MockInterface */
    private function mockForumRepo(): mixed
    {
        /** @var ForumRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(ForumRepositoryInterface::class);
        $repo->shouldIgnoreMissing(false);
        $this->app->instance(ForumRepositoryInterface::class, $repo);

        $topicRepo = Mockery::mock(TopicRepository::class);
        $topicRepo->shouldIgnoreMissing(false);
        $this->app->instance(TopicRepository::class, $topicRepo);

        $postRepo = Mockery::mock(PostLookupRepository::class);
        $postRepo->shouldIgnoreMissing(false);
        $this->app->instance(PostLookupRepository::class, $postRepo);

        return $repo;
    }

    /** @return TopicRepository&MockInterface */
    private function mockTopicRepo(): mixed
    {
        /** @var TopicRepository&MockInterface $repo */
        $repo = $this->app->make(TopicRepository::class);

        return $repo;
    }

    /** @return PostLookupRepository&MockInterface */
    private function mockPostRepo(): mixed
    {
        /** @var PostLookupRepository&MockInterface $repo */
        $repo = $this->app->make(PostLookupRepository::class);

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

        $result = $this->service()->buildComposeFrame(1, 'invalid_type');

        $this->assertNull($result);
    }

    // --- buildComposeFrame: quote with post not found ---

    public function test_build_compose_frame_quote_nonexistent_post_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $this->mockPostRepo()->shouldReceive('getPostForQuote')->with(999)->andReturn(null);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service()->buildComposeFrame(999, 'quote'));
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

        $this->mockPostRepo()->shouldReceive('getPostForEdit')->with(999)->andReturn(null);

        $result = $this->service()->buildComposeFrame(999, 'edit');

        $this->assertNull($result);
    }

    // --- buildComposeFrame: new topic (golden path) ---

    public function test_build_compose_frame_new_topic_returns_title_and_body(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('getForumName')->with(1)->andReturn('Test Forum');

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->buildComposeFrame(1, 'new'));

        $this->assertInstanceOf(ForumComposeViewModel::class, $result);
        $this->assertStringContainsString('Test Forum', (string) $result->titleHtml);
        $this->assertTrue($result->hasSubject);
        $this->assertSame('new', $result->hiddenType);
        $this->assertSame(1, $result->hiddenId);
        $this->assertNull($result->postid);
    }

    // --- buildComposeFrame: reply (golden path) ---

    public function test_build_compose_frame_reply_returns_title_and_body(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $this->mockTopicRepo()->shouldReceive('getTopicSubject')->with(1)->andReturn('Test Topic');

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->buildComposeFrame(1, 'reply'));

        $this->assertInstanceOf(ForumComposeViewModel::class, $result);
        $this->assertStringContainsString('Test Topic', (string) $result->titleHtml);
        $this->assertFalse($result->hasSubject);
        $this->assertSame('reply', $result->hiddenType);
    }

    // --- buildComposeFrame: quote (golden path) ---

    public function test_build_compose_frame_quote_with_post_returns_title_and_body(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $this->mockPostRepo()->shouldReceive('getPostForQuote')->with(1)->andReturn([
            'topicid' => 5,
            'topic_subject' => 'Quoted Topic',
            'username' => 'poster',
            'body' => 'Quoted text',
        ]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->buildComposeFrame(1, 'quote'));

        $this->assertInstanceOf(ForumComposeViewModel::class, $result);
        $this->assertStringContainsString('Quoted Topic', (string) $result->titleHtml);
        $this->assertStringContainsString('[quote=poster]', $result->body);
        $this->assertStringContainsString('Quoted text', $result->body);
        $this->assertSame(1, $result->postid);
        $this->assertSame(5, $result->hiddenId);
        $this->assertSame('reply', $result->hiddenType);
    }

    // --- buildComposeFrame: edit (golden path) ---

    public function test_build_compose_frame_edit_with_post_returns_title_and_body(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $this->mockPostRepo()->shouldReceive('getPostForEdit')->with(1)->andReturn([
            'topicid' => 5,
            'topic_subject' => 'Edit Topic',
            'body' => 'Edit text',
            'is_first_post' => true,
        ]);

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->buildComposeFrame(1, 'edit'));

        $this->assertInstanceOf(ForumComposeViewModel::class, $result);
        $this->assertStringContainsString('Edit Post', (string) $result->titleHtml);
        $this->assertSame('Edit text', $result->body);
        $this->assertTrue($result->hasSubject);
        $this->assertSame('Edit Topic', $result->subject);
    }

    // --- checkWhetherExist ---

    public function test_check_whether_exist_forum_not_found_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $repo->shouldReceive('forumExists')->with(999)->andReturn(false);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service()->checkWhetherExist(999, 'forum'));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when forum not found');
    }

    public function test_check_whether_exist_topic_not_found_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $this->mockTopicRepo()->shouldReceive('topicExists')->with(999)->andReturn(null);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service()->checkWhetherExist(999, 'topic'));
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected abort when topic not found');
    }

    public function test_check_whether_exist_post_not_found_aborts(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();

        $this->mockPostRepo()->shouldReceive('postExists')->with(999)->andReturn(null);

        $threw = false;
        try {
            $this->callWithSuppressedErrors(fn () => $this->service()->checkWhetherExist(999, 'post'));
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
            $this->callWithSuppressedErrors(fn () => $this->service()->checkWhetherExist(0, 'forum'));
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

        $this->callWithSuppressedErrors(fn () => $this->service()->checkWhetherExist(1, 'forum'));

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

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->buildNewTopic(Request::create('/forums.php', 'GET', ['forumid' => 1])));

        $this->assertInstanceOf(ForumComposeViewModel::class, $result);
        $this->assertStringContainsString('Test Forum', (string) $result->titleHtml);
    }

    // --- buildReply ---

    public function test_build_reply_with_valid_topicid_returns_compose_frame(): void
    {
        $repo = $this->mockForumRepo();
        $this->setUser();
        $this->setRequest(['topicid' => 1]);

        $this->mockTopicRepo()->shouldReceive('topicExists')->with(1)->andReturn(1);
        $repo->shouldReceive('forumExists')->with(1)->andReturn(true);
        $this->mockTopicRepo()->shouldReceive('getTopicSubject')->with(1)->andReturn('Test Topic');

        $result = $this->callWithSuppressedErrors(fn () => $this->service()->buildReply(Request::create('/forums.php', 'GET', ['topicid' => 1])));

        $this->assertInstanceOf(ForumComposeViewModel::class, $result);
        $this->assertStringContainsString('Test Topic', (string) $result->titleHtml);
    }
}
