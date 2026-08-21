<?php

namespace Tests\Feature;

use App\Models\Forum;
use App\Models\Message;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserSocialTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_api_bookmark_store_and_delete(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->owner($user)->create();

        Sanctum::actingAs($user, ['bookmark:store']);

        $this->postJson('/api/v1/bookmarks', ['torrent_id' => $torrent->id])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0);

        $this->assertDatabaseHas('bookmarks', [
            'userid' => $user->id,
            'torrentid' => $torrent->id,
        ]);

        Sanctum::actingAs($user, ['bookmark:delete']);

        $this->postJson('/api/v1/bookmarks/delete', ['torrent_id' => $torrent->id])
            ->assertStatus(200)
            ->assertJsonPath('ret', 0);

        $this->assertDatabaseMissing('bookmarks', [
            'userid' => $user->id,
            'torrentid' => $torrent->id,
        ]);
    }

    public function test_messages_inbox_renders_received_message(): void
    {
        $receiver = User::factory()->create();
        $sender = User::factory()->create();
        $message = Message::factory()->between($sender, $receiver)->create();

        $this->withNexusCookie($receiver)
            ->get('/messages')
            ->assertStatus(200)
            ->assertSee($message->subject);
    }

    public function test_sendmessage_and_takemessage_saves_pm_and_redirects(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $this->withNexusCookie($sender)
            ->get('/sendmessage')
            ->assertStatus(200);

        $subject = 'Test PM subject';
        $body = 'This is a test private message.';

        $response = $this->withNexusCookie($sender)
            ->post('/takemessage', [
                'receiver' => (string) $receiver->id,
                'subject' => $subject,
                'body' => $body,
                'returnto' => '/messages',
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('messages', (string) $response->headers->get('Location'));

        $this->assertDatabaseHas('messages', [
            'sender' => $sender->id,
            'receiver' => $receiver->id,
            'subject' => $subject,
        ]);
    }

    public function test_friends_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->get('/friends')
            ->assertStatus(200);
    }

    public function test_forums_page_renders_existing_forum(): void
    {
        $user = User::factory()->create();
        $forum = Forum::factory()->create();

        $this->withNexusCookie($user)
            ->get('/forums')
            ->assertStatus(200)
            ->assertSee('Forums');
    }

    public function test_usercp_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->get('/usercp')
            ->assertStatus(200);
    }

    public function test_userdetails_page_renders_user_profile(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        $this->withNexusCookie($viewer)
            ->get('/userdetails?id='.$target->id)
            ->assertStatus(200)
            ->assertSee($target->username);
    }
}
