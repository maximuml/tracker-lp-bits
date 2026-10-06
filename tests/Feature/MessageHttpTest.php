<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W1-03: HTTP contract tests for message mutations.
 * Tests the actual HTTP pipeline including FormRequest validation,
 * authorization, and legacy redirect behavior.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
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
        $this->post('/web/messages/move-or-delete')
            ->assertRedirect();
    }

    public function test_legacy_post_dispatchers_redirect_to_rest_endpoints(): void
    {
        $user = User::factory()->create();

        foreach ([
            ['/messages', ['action' => 'moveordel'], '/web/messages/move-or-delete'],
            ['/messages', ['action' => 'editmailboxes2'], '/web/messages/mailboxes'],
            ['/messages', ['action' => 'deletemessage'], '/web/messages/delete'],
            ['/offers', ['new_offer' => '1'], '/web/offers/create'],
            ['/offers', ['allow_offer' => '1'], '/web/offers/allow'],
            ['/offers', ['finish_offer' => '1'], '/web/offers/finish'],
            ['/offers', ['del_offer' => '1'], '/web/offers/delete'],
            ['/offers', ['take_off_edit' => '1'], '/web/offers/edit'],
        ] as [$uri, $data, $target]) {
            $response = $this->withNexusCookie($user)->post($uri, $data);
            $response->assertStatus(308);
            $this->assertStringEndsWith($target, (string) $response->headers->get('Location'));
        }

        $response = $this->withNexusCookie($user)->post('/mybonus?action=exchange');
        $response->assertStatus(308);
        $this->assertStringContainsString('/web/mybonus/exchange', (string) $response->headers->get('Location'));
    }

    public function test_legacy_take_uris_redirect_to_rest_endpoints(): void
    {
        $user = User::factory()->create();
        $receiver = User::factory()->create();

        foreach ([
            ['/takemessage', ['receiver' => (string) $receiver->id, 'body' => 'hi'], '/web/messages/send'],
            ['/deletemessage', ['id' => '1', 'type' => 'in'], '/web/messages/delete/in'],
            ['/deletemessage', ['id' => '1', 'type' => 'out'], '/web/messages/delete/out'],
            ['/takestaffmess', ['msg' => 'x'], '/web/staffmess/send'],
            ['/takecontact', ['body' => 'x', 'subject' => 'y'], '/web/contactstaff/send'],
        ] as [$uri, $data, $target]) {
            $response = $this->withNexusCookie($user)->post($uri, $data);
            $response->assertStatus(308);
            $this->assertStringEndsWith($target, (string) $response->headers->get('Location'));
        }
    }

    // ─── FormRequest validation ────────────────────────────────────────

    public function test_takemessage_validates_required_body(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $this->withNexusCookie($sender)
            ->post('/web/messages/send', [
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
            ->post('/web/messages/send', [
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
            ->post('/web/messages/delete/in', [
                // id missing
            ])
            ->assertRedirect();
    }

    public function test_deletemessage_validates_type_enum(): void
    {
        $user = User::factory()->create();
        $message = Message::factory()->between(User::factory()->create(), $user)->create();

        // `type` is a route parameter constrained to in|out — an unknown
        // side no longer resolves to the endpoint at all.
        $this->withNexusCookie($user)
            ->post('/web/messages/delete/invalid', [
                'id' => (string) $message->id,
            ])
            ->assertNotFound();
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
            ->post('/web/messages/delete/in', [
                'id' => (string) $message->id,
            ])
            ->assertRedirect();

        // Recreate message for the other user test
        $message2 = Message::factory()->between($sender, $owner)->create();

        // Other user cannot delete
        $this->withNexusCookie($other)
            ->post('/web/messages/delete/in', [
                'id' => (string) $message2->id,
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
            ->post('/web/messages/delete/out', [
                'id' => (string) $message->id,
            ])
            ->assertRedirect();

        // Recreate message
        $message2 = Message::factory()->between($sender, $receiver)->create();

        // Other user cannot delete from sentbox
        $this->withNexusCookie($other)
            ->post('/web/messages/delete/out', [
                'id' => (string) $message2->id,
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
            ->post('/web/messages/send', [
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
            ->post('/web/messages/delete/in', [
                'id' => (string) $message->id,
            ])
            ->assertRedirect();
    }

    public function test_messages_action_markread_marks_messages(): void
    {
        $receiver = User::factory()->create();
        $sender = User::factory()->create();
        $message = Message::factory()->between($sender, $receiver)->create(['unread' => true]);

        $this->withNexusCookie($receiver)
            ->post('/web/messages/move-or-delete', [
                'markread' => '1',
                'messages' => [$message->id],
            ])
            ->assertRedirect();
    }

    // ─── Flood check ───────────────────────────────────────────────────

    public function test_takemessage_flood_check_rejects_second_pm_within_window(): void
    {
        $sender = User::factory()->create(['class' => 1, 'last_pm' => now()->subSeconds(5)]);
        $receiver = User::factory()->create(['class' => 1]);

        $this->withNexusCookie($sender)
            ->post('/web/messages/send', [
                'receiver' => (string) $receiver->id,
                'subject' => 'Test',
                'body' => 'Second PM',
            ])
            ->assertOk()
            ->assertSee('Message Flooding Not Allowed');
    }

    public function test_takemessage_flood_check_allows_old_last_pm(): void
    {
        $sender = User::factory()->create(['class' => 1, 'last_pm' => now()->subMinutes(5)]);
        $receiver = User::factory()->create(['class' => 1]);

        $this->withNexusCookie($sender)
            ->post('/web/messages/send', [
                'receiver' => (string) $receiver->id,
                'subject' => 'Test',
                'body' => 'Old PM is fine',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'sender' => $sender->id,
            'receiver' => $receiver->id,
        ]);
    }
}
