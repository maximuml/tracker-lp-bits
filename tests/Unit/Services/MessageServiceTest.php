<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Message;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Services\MessageService;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for MessageService.
 *
 * Covers takeMessage (POST validation, send success, forward validation,
 * PM rejection rules), deletemessage (inbox/outbox delete, invalid id,
 * unknown type), and handleMessagesActionPublic (action routing,
 * viewmessage redirect, non-POST guard).
 */
final class MessageServiceTest extends TestCase
{
    use DatabaseTransactions;

    private MessageService $service;

    private int $initialObLevel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialObLevel = ob_get_level();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('messages')->truncate();
        DB::table('pmboxes')->truncate();
        DB::table('blocks')->truncate();
        DB::table('friends')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->service = new MessageService;
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }
        Mockery::close();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $overrides */
    private function createUser(array $overrides = []): User
    {
        $id = (int) DB::table('users')->insertGetId(array_merge([
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
            'acceptpms' => 'yes',
            'notifs' => '',
            'last_pm' => null,
        ], $overrides));

        return User::query()->findOrFail($id);
    }

    /** @param  array<string, mixed>  $overrides */
    private function createMessage(int $senderId, int $receiverId, array $overrides = []): int
    {
        return (int) DB::table('messages')->insertGetId(array_merge([
            'sender' => $senderId,
            'receiver' => $receiverId,
            'added' => now()->toDateTimeString(),
            'subject' => 'Test subject',
            'msg' => 'Test message body',
            'location' => 1,
            'unread' => 1,
            'saved' => 0,
        ], $overrides));
    }

    private function login(User $user): void
    {
        auth()->login($user);
    }

    private function mockGlobals(): void
    {
        $globals = new Globals;
        $this->app->instance(Globals::class, $globals);
    }

    /** @return MessageRepository&MockInterface */
    private function mockMessageRepo(): mixed
    {
        /** @var MessageRepository&MockInterface $repo */
        $repo = Mockery::mock(MessageRepository::class);
        $repo->shouldIgnoreMissing();
        $this->app->instance(MessageRepository::class, $repo);

        return $repo;
    }

    /**
     * Call the service while suppressing E_NOTICE/E_WARNING from the
     * legacy rendering system triggered by LegacyResponse::abort().
     */
    private function callService(callable $callback): mixed
    {
        set_error_handler(function (int $severity): bool {
            return true;
        }, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Assert that calling the service throws (LegacyResponse::abort or
     * any Throwable from the legacy rendering system).
     */
    private function assertServiceThrows(callable $callback): void
    {
        $threw = false;
        try {
            $this->callService($callback);
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected exception was not thrown');
    }

    // ─── Instantiation ────────────────────────────────────────────────

    public function test_can_instantiate_service(): void
    {
        $service = new MessageService;

        $this->assertInstanceOf(MessageService::class, $service);
    }

    // ─── takeMessage: validation / abort paths ────────────────────────

    public function test_take_message_rejects_non_post_method(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $this->login($sender);

        $request = Request::create('/messages.php', 'GET', [
            'receiver' => 1,
            'body' => 'Hello',
        ]);

        $this->assertServiceThrows(fn () => $this->service->takeMessage($request));
    }

    public function test_take_message_rejects_unauthenticated_user(): void
    {
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'POST', [
            'receiver' => 1,
            'body' => 'Hello',
        ]);

        $this->assertServiceThrows(fn () => $this->service->takeMessage($request));
    }

    public function test_take_message_rejects_zero_receiver(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $this->login($sender);

        $request = Request::create('/messages.php', 'POST', [
            'receiver' => 0,
            'body' => 'Hello',
            'subject' => 'Test',
        ]);

        $this->assertServiceThrows(fn () => $this->service->takeMessage($request));
    }

    public function test_take_message_rejects_empty_body(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $recipient = $this->createUser();
        $this->login($sender);

        $request = Request::create('/messages.php', 'POST', [
            'receiver' => $recipient->id,
            'body' => '',
            'subject' => 'Test',
        ]);

        $this->assertServiceThrows(fn () => $this->service->takeMessage($request));
    }

    public function test_take_message_rejects_forward_with_zero_origmsg(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $this->login($sender);

        $request = Request::create('/messages.php', 'POST', [
            'forward' => '1',
            'origmsg' => 0,
            'body' => 'Hello',
            'subject' => 'Test',
        ]);

        $this->assertServiceThrows(fn () => $this->service->takeMessage($request));
    }

    public function test_take_message_rejects_parked_recipient(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $recipient = $this->createUser(['parked' => 1]);
        $this->login($sender);

        $request = Request::create('/messages.php', 'POST', [
            'receiver' => $recipient->id,
            'body' => 'Hello',
            'subject' => 'Test',
        ]);

        $this->assertServiceThrows(fn () => $this->service->takeMessage($request));
    }

    public function test_take_message_rejects_recipient_blocking_all_pms(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $recipient = $this->createUser(['acceptpms' => 'no']);
        $this->login($sender);

        $request = Request::create('/messages.php', 'POST', [
            'receiver' => $recipient->id,
            'body' => 'Hello',
            'subject' => 'Test',
        ]);

        $this->assertServiceThrows(fn () => $this->service->takeMessage($request));
    }

    public function test_take_message_rejects_recipient_friends_only(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $recipient = $this->createUser(['acceptpms' => 'friends']);
        $this->login($sender);

        $request = Request::create('/messages.php', 'POST', [
            'receiver' => $recipient->id,
            'body' => 'Hello',
            'subject' => 'Test',
        ]);

        $this->assertServiceThrows(fn () => $this->service->takeMessage($request));
    }

    // ─── takeMessage: success path ────────────────────────────────────

    public function test_take_message_succeeds_and_creates_message(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser(['last_pm' => null]);
        $recipient = $this->createUser(['acceptpms' => 'yes', 'notifs' => '']);
        $this->login($sender);

        $request = Request::create('/messages.php', 'POST', [
            'receiver' => $recipient->id,
            'body' => 'Hello world',
            'subject' => 'Test subject',
        ]);

        $result = $this->callService(fn () => $this->service->takeMessage($request));

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame(1, DB::table('messages')->count());
        $msg = DB::table('messages')->first();
        $this->assertNotNull($msg);
        $this->assertSame($sender->id, (int) $msg->sender);
        $this->assertSame($recipient->id, (int) $msg->receiver);
        $this->assertSame('Hello world', $msg->msg);
        $this->assertSame('Test subject', $msg->subject);
    }

    public function test_take_message_updates_sender_last_pm(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser(['last_pm' => null]);
        $recipient = $this->createUser(['acceptpms' => 'yes', 'notifs' => '']);
        $this->login($sender);

        $request = Request::create('/messages.php', 'POST', [
            'receiver' => $recipient->id,
            'body' => 'Hello',
            'subject' => 'Test',
        ]);

        $this->callService(fn () => $this->service->takeMessage($request));

        $lastPm = DB::table('users')->where('id', $sender->id)->value('last_pm');
        $this->assertNotNull($lastPm);
        $this->assertNotSame('', (string) $lastPm);
    }

    public function test_take_message_redirects_to_messages_php(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser(['last_pm' => null]);
        $recipient = $this->createUser(['acceptpms' => 'yes', 'notifs' => '']);
        $this->login($sender);

        $request = Request::create('/messages.php', 'POST', [
            'receiver' => $recipient->id,
            'body' => 'Hello',
            'subject' => 'Test',
        ]);

        $result = $this->callService(fn () => $this->service->takeMessage($request));

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('messages.php', $result->getTargetUrl());
    }

    // ─── deletemessage ────────────────────────────────────────────────

    public function test_delete_message_rejects_invalid_id(): void
    {
        $this->mockGlobals();
        $user = $this->createUser();
        $this->login($user);

        $request = Request::create('/messages.php', 'POST', [
            'id' => 0,
            'type' => 'in',
        ]);

        $this->assertServiceThrows(fn () => $this->service->deletemessage($request));
    }

    public function test_delete_message_rejects_unauthenticated_user(): void
    {
        $this->mockGlobals();

        $request = Request::create('/messages.php', 'POST', [
            'id' => 1,
            'type' => 'in',
        ]);

        $this->assertServiceThrows(fn () => $this->service->deletemessage($request));
    }

    public function test_delete_message_inbox_deletes_message(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $receiver = $this->createUser();
        $this->login($receiver);

        $msgId = $this->createMessage($sender->id, $receiver->id, [
            'location' => 1,
            'saved' => 0,
        ]);

        $request = Request::create('/messages.php', 'POST', [
            'id' => $msgId,
            'type' => 'in',
        ]);

        $result = $this->callService(fn () => $this->service->deletemessage($request));

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame(0, DB::table('messages')->count());
    }

    public function test_delete_message_outbox_updates_saved_flag(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $receiver = $this->createUser();
        $this->login($sender);

        $msgId = $this->createMessage($sender->id, $receiver->id, [
            'location' => 1,
            'saved' => 1,
        ]);

        $request = Request::create('/messages.php', 'POST', [
            'id' => $msgId,
            'type' => 'out',
        ]);

        $result = $this->callService(fn () => $this->service->deletemessage($request));

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('out=1', $result->getTargetUrl());
        // Message should still exist but saved flag updated
        $this->assertSame(1, DB::table('messages')->count());
        $saved = DB::table('messages')->where('id', $msgId)->value('saved');
        $this->assertSame(0, (int) $saved);
    }

    public function test_delete_message_rejects_unknown_type(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $receiver = $this->createUser();
        $this->login($receiver);

        $msgId = $this->createMessage($sender->id, $receiver->id);

        $request = Request::create('/messages.php', 'POST', [
            'id' => $msgId,
            'type' => 'invalid',
        ]);

        $this->assertServiceThrows(fn () => $this->service->deletemessage($request));
    }

    public function test_delete_message_inbox_rejects_non_owner(): void
    {
        $this->mockGlobals();
        $sender = $this->createUser();
        $receiver = $this->createUser();
        $otherUser = $this->createUser();
        $this->login($otherUser);

        $msgId = $this->createMessage($sender->id, $receiver->id);

        $request = Request::create('/messages.php', 'POST', [
            'id' => $msgId,
            'type' => 'in',
        ]);

        $this->assertServiceThrows(fn () => $this->service->deletemessage($request));
    }

    // ─── handleMessagesActionPublic ───────────────────────────────────

    public function test_handle_messages_action_returns_null_for_empty_action(): void
    {
        $this->mockGlobals();
        $this->mockMessageRepo();

        $request = Request::create('/messages.php', 'GET');

        $result = $this->service->handleMessagesActionPublic($request);

        $this->assertNull($result);
    }

    public function test_handle_messages_action_viewmessage_redirects_for_invalid_id(): void
    {
        $this->mockGlobals();
        $repo = $this->mockMessageRepo();
        $user = $this->createUser();
        $this->login($user);

        $repo->shouldReceive('getMessageForUser')->with(0, $user->id)->andReturn(null);

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'viewmessage',
            'id' => 0,
        ]);

        $result = $this->service->handleMessagesActionPublic($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('messages.php', $result->getTargetUrl());
    }

    public function test_handle_messages_action_moveordel_redirects_for_non_post(): void
    {
        $this->mockGlobals();
        $this->mockMessageRepo();

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'moveordel',
        ]);

        $result = $this->service->handleMessagesActionPublic($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('messages.php', $result->getTargetUrl());
    }

    public function test_handle_messages_action_editmailboxes2_redirects_for_non_post(): void
    {
        $this->mockGlobals();
        $this->mockMessageRepo();

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'editmailboxes2',
        ]);

        $result = $this->service->handleMessagesActionPublic($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('messages.php', $result->getTargetUrl());
    }

    public function test_handle_messages_action_deletemessage_redirects_for_non_post(): void
    {
        $this->mockGlobals();
        $this->mockMessageRepo();

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'deletemessage',
        ]);

        $result = $this->service->handleMessagesActionPublic($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('messages.php', $result->getTargetUrl());
    }

    public function test_handle_messages_action_viewmessage_returns_null_for_valid_message(): void
    {
        $this->mockGlobals();
        $repo = $this->mockMessageRepo();
        $sender = $this->createUser();
        $receiver = $this->createUser();
        $this->login($receiver);

        $msgId = $this->createMessage($sender->id, $receiver->id);

        $repo->shouldReceive('getMessageForUser')
            ->with($msgId, $receiver->id)
            ->andReturn(Message::query()->find($msgId));

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'viewmessage',
            'id' => $msgId,
        ]);

        $result = $this->service->handleMessagesActionPublic($request);

        $this->assertNull($result);
    }

    public function test_handle_messages_action_unknown_action_returns_null(): void
    {
        $this->mockGlobals();
        $this->mockMessageRepo();

        $request = Request::create('/messages.php', 'GET', [
            'action' => 'unknown_action',
        ]);

        $result = $this->service->handleMessagesActionPublic($request);

        $this->assertNull($result);
    }
}
