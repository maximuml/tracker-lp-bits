<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AgentDenyController;
use App\Models\AgentDeny;
use App\Repositories\AgentDenyRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class AgentDenyControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_list_of_agent_denies(): void
    {
        $agentDeny = new AgentDeny;
        $agentDeny->id = 1;
        $agentDeny->family_id = 1;
        $agentDeny->name = 'Test';
        $agentDeny->peer_id = 'peer123';
        $agentDeny->agent = 'uTorrent';
        $agentDeny->comment = 'Test comment';

        $collection = new Collection([$agentDeny]);

        /** @var AgentDenyRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentDenyRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($collection);

        $controller = new AgentDenyController($repository);
        $request = Request::create('/api/v1/agent-denies', 'GET', []);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_creates_agent_deny(): void
    {
        $agentDeny = new AgentDeny;
        $agentDeny->id = 1;
        $agentDeny->family_id = 1;
        $agentDeny->name = 'Test';
        $agentDeny->peer_id = 'peer123';
        $agentDeny->agent = 'uTorrent';
        $agentDeny->comment = 'Test comment';

        $data = [
            'family_id' => 1,
            'name' => 'Test',
            'peer_id' => 'peer123',
            'agent' => 'uTorrent',
            'comment' => 'Test comment',
        ];

        /** @var AgentDenyRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentDenyRepository::class);
        $repository->shouldReceive('store')
            ->once()
            ->with($data)
            ->andReturn($agentDeny);

        $controller = new AgentDenyController($repository);
        $request = Request::create('/api/v1/agent-denies', 'POST', $data);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var AgentDenyRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentDenyRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new AgentDenyController($repository);
        $request = Request::create('/api/v1/agent-denies', 'POST', []);

        $controller->store($request);
    }

    public function test_update_modifies_agent_deny(): void
    {
        $agentDeny = new AgentDeny;
        $agentDeny->id = 1;
        $agentDeny->family_id = 1;
        $agentDeny->name = 'Updated';
        $agentDeny->peer_id = 'peer123';
        $agentDeny->agent = 'uTorrent';
        $agentDeny->comment = 'Updated comment';

        $data = [
            'family_id' => 1,
            'name' => 'Updated',
            'peer_id' => 'peer123',
            'agent' => 'uTorrent',
            'comment' => 'Updated comment',
        ];

        /** @var AgentDenyRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentDenyRepository::class);
        $repository->shouldReceive('update')
            ->once()
            ->with($data, 1)
            ->andReturn($agentDeny);

        $controller = new AgentDenyController($repository);
        $request = Request::create('/api/v1/agent-denies/1', 'PUT', $data);

        $result = $controller->update($request, 1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_update_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var AgentDenyRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentDenyRepository::class);
        $repository->shouldNotReceive('update');

        $controller = new AgentDenyController($repository);
        $request = Request::create('/api/v1/agent-denies/1', 'PUT', []);

        $controller->update($request, 1);
    }

    public function test_destroy_removes_agent_deny(): void
    {
        /** @var AgentDenyRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(AgentDenyRepository::class);
        $repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $controller = new AgentDenyController($repository);

        $result = $controller->destroy(1);

        $this->assertSame(0, $result['ret']);
    }
}
