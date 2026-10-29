<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\HitAndRunMode;
use App\Enums\HitAndRunStatus;
use App\Exceptions\NexusException;
use App\Models\HitAndRun;
use App\Models\Torrent;
use App\Models\TorrentBuyLog;
use App\Models\User;
use App\Services\BonusPurchaseService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * B-04: TOCTOU guards on bonus-consuming purchases.
 *
 * consumeToCancelHitAndRun() must not charge twice for the same H&R and
 * consumeToBuyTorrent() must not sell the same torrent twice — the
 * status/duplicate checks now run inside the transaction under locks.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class BonusPurchaseServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BonusPurchaseService $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(BonusPurchaseService::class);
        Settings::saveBatch('hr', ['mode' => HitAndRunMode::GLOBAL->value]);
        Settings::resetCache();
    }

    public function test_consume_to_cancel_hit_and_run_marks_pardoned_once(): void
    {
        $user = User::factory()->create(['seedbonus' => 10000.0]);
        $hitAndRun = HitAndRun::factory()->create([
            'uid' => $user->id,
            'status' => HitAndRunStatus::INSPECTING->value,
        ]);

        $result = $this->repository->consumeToCancelHitAndRun($user->id, $hitAndRun->id);

        $this->assertTrue($result);
        $this->assertSame(
            HitAndRunStatus::PARDONED->value,
            HitAndRun::query()->where('id', $hitAndRun->id)->value('status')
        );
    }

    /**
     * B-04: a second call (the replayed request / the losing side of the
     * race) must throw instead of consuming bonus a second time.
     */
    public function test_consume_to_cancel_hit_and_run_rejects_repeat(): void
    {
        $user = User::factory()->create(['seedbonus' => 10000.0]);
        $hitAndRun = HitAndRun::factory()->create([
            'uid' => $user->id,
            'status' => HitAndRunStatus::INSPECTING->value,
        ]);

        $this->repository->consumeToCancelHitAndRun($user->id, $hitAndRun->id);
        $bonusAfterFirst = (float) DB::table('users')->where('id', $user->id)->value('seedbonus');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already pardoned');
        try {
            $this->repository->consumeToCancelHitAndRun($user->id, $hitAndRun->id);
        } finally {
            $this->assertSame(
                $bonusAfterFirst,
                (float) DB::table('users')->where('id', $user->id)->value('seedbonus')
            );
        }
    }

    public function test_consume_to_cancel_hit_and_run_rejects_other_users_row(): void
    {
        $user = User::factory()->create(['seedbonus' => 10000.0]);
        $other = User::factory()->create();
        $hitAndRun = HitAndRun::factory()->create(['uid' => $other->id]);

        $this->expectException(\LogicException::class);
        $this->repository->consumeToCancelHitAndRun($user->id, $hitAndRun->id);
    }

    /**
     * B-04: the duplicate-buy check lives inside the transaction — a
     * second call always throws and never double-charges.
     */
    public function test_consume_to_buy_torrent_rejects_duplicate_purchase(): void
    {
        $owner = User::factory()->create();
        $torrent = Torrent::factory()->create(['owner' => $owner->id, 'price' => 100]);
        $buyer = User::factory()->create(['seedbonus' => 10000.0]);

        $buyLog = $this->repository->consumeToBuyTorrent($buyer->id, $torrent->id);

        $this->assertNotNull($buyLog);
        $this->assertSame(1, TorrentBuyLog::query()
            ->where('uid', $buyer->id)
            ->where('torrent_id', $torrent->id)
            ->count());
        $bonusAfterFirst = (float) DB::table('users')->where('id', $buyer->id)->value('seedbonus');

        try {
            $this->repository->consumeToBuyTorrent($buyer->id, $torrent->id);
            $this->fail('Expected NexusException for duplicate purchase');
        } catch (NexusException) {
            $this->assertSame(1, TorrentBuyLog::query()
                ->where('uid', $buyer->id)
                ->where('torrent_id', $torrent->id)
                ->count());
            $this->assertSame(
                $bonusAfterFirst,
                (float) DB::table('users')->where('id', $buyer->id)->value('seedbonus')
            );
        }
    }

    public function test_consume_to_buy_torrent_allows_different_buyers(): void
    {
        $owner = User::factory()->create();
        $torrent = Torrent::factory()->create(['owner' => $owner->id, 'price' => 100]);
        $buyerA = User::factory()->create(['seedbonus' => 10000.0]);
        $buyerB = User::factory()->create(['seedbonus' => 10000.0]);

        $this->repository->consumeToBuyTorrent($buyerA->id, $torrent->id);
        $this->repository->consumeToBuyTorrent($buyerB->id, $torrent->id);

        $this->assertSame(2, TorrentBuyLog::query()->where('torrent_id', $torrent->id)->count());
    }
}
