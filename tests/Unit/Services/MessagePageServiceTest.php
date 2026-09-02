<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MessagePageService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for MessagePageService.
 *
 * Covers build() routing for viewmailbox (inbox, sentbox, custom),
 * viewmessage (valid, invalid id, nonexistent), forward (valid,
 * nonexistent), editmailboxes (with and without boxes), and unknown
 * action defaulting to viewmailbox.
 */
final class MessagePageServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $initialObLevel;

    private MessagePageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialObLevel = ob_get_level();

        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('messages')->truncate();
        DB::table('pmboxes')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->mockCache();
        $this->service = new MessagePageService;
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }
        Mockery::close();
        parent::tearDown();
    }

    private function mockCache(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get_value')->andReturn(false);
        $cache->shouldReceive('delete_value')->andReturn(true);
        $this->app->instance(LegacyRedisCache::class, $cache);
    }

    /** @param array<string, mixed> $overrides */
    private function createUser(array $overrides = []): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
            'pmnum' => 20,
        ], $overrides));
    }

    /** @param array<string, mixed> $data */
    private function insertMessage(array $data): int
    {
        $merged = array_merge([
            'sender' => null,
            'receiver' => 1,
            'added' => now()->toDateTimeString(),
            'subject' => 'Test subject',
            'msg' => 'Test message body',
            'location' => 1,
            'unread' => 1,
            'saved' => 0,
        ], $data);

        // sender=0 (system) violates the FK constraint; use null instead
        if (($merged['sender'] ?? null) === 0) {
            $merged['sender'] = null;
        }

        return (int) DB::table('messages')->insertGetId($merged);
    }

    private function insertPmBox(int $userId, int $boxNumber, string $name): int
    {
        return (int) DB::table('pmboxes')->insertGetId([
            'userid' => $userId,
            'boxnumber' => $boxNumber,
            'name' => $name,
        ]);
    }

    /** @param array<string, mixed> $userData */
    private function authenticatedUser(array $userData = []): void
    {
        $defaults = ['id' => 1, 'username' => 'testuser', 'class' => 1, 'pmnum' => 20];
        $currentUser = new CurrentUser;
        $currentUser->set(array_merge($defaults, $userData));
        $this->app->instance(CurrentUser::class, $currentUser);
    }

    private function mockGlobals(): void
    {
        $globals = new Globals;
        $globals->set('BASEURL', 'example.com');
        $globals->set('CONTENT_WIDTH', '737');
        $globals->set('lang_messages', [
            'text_inbox' => 'Inbox',
            'text_sentbox' => 'Sentbox',
            'text_sender' => 'Sender',
            'text_receiver' => 'Receiver',
            'text_system' => 'System',
            'text_no_subject' => 'No subject',
            'text_new' => 'New',
            'text_from' => 'From',
            'text_to' => 'To',
            'text_reply' => 'Reply',
            'std_error' => 'Error',
            'std_no_permission' => 'No permission.',
            'std_invalid_mailbox' => 'Invalid mailbox.',
            'std_no_permission_forwarding' => 'No permission to forward.',
            'select_inbox' => 'Inbox',
            'select_sentbox' => 'Sentbox',
        ]);
        $this->app->instance(Globals::class, $globals);
    }

    /**
     * Call the service while suppressing E_NOTICE/E_WARNING from the
     * legacy rendering system triggered by LegacyResponse::abort().
     */
    private function callBuild(Request $request): mixed
    {
        set_error_handler(function (int $severity): bool {
            return true;
        }, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);

        try {
            return $this->service->build($request);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Assert that calling build() with $request triggers an abort/guard.
     */
    private function assertBuildThrows(Request $request): void
    {
        $threw = false;
        try {
            $this->callBuild($request);
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected exception was not thrown');
    }

    // --- build: default action (viewmailbox) ---

    public function test_build_defaults_to_viewmailbox_for_empty_action(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'GET');

        $data = $this->callBuild($request);

        $this->assertIsArray($data);
        $this->assertSame('viewmailbox', $data['action']);
        $this->assertArrayHasKey('viewmailbox', $data);
        $this->assertSame($userId, $data['userId']);
    }

    public function test_build_viewmailbox_inbox_returns_mailbox_data(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'GET', ['box' => 1]);

        $data = $this->callBuild($request);

        $this->assertArrayHasKey('viewmailbox', $data);
        $mailbox = $data['viewmailbox'];
        $this->assertSame(1, $mailbox['mailbox']);
        $this->assertSame('Inbox', $mailbox['mailboxName']);
        $this->assertSame('Sender', $mailbox['senderReceiver']);
        $this->assertFalse($mailbox['isSentBox']);
        $this->assertIsArray($mailbox['rows']);
        $this->assertFalse($mailbox['hasMessages']);
    }

    public function test_build_viewmailbox_sentbox_returns_sentbox_data(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'GET', ['box' => -1]);

        $data = $this->callBuild($request);

        $mailbox = $data['viewmailbox'];
        $this->assertSame(-1, $mailbox['mailbox']);
        $this->assertSame('Sentbox', $mailbox['mailboxName']);
        $this->assertSame('Receiver', $mailbox['senderReceiver']);
        $this->assertTrue($mailbox['isSentBox']);
    }

    public function test_build_viewmailbox_with_messages_returns_rows(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $this->insertMessage([
            'sender' => 0,
            'receiver' => $userId,
            'subject' => 'System message',
            'msg' => 'Hello user',
            'location' => 1,
            'unread' => 1,
        ]);

        $request = Request::create('/messages.php', 'GET', ['box' => 1]);

        $data = $this->callBuild($request);

        $mailbox = $data['viewmailbox'];
        $this->assertTrue($mailbox['hasMessages']);
        $this->assertCount(1, $mailbox['rows']);
        $this->assertSame('System', $mailbox['rows'][0]['username']);
        $this->assertSame('System message', $mailbox['rows'][0]['subject']);
    }

    public function test_build_viewmailbox_custom_mailbox_returns_name(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();
        $this->insertPmBox($userId, 2, 'My Custom Box');

        $request = Request::create('/messages.php', 'GET', ['box' => 2]);

        $data = $this->callBuild($request);

        $mailbox = $data['viewmailbox'];
        $this->assertSame(2, $mailbox['mailbox']);
        $this->assertSame('My Custom Box', $mailbox['mailboxName']);
    }

    public function test_build_viewmailbox_invalid_custom_mailbox_throws(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        // box=99 doesn't exist for this user
        $request = Request::create('/messages.php', 'GET', ['box' => 99]);

        $this->assertBuildThrows($request);
    }

    // --- build: viewmessage ---

    public function test_build_viewmessage_returns_message_data(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $msgId = $this->insertMessage([
            'sender' => 0,
            'receiver' => $userId,
            'subject' => 'Test PM',
            'msg' => 'Hello world',
            'location' => 1,
            'unread' => 1,
        ]);

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'viewmessage',
            'id' => $msgId,
        ]);

        $data = $this->callBuild($request);

        $this->assertArrayHasKey('viewmessage', $data);
        $viewmessage = $data['viewmessage'];
        $this->assertSame($msgId, $viewmessage['pmId']);
        $this->assertSame('Test PM', $viewmessage['subject']);
        $this->assertSame('System', $viewmessage['sender']);
        $this->assertFalse($viewmessage['isSender']);
    }

    public function test_build_viewmessage_with_invalid_id_throws(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'viewmessage',
            'id' => 0,
        ]);

        $this->assertBuildThrows($request);
    }

    public function test_build_viewmessage_with_nonexistent_message_throws(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'viewmessage',
            'id' => 99999,
        ]);

        $this->assertBuildThrows($request);
    }

    public function test_build_viewmessage_marks_message_as_read(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $msgId = $this->insertMessage([
            'sender' => 0,
            'receiver' => $userId,
            'subject' => 'Unread PM',
            'msg' => 'Hello',
            'location' => 1,
            'unread' => 1,
        ]);

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'viewmessage',
            'id' => $msgId,
        ]);

        $this->callBuild($request);

        $unread = DB::table('messages')->where('id', $msgId)->value('unread');
        $this->assertSame(0, (int) $unread);
    }

    // --- build: forward ---

    public function test_build_forward_returns_forward_data(): void
    {
        $userId = $this->createUser();
        $senderId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $msgId = $this->insertMessage([
            'sender' => $senderId,
            'receiver' => $userId,
            'subject' => 'Forward me',
            'msg' => 'Forward this message',
            'location' => 1,
            'unread' => 0,
            'saved' => 0,
        ]);

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'forward',
            'id' => $msgId,
        ]);

        $data = $this->callBuild($request);

        $this->assertArrayHasKey('forward', $data);
        $forward = $data['forward'];
        $this->assertSame($msgId, $forward['pmId']);
        $this->assertStringContainsString('Fwd:', $forward['subject']);
        $this->assertStringContainsString('Forward this message', $forward['body']);
    }

    public function test_build_forward_with_nonexistent_message_throws(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'forward',
            'id' => 99999,
        ]);

        $this->assertBuildThrows($request);
    }

    // --- build: editmailboxes ---

    public function test_build_editmailboxes_returns_boxes(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();
        $this->insertPmBox($userId, 2, 'Work');
        $this->insertPmBox($userId, 3, 'Personal');

        $request = Request::create('/messages.php', 'GET', ['action' => 'editmailboxes']);

        $data = $this->callBuild($request);

        $this->assertArrayHasKey('editmailboxes', $data);
        $editmailboxes = $data['editmailboxes'];
        $this->assertTrue($editmailboxes['hasBoxes']);
        $this->assertCount(2, $editmailboxes['boxes']);
        $this->assertSame('Work', $editmailboxes['boxes'][0]['name']);
        $this->assertSame('Personal', $editmailboxes['boxes'][1]['name']);
    }

    public function test_build_editmailboxes_empty_when_no_boxes(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'GET', ['action' => 'editmailboxes']);

        $data = $this->callBuild($request);

        $editmailboxes = $data['editmailboxes'];
        $this->assertFalse($editmailboxes['hasBoxes']);
        $this->assertSame([], $editmailboxes['boxes']);
    }

    // --- build: unknown action defaults to viewmailbox ---

    public function test_build_unknown_action_defaults_to_viewmailbox(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'GET', ['action' => 'unknown_action']);

        $data = $this->callBuild($request);

        $this->assertSame('viewmailbox', $data['action']);
        $this->assertArrayHasKey('viewmailbox', $data);
    }

    // --- build: data structure ---

    public function test_build_returns_required_top_level_keys(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId]);
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'GET');

        $data = $this->callBuild($request);

        $this->assertArrayHasKey('lang', $data);
        $this->assertArrayHasKey('curUser', $data);
        $this->assertArrayHasKey('userId', $data);
        $this->assertArrayHasKey('action', $data);
        $this->assertArrayHasKey('baseUrl', $data);
        $this->assertArrayHasKey('contentWidth', $data);
    }
}
