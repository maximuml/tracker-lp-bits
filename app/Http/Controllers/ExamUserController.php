<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExamUserResource;
use App\Repositories\ExamRepository;
use App\Support\Locale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamUserController extends Controller
{
    /** @var mixed */
    private $repository;

    /**
     * @return mixed
     */
    public function __construct(ExamRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $result = $this->repository->listUser($request->all());
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
    public function store(Request $request): array
    {
        $rules = [
            'uid' => 'required',
        ];
        $request->validate($rules);
        $timeRange = $request->get('time_range', []);
        $begin = isset($timeRange[0]) ? Carbon::parse($timeRange[0])->toDateTimeString() : null;
        $end = isset($timeRange[1]) ? Carbon::parse($timeRange[1])->toDateTimeString() : null;

        $result = $this->repository->assignToUser($request->uid, $request->exam_id, $begin, $end);
        $resource = new ExamUserResource($result);

        return $this->success($resource, 'Assign exam success!');
    }

    /**
     * Display the specified resource.
     *
     * @param  mixed  $id
     * @return array<int|string, mixed>
     */
    public function show($id): array
    {

        return [];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     * @return array<int|string, mixed>
     */
    public function update(Request $request, $id): array
    {

        return [];
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
    public function avoid(Request $request): array
    {
        $request->validate(['id' => 'required']);
        $result = $this->repository->avoidExamUser($request->id);

        return $this->success($result, 'Avoid user exam success!');
    }

    /**
     * @return array<string, mixed>
     */
    public function recover(Request $request): array
    {
        $request->validate(['id' => 'required']);
        $result = $this->repository->recoverExamUser($request->id);

        return $this->success($result, 'Recover user exam success!');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function bulkAvoid(Request $request): array
    {
        $result = $this->repository->avoidExamUserBulk($request->all(), Auth::user());

        return $this->success(['result' => $result], 'Affected: '.intval($result));
    }

    /**
     * @return array<int|string, mixed>
     */
    public function bulkDelete(Request $request): array
    {
        $result = $this->repository->removeExamUserBulk($request->all(), Auth::user());

        return $this->success(['result' => $result], 'Affected: '.intval($result));
    }
}
