<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\IndexPoll;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Categories\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class IndexPollTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestSettings(['showpolls_main' => 'yes']);
    }

    private function seedPoll(): Poll
    {
        return Poll::factory()->create([
            'question' => 'Best client?',
            'option0' => 'qBittorrent',
            'option1' => 'Deluge',
        ]);
    }

    public function test_renders_vote_form_for_logged_in_user(): void
    {
        $this->seedPoll();
        $user = User::factory()->create();

        Livewire::actingAs($user, 'nexus-web')
            ->test(IndexPoll::class)
            ->assertSee('Best client?')
            ->assertSee('wire:submit="vote"', false)
            ->assertSee('wire:model.number="choice"', false);
    }

    public function test_vote_records_choice_and_shows_bars(): void
    {
        $poll = $this->seedPoll();
        $user = User::factory()->create();

        Livewire::actingAs($user, 'nexus-web')
            ->test(IndexPoll::class)
            ->set('choice', 1)
            ->call('vote')
            ->assertSee('unsltbar')
            ->assertSee('sltbar');

        $this->assertSame(1, (int) DB::table('pollanswers')->where('pollid', $poll->id)->where('userid', $user->id)->value('selection'));
    }

    public function test_vote_does_not_record_invalid_choice(): void
    {
        $this->seedPoll();
        $user = User::factory()->create();

        Livewire::actingAs($user, 'nexus-web')
            ->test(IndexPoll::class)
            ->set('choice', 42)
            ->call('vote')
            ->assertSee('wire:submit="vote"', false);

        $this->assertSame(0, DB::table('pollanswers')->count());
    }
}
