<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\OfferVote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OfferVoteTest extends TestCase
{
    use DatabaseTransactions;

    private function insertOffer(int $ownerId): int
    {
        return (int) DB::table('offers')->insertGetId([
            'userid' => $ownerId,
            'name' => 'Test Offer '.uniqid(),
            'added' => now()->toDateTimeString(),
            'yeah' => 0,
            'against' => 0,
            'category' => 0,
            'comments' => 0,
            'allowed' => 1,
        ]);
    }

    public function test_render_shows_vote_buttons_for_pending_offer(): void
    {
        $owner = User::factory()->create();
        $voter = User::factory()->create();
        $offerId = $this->insertOffer($owner->id);
        $this->actingAs($voter, 'nexus-web');

        Livewire::test(OfferVote::class, [
            'offerId' => $offerId,
            'canAgainst' => true,
            'yeah' => 0,
            'against' => 0,
        ])
            ->assertSee(__('offers.text_for'))
            ->assertSee(__('offers.text_against'))
            ->assertOk();
    }

    public function test_yeah_vote_records_and_updates_counts(): void
    {
        $owner = User::factory()->create();
        $voter = User::factory()->create();
        $offerId = $this->insertOffer($owner->id);
        $this->actingAs($voter, 'nexus-web');

        Livewire::test(OfferVote::class, [
            'offerId' => $offerId,
            'canAgainst' => false,
        ])
            ->call('vote', 'yeah')
            ->assertSet('voted', true)
            ->assertSet('yeah', 1)
            ->assertSet('error', '');

        $this->assertTrue(
            (bool) DB::table('offervotes')->where('offerid', $offerId)->where('userid', $voter->id)->exists()
        );
        $this->assertSame(1, (int) DB::table('offers')->where('id', $offerId)->value('yeah'));
    }

    public function test_self_vote_shows_inline_error(): void
    {
        $owner = User::factory()->create();
        $offerId = $this->insertOffer($owner->id);
        $this->actingAs($owner, 'nexus-web');

        Livewire::test(OfferVote::class, ['offerId' => $offerId, 'canAgainst' => true])
            ->call('vote', 'yeah')
            ->assertSet('error', __('offers.std_cannot_vote_youself'))
            ->assertSet('voted', false);

        $this->assertSame(0, (int) DB::table('offervotes')->where('offerid', $offerId)->count());
    }

    public function test_double_vote_shows_already_voted(): void
    {
        $owner = User::factory()->create();
        $voter = User::factory()->create();
        $offerId = $this->insertOffer($owner->id);
        DB::table('offervotes')->insert([
            'offerid' => $offerId,
            'userid' => $voter->id,
            'vote' => \App\Enums\OfferVote::YEAH->value,
        ]);
        $this->actingAs($voter, 'nexus-web');

        Livewire::test(OfferVote::class, ['offerId' => $offerId, 'canAgainst' => true])
            ->call('vote', 'yeah')
            ->assertSet('error', __('offers.std_already_voted'))
            ->assertSet('voted', false);
    }

    public function test_invalid_vote_is_a_noop(): void
    {
        $owner = User::factory()->create();
        $voter = User::factory()->create();
        $offerId = $this->insertOffer($owner->id);
        $this->actingAs($voter, 'nexus-web');

        Livewire::test(OfferVote::class, ['offerId' => $offerId, 'canAgainst' => true])
            ->call('vote', 'maybe')
            ->assertSet('voted', false)
            ->assertSet('error', '');

        $this->assertSame(0, (int) DB::table('offervotes')->where('offerid', $offerId)->count());
    }
}
