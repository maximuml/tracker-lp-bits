<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class MessageInboxNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_inbox_uses_mailbox_tabs_and_compact_search(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->get('/web/messages')
            ->assertOk()
            ->assertSee('aria-label="Mailboxes"', false)
            ->assertSeeInOrder([
                'class="nx-tabs__link nx-tabs__link--active"',
                'href="/web/messages"',
                'aria-current="page"',
            ], false)
            ->assertSee('href="/web/messages?action=editmailboxes"', false)
            ->assertSee('aria-label="Search within"', false)
            ->assertSee('aria-label="Mailboxes"', false)
            ->assertSee('name="keyword"', false)
            ->assertSee('name="place"', false)
            ->assertSee('name="box"', false)
            ->assertDontSee(__('messages.col_search_message'));
    }

    public function test_unread_filter_chip_is_current_for_unread_mailbox(): void
    {
        /** @var User $receiver */
        $receiver = User::factory()->create();
        /** @var User $sender */
        $sender = User::factory()->create();
        Message::factory()->between($sender, $receiver)->create(['unread' => true]);

        $this->withNexusCookie($receiver)
            ->get('/web/messages?action=viewmailbox&box=1&unread=yes')
            ->assertOk()
            ->assertSee(__('messages.text_all_messages'))
            ->assertSee(__('messages.text_unread'))
            ->assertSee(__('messages.text_read'))
            ->assertSeeInOrder([
                'class="nx-chip nx-chip--on"',
                'href="/web/messages?action=viewmailbox&amp;box=1&amp;unread=yes"',
                'aria-current="true"',
            ], false);
    }
}
