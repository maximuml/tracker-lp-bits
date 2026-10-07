<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\ThanksSection;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ThanksSectionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_render_shows_thanks_button(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(ThanksSection::class, ['torrentId' => $torrent->id])
            ->assertSee(__('details.submit_say_thanks'))
            ->assertSee(__('details.text_no_thanks_added'))
            ->assertOk();
    }

    public function test_thank_records_and_disables_button(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $torrent = Torrent::factory()->owner($owner)->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(ThanksSection::class, ['torrentId' => $torrent->id])
            ->call('thank')
            ->assertSet('status', '')
            ->assertSee(__('details.submit_you_said_thanks'));

        $this->assertSame(1, DB::table('thanks')->where('torrentid', $torrent->id)->count());
    }

    public function test_thank_twice_sets_status(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $torrent = Torrent::factory()->owner($owner)->create();
        $this->actingAs($user, 'nexus-web');

        DB::table('thanks')->insert(['userid' => $user->id, 'torrentid' => $torrent->id]);

        Livewire::test(ThanksSection::class, ['torrentId' => $torrent->id])
            ->call('thank')
            ->assertSet('status', 'you already thank this torrent');

        $this->assertSame(1, DB::table('thanks')->where('torrentid', $torrent->id)->count());
    }

    public function test_thank_as_guest_does_nothing(): void
    {
        $torrent = Torrent::factory()->create();

        Livewire::test(ThanksSection::class, ['torrentId' => $torrent->id])
            ->call('thank');

        $this->assertSame(0, DB::table('thanks')->where('torrentid', $torrent->id)->count());
    }
}
