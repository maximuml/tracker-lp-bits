<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * W1-03: HTTP contract tests for message mutations.
 * Tests the actual HTTP pipeline including FormRequest validation,
 * authorization, and legacy redirect behavior.
 */
final class MessageHttpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    // ─── Authentication ────────────────────────────────────────────────

    public function test_takemessage_redirects_unauthenticated_user(): void
    {
        $this->post('/takemessage', [
            'receiver' => '1',
            'body' => 'Hello',
            'subject' => 'Test',
        ])->assertRedirect();
    }

    public function test_deletemessage_redirects_unauthenticated_user(): void
    {
        $this->post('/deletemessage', [
            'id' => 1,
            'type' => 'in',
        ])->assertRedirect();
    }

    public function test_messages_action_redirects_unauthenticated_user(): void
    {
        $this->post('/messages', [
            'action' => 'moveordel',
        ])->assertRedirect();
    }

    // ─── FormRequest validation ────────────────────────────────────────

    public function test_takemessage_validates_required_body(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $this->withNexusCookie($sender)
            ->post('/takemessage', [
                'receiver' => (string) $receiver->id,
                'subject' => 'Test',
                // body missing
            ])
            ->assertRedirect();
    }

    public function test_takemessage_validates_required_receiver(): void
    {
        $sender = User::factory()->create();

        $this->withNexusCookie($sender)
            ->post('/takemessage', [
                'body' => 'Hello',
                'subject' => 'Test',
                // receiver missing
            ])
            ->assertRedirect();
    }

    public function test_deletemessage_validates_required_id(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/deletemessage', [
                'type' => 'in',
                // id missing
            ])
            ->assertRedirect();
    }

    public function test_deletemessage_validates_type_enum(): void
    {
        $user = User::factory()->create();
        $message = Message::factory()->between(User::factory()->create(), $user)->create();

        $this->withNexusCookie($user)
            ->post('/deletemessage', [
                'id' => (string) $message->id,
                'type' => 'invalid',
            ])
            ->assertRedirect();
    }

    public function test_messages_action_validates_action_enum(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/messages', [
                'action' => 'invalid_action',
            ])
            ->assertRedirect();
    }

    // ─── Authorization: ownership ───────────────────────────────────────

    public function test_deletemessage_inbox_only_for_receiver(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $sender = User::factory()->create();
        $message = Message::factory()->between($sender, $owner)->create();

        // Owner (receiver) can delete
        $this->withNexusCookie($owner)
            ->post('/deletemessage', [
                'id' => (string) $message->id,
                'type' => 'in',
            ])
            ->assertRedirect();

        // Recreate message for the other user test
        $message2 = Message::factory()->between($sender, $owner)->create();

        // Other user cannot delete
        $this->withNexusCookie($other)
            ->post('/deletemessage', [
                'id' => (string) $message2->id,
                'type' => 'in',
            ])
            ->assertRedirect();
    }

    public function test_deletemessage_sentbox_only_for_sender(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $other = User::factory()->create();
        $message = Message::factory()->between($sender, $receiver)->create();

        // Sender can delete from sentbox
        $this->withNexusCookie($sender)
            ->post('/deletemessage', [
                'id' => (string) $message->id,
                'type' => 'out',
            ])
            ->assertRedirect();

        // Recreate message
        $message2 = Message::factory()->between($sender, $receiver)->create();

        // Other user cannot delete from sentbox
        $this->withNexusCookie($other)
            ->post('/deletemessage', [
                'id' => (string) $message2->id,
                'type' => 'out',
            ])
            ->assertRedirect();
    }

    // ─── HTTP method boundaries ────────────────────────────────────────

    public function test_takemessage_rejects_get(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->get('/takemessage')
            ->assertStatus(405);
    }

    public function test_deletemessage_rejects_get(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->get('/deletemessage')
            ->assertStatus(405);
    }

    // ─── Success paths ─────────────────────────────────────────────────

    public function test_takemessage_creates_message_and_redirects(): void
    {
        $sender = User::factory()->create(['last_pm' => null]);
        $receiver = User::factory()->create();

        $this->withNexusCookie($sender)
            ->post('/takemessage', [
                'receiver' => (string) $receiver->id,
                'body' => 'Hello world',
                'subject' => 'Test subject',
                'returnto' => '/messages',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'sender' => $sender->id,
            'receiver' => $receiver->id,
            'subject' => 'Test subject',
        ]);
    }

    public function test_deletemessage_inbox_deletes_message(): void
    {
        $receiver = User::factory()->create();
        $sender = User::factory()->create();
        $message = Message::factory()->between($sender, $receiver)->create();

        $this->withNexusCookie($receiver)
            ->post('/deletemessage', [
                'id' => (string) $message->id,
                'type' => 'in',
            ])
            ->assertRedirect();
    }

    public function test_messages_action_markread_marks_messages(): void
    {
        $receiver = User::factory()->create();
        $sender = User::factory()->create();
        $message = Message::factory()->between($sender, $receiver)->create(['unread' => true]);

        $this->withNexusCookie($receiver)
            ->post('/messages', [
                'action' => 'moveordel',
                'markread' => '1',
                'messages' => [$message->id],
            ])
            ->assertRedirect();
    }
}
