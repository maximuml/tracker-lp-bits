<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\UserMedalController;
use App\Http\Requests\UserMedalStoreRequest;
use App\Http\Requests\UserMedalUpdateRequest;
use App\Models\Medal;
use App\Repositories\MedalRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class UserMedalControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_list_of_user_medals(): void
    {
        $medal = $this->makeMedal(1);

        $paginator = new LengthAwarePaginator([$medal], 1, 15, 1);

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($paginator);

        $controller = new UserMedalController($repository);
        $request = Request::create('/api/v1/user-medals', 'GET', []);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_grants_medal_to_user(): void
    {
        $data = ['medal_id' => 1, 'uid' => 5, 'duration' => 30];

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldReceive('grantToUser')
            ->once()
            ->with(5, 1, 30)
            ->andReturn(true);

        $controller = new UserMedalController($repository);
        $request = UserMedalStoreRequest::create('/api/v1/user-medals', 'POST', $data);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldNotReceive('grantToUser');

        $controller = new UserMedalController($repository);
        $request = UserMedalStoreRequest::create('/api/v1/user-medals', 'POST', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);
    }

    public function test_show_returns_medal_detail(): void
    {
        $medal = $this->makeMedal(1);

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldReceive('getDetail')
            ->once()
            ->with(1)
            ->andReturn($medal);

        $controller = new UserMedalController($repository);

        $result = $controller->show(1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_update_modifies_medal(): void
    {
        $medal = $this->makeMedal(1);

        $data = [
            'name' => 'Updated Medal',
            'price' => 200,
            'image_large' => 'https://example.com/large.png',
            'image_small' => 'https://example.com/small.png',
        ];

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldReceive('update')
            ->once()
            ->with($data, 1)
            ->andReturn($medal);

        $controller = new UserMedalController($repository);
        $request = UserMedalUpdateRequest::create('/api/v1/user-medals/1', 'PUT', $data);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->update($request, 1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_update_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var MedalRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MedalRepository::class);
        $repository->shouldNotReceive('update');

        $controller = new UserMedalController($repository);
        $request = UserMedalUpdateRequest::create('/api/v1/user-medals/1', 'PUT', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->update($request, 1);
    }

    /**
     * Create a Medal model with all attributes needed by MedalResource.
     */
    private function makeMedal(int $id): Medal
    {
        $medal = new Medal;
        $medal->id = $id;
        $medal->name = 'Test Medal';
        $medal->price = 100;
        $medal->get_type = 0;
        $medal->image_large = 'https://example.com/large.png';
        $medal->image_small = 'https://example.com/small.png';
        $medal->duration = 30;
        $medal->description = 'Test';

        return $medal;
    }
}
