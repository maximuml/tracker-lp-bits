<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Models\User;
use App\Repositories\UserStatRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UserStatRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserStatRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserStatRepository;
    }

    public function test_add_seed_accumulators_adds_rather_than_replaces(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 100, 'seed_points' => 50]);

        $this->repository->addSeedAccumulators($user->id, 1.5, 2.5);

        $row = DB::table('users')->where('id', $user->id)->first(['seedbonus', 'seed_points']);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(102.5, (float) $row->seedbonus, 0.001);
        $this->assertEqualsWithDelta(51.5, (float) $row->seed_points, 0.001);

        // A second application must add again — the accumulating columns
        // are shared with updatePoints()/credit(), so an absolute write
        // would silently lose any credit landed between job read and write.
        $this->repository->addSeedAccumulators($user->id, 1.0, 1.0);

        $row = DB::table('users')->where('id', $user->id)->first(['seedbonus', 'seed_points']);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(103.5, (float) $row->seedbonus, 0.001);
        $this->assertEqualsWithDelta(52.5, (float) $row->seed_points, 0.001);
    }

    public function test_upsert_seed_bonus_leaves_accumulators_untouched(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 100, 'seed_points' => 50]);

        $this->repository->upsertSeedBonus([[
            'id' => $user->id,
            'seed_points_per_hour' => 9.0,
            'seed_bonus_per_hour' => 3.0,
            'seeding_torrent_count' => 4,
            'seeding_torrent_size' => 1024.0,
            'seed_points_updated_at' => now()->toDateTimeString(),
        ]]);

        $row = DB::table('users')->where('id', $user->id)
            ->first(['seedbonus', 'seed_points', 'seed_points_per_hour', 'seeding_torrent_count']);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(100.0, (float) $row->seedbonus, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $row->seed_points, 0.001);
        $this->assertEqualsWithDelta(9.0, (float) $row->seed_points_per_hour, 0.001);
        $this->assertSame(4, (int) $row->seeding_torrent_count);
    }
}
