<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\BookmarkIcon;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class BookmarkIconTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_render_unbookmarked_state(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(BookmarkIcon::class, ['torrentId' => $torrent->id])
            ->assertSet('bookmarked', false)
            ->assertSee('delbookmark')
            ->assertOk();
    }

    public function test_render_bookmarked_state(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        DB::table('bookmarks')->insert(['userid' => $user->id, 'torrentid' => $torrent->id]);
        $this->actingAs($user, 'nexus-web');

        Livewire::test(BookmarkIcon::class, ['torrentId' => $torrent->id])
            ->assertSet('bookmarked', true)
            ->assertSee('class="bookmark"', false)
            ->assertOk();
    }

    public function test_toggle_adds_and_removes_bookmark(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(BookmarkIcon::class, ['torrentId' => $torrent->id])
            ->call('toggle')
            ->assertSet('bookmarked', true)
            ->call('toggle')
            ->assertSet('bookmarked', false);

        $this->assertSame(0, DB::table('bookmarks')->count());
    }

    public function test_guest_toggle_noop(): void
    {
        $torrent = Torrent::factory()->create();

        Livewire::test(BookmarkIcon::class, ['torrentId' => $torrent->id])
            ->call('toggle')
            ->assertSet('bookmarked', false);
    }
}
