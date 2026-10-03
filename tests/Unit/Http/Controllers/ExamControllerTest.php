<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Enums\ExamIndex;
use App\Http\Controllers\ExamController;
use App\Http\Requests\ExamRequest;
use App\Http\Requests\GenericIndexRequest;
use App\Models\Exam;
use App\Support\RedisGuard;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class ExamControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `Setting::get` reads the `nexus_settings_in_laravel` cache key
        // before falling back to the DB — seeding it keeps Locale::trans
        // reachable in the no-services suite (CACHE_DRIVER=array). The
        // seed uses the same defaults file as SettingsTableSeeder so the
        // value sticking in Setting::get's process-static stays identical
        // to a freshly seeded database for every later test. The
        // RedisGuard reset clears any down-flag an earlier test left so
        // `attempt()` actually runs the remember() call instead of
        // short-circuiting to `getFromDb()`.
        RedisGuard::reset();
        $settings = require base_path('database/settings.default.php');
        Cache::put('nexus_settings_in_laravel', $settings, 600);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_delegates_to_repository_get_list(): void
    {
        $paginator = new LengthAwarePaginator([$this->makeExam(1)], 1, 15, 1);

        /** @var ExamRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepositoryInterface::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($paginator);
        $this->app->instance(ExamRepositoryInterface::class, $repository);

        $request = GenericIndexRequest::create('/api/exams', 'GET', []);
        $request->setContainer($this->app);
        $request->validateResolved();

        $result = $this->app->make(ExamController::class)->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_delegates_to_repository(): void
    {
        $data = $this->validExamData();

        /** @var ExamRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepositoryInterface::class);
        $repository->shouldReceive('store')
            ->once()
            ->with($data)
            ->andReturn($this->makeExam(9));
        $this->app->instance(ExamRepositoryInterface::class, $repository);

        $request = ExamRequest::create('/api/exams', 'POST', $data);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        $result = $this->app->make(ExamController::class)->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_show_returns_repository_detail(): void
    {
        /** @var ExamRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepositoryInterface::class);
        $repository->shouldReceive('getDetail')
            ->once()
            ->with(5)
            ->andReturn($this->makeExam(5));
        $this->app->instance(ExamRepositoryInterface::class, $repository);

        $result = $this->app->make(ExamController::class)->show(5);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_update_delegates_to_repository(): void
    {
        $data = $this->validExamData();

        /** @var ExamRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepositoryInterface::class);
        $repository->shouldReceive('update')
            ->once()
            ->with($data, 7)
            ->andReturn($this->makeExam(7));
        $this->app->instance(ExamRepositoryInterface::class, $repository);

        $request = ExamRequest::create('/api/exams/7', 'PUT', $data);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        $result = $this->app->make(ExamController::class)->update($request, 7);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_destroy_delegates_to_repository(): void
    {
        /** @var ExamRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepositoryInterface::class);
        $repository->shouldReceive('delete')
            ->once()
            ->with(3)
            ->andReturn(true);
        $this->app->instance(ExamRepositoryInterface::class, $repository);

        $result = $this->app->make(ExamController::class)->destroy(3);

        $this->assertSame(0, $result['ret']);
    }

    public function test_indexes_delegates_to_repository(): void
    {
        $payload = [['index' => ExamIndex::UPLOADED->value, 'name' => 'Uploaded']];

        /** @var ExamRepositoryInterface&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepositoryInterface::class);
        $repository->shouldReceive('listIndexes')
            ->once()
            ->andReturn($payload);
        $this->app->instance(ExamRepositoryInterface::class, $repository);

        $result = $this->app->make(ExamController::class)->indexes();

        $this->assertSame(0, $result['ret']);
        $this->assertSame($payload, $result['data']);
    }

    private function makeExam(int $id): Exam
    {
        $exam = new Exam;
        $exam->id = $id;
        $exam->name = 'Monthly exam';
        $exam->filters = [];
        $exam->indexes = [
            ['index' => ExamIndex::UPLOADED->value, 'checked' => true, 'require_value' => '10'],
        ];

        return $exam;
    }

    /** @return array<string, mixed> */
    private function validExamData(): array
    {
        return [
            'name' => 'Monthly exam',
            'indexes' => [
                ['index' => ExamIndex::UPLOADED->value, 'require_value' => '10'],
            ],
            'status' => '1',
            'duration' => 30,
        ];
    }
}
