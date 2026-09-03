<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\ClientNotAllowedException;
use App\Models\AgentAllow;
use App\Models\AgentDeny;
use App\Repositories\AgentAllowRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for AgentAllowRepository.
 *
 * Covers getList(), store(), update(), getDetail(), delete(),
 * getPatternMatches(), and checkClientSimple().
 */
final class AgentAllowRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private AgentAllowRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        AgentAllow::flushEventListeners();
        AgentDeny::flushEventListeners();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('agent_allowed_exception')->delete();
        DB::table('agent_allowed_family')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        Cache::flush();
        $this->repository = new AgentAllowRepository;
    }

    public function test_get_list_returns_paginated_results(): void
    {
        $this->insertAgentAllow(['family' => 'qBittorrent', 'start_name' => 'v4.3']);
        $this->insertAgentAllow(['family' => 'Transmission', 'start_name' => 'v3.0']);

        $paginator = $this->repository->getList([]);

        $this->assertCount(2, $paginator->items());
    }

    public function test_get_list_filters_by_family(): void
    {
        $this->insertAgentAllow(['family' => 'qBittorrent', 'start_name' => 'v4.3']);
        $this->insertAgentAllow(['family' => 'Transmission', 'start_name' => 'v3.0']);

        $paginator = $this->repository->getList(['family' => 'qBit']);

        $items = $paginator->items();
        $this->assertCount(1, $items);
        $this->assertSame('qBittorrent', $items[0]->family);
    }

    public function test_get_list_sorts_by_allowed_columns(): void
    {
        $first = $this->insertAgentAllow(['family' => 'Alpha', 'start_name' => 'v1']);
        $this->insertAgentAllow(['family' => 'Beta', 'start_name' => 'v2']);

        $paginator = $this->repository->getList(['sort_field' => 'family', 'sort_type' => 'asc']);

        $items = $paginator->items();
        $this->assertSame('Alpha', $items[0]->family);
        $this->assertSame($first, (int) $items[0]->id);
    }

    public function test_get_list_falls_back_to_id_for_invalid_sort(): void
    {
        $first = $this->insertAgentAllow(['family' => 'Alpha', 'start_name' => 'v1']);
        $this->insertAgentAllow(['family' => 'Beta', 'start_name' => 'v2']);

        $paginator = $this->repository->getList(['sort_field' => 'evil', 'sort_type' => 'asc']);

        $items = $paginator->items();
        $this->assertSame($first, (int) $items[0]->id);
    }

    public function test_store_creates_agent_allow(): void
    {
        $model = $this->repository->store([
            'family' => 'NewClient',
            'start_name' => 'v1.0',
            'peer_id_pattern' => '/^-QB/',
            'peer_id_start' => '-QB4500',
            'peer_id_match_num' => 0,
            'peer_id_matchtype' => 'dec',
            'agent_pattern' => '/qBittorrent\/4/',
            'agent_start' => 'qBittorrent/4.5.0',
            'agent_match_num' => 0,
            'agent_matchtype' => 'dec',
            'exception' => 0,
            'allowhttps' => 1,
            'comment' => 'test',
        ]);

        $this->assertInstanceOf(AgentAllow::class, $model);
        $this->assertDatabaseHas('agent_allowed_family', [
            'family' => 'NewClient',
            'start_name' => 'v1.0',
        ]);
    }

    public function test_store_throws_when_pattern_does_not_match_start(): void
    {
        $this->expectException(ClientNotAllowedException::class);

        $this->repository->store([
            'family' => 'BadClient',
            'start_name' => 'v1.0',
            'peer_id_pattern' => '/^-XX/',
            'peer_id_start' => '-QB4500',
            'peer_id_match_num' => 0,
            'peer_id_matchtype' => 'dec',
            'agent_pattern' => '/qBittorrent/',
            'agent_start' => 'qBittorrent/4.5.0',
            'agent_match_num' => 0,
            'agent_matchtype' => 'dec',
            'exception' => 0,
            'allowhttps' => 1,
            'comment' => '',
        ]);
    }

    public function test_update_modifies_agent_allow(): void
    {
        $id = $this->insertAgentAllow(['family' => 'Original', 'start_name' => 'v1']);

        $model = $this->repository->update([
            'family' => 'Updated',
            'start_name' => 'v2',
            'peer_id_pattern' => '/^-QB/',
            'peer_id_start' => '-QB4500',
            'peer_id_match_num' => 0,
            'peer_id_matchtype' => 'dec',
            'agent_pattern' => '/qBittorrent/',
            'agent_start' => 'qBittorrent/4.5.0',
            'agent_match_num' => 0,
            'agent_matchtype' => 'dec',
            'exception' => 0,
            'allowhttps' => 1,
            'comment' => 'updated',
        ], $id);

        $this->assertSame($id, $model->id);
        $this->assertSame('Updated', $model->family);
    }

    public function test_get_detail_returns_model_when_found(): void
    {
        $id = $this->insertAgentAllow(['family' => 'DetailClient', 'start_name' => 'v1']);

        $model = $this->repository->getDetail($id);

        $this->assertInstanceOf(AgentAllow::class, $model);
        $this->assertSame($id, $model->id);
        $this->assertSame('DetailClient', $model->family);
    }

    public function test_get_detail_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getDetail(999999);
    }

    public function test_delete_removes_agent_allow(): void
    {
        $id = $this->insertAgentAllow(['family' => 'DeleteMe', 'start_name' => 'v1']);

        $result = $this->repository->delete($id);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('agent_allowed_family')->where('id', $id)->count());
    }

    public function test_delete_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->delete(999999);
    }

    public function test_delete_cascades_to_denies(): void
    {
        $id = $this->insertAgentAllow(['family' => 'WithDenies', 'start_name' => 'v1']);
        DB::table('agent_allowed_exception')->insert([
            'family_id' => $id,
            'name' => 'BadPeer',
            'peer_id' => '-BAD',
            'agent' => 'BadAgent',
            'comment' => '',
        ]);

        $this->repository->delete($id);

        $this->assertSame(0, DB::table('agent_allowed_exception')->where('family_id', $id)->count());
    }

    public function test_get_pattern_matches_returns_captured_groups(): void
    {
        $matches = $this->repository->getPatternMatches('/^qBittorrent\/(\d+)\.(\d+)/', 'qBittorrent/4.5.0', 2);

        $this->assertSame(['4', '5'], $matches);
    }

    public function test_get_pattern_matches_throws_when_no_match(): void
    {
        $this->expectException(ClientNotAllowedException::class);

        $this->repository->getPatternMatches('/^-XX/', '-QB4500', 0);
    }

    public function test_get_pattern_matches_slices_to_match_num(): void
    {
        $matches = $this->repository->getPatternMatches('/^qBittorrent\/(\d+)\.(\d+)\.(\d+)/', 'qBittorrent/4.5.0.1', 2);

        $this->assertSame(['4', '5'], $matches);
    }

    public function test_check_client_simple_throws_when_no_allows(): void
    {
        $this->expectException(ClientNotAllowedException::class);

        $this->repository->checkClientSimple('-QB4500', 'qBittorrent/4.5.0');
    }

    public function test_check_client_simple_passes_when_pattern_matches(): void
    {
        $this->insertAgentAllow([
            'family' => 'qBittorrent',
            'start_name' => 'v4.5',
            'peer_id_pattern' => '/^-QB/',
            'peer_id_start' => '-QB4500',
            'agent_pattern' => '/qBittorrent\/4/',
            'agent_start' => 'qBittorrent/4.5.0',
            'exception' => 0,
            'allowhttps' => 1,
        ]);

        $result = $this->repository->checkClientSimple('-QB4500', 'qBittorrent/4.5.0');

        $this->assertInstanceOf(AgentAllow::class, $result);
        $this->assertSame('qBittorrent', $result->family);
    }

    public function test_check_client_simple_throws_when_no_pattern_matches(): void
    {
        $this->insertAgentAllow([
            'family' => 'qBittorrent',
            'start_name' => 'v4.5',
            'peer_id_pattern' => '/^-QB/',
            'peer_id_start' => '-QB4500',
            'agent_pattern' => '/qBittorrent\/4/',
            'agent_start' => 'qBittorrent/4.5.0',
            'exception' => 0,
            'allowhttps' => 1,
        ]);

        $this->expectException(ClientNotAllowedException::class);

        $this->repository->checkClientSimple('-XX1234', 'UnknownClient/1.0');
    }

    public function test_check_client_simple_throws_when_denied_by_exception(): void
    {
        $id = $this->insertAgentAllow([
            'family' => 'qBittorrent',
            'start_name' => 'v4.5',
            'peer_id_pattern' => '/^-QB/',
            'peer_id_start' => '-QB4500',
            'agent_pattern' => '/qBittorrent\/4/',
            'agent_start' => 'qBittorrent/4.5.0',
            'exception' => 1,
            'allowhttps' => 1,
        ]);
        DB::table('agent_allowed_exception')->insert([
            'family_id' => $id,
            'name' => 'BannedPeer',
            'peer_id' => '-QB4500',
            'agent' => 'qBittorrent/4.5.0',
            'comment' => 'banned version',
        ]);

        $this->expectException(ClientNotAllowedException::class);

        $this->repository->checkClientSimple('-QB4500', 'qBittorrent/4.5.0');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertAgentAllow(array $overrides = []): int
    {
        return (int) DB::table('agent_allowed_family')->insertGetId(array_merge([
            'family' => 'TestClient',
            'start_name' => 'v1.0',
            'peer_id_pattern' => '',
            'peer_id_match_num' => 0,
            'peer_id_matchtype' => 'dec',
            'peer_id_start' => '',
            'agent_pattern' => '',
            'agent_match_num' => 0,
            'agent_matchtype' => 'dec',
            'agent_start' => '',
            'exception' => 0,
            'allowhttps' => 1,
            'comment' => '',
            'hits' => 0,
        ], $overrides));
    }
}
