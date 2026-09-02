<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ShoutboxService;
use App\Support\Shoutbox;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Unit tests for ShoutboxService.
 *
 * Covers postMessage (validation, lock), deleteMessage (nonexistent,
 * owner, non-owner, time window), editMessage (nonexistent, owner,
 * validation), toggleReaction (invalid, add, remove), and clearAll
 * (non-admin rejection).
 */
final class ShoutboxServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ShoutboxService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Flush Redis to clear any leftover locks from previous tests
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('shoutbox')->truncate();
        DB::table('shoutbox_reactions')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->service = new ShoutboxService;
    }

    private function insertUser(int $id): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'username' => 'user'.$id,
            'email' => 'user'.$id.'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => 'passkey'.$id,
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
        ]);
    }

    /** @return array<string, mixed> */
    private function userArray(int $id): array
    {
        return ['id' => $id, 'username' => 'user'.$id, 'class' => 1];
    }

    private function insertMessage(int $userId, string $text = 'Hello', ?int $date = null): int
    {
        return (int) DB::table('shoutbox')->insertGetId([
            'userid' => $userId,
            'date' => $date ?? time(),
            'text' => $text,
            'type' => 'sb',
        ]);
    }

    // --- postMessage ---

    public function test_post_message_rejects_zero_user_id(): void
    {
        $result = $this->service->postMessage(['id' => 0], 'Hello');

        $this->assertFalse($result);
        $this->assertSame(0, DB::table('shoutbox')->count());
    }

    public function test_post_message_rejects_empty_text(): void
    {
        $this->insertUser(1);

        $result = $this->service->postMessage($this->userArray(1), '');

        $this->assertFalse($result);
        $this->assertSame(0, DB::table('shoutbox')->count());
    }

    public function test_post_message_rejects_text_over_max_length(): void
    {
        $this->insertUser(1);

        $result = $this->service->postMessage($this->userArray(1), str_repeat('x', Shoutbox::MAX_MESSAGE_LENGTH + 1));

        $this->assertFalse($result);
        $this->assertSame(0, DB::table('shoutbox')->count());
    }

    public function test_post_message_succeeds_with_valid_input(): void
    {
        $this->insertUser(1);

        $result = $this->service->postMessage($this->userArray(1), 'Hello world');

        $this->assertTrue($result);
        $this->assertSame(1, DB::table('shoutbox')->count());
        $msg = DB::table('shoutbox')->first();
        $this->assertNotNull($msg);
        $this->assertSame(1, (int) $msg->userid);
        $this->assertSame('Hello world', $msg->text);
        $this->assertSame('sb', $msg->type);
    }

    public function test_post_message_at_max_length_succeeds(): void
    {
        $this->insertUser(1);

        $result = $this->service->postMessage($this->userArray(1), str_repeat('x', Shoutbox::MAX_MESSAGE_LENGTH));

        $this->assertTrue($result);
    }

    // --- deleteMessage ---

    public function test_delete_message_rejects_invalid_id(): void
    {
        $result = $this->service->deleteMessage($this->userArray(1), 0);

        $this->assertFalse($result);
    }

    public function test_delete_message_returns_true_for_nonexistent(): void
    {
        $result = $this->service->deleteMessage($this->userArray(1), 99999);

        $this->assertTrue($result);
    }

    public function test_delete_message_by_owner_succeeds(): void
    {
        $this->insertUser(1);
        $id = $this->insertMessage(1, 'My message');

        $result = $this->service->deleteMessage($this->userArray(1), $id);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('shoutbox')->count());
    }

    public function test_delete_message_by_non_owner_fails(): void
    {
        $this->insertUser(1);
        $this->insertUser(2);
        $id = $this->insertMessage(1, 'User 1 message');

        // User 2 is not the owner and doesn't have SB_MANAGE
        $result = $this->service->deleteMessage($this->userArray(2), $id);

        $this->assertFalse($result);
        $this->assertSame(1, DB::table('shoutbox')->count());
    }

    public function test_delete_message_after_edit_window_fails(): void
    {
        $this->insertUser(1);
        // Message from 10 minutes ago (well beyond 120s edit window)
        $id = $this->insertMessage(1, 'Old message', time() - 600);

        $result = $this->service->deleteMessage($this->userArray(1), $id);

        $this->assertFalse($result);
        $this->assertSame(1, DB::table('shoutbox')->count());
    }

    public function test_delete_message_also_deletes_reactions(): void
    {
        $this->insertUser(1);
        $id = $this->insertMessage(1, 'My message');

        DB::table('shoutbox_reactions')->insert([
            'shoutbox_id' => $id,
            'user_id' => 1,
            'reaction' => '👍',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->service->deleteMessage($this->userArray(1), $id);

        $this->assertSame(0, DB::table('shoutbox')->count());
        $this->assertSame(0, DB::table('shoutbox_reactions')->count());
    }

    // --- editMessage ---

    public function test_edit_message_rejects_empty_text(): void
    {
        $this->insertUser(1);
        $id = $this->insertMessage(1, 'Original');

        $result = $this->service->editMessage($this->userArray(1), $id, '');

        $this->assertFalse($result);
    }

    public function test_edit_message_rejects_over_max_length(): void
    {
        $this->insertUser(1);
        $id = $this->insertMessage(1, 'Original');

        $result = $this->service->editMessage($this->userArray(1), $id, str_repeat('x', Shoutbox::MAX_MESSAGE_LENGTH + 1));

        $this->assertFalse($result);
    }

    public function test_edit_message_nonexistent_returns_false(): void
    {
        $this->insertUser(1);

        $result = $this->service->editMessage($this->userArray(1), 99999, 'New text');

        $this->assertFalse($result);
    }

    public function test_edit_message_by_owner_succeeds(): void
    {
        $this->insertUser(1);
        $id = $this->insertMessage(1, 'Original');

        $result = $this->service->editMessage($this->userArray(1), $id, 'Edited text');

        $this->assertTrue($result);
        $msg = DB::table('shoutbox')->where('id', $id)->first();
        $this->assertNotNull($msg);
        $this->assertSame('Edited text', $msg->text);
        $this->assertSame(1, (int) $msg->edited_by);
    }

    public function test_edit_message_by_non_owner_fails(): void
    {
        $this->insertUser(1);
        $this->insertUser(2);
        $id = $this->insertMessage(1, 'User 1 message');

        $result = $this->service->editMessage($this->userArray(2), $id, 'Hacked');

        $this->assertFalse($result);
        $msg = DB::table('shoutbox')->where('id', $id)->first();
        $this->assertNotNull($msg);
        $this->assertSame('User 1 message', $msg->text);
    }

    public function test_edit_message_after_edit_window_fails(): void
    {
        $this->insertUser(1);
        $id = $this->insertMessage(1, 'Old', time() - 600);

        $result = $this->service->editMessage($this->userArray(1), $id, 'New text');

        $this->assertFalse($result);
    }

    // --- toggleReaction ---

    public function test_toggle_reaction_rejects_invalid_id(): void
    {
        $result = $this->service->toggleReaction($this->userArray(1), 0, '👍');

        $this->assertNull($result);
    }

    public function test_toggle_reaction_rejects_invalid_reaction(): void
    {
        $this->insertUser(1);
        $id = $this->insertMessage(1, 'Hello');

        $result = $this->service->toggleReaction($this->userArray(1), $id, 'invalid');

        $this->assertNull($result);
        $this->assertSame(0, DB::table('shoutbox_reactions')->count());
    }

    public function test_toggle_reaction_adds_new_reaction(): void
    {
        $this->insertUser(1);
        $id = $this->insertMessage(1, 'Hello');

        $result = $this->service->toggleReaction($this->userArray(1), $id, '👍');

        $this->assertSame('added', $result);
        $this->assertSame(1, DB::table('shoutbox_reactions')->count());
        $reaction = DB::table('shoutbox_reactions')->first();
        $this->assertNotNull($reaction);
        $this->assertSame($id, (int) $reaction->shoutbox_id);
        $this->assertSame(1, (int) $reaction->user_id);
        $this->assertSame('👍', $reaction->reaction);
    }

    public function test_toggle_reaction_removes_existing_reaction(): void
    {
        $this->insertUser(1);
        $id = $this->insertMessage(1, 'Hello');

        $this->service->toggleReaction($this->userArray(1), $id, '👍');

        $result = $this->service->toggleReaction($this->userArray(1), $id, '👍');

        $this->assertSame('removed', $result);
        $this->assertSame(0, DB::table('shoutbox_reactions')->count());
    }

    public function test_toggle_reaction_independent_reactions(): void
    {
        $this->insertUser(1);
        $id = $this->insertMessage(1, 'Hello');

        $this->service->toggleReaction($this->userArray(1), $id, '👍');
        $result = $this->service->toggleReaction($this->userArray(1), $id, '🔥');

        $this->assertSame('added', $result);
        $this->assertSame(2, DB::table('shoutbox_reactions')->count());
    }

    // --- clearAll ---

    public function test_clear_all_rejects_non_admin_user(): void
    {
        $this->insertUser(1);
        $this->insertMessage(1, 'Hello');

        $result = $this->service->clearAll($this->userArray(1));

        $this->assertFalse($result);
        $this->assertSame(1, DB::table('shoutbox')->count());
    }

    public function test_clear_all_rejects_nonexistent_user(): void
    {
        $result = $this->service->clearAll(['id' => 99999]);

        $this->assertFalse($result);
    }
}
