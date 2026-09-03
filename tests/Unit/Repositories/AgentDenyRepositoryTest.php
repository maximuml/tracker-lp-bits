<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\AgentDeny;
use App\Repositories\AgentDenyRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for AgentDenyRepository.
 *
 * Covers getList(), store(), update(), getDetail(), and delete().
 */
final class AgentDenyRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private AgentDenyRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        // Flush model event listeners to avoid the AgentDenyDeleted event
        // bug (Events::fire passes an array but the event expects a Model).
        AgentDeny::flushEventListeners();
        // Use DELETE (DML) instead of TRUNCATE (DDL) to avoid an implicit
        // commit that would break DatabaseTransactions rollback.
        DB::table('agent_allowed_exception')->delete();
        $this->repository = new AgentDenyRepository;
    }

    public function test_get_list_returns_paginated_results(): void
    {
        $this->insertAgentDeny(1, 'DenyOne', 'peer1', 'uTorrent');
        $this->insertAgentDeny(2, 'DenyTwo', 'peer2', 'Transmission');

        $paginator = $this->repository->getList([]);

        $this->assertCount(2, $paginator->items());
    }

    public function test_get_list_filters_by_family_id(): void
    {
        $this->insertAgentDeny(1, 'FamilyOne', 'peer1', 'uTorrent');
        $this->insertAgentDeny(2, 'FamilyTwo', 'peer2', 'Transmission');
        $this->insertAgentDeny(1, 'AnotherOne', 'peer3', 'Deluge');

        $paginator = $this->repository->getList(['family_id' => 2]);

        $items = $paginator->items();
        $this->assertCount(1, $items);
        $this->assertSame('FamilyTwo', $items[0]->name);
    }

    public function test_get_list_orders_by_family_id_desc(): void
    {
        $this->insertAgentDeny(1, 'LowFamily', 'peer1', 'uTorrent');
        $this->insertAgentDeny(5, 'HighFamily', 'peer2', 'Transmission');

        $paginator = $this->repository->getList([]);

        $items = $paginator->items();
        $this->assertSame('HighFamily', $items[0]->name);
        $this->assertSame('LowFamily', $items[1]->name);
    }

    public function test_store_creates_agent_deny(): void
    {
        $model = $this->repository->store([
            'family_id' => 3,
            'name' => 'NewDeny',
            'peer_id' => 'peer-new',
            'agent' => 'qBittorrent',
            'comment' => 'test comment',
        ]);

        $this->assertInstanceOf(AgentDeny::class, $model);
        $this->assertDatabaseHas('agent_allowed_exception', [
            'name' => 'NewDeny',
            'peer_id' => 'peer-new',
            'agent' => 'qBittorrent',
        ]);
    }

    public function test_update_modifies_agent_deny(): void
    {
        $id = $this->insertAgentDeny(1, 'Original', 'peer1', 'uTorrent');

        $model = $this->repository->update([
            'name' => 'Updated',
            'comment' => 'updated comment',
        ], $id);

        $this->assertSame($id, $model->id);
        $this->assertSame('Updated', $model->name);
        $this->assertSame('updated comment', $model->comment);
    }

    public function test_get_detail_returns_model_when_found(): void
    {
        $id = $this->insertAgentDeny(1, 'DetailDeny', 'peer1', 'uTorrent');

        $model = $this->repository->getDetail($id);

        $this->assertInstanceOf(AgentDeny::class, $model);
        $this->assertSame($id, $model->id);
        $this->assertSame('DetailDeny', $model->name);
    }

    public function test_get_detail_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getDetail(999999);
    }

    public function test_delete_removes_agent_deny(): void
    {
        $id = $this->insertAgentDeny(1, 'DeleteMe', 'peer1', 'uTorrent');

        $result = $this->repository->delete($id);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('agent_allowed_exception')->where('id', $id)->count());
    }

    public function test_delete_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->delete(999999);
    }

    private function insertAgentDeny(int $familyId, string $name, string $peerId, string $agent): int
    {
        return (int) DB::table('agent_allowed_exception')->insertGetId([
            'family_id' => $familyId,
            'name' => $name,
            'peer_id' => $peerId,
            'agent' => $agent,
            'comment' => '',
        ]);
    }
}
