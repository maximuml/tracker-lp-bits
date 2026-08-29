<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AgentAllowController;
use App\Models\AgentAllow;
use App\Repositories\AgentAllowRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class AgentAllowControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_list_of_agent_allows(): void
    {
        $agentAllow = $this->makeAgentAllow(1);

        $paginator = new LengthAwarePaginator([$agentAllow], 1, 15, 1);

        /** @var AgentAllowRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentAllowRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($paginator);

        $controller = new AgentAllowController($repository);
        $request = Request::create('/api/v1/agent-allows', 'GET', []);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_creates_agent_allow(): void
    {
        $agentAllow = $this->makeAgentAllow(1);

        $data = $this->validData();

        /** @var AgentAllowRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentAllowRepository::class);
        $repository->shouldReceive('store')
            ->once()
            ->with($data)
            ->andReturn($agentAllow);

        $controller = new AgentAllowController($repository);
        $request = Request::create('/api/v1/agent-allows', 'POST', $data);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var AgentAllowRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentAllowRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new AgentAllowController($repository);
        $request = Request::create('/api/v1/agent-allows', 'POST', []);

        $controller->store($request);
    }

    public function test_store_validates_matchtype_enum(): void
    {
        $this->expectException(ValidationException::class);

        /** @var AgentAllowRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentAllowRepository::class);
        $repository->shouldNotReceive('store');

        $data = $this->validData();
        $data['peer_id_matchtype'] = 'invalid';
        $data['agent_matchtype'] = 'invalid';

        $controller = new AgentAllowController($repository);
        $request = Request::create('/api/v1/agent-allows', 'POST', $data);

        $controller->store($request);
    }

    public function test_update_modifies_agent_allow(): void
    {
        $agentAllow = $this->makeAgentAllow(1);

        $data = $this->validData();

        /** @var AgentAllowRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentAllowRepository::class);
        $repository->shouldReceive('update')
            ->once()
            ->with($data, 1)
            ->andReturn($agentAllow);

        $controller = new AgentAllowController($repository);
        $request = Request::create('/api/v1/agent-allows/1', 'PUT', $data);

        $result = $controller->update($request, 1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_destroy_removes_agent_allow(): void
    {
        /** @var AgentAllowRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentAllowRepository::class);
        $repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $controller = new AgentAllowController($repository);

        $result = $controller->destroy(1);

        $this->assertSame(0, $result['ret']);
    }

    public function test_check_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var AgentAllowRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentAllowRepository::class);
        $repository->shouldNotReceive('checkClient');

        $controller = new AgentAllowController($repository);
        $request = Request::create('/api/v1/agent-allows/check', 'POST', []);

        $controller->check($request);
    }

    /**
     * Create an AgentAllow model with all attributes needed by AgentAllowResource.
     */
    private function makeAgentAllow(int $id): AgentAllow
    {
        $agentAllow = new AgentAllow;
        $agentAllow->id = $id;
        $agentAllow->family = 'uTorrent';
        $agentAllow->start_name = 'uTorrent 3.0';
        $agentAllow->peer_id_pattern = '-UT3';
        $agentAllow->peer_id_match_num = 4;
        $agentAllow->peer_id_matchtype = 'dec';
        $agentAllow->peer_id_start = 'UT3100';
        $agentAllow->agent_pattern = 'uTorrent/3.0';
        $agentAllow->agent_match_num = 4;
        $agentAllow->agent_matchtype = 'dec';
        $agentAllow->agent_start = 'uTorrent/3.0';
        $agentAllow->exception = 'no';
        $agentAllow->comment = 'Test';
        $agentAllow->allowhttps = 'yes';
        $agentAllow->hits = 0;

        return $agentAllow;
    }

    /**
     * Valid data for store/update requests.
     *
     * @return array<string, mixed>
     */
    private function validData(): array
    {
        return [
            'family' => 'uTorrent',
            'start_name' => 'uTorrent 3.0',
            'peer_id_pattern' => '-UT3',
            'peer_id_match_num' => 4,
            'peer_id_matchtype' => 'dec',
            'peer_id_start' => 'UT3100',
            'agent_pattern' => 'uTorrent/3.0',
            'agent_match_num' => 4,
            'agent_matchtype' => 'dec',
            'agent_start' => 'uTorrent/3.0',
            'exception' => 'no',
            'allowhttps' => 'yes',
        ];
    }
}
