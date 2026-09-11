<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Http\Requests\ExamUserAvoidRequest;
use App\Http\Requests\ExamUserBulkRequest;
use App\Http\Requests\ExamUserIndexRequest;
use App\Http\Requests\UidRequest;
use App\Http\Resources\ExamUserResource;
use App\Models\User;
use App\Support\Locale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ExamUserController extends Controller
{
    private ExamRepositoryInterface $repository;

    /**
     * @return mixed
     */
    public function __construct(ExamRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(ExamUserIndexRequest $request): array
    {
        $result = $this->repository->listUser($request->validated());
        $resource = ExamUserResource::collection($result);
        $resource->additional([
            'page_title' => Locale::trans('exam-user.admin.list.page_title', [], null),
        ]);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(UidRequest $request): array
    {
        $timeRange = $request->get('time_range', []);
        $begin = isset($timeRange[0]) ? Carbon::parse($timeRange[0])->toDateTimeString() : null;
        $end = isset($timeRange[1]) ? Carbon::parse($timeRange[1])->toDateTimeString() : null;

        $result = $this->repository->assignToUser($request->uid, $request->exam_id, $begin, $end);
        $resource = new ExamUserResource($result);

        return $this->success($resource, 'Assign exam success!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function destroy($id): array
    {
        $result = $this->repository->removeExamUser($id);

        return $this->success($result, 'Remove user exam success!');
    }

    /**
     * @return array<string, mixed>
     */
    public function avoid(ExamUserAvoidRequest $request): array
    {
        $result = $this->repository->avoidExamUser($request->id);

        return $this->success($result, 'Avoid user exam success!');
    }

    /**
     * @return array<string, mixed>
     */
    public function recover(ExamUserAvoidRequest $request): array
    {
        $result = $this->repository->recoverExamUser($request->id);

        return $this->success($result, 'Recover user exam success!');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function bulkAvoid(ExamUserBulkRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $result = $this->repository->avoidExamUserBulk($request->validated(), $user);

        return $this->success(['result' => $result], 'Affected: '.intval($result));
    }

    /**
     * @return array<int|string, mixed>
     */
    public function bulkDelete(ExamUserBulkRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $result = $this->repository->removeExamUserBulk($request->validated(), $user);

        return $this->success(['result' => $result], 'Affected: '.intval($result));
    }
}
