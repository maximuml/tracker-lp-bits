<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\ExamUserController;
use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\User;
use App\Repositories\ExamRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class ExamUserControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_assigns_exam_to_user(): void
    {
        $exam = new Exam;
        $exam->id = 10;
        $exam->name = 'Test Exam';
        $exam->description = 'Test';
        $exam->begin = null;
        $exam->end = null;
        $exam->duration = 0;
        $exam->filters = [];
        $exam->indexes = [['index' => '1', 'require_value' => 1073741824]];
        $exam->status = 1;
        $exam->is_discovered = 0;
        $exam->priority = 0;

        $examUser = new ExamUser;
        $examUser->id = 1;
        $examUser->uid = 5;
        $examUser->exam_id = 10;
        $examUser->status = 0;
        $examUser->is_done = 0;
        $examUser->progress = [];
        $examUser->begin = null;
        $examUser->end = null;
        $examUser->setRelation('exam', $exam);

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('assignToUser')
            ->once()
            ->with(5, 10, null, null)
            ->andReturn($examUser);

        $controller = new ExamUserController($repository);
        $request = Request::create('/api/v1/exam-users', 'POST', ['uid' => 5, 'exam_id' => 10]);

        $result = $controller->store($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_store_validates_uid_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldNotReceive('assignToUser');

        $controller = new ExamUserController($repository);
        $request = Request::create('/api/v1/exam-users', 'POST', []);

        $controller->store($request);
    }

    public function test_destroy_removes_exam_user(): void
    {
        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('removeExamUser')
            ->once()
            ->with(1)
            ->andReturn(true);

        $controller = new ExamUserController($repository);

        $result = $controller->destroy(1);

        $this->assertSame(0, $result['ret']);
    }

    public function test_avoid_marks_exam_user_as_avoided(): void
    {
        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('avoidExamUser')
            ->once()
            ->with(1)
            ->andReturn(true);

        $controller = new ExamUserController($repository);
        $request = Request::create('/api/v1/exam-users/1/avoid', 'POST', ['id' => 1]);

        $result = $controller->avoid($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_avoid_validates_id_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldNotReceive('avoidExamUser');

        $controller = new ExamUserController($repository);
        $request = Request::create('/api/v1/exam-users/avoid', 'POST', []);

        $controller->avoid($request);
    }

    public function test_recover_recovers_exam_user(): void
    {
        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('recoverExamUser')
            ->once()
            ->with(1)
            ->andReturn(true);

        $controller = new ExamUserController($repository);
        $request = Request::create('/api/v1/exam-users/1/recover', 'POST', ['id' => 1]);

        $result = $controller->recover($request);

        $this->assertSame(0, $result['ret']);
    }

    public function test_recover_validates_id_required(): void
    {
        $this->expectException(ValidationException::class);

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldNotReceive('recoverExamUser');

        $controller = new ExamUserController($repository);
        $request = Request::create('/api/v1/exam-users/recover', 'POST', []);

        $controller->recover($request);
    }

    public function test_bulk_avoid_returns_affected_count(): void
    {
        $user = new User;
        $user->id = 5;

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('avoidExamUserBulk')
            ->once()
            ->with(['ids' => [1, 2]], Mockery::type(User::class))
            ->andReturn(2);

        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new ExamUserController($repository);
        $request = Request::create('/api/v1/exam-users/bulk-avoid', 'POST', ['ids' => [1, 2]]);

        $result = $controller->bulkAvoid($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(2, $result['data']['result']);
    }

    public function test_bulk_avoid_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldNotReceive('avoidExamUserBulk');

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new ExamUserController($repository);
        $request = Request::create('/api/v1/exam-users/bulk-avoid', 'POST', ['ids' => [1, 2]]);

        $controller->bulkAvoid($request);
    }

    public function test_bulk_delete_returns_affected_count(): void
    {
        $user = new User;
        $user->id = 5;

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldReceive('removeExamUserBulk')
            ->once()
            ->with(['ids' => [1, 2]], Mockery::type(User::class))
            ->andReturn(2);

        Auth::shouldReceive('user')->once()->andReturn($user);

        $controller = new ExamUserController($repository);
        $request = Request::create('/api/v1/exam-users/bulk-delete', 'POST', ['ids' => [1, 2]]);

        $result = $controller->bulkDelete($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(2, $result['data']['result']);
    }

    public function test_bulk_delete_throws_when_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unauthenticated');

        /** @var ExamRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(ExamRepository::class);
        $repository->shouldNotReceive('removeExamUserBulk');

        Auth::shouldReceive('user')->once()->andReturn(null);

        $controller = new ExamUserController($repository);
        $request = Request::create('/api/v1/exam-users/bulk-delete', 'POST', ['ids' => [1, 2]]);

        $controller->bulkDelete($request);
    }
}
