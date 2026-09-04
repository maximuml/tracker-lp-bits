<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\HitAndRunStatus;
use App\Models\HitAndRun;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unit tests for HitAndRunRepository.
 *
 * Covers getList(), store(), update(), getDetail(), delete().
 */
final class HitAndRunRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private HitAndRunRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new HitAndRunRepository;
    }

    public function test_get_list_returns_paginated_hit_and_runs(): void
    {
        HitAndRun::factory()->create();

        $result = $this->repository->getList([]);

        $this->assertNotNull($result);
        $this->assertGreaterThanOrEqual(1, $result->total());
    }

    public function test_get_list_filters_by_status(): void
    {
        $hr1 = HitAndRun::factory()->create(['status' => HitAndRunStatus::INSPECTING->value]);
        $hr2 = HitAndRun::factory()->create(['status' => HitAndRunStatus::REACHED->value]);

        $result = $this->repository->getList(['status' => HitAndRunStatus::INSPECTING->value]);

        foreach ($result->items() as $item) {
            $this->assertSame(HitAndRunStatus::INSPECTING->value, $item->status);
        }
    }

    public function test_get_list_filters_by_uid(): void
    {
        $hr1 = HitAndRun::factory()->create();
        $hr2 = HitAndRun::factory()->create();

        $result = $this->repository->getList(['uid' => $hr1->uid]);

        foreach ($result->items() as $item) {
            $this->assertSame($hr1->uid, $item->uid);
        }
    }

    public function test_get_list_filters_by_torrent_id(): void
    {
        $hr1 = HitAndRun::factory()->create();
        $hr2 = HitAndRun::factory()->create();

        $result = $this->repository->getList(['torrent_id' => $hr1->torrent_id]);

        foreach ($result->items() as $item) {
            $this->assertSame($hr1->torrent_id, $item->torrent_id);
        }
    }

    public function test_store_creates_new_hit_and_run(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $snatch = Snatch::query()->create([
            'torrentid' => $torrent->id,
            'userid' => $user->id,
            'ip' => '127.0.0.1',
            'port' => 54321,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'startdat' => now()->toDateTimeString(),
            'last_action' => now()->toDateTimeString(),
            'finished' => true,
        ]);

        $model = $this->repository->store([
            'uid' => $user->id,
            'snatched_id' => $snatch->id,
            'torrent_id' => $torrent->id,
            'status' => HitAndRunStatus::INSPECTING->value,
            'comment' => 'Test H&R',
        ]);

        $this->assertDatabaseHas('hit_and_runs', [
            'id' => $model->id,
            'uid' => $user->id,
            'torrent_id' => $torrent->id,
            'comment' => 'Test H&R',
        ]);
    }

    public function test_update_modifies_hit_and_run(): void
    {
        $model = HitAndRun::factory()->create([
            'status' => HitAndRunStatus::INSPECTING->value,
        ]);

        $updated = $this->repository->update([
            'status' => HitAndRunStatus::REACHED->value,
            'comment' => 'Updated comment',
        ], $model->id);

        $this->assertSame(HitAndRunStatus::REACHED->value, $updated->status);
        $this->assertSame('Updated comment', $updated->comment);
    }

    public function test_get_detail_returns_hit_and_run_with_relations(): void
    {
        $model = HitAndRun::factory()->create();

        $found = $this->repository->getDetail($model->id);

        $this->assertSame($model->id, $found->id);
        $this->assertTrue($found->relationLoaded('user'));
        $this->assertTrue($found->relationLoaded('torrent'));
    }

    public function test_get_detail_throws_for_nonexistent_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getDetail(999999);
    }

    public function test_delete_removes_hit_and_run(): void
    {
        $model = HitAndRun::factory()->create();

        $result = $this->repository->delete($model->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('hit_and_runs', ['id' => $model->id]);
    }

    public function test_delete_throws_for_nonexistent_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->delete(999999);
    }
}
