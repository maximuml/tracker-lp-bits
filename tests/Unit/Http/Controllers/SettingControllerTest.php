<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Enums\UserClass as UserClassEnum;
use App\Http\Controllers\SettingController;
use App\Http\Requests\SettingIndexRequest;
use App\Http\Requests\SettingStoreRequest;
use App\Models\User;
use App\Repositories\SettingRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class SettingControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function actingAsUserClass(int $class): void
    {
        $user = new User;
        $user->id = 1;
        $user->class = $class;
        Auth::shouldReceive('user')->andReturn($user);
    }

    public function test_index_returns_settings_list(): void
    {
        $this->actingAsUserClass(UserClassEnum::SYSOP->value);
        $settings = ['hr' => ['inspect_time' => 72]];

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($settings);

        $controller = new SettingController($repository);
        $request = SettingIndexRequest::create('/api/v1/settings', 'GET', []);
        $request->setContainer(app());
        $request->validateResolved();

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_index_returns_filtered_settings_by_prefix(): void
    {
        $this->actingAsUserClass(UserClassEnum::SYSOP->value);
        $settings = ['hr' => ['inspect_time' => 72]];

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with(['prefix' => 'hr'])
            ->andReturn($settings);

        $controller = new SettingController($repository);
        $request = SettingIndexRequest::create('/api/v1/settings', 'GET', ['prefix' => 'hr']);
        $request->setContainer(app());
        $request->validateResolved();

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_saves_hr_settings(): void
    {
        $this->actingAsUserClass(UserClassEnum::SYSOP->value);
        $data = [
            'hr' => [
                'ban_user_when_counts_reach' => 3,
                'ignore_when_ratio_reach' => 1.0,
                'inspect_time' => 72,
                'seed_time_minimum' => 24,
                'mode' => 'manual',
            ],
        ];

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldReceive('store')
            ->once()
            ->with($data)
            ->andReturn(true);

        $controller = new SettingController($repository);
        $request = SettingStoreRequest::create('/api/v1/settings', 'POST', $data);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_store_validates_hr_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new SettingController($repository);
        $request = SettingStoreRequest::create('/api/v1/settings', 'POST', [
            'hr' => ['inspect_time' => 72],
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);
    }

    public function test_store_validates_hr_mode_enum(): void
    {
        $this->expectException(ValidationException::class);

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new SettingController($repository);
        $request = SettingStoreRequest::create('/api/v1/settings', 'POST', [
            'hr' => [
                'ban_user_when_counts_reach' => 3,
                'ignore_when_ratio_reach' => 1.0,
                'inspect_time' => 72,
                'seed_time_minimum' => 24,
                'mode' => 'invalid-mode',
            ],
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->store($request);
    }

    public function test_index_rejects_non_sysop_with_403(): void
    {
        $this->actingAsUserClass(UserClassEnum::ADMINISTRATOR->value);

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldNotReceive('getList');

        $controller = new SettingController($repository);
        $request = SettingIndexRequest::create('/api/v1/settings', 'GET', []);
        $request->setContainer(app());
        $request->validateResolved();

        try {
            $controller->index($request);
            $this->fail('Expected HttpException for non-sysop actor');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_index_rejects_guest_with_403(): void
    {
        Auth::shouldReceive('user')->andReturn(null);

        /** @var SettingRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldNotReceive('getList');

        $controller = new SettingController($repository);
        $request = SettingIndexRequest::create('/api/v1/settings', 'GET', []);
        $request->setContainer(app());
        $request->validateResolved();

        try {
            $controller->index($request);
            $this->fail('Expected HttpException for guest');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
