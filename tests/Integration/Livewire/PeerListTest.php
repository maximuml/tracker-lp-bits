<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Contracts\Repositories\TorrentAjaxRepositoryInterface;
use App\Livewire\PeerList;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class PeerListTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_render_closed_shows_counts_and_see_link(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(PeerList::class, ['torrentId' => $torrent->id, 'seeders' => 3, 'leechers' => 2])
            ->assertSee('3 Seeders')
            ->assertSee('2 Leechers')
            ->assertSee('View full list')
            ->assertSee('class="nx-hidden" ><a href="#" wire:click.prevent="hide"', false)
            ->assertOk();
    }

    public function test_show_builds_seeder_and_leecher_tables(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $this->actingAs($user, 'nexus-web');

        $repo = $this->mockTorrentAjaxRepository();
        $repo->shouldReceive('peerList')->once()->with($torrent->id, Mockery::any())->andReturn($this->peerListData());

        Livewire::test(PeerList::class, ['torrentId' => $torrent->id])
            ->call('show')
            ->assertSee('Seeders')
            ->assertSee('Leechers')
            ->assertSee('class="nx-hidden" ><a href="#" wire:click.prevent="show"', false)
            ->assertOk();
    }

    public function test_open_on_load_renders_tables(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $this->actingAs($user, 'nexus-web');

        $repo = $this->mockTorrentAjaxRepository();
        $repo->shouldReceive('peerList')->once()->andReturn($this->peerListData());

        Livewire::test(PeerList::class, ['torrentId' => $torrent->id, 'openOnLoad' => true])
            ->assertSee('Seeders')
            ->assertOk();
    }

    public function test_hide_collapses_tables(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $this->actingAs($user, 'nexus-web');

        $repo = $this->mockTorrentAjaxRepository();
        $repo->shouldReceive('peerList')->once()->andReturn($this->peerListData());

        Livewire::test(PeerList::class, ['torrentId' => $torrent->id])
            ->call('show')
            ->call('hide')
            ->assertSee('View full list')
            ->assertSee('class="nx-hidden" ><a href="#" wire:click.prevent="hide"', false)
            ->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function peerListData(): array
    {
        return [
            'torrent' => ['id' => 1, 'owner' => 0, 'anonymous' => 'no'],
            'seeders' => [],
            'leechers' => [],
            'privacyData' => [],
            'showLocationColumn' => false,
            'enablelocationTweak' => 'no',
            'peerIpInfo' => [],
            'usernameHtmlMap' => [],
        ];
    }

    private function mockTorrentAjaxRepository(): MockInterface
    {
        $mock = Mockery::mock(TorrentAjaxRepositoryInterface::class);
        app()->instance(TorrentAjaxRepositoryInterface::class, $mock);

        return $mock;
    }
}
