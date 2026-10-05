<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\TorrentFileList;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TorrentFileListTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_render_closed_shows_see_full_list(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(TorrentFileList::class, ['torrentId' => $torrent->id])
            ->assertSee('View full list')
            ->assertSee('class="nx-hidden" ><a href="#" wire:click.prevent="hide"', false)
            ->assertOk();
    }

    public function test_show_renders_file_rows(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        DB::table('files')->insert([
            'torrent' => $torrent->id,
            'filename' => '01 - In the End.flac',
            'size' => 12345678,
        ]);
        $this->actingAs($user, 'nexus-web');

        Livewire::test(TorrentFileList::class, ['torrentId' => $torrent->id])
            ->call('show')
            ->assertSee('class="nx-hidden" ><a href="#" wire:click.prevent="show"', false)
            ->assertSee('01 - In the End.flac')
            ->assertSee('FLAC')
            ->assertOk();
    }

    public function test_hide_collapses_list(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        DB::table('files')->insert([
            'torrent' => $torrent->id,
            'filename' => 'movie.mkv',
            'size' => 2048,
        ]);
        $this->actingAs($user, 'nexus-web');

        Livewire::test(TorrentFileList::class, ['torrentId' => $torrent->id])
            ->call('show')
            ->call('hide')
            ->assertSee('class="nx-hidden" ><a href="#" wire:click.prevent="hide"', false)
            ->assertDontSee('movie.mkv')
            ->assertOk();
    }
}
