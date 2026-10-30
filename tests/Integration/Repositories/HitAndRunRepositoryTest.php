<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Enums\HitAndRunStatus;
use App\Events\HitAndRunCreated;
use App\Events\HitAndRunDeleted;
use App\Events\HitAndRunUpdated;
use App\Models\HitAndRun;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Support\Settings;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for HitAndRunRepository.
 *
 * Covers getList(), store(), update(), getDetail(), delete().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class HitAndRunRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private HitAndRunRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(HitAndRunRepository::class);
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

    /**
     * M4: lifecycle events must map to the real model transition — a create
     * dispatches HitAndRunCreated (previously everything came out as
     * HitAndRunUpdated via the `saved` hook).
     */
    public function test_store_dispatches_hit_and_run_created_event(): void
    {
        Event::fake([HitAndRunCreated::class, HitAndRunUpdated::class, HitAndRunDeleted::class]);

        $model = HitAndRun::factory()->create();

        Event::assertDispatched(HitAndRunCreated::class, fn ($e) => $e->model->id === $model->id);
        Event::assertNotDispatched(HitAndRunUpdated::class);
        Event::assertNotDispatched(HitAndRunDeleted::class);
    }

    public function test_update_dispatches_hit_and_run_updated_event(): void
    {
        $model = HitAndRun::factory()->create(['status' => HitAndRunStatus::INSPECTING->value]);
        Event::fake([HitAndRunCreated::class, HitAndRunUpdated::class, HitAndRunDeleted::class]);

        $this->repository->update(['status' => HitAndRunStatus::REACHED->value], $model->id);

        Event::assertDispatched(HitAndRunUpdated::class);
        Event::assertNotDispatched(HitAndRunCreated::class);
        Event::assertNotDispatched(HitAndRunDeleted::class);
    }

    /**
     * M4: delete dispatches exactly once — the repository used to fire a
     * second manual clearCache() on top of the model `deleted` hook.
     */
    public function test_delete_dispatches_hit_and_run_deleted_once(): void
    {
        $model = HitAndRun::factory()->create();
        Event::fake([HitAndRunCreated::class, HitAndRunUpdated::class, HitAndRunDeleted::class]);

        $this->repository->delete($model->id);

        Event::assertDispatchedTimes(HitAndRunDeleted::class, 1);
        Event::assertNotDispatched(HitAndRunUpdated::class);
    }

    /**
     * M5: H&R configuration is global — getConfig takes no section argument
     * and reads the flat `hr.*` settings keys.
     */
    public function test_get_config_reads_global_hr_settings(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'hr.inspect_time'],
            ['value' => '48', 'autoload' => 1],
        );
        Settings::resetCache();

        $this->assertSame('48', HitAndRun::getConfig('inspect_time'));
    }
}
