<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\DTOs\Auth\ActorContext;
use App\Enums\UserClass;
use App\Livewire\Shoutbox;
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
}
