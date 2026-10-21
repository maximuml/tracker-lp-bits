<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Http\Controllers\RewardController;
use App\Http\Requests\RewardIndexRequest;
use App\Http\Requests\RewardRequest;
use App\Models\Reward;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\RewardRepository;
use App\Services\MagicRewardService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
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

        $controller = new RewardController($repository, app(MagicRewardService::class));
        $request = RewardIndexRequest::create('/api/v1/rewards', 'GET', ['torrent_id' => 10]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

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

        $controller = new RewardController($repository, app(MagicRewardService::class));
        $request = RewardIndexRequest::create('/api/v1/rewards', 'GET', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->index($request);
    }

    public function test_store_creates_reward_for_authenticated_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['seedbonus' => 500]);
        /** @var User $owner */
        $owner = User::factory()->create(['seedbonus' => 0]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create(['owner' => $owner->id]);

        Auth::shouldReceive('user')->andReturn($user);

        $controller = new RewardController(app(RewardRepository::class), app(MagicRewardService::class));
        $request = RewardRequest::create('/api/v1/rewards', 'POST', ['torrent_id' => $torrent->id, 'value' => 100]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame(400.0, (float) DB::table('users')->where('id', $user->id)->value('seedbonus'));
        $this->assertSame(100.0, (float) DB::table('users')->where('id', $owner->id)->value('seedbonus'));
        $this->assertDatabaseHas('magic', ['torrentid' => $torrent->id, 'userid' => $user->id, 'value' => 100]);
    }

    public function test_store_throws_when_user_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new RewardController(app(RewardRepository::class), app(MagicRewardService::class));
        $request = RewardRequest::create('/api/v1/rewards', 'POST', ['torrent_id' => 10, 'value' => 100]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);
    }

    public function test_store_validates_torrent_id_and_value_required(): void
    {
        $this->expectException(ValidationException::class);

        $controller = new RewardController(app(RewardRepository::class), app(MagicRewardService::class));
        $request = RewardRequest::create('/api/v1/rewards', 'POST', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);
    }
}
