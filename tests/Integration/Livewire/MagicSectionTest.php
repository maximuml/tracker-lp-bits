<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\MagicSection;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class MagicSectionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_render_shows_options_for_rich_user(): void
    {
        $user = User::factory()->create(['seedbonus' => 500.0]);
        $owner = User::factory()->create();
        $torrent = Torrent::factory()->owner($owner)->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(MagicSection::class, ['torrentId' => $torrent->id])
            ->assertSee('+50')
            ->assertSee('+200')
            ->assertOk();
    }

    public function test_render_shows_no_options_for_owner(): void
    {
        $owner = User::factory()->create(['seedbonus' => 500.0]);
        $torrent = Torrent::factory()->owner($owner)->create();
        $this->actingAs($owner, 'nexus-web');

        Livewire::test(MagicSection::class, ['torrentId' => $torrent->id])
            ->assertDontSee('+50')
            ->assertOk();
    }

    public function test_render_disabled_for_low_bonus(): void
    {
        $user = User::factory()->create(['seedbonus' => 10.0]);
        $owner = User::factory()->create();
        $torrent = Torrent::factory()->owner($owner)->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(MagicSection::class, ['torrentId' => $torrent->id])
            ->assertSee(__('details.magic_have_no_enough_bonus_value'))
            ->assertOk();
    }

    public function test_give_records_magic_and_moves_bonus(): void
    {
        $user = User::factory()->create(['seedbonus' => 500.0]);
        $owner = User::factory()->create(['seedbonus' => 0.0]);
        $torrent = Torrent::factory()->owner($owner)->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(MagicSection::class, ['torrentId' => $torrent->id])
            ->call('give', 50)
            ->assertSet('status', '')
            ->assertSee((string) str_replace('Number', '50', __('details.magic_value_number')));

        $this->assertSame(1, DB::table('magic')->where('torrentid', $torrent->id)->where('userid', $user->id)->count());
        $this->assertSame(450.0, (float) $user->fresh()->seedbonus);
        $this->assertSame(50.0, (float) $owner->fresh()->seedbonus);
    }

    public function test_give_twice_sets_status(): void
    {
        $user = User::factory()->create(['seedbonus' => 500.0]);
        $owner = User::factory()->create();
        $torrent = Torrent::factory()->owner($owner)->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(MagicSection::class, ['torrentId' => $torrent->id])
            ->call('give', 50)
            ->call('give', 50)
            ->assertSet('status', 'You already gave the magic value!');
    }

    public function test_give_to_own_torrent_sets_status(): void
    {
        $owner = User::factory()->create(['seedbonus' => 500.0]);
        $torrent = Torrent::factory()->owner($owner)->create();
        $this->actingAs($owner, 'nexus-web');

        Livewire::test(MagicSection::class, ['torrentId' => $torrent->id])
            ->call('give', 50)
            ->assertSet('status', 'You are giving magic to yourself.');
    }

    public function test_guest_give_noop(): void
    {
        $torrent = Torrent::factory()->create();

        Livewire::test(MagicSection::class, ['torrentId' => $torrent->id])
            ->call('give', 50)
            ->assertSet('status', '');

        $this->assertSame(0, DB::table('magic')->where('torrentid', $torrent->id)->count());
    }

    public function test_show_all_reveals_hidden_givers(): void
    {
        $givers = [];
        for ($i = 0; $i < 8; $i++) {
            $givers[] = User::factory()->create();
        }
        $owner = User::factory()->create();
        $torrent = Torrent::factory()->owner($owner)->create();
        $now = now()->toDateTimeString();
        foreach ($givers as $i => $giver) {
            DB::table('magic')->insert(['torrentid' => $torrent->id, 'userid' => $giver->id, 'value' => 50 + $i, 'created_at' => $now, 'updated_at' => $now]);
        }
        $viewer = User::factory()->create(['seedbonus' => 0.0]);
        $this->actingAs($viewer, 'nexus-web');

        Livewire::test(MagicSection::class, ['torrentId' => $torrent->id])
            ->assertSet('showAll', false)
            ->call('showAllGivers')
            ->assertSet('showAll', true);
    }
}
