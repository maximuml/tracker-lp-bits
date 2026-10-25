<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass;
use App\Livewire\Shoutbox;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ShoutboxTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('shoutbox')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function actor(int $id): ActorContext
    {
        return new ActorContext(
            id: $id,
            username: 'user'.$id,
            class: UserClass::PEASANT,
            locale: 'en',
            stylesheet: 0,
            page: 0,
            passkey: 'passkey'.$id,
            permissions: [],
            user: null,
        );
    }

    private function guestActor(): ActorContext
    {
        return $this->actor(0);
    }

    /** @return array<string, mixed> */
    private function mountParams(bool $canManage = false): array
    {
        return [
            'cardTitle' => 'Shoutbox',
            'autoRefreshLabel' => 'Auto refresh',
            'refreshSeconds' => '120',
            'secondsLabel' => 'seconds',
            'historyLabel' => 'History',
            'canManage' => $canManage,
            'clearConfirm' => 'Sure?',
            'clearLabel' => 'Clear',
            'showHideTitle' => 'Show/Hide',
        ];
    }

    private function insertMessage(int $userId, string $text = 'Hello'): int
    {
        return (int) DB::table('shoutbox')->insertGetId([
            'userid' => $userId,
            'date' => time(),
            'text' => $text,
            'type' => 0,
        ]);
    }

    public function test_render_lists_shouts(): void
    {
        $this->insertMessage(1, 'livewire render check');
        app()->instance(ActorContext::class, $this->actor(1));

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->assertSee('livewire render check')
            ->assertOk();
    }

    public function test_send_posts_message_and_clears_input(): void
    {
        app()->instance(ActorContext::class, $this->actor(1));

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->set('text', 'hello from livewire')
            ->call('send')
            ->assertSet('text', '')
            ->assertSet('status', '');

        $this->assertSame(1, DB::table('shoutbox')->where('text', 'hello from livewire')->count());
    }

    public function test_send_empty_text_is_noop(): void
    {
        app()->instance(ActorContext::class, $this->actor(1));

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->set('text', '   ')
            ->call('send');

        $this->assertSame(0, DB::table('shoutbox')->count());
    }

    public function test_send_as_guest_does_not_post(): void
    {
        app()->instance(ActorContext::class, $this->guestActor());

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->set('text', 'guest shout')
            ->call('send');

        $this->assertSame(0, DB::table('shoutbox')->count());
    }

    public function test_toggle_collapses_and_expands_the_card(): void
    {
        app()->instance(ActorContext::class, $this->actor(1));

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->assertSee('class="minus"', false)
            ->call('toggle')
            ->assertSet('open', false)
            ->assertSee('class="plus"', false)
            ->assertSee('nx-hidden')
            ->call('toggle')
            ->assertSet('open', true)
            ->assertDontSee('kshoutbox" class="p-[10pt] nx-hidden');
    }

    public function test_expand_event_opens_a_collapsed_card(): void
    {
        app()->instance(ActorContext::class, $this->actor(1));

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->call('toggle')
            ->assertSet('open', false)
            ->dispatch('shoutbox-expand')
            ->assertSet('open', true);
    }

    public function test_start_edit_loads_raw_text_and_save_updates(): void
    {
        app()->instance(ActorContext::class, $this->actor(1));
        $id = $this->insertMessage(1, 'original text');

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->call('startEdit', $id)
            ->assertSet('editingId', $id)
            ->assertSet('editText', 'original text')
            ->set('editText', 'edited text')
            ->call('saveEdit')
            ->assertSet('editingId', null)
            ->assertSet('status', '');

        $this->assertSame('edited text', (string) DB::table('shoutbox')->where('id', $id)->value('text'));
    }

    public function test_cancel_edit_discards_draft(): void
    {
        app()->instance(ActorContext::class, $this->actor(1));
        $id = $this->insertMessage(1, 'keep me');

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->call('startEdit', $id)
            ->set('editText', 'scrap this')
            ->call('cancelEdit')
            ->assertSet('editingId', null)
            ->assertSet('editText', '');

        $this->assertSame('keep me', (string) DB::table('shoutbox')->where('id', $id)->value('text'));
    }

    public function test_edit_other_users_message_is_denied(): void
    {
        app()->instance(ActorContext::class, $this->actor(2));
        $id = $this->insertMessage(1, 'not yours');

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->call('startEdit', $id)
            ->set('editText', 'hijack attempt')
            ->call('saveEdit')
            ->assertSet('editingId', $id)
            ->assertSet('status', __('shoutbox.js_request_failed'));

        $this->assertSame('not yours', (string) DB::table('shoutbox')->where('id', $id)->value('text'));
    }

    public function test_confirm_dialogs_set_and_cancel_state(): void
    {
        app()->instance(ActorContext::class, $this->actor(1));
        $id = $this->insertMessage(1, 'bye');

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->call('confirmDelete', $id)
            ->assertSet('confirmingDeleteId', $id)
            ->assertSet('confirmingClear', false)
            ->call('cancelConfirm')
            ->assertSet('confirmingDeleteId', null)
            ->call('confirmClear')
            ->assertSet('confirmingClear', true)
            ->call('cancelConfirm')
            ->assertSet('confirmingClear', false);
    }

    public function test_delete_removes_own_message(): void
    {
        app()->instance(ActorContext::class, $this->actor(1));
        $id = $this->insertMessage(1, 'delete me');

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->call('delete', $id)
            ->assertSet('status', '');

        $this->assertSame(0, DB::table('shoutbox')->where('id', $id)->count());
    }

    public function test_react_toggles_reaction_and_closes_picker(): void
    {
        // shoutbox_reactions.user_id is FK'd — the actor needs a real users row.
        $user = User::factory()->create();
        app()->instance(ActorContext::class, $this->actor((int) $user->id));
        $id = $this->insertMessage(2, 'react to me');

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->call('togglePicker', $id)
            ->assertSet('openPickerId', $id)
            ->call('react', $id, '👍')
            ->assertSet('openPickerId', null);

        $this->assertSame(1, DB::table('shoutbox_reactions')->where('shoutbox_id', $id)->count());
    }

    public function test_clear_requires_manage_permission(): void
    {
        app()->instance(ActorContext::class, $this->actor(1));
        $this->insertMessage(1, 'still here');

        Livewire::test(Shoutbox::class, $this->mountParams())
            ->call('clear')
            ->assertSet('status', __('shoutbox.js_request_failed'));

        $this->assertSame(1, DB::table('shoutbox')->count());
    }

    public function test_clear_as_staff_wipes_all_rows(): void
    {
        app()->instance(ActorContext::class, new ActorContext(
            id: 1,
            username: 'staff1',
            class: UserClass::SYSOP,
            locale: 'en',
            stylesheet: 0,
            page: 0,
            passkey: 'passkey1',
            permissions: [PermissionEnum::SB_MANAGE],
            user: null,
        ));
        $this->insertMessage(1, 'wipe me');

        Livewire::test(Shoutbox::class, $this->mountParams(canManage: true))
            ->call('clear')
            ->assertSet('status', '');

        $this->assertSame(0, DB::table('shoutbox')->count());
    }
}
