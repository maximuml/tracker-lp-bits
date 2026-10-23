<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageSubject;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class MessageQuickReplyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_receiver_sees_quick_reply_form_with_hidden_fields(): void
    {
        $sender = $this->createUser();
        $receiver = $this->createUser(true);
        $message = $this->createMessage($sender, $receiver, 'Hi');

        $this->withNexusCookie($receiver)
            ->get('/web/messages?action=viewmessage&id='.$message->id)
            ->assertOk()
            ->assertSee('class="nx-quickreply"', false)
            ->assertSee('name="receiver" value="'.$sender->id.'"', false)
            ->assertSee('name="origmsg" value="'.$message->id.'"', false)
            ->assertSee('name="subject" value="Re: Hi"', false)
            ->assertSee('name="returnto" value="/web/messages?action=viewmessage&amp;id='.$message->id.'"', false)
            ->assertSee('name="save" value="yes"', false)
            ->assertSee('id="quickreply-body" name="body" rows="4" required', false);
    }

    public function test_sender_and_system_message_do_not_show_quick_reply(): void
    {
        $sender = $this->createUser();
        $receiver = $this->createUser();
        $outgoing = $this->createMessage($sender, $receiver);

        $this->withNexusCookie($sender)
            ->get('/web/messages?action=viewmessage&id='.$outgoing->id)
            ->assertOk()
            ->assertDontSee('class="nx-quickreply"', false);

        $systemSender = $this->createUser();
        $systemMessage = $this->createMessage($systemSender, $receiver);
        $systemSender->delete();

        $this->withNexusCookie($receiver)
            ->get('/web/messages?action=viewmessage&id='.$systemMessage->id)
            ->assertOk()
            ->assertDontSee('class="nx-quickreply"', false);
    }

    public function test_posting_quick_reply_creates_message_and_returns_to_original(): void
    {
        $sender = $this->createUser();
        $receiver = $this->createUser(true);
        $original = $this->createMessage($sender, $receiver, 'Original subject');
        $returnto = '/web/messages?action=viewmessage&id='.$original->id;

        $this->withNexusCookie($receiver)
            ->post('/web/messages/send', [
                'receiver' => (string) $sender->id,
                'origmsg' => (string) $original->id,
                'subject' => MessageSubject::reply($original->subject),
                'body' => 'A quick reply',
                'save' => 'yes',
                'returnto' => $returnto,
            ])
            ->assertRedirect($returnto);

        $this->assertDatabaseHas('messages', [
            'sender' => $receiver->id,
            'receiver' => $sender->id,
            'subject' => 'Re: Original subject',
            'msg' => 'A quick reply',
            'saved' => 1,
        ]);
    }

    private function createUser(bool $savePms = false): User
    {
        $created = User::factory()->create($savePms ? ['savepms' => true] : []);

        return User::query()->whereKey($created->getKey())->firstOrFail();
    }

    private function createMessage(User $sender, User $receiver, ?string $subject = null): Message
    {
        $state = $subject === null ? [] : ['subject' => $subject];
        $created = Message::factory()->between($sender, $receiver)->create($state);

        return Message::query()->whereKey($created->getKey())->firstOrFail();
    }
}
