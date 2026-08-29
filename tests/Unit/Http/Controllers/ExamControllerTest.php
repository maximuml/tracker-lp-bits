<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\ExamController;
use App\Models\Exam;
use App\Repositories\ExamRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class ExamControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_list_of_exams(): void
    {
        $exam = $this->makeExam(1, 'Test Exam');

        $paginator = new LengthAwarePaginator([$exam], 1, 15, 1);

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('getList')
            ->once()
            ->with([])
            ->andReturn($paginator);

        $controller = new ExamController($repository);
        $request = Request::create('/api/v1/exams', 'GET', []);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_creates_exam(): void
    {
        $exam = $this->makeExam(1, 'Test Exam');

        $data = [
            'name' => 'Test Exam',
            'indexes' => [['index' => '1', 'require_value' => 1073741824]],
            'status' => '1',
        ];

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('store')
            ->once()
            ->andReturn($exam);

        $controller = new ExamController($repository);
        $request = Request::create('/api/v1/exams', 'POST', $data);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new ExamController($repository);
        $request = Request::create('/api/v1/exams', 'POST', []);

        $controller->store($request);
    }

    public function test_store_validates_indexes_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldNotReceive('store');

        $controller = new ExamController($repository);
        $request = Request::create('/api/v1/exams', 'POST', [
            'name' => 'Test',
            'indexes' => [],
            'status' => '1',
        ]);

        $controller->store($request);
    }

    public function test_show_returns_exam_detail(): void
    {
        $exam = $this->makeExam(1, 'Test Exam');

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('getDetail')
            ->once()
            ->with(1)
            ->andReturn($exam);

        $controller = new ExamController($repository);

        $result = $controller->show(1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_update_modifies_exam(): void
    {
        $exam = $this->makeExam(1, 'Updated Exam');

        $data = [
            'name' => 'Updated Exam',
            'indexes' => [['index' => '1', 'require_value' => 1073741824]],
            'status' => '1',
        ];

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('update')
            ->once()
            ->andReturn($exam);

        $controller = new ExamController($repository);
        $request = Request::create('/api/v1/exams/1', 'PUT', $data);

        $result = $controller->update($request, 1);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_destroy_removes_exam(): void
    {
        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $controller = new ExamController($repository);

        $result = $controller->destroy(1);

        $this->assertSame(0, $result['ret']);
    }

    public function test_indexes_returns_list_of_indexes(): void
    {
        $indexes = [
            '1' => ['name' => 'Uploaded', 'unit' => 'GB', 'source_user_field' => 'uploaded'],
            '3' => ['name' => 'Downloaded', 'unit' => 'GB', 'source_user_field' => 'downloaded'],
        ];

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('listIndexes')
            ->once()
            ->andReturn($indexes);

        $controller = new ExamController($repository);

        $result = $controller->indexes();

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    /**
     * Create an Exam model with all attributes needed by ExamResource.
     */
    private function makeExam(int $id, string $name): Exam
    {
        $exam = new Exam;
        $exam->id = $id;
        $exam->name = $name;
        $exam->description = 'Test description';
        $exam->begin = null;
        $exam->end = null;
        $exam->duration = 0;
        $exam->filters = [];
        $exam->indexes = [['index' => '1', 'require_value' => 1073741824]];
        $exam->status = 1;
        $exam->is_discovered = 0;
        $exam->priority = 0;

        return $exam;
    }
}
