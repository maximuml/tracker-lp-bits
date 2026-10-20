<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Torrent;
use App\Models\User;
use App\Services\MagicRewardService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression tests for the magic-award TOCTOU fix: give() now runs
 * inside a transaction with both balance rows locked, so a repeated
 * call can no longer double-insert the magic row / double-transfer
 * the bonus, and bonus_logs carry the real locked balances.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class MagicRewardServiceTest extends TestCase
{
    use DatabaseTransactions;

    private MagicRewardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MagicRewardService::class);
        Settings::saveBatch('tweak', ['bonus' => 'enable']);
    }

    public function test_give_transfers_once_and_logs_locked_balances(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['seedbonus' => 300]);
        /** @var User $giver */
        $giver = User::factory()->create(['seedbonus' => 500]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create(['owner' => $owner->id]);

        $this->service->give($giver, (int) $torrent->id, 100);

        $this->assertSame(400.0, (float) DB::table('users')->where('id', $giver->id)->value('seedbonus'));
        $this->assertSame(400.0, (float) DB::table('users')->where('id', $owner->id)->value('seedbonus'));
        $this->assertDatabaseHas('magic', ['torrentid' => $torrent->id, 'userid' => $giver->id, 'value' => 100]);
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $giver->id,
            'old_total_value' => 500,
            'new_total_value' => 400,
        ]);
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $owner->id,
            'old_total_value' => 300,
            'new_total_value' => 400,
        ]);
    }

    public function test_second_give_to_same_torrent_is_rejected(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['seedbonus' => 0]);
        /** @var User $giver */
        $giver = User::factory()->create(['seedbonus' => 500]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create(['owner' => $owner->id]);

        $this->service->give($giver, (int) $torrent->id, 100);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already gave');
        try {
            $this->service->give($giver, (int) $torrent->id, 100);
        } finally {
            // Exactly one deduction happened.
            $this->assertSame(400.0, (float) DB::table('users')->where('id', $giver->id)->value('seedbonus'));
            $this->assertSame(1, DB::table('magic')->where('torrentid', $torrent->id)->where('userid', $giver->id)->count());
        }
    }

    public function test_give_above_balance_is_rejected(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['seedbonus' => 0]);
        /** @var User $giver */
        $giver = User::factory()->create(['seedbonus' => 50]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create(['owner' => $owner->id]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('such bonus');
        try {
            $this->service->give($giver, (int) $torrent->id, 100);
        } finally {
            $this->assertSame(50.0, (float) DB::table('users')->where('id', $giver->id)->value('seedbonus'));
            $this->assertSame(0, DB::table('magic')->where('torrentid', $torrent->id)->count());
        }
    }
}
