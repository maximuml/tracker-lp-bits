<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Enums\TorrentApprovalStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentApprovalRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pins TorrentApprovalRepository — including the approval-log paginator
 * (regression: it used to filter by a nonexistent `tid` column).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TorrentApprovalRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentApprovalRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(TorrentApprovalRepository::class);
    }

    public function test_should_show_approval_status_icon_returns_bool(): void
    {
        $this->assertIsBool($this->repository->shouldShowApprovalStatusIcon(TorrentApprovalStatus::ALLOW->value));
        $this->assertIsBool($this->repository->shouldShowApprovalStatusIcon(TorrentApprovalStatus::DENY->value));
    }

    public function test_get_approval_deny_count_counts_only_owner_denies(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();

        Torrent::factory()->create(['owner' => $owner->id, 'approval_status' => TorrentApprovalStatus::DENY->value]);
        Torrent::factory()->create(['owner' => $owner->id, 'approval_status' => TorrentApprovalStatus::ALLOW->value]);
        Torrent::factory()->create(['owner' => $other->id, 'approval_status' => TorrentApprovalStatus::DENY->value]);

        $this->assertSame(1, $this->repository->getApprovalDenyCount((int) $owner->id));
        $this->assertSame(1, $this->repository->getApprovalDenyCount((int) $other->id));
    }

    public function test_list_deny_reasons_orders_by_priority_desc(): void
    {
        DB::table('torrent_deny_reasons')->insert([
            ['name' => 'low', 'hits' => 0, 'priority' => 1],
            ['name' => 'high', 'hits' => 0, 'priority' => 9],
        ]);

        $reasons = $this->repository->listDenyReasons();

        $this->assertGreaterThanOrEqual(2, $reasons->count());
        $this->assertSame('high', $reasons->first()->name);
    }

    public function test_paginate_approval_logs_filters_torrent_and_action_types(): void
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        /** @var Torrent $otherTorrent */
        $otherTorrent = Torrent::factory()->create();
        /** @var User $operator */
        $operator = User::factory()->create();

        DB::table('torrent_operation_logs')->insert([
            ['torrent_id' => $torrent->id, 'uid' => $operator->id, 'action_type' => 'approval_allow', 'comment' => 'a'],
            ['torrent_id' => $torrent->id, 'uid' => $operator->id, 'action_type' => 'unrelated', 'comment' => 'b'],
            ['torrent_id' => $otherTorrent->id, 'uid' => $operator->id, 'action_type' => 'approval_allow', 'comment' => 'c'],
        ]);

        $page = $this->repository->paginateApprovalLogs((int) $torrent->id, ['approval_allow'], 10);

        $this->assertSame(1, $page->total());
        $this->assertSame('a', $page->items()[0]->comment);
    }
}
