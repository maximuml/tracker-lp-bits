<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Livewire\BbcodeEditor;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class MessageComposeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_new_compose_form_uses_compact_editor_and_save_option(): void
    {
        $sender = $this->createUser();
        $receiver = $this->createUser();

        $this->withNexusCookie($sender)
            ->get('/web/sendmessage?receiver='.$receiver->id)
            ->assertOk()
            ->assertSee('<form id="compose" name="compose" class="nx-compose" method="post" action="/web/messages/send">', false)
            ->assertSee('label for="subject"', false)
            ->assertSee('label for="body"', false)
            ->assertSee('bbcode-smilies-details', false)
            ->assertSee('bbcode-editor__head', false)
            ->assertSee('name="save" value="yes"', false)
            ->assertDontSee('name="delete"', false)
            ->assertDontSee('name="origmsg"', false);
    }

    public function test_reply_subject_and_returnto_are_escaped_once(): void
    {
        $sender = $this->createUser();
        $receiver = $this->createUser();
        $message = $this->createMessage($sender, $receiver, 'A & B');
        $returnto = '/web/messages?action=viewmessage&id=5';

        $this->withNexusCookie($receiver)
            ->get('/web/sendmessage?receiver='.$sender->id.'&replyto='.$message->id)
            ->assertOk()
            ->assertSee('name="origmsg" value="'.$message->id.'"', false)
            ->assertSee('name="delete" value="yes"', false)
            ->assertSee('name="subject" maxlength="100" value="Re: A &amp; B"', false)
            ->assertDontSee('&amp;amp;', false);

        $this->withNexusCookie($receiver)
            ->get('/web/sendmessage?receiver='.$sender->id.'&returnto='.urlencode($returnto))
            ->assertOk()
            ->assertSee('name="returnto" value="/web/messages?action=viewmessage&amp;id=5"', false)
            ->assertDontSee('&amp;amp;', false);
    }

    public function test_reply_to_message_for_another_user_is_denied(): void
    {
        $sender = $this->createUser();
        $receiver = $this->createUser();
        $other = $this->createUser();
        $message = $this->createMessage($sender, $receiver);

        $this->withNexusCookie($other)
            ->get('/web/sendmessage?receiver='.$sender->id.'&replyto='.$message->id)
            ->assertOk()
            ->assertSee(__('sendmessage.std_permission_denied'))
            ->assertDontSee('class="nx-compose"', false);
    }

    public function test_noncompact_livewire_editor_keeps_legacy_smilies_and_preview_bar(): void
    {
        Livewire::test(BbcodeEditor::class)
            ->assertSee('class="bbcode-smilies-wrap"', false)
            ->assertDontSee('bbcode-smilies-details', false)
            ->assertSee('class="text-center mt-2"', false);
    }

    private function createUser(): User
    {
        $created = User::factory()->create();

        return User::query()->whereKey($created->getKey())->firstOrFail();
    }

    private function createMessage(User $sender, User $receiver, string $subject = ''): Message
    {
        $created = Message::factory()->between($sender, $receiver)->create(['subject' => $subject]);

        return Message::query()->whereKey($created->getKey())->firstOrFail();
    }
}
