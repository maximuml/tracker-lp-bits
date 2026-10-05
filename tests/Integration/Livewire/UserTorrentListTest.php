<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Contracts\Repositories\TorrentAjaxRepositoryInterface;
use App\Enums\UserClass;
use App\Livewire\UserTorrentList;
use App\Models\User;
use App\Repositories\TorrentModerationRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Categories\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UserTorrentListTest extends TestCase
{
    use DatabaseTransactions;

    private function cannedListData(): array
    {
        return [
            'count' => 1,
            'total_size' => 1024,
            'rows' => [],
            'seedTimeAndUploaded' => collect(),
            'torrentRep' => app(TorrentModerationRepository::class),
            'pagertop' => '',
            'pagerbottom' => '',
            'hasSize' => true,
            'title' => 'Torrents',
        ];
    }

    public function test_closed_state_renders_toggle_without_list(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);

        Livewire::actingAs($user, 'nexus-web')
            ->test(UserTorrentList::class, ['userId' => $user->id, 'type' => 'uploaded', 'label' => 'Uploaded'])
            ->assertSee('wire:click.prevent="toggle"', false)
            ->assertSee('class="plus"', false);
    }

    public function test_toggle_loads_list_for_own_profile(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        app()->instance(TorrentAjaxRepositoryInterface::class, \Mockery::mock(TorrentAjaxRepositoryInterface::class, function ($mock) {
            $mock->shouldReceive('userTorrentList')->once()->andReturn($this->cannedListData());
        }));

        Livewire::actingAs($user, 'nexus-web')
            ->test(UserTorrentList::class, ['userId' => $user->id, 'type' => 'uploaded', 'label' => 'Uploaded'])
            ->call('toggle')
            ->assertSet('open', true)
            ->assertSee('class="minus"', false)
            ->call('toggle')
            ->assertSet('open', false)
            ->assertSee('class="plus"', false);
    }

    public function test_toggle_does_not_query_repo_when_forbidden(): void
    {
        $owner = User::factory()->create(['class' => UserClass::USER->value]);
        $viewer = User::factory()->create(['class' => UserClass::USER->value]);
        app()->instance(TorrentAjaxRepositoryInterface::class, \Mockery::mock(TorrentAjaxRepositoryInterface::class, function ($mock) {
            $mock->shouldNotReceive('userTorrentList');
        }));

        Livewire::actingAs($viewer, 'nexus-web')
            ->test(UserTorrentList::class, ['userId' => $owner->id, 'type' => 'uploaded', 'label' => 'Uploaded'])
            ->call('toggle')
            ->assertSet('open', true);
    }
}
