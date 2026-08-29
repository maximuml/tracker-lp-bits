<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\RewardController;
use App\Http\Requests\RewardRequest;
use App\Models\Reward;
use App\Models\User;
use App\Repositories\RewardRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class RewardControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_list_of_rewards(): void
    {
        $reward = new Reward;
        $reward->id = 1;
        $reward->torrentid = 10;
        $reward->userid = 5;
        $reward->value = 100;

        $collection = new Collection([$reward]);

        /** @var RewardRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(RewardRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with(['torrent_id' => 10])
            ->andReturn($collection);

        $controller = new RewardController($repository);
        $request = Request::create('/api/v1/rewards', 'GET', ['torrent_id' => 10]);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_index_validates_torrent_id_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var RewardRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(RewardRepository::class);
        $repository->shouldNotReceive('getList');

        $controller = new RewardController($repository);
        $request = Request::create('/api/v1/rewards', 'GET', []);

        $controller->index($request);
    }

    public function test_store_creates_reward_for_authenticated_user(): void
    {
        $user = new User;
        $user->id = 5;

        $reward = new Reward;
        $reward->id = 1;
        $reward->torrentid = 10;
        $reward->userid = 5;
        $reward->value = 100;

        /** @var RewardRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(RewardRepository::class);
        $repository->shouldReceive('store')
            ->once()
            ->with(10, 100.0, Mockery::type(User::class))
            ->andReturn($reward);

        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new RewardController($repository);
        $request = RewardRequest::create('/api/v1/rewards', 'POST', ['torrent_id' => 10, 'value' => 100]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_throws_when_user_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var RewardRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(RewardRepository::class);
        $repository->shouldNotReceive('store');

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new RewardController($repository);
        $request = RewardRequest::create('/api/v1/rewards', 'POST', ['torrent_id' => 10, 'value' => 100]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);
    }

    public function test_store_validates_torrent_id_and_value_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var RewardRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(RewardRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new RewardController($repository);
        $request = RewardRequest::create('/api/v1/rewards', 'POST', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);
    }
}
