<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Reward;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\RewardRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for RewardRepository.
 *
 * Covers getList(), store(), update(), getDetail(), and delete().
 */
final class RewardRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private RewardRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('magic')->delete();
        DB::table('torrents')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new RewardRepository;
    }

    private function createTorrent(int $ownerId): Torrent
    {
        $id = (int) DB::table('torrents')->insertGetId([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $ownerId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'added' => now()->toDateTimeString(),
        ]);

        return Torrent::query()->findOrFail($id);
    }

    private function createReward(int $torrentId, int $userId, float $value): Reward
    {
        return Reward::query()->create([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'value' => $value,
        ]);
    }

    public function test_get_list_returns_paginated_rewards(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $owner */
        $owner = User::factory()->create();
        $torrent = $this->createTorrent($owner->id);

        $this->createReward($torrent->id, $user->id, 100.0);

        $result = $this->repository->getList([]);

        $this->assertGreaterThan(0, $result->count());
    }

    public function test_get_list_filters_by_torrent_id(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $owner */
        $owner = User::factory()->create();
        $torrent1 = $this->createTorrent($owner->id);
        $torrent2 = $this->createTorrent($owner->id);

        $this->createReward($torrent1->id, $user->id, 100.0);
        $this->createReward($torrent2->id, $user->id, 200.0);

        $result = $this->repository->getList(['torrent_id' => $torrent1->id]);

        foreach ($result as $reward) {
            $this->assertSame($torrent1->id, (int) $reward->torrentid);
        }
    }

    public function test_store_throws_when_bonus_not_enough(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 50.0]);
        /** @var User $owner */
        $owner = User::factory()->create();
        $torrent = $this->createTorrent($owner->id);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('your bonus not enough.');

        $this->repository->store($torrent->id, 100.0, $user);
    }

    public function test_store_throws_when_already_rewarded(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 500.0]);
        /** @var User $owner */
        $owner = User::factory()->create();
        $torrent = $this->createTorrent($owner->id);

        $this->createReward($torrent->id, $user->id, 100.0);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('you already reward this torrent.');

        $this->repository->store($torrent->id, 100.0, $user);
    }

    public function test_store_throws_when_rewarding_own_torrent(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 500.0]);
        $torrent = $this->createTorrent($user->id);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("you can't reward to yourself.");

        $this->repository->store($torrent->id, 100.0, $user);
    }

    public function test_store_succeeds_and_transfers_bonus(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 500.0]);
        /** @var User $owner */
        $owner = User::factory()->create(['seedbonus' => 0.0]);
        $torrent = $this->createTorrent($owner->id);

        $result = $this->repository->store($torrent->id, 100.0, $user);

        $this->assertInstanceOf(Reward::class, $result);
        $this->assertSame($torrent->id, (int) $result->torrentid);
        $this->assertSame($user->id, (int) $result->userid);

        $user->refresh();
        $owner->refresh();

        $this->assertSame(400.0, (float) $user->seedbonus);
        $this->assertSame(100.0, (float) $owner->seedbonus);
    }

    public function test_update_modifies_reward(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $owner */
        $owner = User::factory()->create();
        $torrent = $this->createTorrent($owner->id);
        $reward = $this->createReward($torrent->id, $user->id, 100.0);

        $result = $this->repository->update(['value' => 200.0], $reward->id);

        $this->assertSame(200.0, (float) $result->value);
    }

    public function test_get_detail_returns_reward(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $owner */
        $owner = User::factory()->create();
        $torrent = $this->createTorrent($owner->id);
        $reward = $this->createReward($torrent->id, $user->id, 100.0);

        $result = $this->repository->getDetail($reward->id);

        $this->assertSame($reward->id, $result->id);
        $this->assertSame(100.0, (float) $result->value);
    }

    public function test_get_detail_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getDetail(99999);
    }

    public function test_delete_removes_reward(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $owner */
        $owner = User::factory()->create();
        $torrent = $this->createTorrent($owner->id);
        $reward = $this->createReward($torrent->id, $user->id, 100.0);

        $result = $this->repository->delete($reward->id);

        $this->assertTrue($result);
        $this->assertFalse(Reward::query()->where('id', $reward->id)->exists());
    }

    public function test_delete_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->delete(99999);
    }
}
